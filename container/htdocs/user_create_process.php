<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// 管理者でない場合はエラー
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     echo json_encode(['success' => false, 'message' => '権限がありません。']);
//     exit();
// }

// POSTリクエスト以外はエラー
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '無効なリクエストです。']);
    exit();
}

$user_id = $_POST['user_id'] ?? '';
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
//$role = 'user'; // デフォルトの権限は 'user'
$role = $_POST['role'] ?? 'user'; // 'user' or 'admin'

// バリデーション
if (empty($user_id) || empty($name) || empty($password) || !in_array($role, ['user', 'admin'])) {
    echo json_encode(['success' => false, 'message' => '必須項目が不足しているか、権限の値が不正です。']);
    exit();
}

// メールアドレスが空の場合はnullをセット
if (empty($email)) {
    $email = null;
}

// パスワードをハッシュ化
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // 新しいユーザーを挿入
    $sql = "INSERT INTO User (user_id, name, email, password, role) VALUES (?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('sssss', $user_id, $name, $email, $hashed_password, $role);
    $stmt->execute();

    echo json_encode(['success' => true]);

} catch (mysqli_sql_exception $e) {
    // user_idやemailの重複エラーをハンドリング
    if ($e->getCode() == 1062) { // Duplicate entry
        echo json_encode(['success' => false, 'message' => '入力された学籍番号またはメールアドレスは既に使用されています。']);
    } else {
        error_log("User Creation Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'データベースエラーが発生しました。']);
    }
} catch (Exception $e) {
    error_log("User Creation Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。']);
}
?>
