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

// POSTリクエスト以外はリダイレクト
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$title = $_POST['title'] ?? '';
$content = $_POST['content'] ?? '';


// バリデーション
if (empty($title) || empty($content)) {
    echo json_encode(['success' => false, 'message' => 'テンプレート名と内容は必須です。']);
    exit();
}

try {
    // トランザクションを開始
    $mysqli->begin_transaction();

    // 1. ユーザーの既存テンプレート数を数える
    $count_sql = "SELECT COUNT(*) as template_count FROM Detail_Template WHERE user_id = ?";
    $count_stmt = $mysqli->prepare($count_sql);
    $count_stmt->bind_param('s', $user_id);
    $count_stmt->execute();
    $result = $count_stmt->get_result();
    $row = $result->fetch_assoc();
    $count_stmt->close();

    // 2. 新しい連番とtemplate_idを生成
    $new_sequence = $row['template_count'] + 1;
    $new_template_id = $user_id . '_' . $new_sequence;

    // 3. 新しいテンプレートを挿入
    $sql = "INSERT INTO Detail_Template (template_id, user_id, title, content, created_at) VALUES (?, ?, ?, ?, CURDATE())";
    $stmt = $mysqli->prepare($sql); //
    if ($stmt === false) throw new Exception("SQLのプリペアに失敗しました: " . $mysqli->error);

    $stmt->bind_param('ssss', $new_template_id, $user_id, $title, $content);
    $success = $stmt->execute();

    if ($success) {
        $stmt->close();
        $mysqli->commit(); // トランザクションをコミット
        echo json_encode(['success' => true, 'new_template' => ['id' => $new_template_id, 'title' => $title]]);
    } else {
        $mysqli->rollback(); // 失敗した場合はロールバック
        echo json_encode(['success' => false, 'message' => 'データベースへの登録に失敗しました。']);
    }
} catch (Exception $e) {
    $mysqli->rollback(); // 例外発生時もロールバック
    error_log("Template Creation Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。']);
}
?>
