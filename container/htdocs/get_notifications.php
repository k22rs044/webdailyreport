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
        $sql = "SELECT 
                    n.notification_id AS comment_id,
                    n.report_id,
                    r.report_date,
                    (SELECT name FROM User WHERE user_id = c.user_id) AS commenter_name,
                    (SELECT name FROM User WHERE user_id = r.user_id) AS report_owner_name
                FROM Notification n
                JOIN Report r ON n.report_id = r.report_id
                JOIN Comment c ON n.notification_id = c.comment_id
                WHERE n.is_read = 0
                ORDER BY n.created_at DESC";
        $stmt = $mysqli->prepare($sql);
    } else {
        // 一般ユーザーの場合：自分宛のコメントのみ取得 (is_readに関わらず)
        $sql = "SELECT 
                    n.notification_id AS comment_id,
                    n.report_id,
                    r.report_date,
                    (SELECT name FROM User WHERE user_id = c.user_id) AS commenter_name
                FROM Notification n
                JOIN Report r ON n.report_id = r.report_id
                JOIN Comment c ON n.notification_id = c.comment_id
                WHERE n.user_id = ? AND n.is_read = 0
                ORDER BY n.created_at DESC";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('s', $recipient_user_id);
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
