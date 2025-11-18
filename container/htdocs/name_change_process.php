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
$new_name = $_POST['new_name'] ?? '';

// バリデーション
if (empty($new_name)) {
    echo json_encode(['success' => false, 'message' => '氏名を入力してください。']);
    exit();
}

if (mb_strlen($new_name) > 50) {
    echo json_encode(['success' => false, 'message' => '氏名は50文字以内で入力してください。']);
    exit();
}

try {
    $stmt = $mysqli->prepare("UPDATE User SET name = ? WHERE user_id = ?");
    $stmt->bind_param('ss', $new_name, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $_SESSION['name'] = $new_name; // セッションの氏名も更新
        echo json_encode(['success' => true, 'message' => '氏名が正常に変更されました。']);
    } else {
        echo json_encode(['success' => false, 'message' => '氏名の変更に失敗しました。既に同じ名前か、ユーザーが見つかりません。']);
    }
    $stmt->close();

} catch (Exception $e) {
    error_log("Name Change Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。氏名の変更に失敗しました。']);
}
?>
