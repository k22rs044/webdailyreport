<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// ログインしていない場合はエラー
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です。']);
    exit();
}

// POSTリクエスト以外はエラー
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '無効なリクエストです。']);
    exit();
}

$comment_ids = json_decode($_POST['comment_ids'] ?? '[]', true);

if (empty($comment_ids) || !is_array($comment_ids)) {
    echo json_encode(['success' => true]); // 更新対象がなくてもエラーではない
    exit();
}

try {
    $placeholders = implode(',', array_fill(0, count($comment_ids), '?'));
    $types = str_repeat('i', count($comment_ids));
    $sql = "UPDATE Comment SET is_read = 1 WHERE comment_id IN ($placeholders)";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$comment_ids);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Mark Notifications Read Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '通知の更新中にエラーが発生しました。']);
}
?>
