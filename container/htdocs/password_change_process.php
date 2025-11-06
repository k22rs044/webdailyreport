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
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';

// バリデーション
if (empty($current_password) || empty($new_password)) {
    echo json_encode(['success' => false, 'message' => '現在のパスワードと新しいパスワードの両方を入力してください。']);
    exit();
}

try {
    // 1. 現在のパスワードハッシュをDBから取得
    $stmt = $mysqli->prepare("SELECT password FROM User WHERE user_id = ?");
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'ユーザーが見つかりません。']);
        exit();
    }

    // 2. 現在のパスワードが正しいか検証 (ハッシュ値で比較)
    if (!password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => '現在のパスワードが正しくありません。']);
        exit();
    }

    // テスト用: 平文でパスワードを比較
    // if ($current_password !== $user['password']) {
    //     echo json_encode(['success' => false, 'message' => '現在のパスワードが正しくありません。']);
    //     exit();
    // }

    // 3. 新しいパスワードをハッシュ化してDBを更新
    // テスト用: 新しいパスワードはハッシュ化して保存
    //$new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    //$update_stmt = $mysqli->prepare("UPDATE User SET password = ? WHERE user_id = ?");
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update_stmt = $mysqli->prepare("UPDATE User SET password = ? WHERE user_id = ?");
    $update_stmt->bind_param('ss', $new_hashed_password, $user_id);
    // テスト用: 平文でパスワードを更新
    //$update_stmt = $mysqli->prepare("UPDATE User SET password = ? WHERE user_id = ?");
    //$update_stmt->bind_param('ss', $new_password, $user_id);
    $update_stmt->execute();
    $update_stmt->close();

    echo json_encode(['success' => true, 'message' => 'パスワードが正常に変更されました。']);

} catch (Exception $e) {
    error_log("Password Change Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。パスワードの変更に失敗しました。']);
}
?>
