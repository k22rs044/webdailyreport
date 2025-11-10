<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// ログインしていない場合はエラーを返す
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です。']);
    exit();
}

// POSTリクエスト以外はエラー
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '無効なリクエストです。']);
    exit();
}

$user_id = $_SESSION['user_id'];
$report_id = $_POST['report_id'] ?? '';
$comment_content = $_POST['comment'] ?? '';

// バリデーション
if (empty($report_id) || empty($comment_content)) {
    echo json_encode(['success' => false, 'message' => '日報IDとコメント内容は必須です。']);
    exit();
}

try {
    // バリデーション: report_idがReportテーブルに存在するか確認
    $sql_report_check = "SELECT report_id FROM Report WHERE report_id = ?";
    $stmt_report_check = $mysqli->prepare($sql_report_check);
    $stmt_report_check->bind_param('s', $report_id);
    $stmt_report_check->execute();
    $stmt_report_check->store_result();
    if ($stmt_report_check->num_rows === 0) {
        $stmt_report_check->close();
        echo json_encode(['success' => false, 'message' => '対象の日報が存在しません。']);
        exit();
    }
    $stmt_report_check->close();


    // コメントをデータベースに挿入
    $sql = "INSERT INTO Comment (report_id, user_id, comment_content, comment_at) VALUES (?, ?, ?, NOW())";
    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) {
        throw new Exception("プリペアに失敗: " . $mysqli->error);
    }
    $stmt->bind_param('sss', $report_id, $user_id, $comment_content);
    $stmt->execute();
    $stmt->close();

    // コメント投稿者の名前を取得 (レスポンス用)
    $author_name = "不明なユーザー";
    $sql_author_name = "SELECT name FROM User WHERE user_id = ?";
    $stmt_author_name = $mysqli->prepare($sql_author_name);
    $stmt_author_name->bind_param('s', $user_id);
    $stmt_author_name->execute();
    $result_author_name = $stmt_author_name->get_result()->fetch_assoc();
    $author_name = $result_author_name['name'] ?? '不明なユーザー';
    $stmt_author_name->close();

    echo json_encode([
        'success' => true,
        'comment' => [
            'author_name' => $author_name,
            'comment_content' => $comment_content,
            'comment_at' => (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y/m/d H:i') // 日本時間でフォーマット
        ]
    ]);

} catch (Exception $e) {
    // エラーの詳細をログに記録
    $error_message = "Comment Submission Error: " . $e->getMessage();
    error_log($error_message);
    // 開発中は、より詳細なエラーメッセージを返す
    echo json_encode(['success' => false, 'message' => 'コメントの投稿に失敗しました。理由: ' . $e->getMessage()]);
}
?>
