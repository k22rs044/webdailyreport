<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// ログインしていない、または管理者でない場合はエラー
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => '権限がありません。']);
    exit();
}

// POSTリクエスト以外はエラー
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '無効なリクエストです。']);
    exit();
}

$user_id = $_POST['user_id'] ?? '';
$new_role = $_POST['new_role'] ?? '';

// バリデーション
if (empty($user_id) || !in_array($new_role, ['user', 'admin'])) {
    echo json_encode(['success' => false, 'message' => '無効なユーザーIDまたは権限です。']);
    exit();
}

// 自分自身の権限を変更しようとした場合はエラー
if ($user_id === $_SESSION['user_id'] && $new_role !== $_SESSION['role']) {
    echo json_encode(['success' => false, 'message' => '自分自身の権限を変更することはできません。']);
    exit();
}

try {
    $sql = "UPDATE User SET role = ? WHERE user_id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ss', $new_role, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'ユーザー権限を更新しました。']);
    } else {
        echo json_encode(['success' => false, 'message' => 'ユーザー権限の更新に失敗しました。変更がなかったか、ユーザーが見つかりません。']);
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Update User Role Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。']);
}
?>
