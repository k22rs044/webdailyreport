<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// ログインしていない場合はエラー
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です。']);
    exit();
}

$user_id = $_SESSION['user_id'];
$new_email = $_POST['new_email'] ?? '';

// バリデーション
if (empty($new_email)) {
    echo json_encode(['success' => false, 'message' => 'メールアドレスを入力してください。']);
    exit();
}

if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => '有効なメールアドレス形式で入力してください。']);
    exit();
}

try {
    $stmt = $mysqli->prepare("UPDATE User SET email = ? WHERE user_id = ?");
    $stmt->bind_param('ss', $new_email, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'メールアドレスが正常に変更されました。']);
    } else {
        // データが変更されなかった場合（同じメールアドレスを入力した場合など）
        echo json_encode(['success' => false, 'message' => 'メールアドレスの変更に失敗しました。既に登録済みか、ユーザーが見つかりません。']);
    }
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) { // Duplicate entry for key 'email'
        echo json_encode(['success' => false, 'message' => 'そのメールアドレスは既に使用されています。']);
    } else {
        error_log("Email Change Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'データベースエラーが発生しました。メールアドレスの変更に失敗しました。']);
    }
}
?>
