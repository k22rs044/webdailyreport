<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// ログインしていない場合はエラーを返す
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です。']);
    exit();
}
$user_id = $_SESSION['user_id'];

// POSTリクエスト以外はエラー
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '無効なリクエストです。']);
    exit();
}

$task_id = $_POST['task_id'] ?? '';

// バリデーション
if (empty($task_id)) {
    echo json_encode(['success' => false, 'message' => '削除する作業概要IDが指定されていません。']);
    exit();
}

try {
    $sql = "DELETE FROM Task_Content WHERE task_id = ? AND user_id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ss', $task_id, $user_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Task Deletion Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。']);
}
?>
