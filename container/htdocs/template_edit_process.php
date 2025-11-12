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
$title = $_POST['title'] ?? '';
$content = $_POST['content'] ?? '';

// バリデーション
if (empty($template_id) || empty($title) || empty($content)) {
    echo json_encode(['success' => false, 'message' => 'ID、テンプレート名、内容は必須です。']);
    exit();
}

try {
    // テンプレートを更新するSQL。user_idも条件に含めることで、他人のテンプレートを編集できないようにする
    $sql = "UPDATE Detail_Template SET title = ?, content = ?, created_at = CURDATE() WHERE template_id = ? AND user_id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ssss', $title, $content, $template_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => '更新対象のデータが見つからないか、内容に変更がありません。']);
    }

} catch (Exception $e) {
    error_log("Template Update Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。']);
}
?>
