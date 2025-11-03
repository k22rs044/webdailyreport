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
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$task_summary = $_POST['task_summary'] ?? '';

// バリデーション
if (empty($task_summary)) {
    echo json_encode(['success' => false, 'message' => '作業概要は必須です。']);
    exit();
}

try {
    $mysqli->begin_transaction();

    // ユーザーの既存作業概要数を数える
    $count_sql = "SELECT COUNT(*) as task_count FROM Task_Content WHERE user_id = ?";
    $count_stmt = $mysqli->prepare($count_sql);
    $count_stmt->bind_param('s', $user_id);
    $count_stmt->execute();
    $row = $count_stmt->get_result()->fetch_assoc();
    $count_stmt->close();

    // 新しいIDを生成
    $new_task_id = $user_id . '_' . ($row['task_count'] + 1);

    // 新しい作業概要を挿入
    $sql = "INSERT INTO Task_Content (task_id, user_id, task_content, task_at) VALUES (?, ?, ?, CURDATE())";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('sss', $new_task_id, $user_id, $task_summary);
    $stmt->execute();
    $stmt->close();

    $mysqli->commit();
    echo json_encode(['success' => true, 'new_task' => ['id' => $new_task_id, 'summary' => $task_summary]]);
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Task_Content Creation Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。']);
}
?>
