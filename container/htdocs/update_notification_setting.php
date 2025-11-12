<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です。']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '無効なリクエストです。']);
    exit();
}

$user_id = $_SESSION['user_id'];
$receive_notifications = $_POST['receive_notifications'] ?? '0'; // '1' for on, '0' for off

// Ensure it's a valid boolean value
$receive_notifications = ($receive_notifications === '1') ? 1 : 0;

try {
    // 通知をオンにする場合
    if ($receive_notifications === 1) {
        // 最初にメールアドレスが登録されているか確認
        $stmt_check_email = $mysqli->prepare("SELECT email, name FROM User WHERE user_id = ?");
        $stmt_check_email->bind_param('s', $user_id);
        $stmt_check_email->execute();
        $user_data = $stmt_check_email->get_result()->fetch_assoc();
        $stmt_check_email->close();

        // メールアドレスが未登録の場合、エラーを返して処理を中断
        if (!$user_data || empty($user_data['email'])) {
            echo json_encode(['success' => false, 'message' => 'メールアドレスが登録されていません。マイページで登録してください。']);
            exit();
        }

        // メールアドレスが登録されている場合のみ、設定を更新してメールを送信
        $stmt_update = $mysqli->prepare("UPDATE User SET receive_notifications = 1 WHERE user_id = ?");
        $stmt_update->bind_param('s', $user_id);
        $stmt_update->execute();
        $stmt_update->close();

        // 確認メールを送信
        if ($user_data && !empty($user_data['email'])) { // このチェックは理論上不要だが念のため
            $to = $user_data['email'];
            $subject = "締め切り通知がオンになりました";
            $message = "{$user_data['name']}様,\n\nWebDailyReportの締め切り通知がオンに設定されました。今後、重要な通知がメールで送信されます。\n\nこのメールは自動送信されたものです。";
            $headers = 'From: noreply@webdailyreport.com' . "\r\n" .
                    'Reply-To: noreply@webdailyreport.com' . "\r\n" .
                    'Content-Type: text/plain; charset=UTF-8' . "\r\n" .
                    'X-Mailer: PHP/' . phpversion();

            if (!mail($to, $subject, $message, $headers)) {
                error_log("Failed to send notification email to: {$to}");
            }
        }

    } else {
        // 通知をオフにする場合は、単純に設定を更新
        $stmt_update = $mysqli->prepare("UPDATE User SET receive_notifications = 0 WHERE user_id = ?");
        $stmt_update->bind_param('s', $user_id);
        $stmt_update->execute();
        $stmt_update->close();
    }

    echo json_encode(['success' => true, 'message' => '通知設定を更新しました。']);

} catch (Exception $e) {
    error_log("Update Notification Setting Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。']);
}
?>
