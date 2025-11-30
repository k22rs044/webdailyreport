<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// ログインしていない場合はエラーを返す
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です。']);
    exit();
}

// POSTリクエスト以外はエラー
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '無効なリクエストです。']);
    exit();
}

$user_id = $_SESSION['user_id'];
$report_id = $_POST['report_id'] ?? '';
$comment_content = $_POST['comment'] ?? '';

// バリデーション
if (empty($report_id) || empty($comment_content)) {
    echo json_encode(['success' => false, 'message' => '日報IDとコメント内容は必須です。']);
    exit();
}

try {
    $mysqli->begin_transaction();

    // バリデーションと日報所有者の取得: report_idがReportテーブルに存在するか確認
    $sql_report_check = "SELECT user_id FROM Report WHERE report_id = ?";
    $stmt_report_check = $mysqli->prepare($sql_report_check);
    $stmt_report_check->bind_param('s', $report_id);
    $stmt_report_check->execute();
    $report_owner_result = $stmt_report_check->get_result();
    $report_owner_row = $report_owner_result->fetch_assoc();
    
    if (!$report_owner_row) {
        $stmt_report_check->close();
        $mysqli->rollback();
        echo json_encode(['success' => false, 'message' => '対象の日報が存在しません。']);
        exit();
    }
    $report_owner_id = $report_owner_row['user_id'];
    $stmt_report_check->close();


    // コメントをデータベースに挿入
    $sql_comment = "INSERT INTO Comment (report_id, user_id, comment_content, comment_at) VALUES (?, ?, ?, NOW())";
    $stmt_comment = $mysqli->prepare($sql_comment);
    if ($stmt_comment === false) throw new Exception("Comment Insert Prepare Failed: " . $mysqli->error);
    $stmt_comment->bind_param('sss', $report_id, $user_id, $comment_content);
    $stmt_comment->execute();
    $comment_id = $stmt_comment->insert_id; // 最後に挿入されたコメントIDを取得
    $stmt_comment->close();

    // 通知テーブルにレコードを挿入 (自分自身へのコメントは通知しない)
    if ($user_id !== $report_owner_id) {
        $sql_notification = "INSERT INTO Notification (notification_id, user_id, report_id, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)";
        $stmt_notification = $mysqli->prepare($sql_notification);
        if ($stmt_notification === false) throw new Exception("Notification Insert Prepare Failed: " . $mysqli->error);
        // notification_id には comment_id を使用する
        $stmt_notification->bind_param('iss', $comment_id, $report_owner_id, $report_id);
        $stmt_notification->execute();
        $stmt_notification->close();
    }

    // その日報にコメントしたことがある他の管理者にも通知を送信
    $sql_admins = "SELECT DISTINCT user_id FROM Comment WHERE report_id = ? AND user_id != ? AND user_id IN (SELECT user_id FROM User WHERE role = 'admin')";
    $stmt_admins = $mysqli->prepare($sql_admins);
    $stmt_admins->bind_param('ss', $report_id, $user_id);
    $stmt_admins->execute();
    $admins_result = $stmt_admins->get_result();
    
    $sql_admin_notification = "INSERT INTO Notification (notification_id, user_id, report_id, created_at, is_read) VALUES (?, ?, ?, NOW(), 0) ON DUPLICATE KEY UPDATE is_read = 0, created_at = NOW()";
    $stmt_admin_notification = $mysqli->prepare($sql_admin_notification);

    while ($admin_row = $admins_result->fetch_assoc()) {
        $admin_id = $admin_row['user_id'];
        $stmt_admin_notification->bind_param('iss', $comment_id, $admin_id, $report_id);
        $stmt_admin_notification->execute();
    }
    $stmt_admin_notification->close();

    // トランザクションをコミット
    $mysqli->commit();

    // コメント投稿者の名前を取得 (レスポンス用)
    $author_name = "不明なユーザー";
    $sql_author_name = "SELECT name FROM User WHERE user_id = ?";
    $stmt_author_name = $mysqli->prepare($sql_author_name);
    $stmt_author_name->bind_param('s', $user_id);
    $stmt_author_name->execute();
    $result_author_name = $stmt_author_name->get_result()->fetch_assoc();
    $author_name = $result_author_name['name'] ?? '不明なユーザー';
    $stmt_author_name->close();

    echo json_encode([
        'success' => true,
        'comment' => [
            'author_name' => $author_name,
            'comment_content' => $comment_content,
            'comment_at' => (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y/m/d H:i') // 日本時間でフォーマット
        ]
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    // エラーの詳細をログに記録
    $error_message = "Comment Submission Error: " . $e->getMessage();
    error_log($error_message);
    // 開発中は、より詳細なエラーメッセージを返す
    echo json_encode(['success' => false, 'message' => 'コメントの投稿に失敗しました。理由: ' . $e->getMessage()]);
}
?>
