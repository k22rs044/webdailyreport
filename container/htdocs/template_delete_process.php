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

$template_id = $_POST['template_id'] ?? '';

// バリデーション
if (empty($template_id)) {
    echo json_encode(['success' => false, 'message' => '削除するテンプレートIDが指定されていません。']);
    exit();
}

try {
    // テンプレートを削除するSQL。user_idも条件に含めることで、他人のテンプレートを削除できないようにする
    $sql = "DELETE FROM Detail_Template WHERE template_id = ? AND user_id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ss', $template_id, $user_id);
    $stmt->execute();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Template Deletion Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。']);
}
?>
