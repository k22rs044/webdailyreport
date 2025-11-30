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
    // トランザクションを開始
    $mysqli->begin_transaction();

    // プレースホルダをIDの数だけ生成 (?,?,?)
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $types = str_repeat('s', count($user_ids));

    // 関連テーブルのデータを削除する
    $related_tables = ['Report', 'Comment', 'Detail_Template', 'Task_Content', 'Notification'];
    foreach ($related_tables as $table) {
        $sql = "DELETE FROM {$table} WHERE user_id IN ($placeholders)";
        $stmt = $mysqli->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Failed to prepare statement for table: {$table}");
        }
        $stmt->bind_param($types, ...$user_ids);
        $stmt->execute();
        $stmt->close();
    }

    // 最後にUserテーブルからユーザーを削除
    $sql_user = "DELETE FROM User WHERE user_id IN ($placeholders)";
    $stmt_user = $mysqli->prepare($sql_user);
    if ($stmt_user === false) {
        throw new Exception("Failed to prepare statement for User table");
    }
    $stmt_user->bind_param($types, ...$user_ids);
    $stmt_user->execute();
    $stmt_user->close();

    // トランザクションをコミット
    $mysqli->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $mysqli->rollback(); // エラーが発生した場合はロールバック
    error_log("User Deletion Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。']);
}
?>
