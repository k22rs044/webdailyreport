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

$user_ids = $_POST['user_ids'] ?? [];

// バリデーション
if (empty($user_ids) || !is_array($user_ids)) {
    echo json_encode(['success' => false, 'message' => '削除するユーザーが選択されていません。']);
    exit();
}

try {
    // プレースホルダをIDの数だけ生成 (?,?,?)
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $types = str_repeat('s', count($user_ids));

    $sql = "DELETE FROM User WHERE user_id IN ($placeholders)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$user_ids);
    $stmt->execute();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("User Deletion Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。']);
}
?>
