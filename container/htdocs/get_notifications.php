<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// ログインしていない場合はエラー
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です。']);
    exit();
}

$recipient_user_id = $_SESSION['user_id'];
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$notifications = [];

try {
    if ($is_admin) {
        // 管理者の場合：すべての日報に対する未読コメントを取得
        // report_owner_nameを追加で取得
        $sql = "SELECT 
                    c.comment_id,
                    r.report_id,
                    r.report_date,
                    u_commenter.name AS commenter_name,
                    u_owner.name AS report_owner_name
                FROM Comment c
                JOIN Report r ON c.report_id = r.report_id
                JOIN User u_commenter ON c.user_id = u_commenter.user_id
                JOIN User u_owner ON r.user_id = u_owner.user_id
                WHERE c.user_id != r.user_id
                ORDER BY c.comment_at DESC";
        $stmt = $mysqli->prepare($sql);
    } else {
        // 一般ユーザーの場合：自分宛のコメントのみ取得 (is_readに関わらず)
        $sql = "SELECT 
                    c.comment_id,
                    r.report_id,
                    r.report_date,
                    u.name AS commenter_name
                FROM Comment c
                JOIN Report r ON c.report_id = r.report_id
                JOIN User u ON c.user_id = u.user_id
                WHERE r.user_id = ? AND c.user_id != ?
                ORDER BY c.comment_at DESC";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('ss', $recipient_user_id, $recipient_user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($is_admin) {
            $row['is_admin_view'] = true; // 管理者ビューであることをJS側に伝えるフラグ
        }
        $notifications[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'notifications' => $notifications]);
} catch (Exception $e) {
    error_log("Get Notifications Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '通知の取得中にエラーが発生しました。']);
}
?>
