<?php
// db_config.php - データベース接続設定 (MySQLi版)

$host = 'db'; // docker-compose.ymlで定義されたサービス名
$dbname = 'DailyReport'; // 使用するデータベース名
$user = 'root'; // 開発環境用のユーザー名
$password = getenv('MYSQL_ROOT_PASSWORD'); // 環境変数からパスワードを取得

// MySQLiのエラー報告設定
// エラー発生時に例外(Exception)をスローするように設定し、try-catchで捕捉できるようにする
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // 1. 接続インスタンスを作成 (ホスト, ユーザー, パスワード, データベース名)
    // new PDO(...) の代わりに new mysqli(...) を使用
    $mysqli = new mysqli($host, $user, $password, $dbname);
    
    // 2. 文字コードをutf8mb4に設定
    $mysqli->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    // 接続に失敗した場合、mysqli_sql_exceptionとしてキャッチされる
    
    // 本番環境では、エラーのログを記録し、ユーザーには汎用的なエラーメッセージを表示します。
    error_log($e->getMessage());
    die("データベースへの接続に失敗しました。");
}

// これ以降、データベース操作には $mysqli オブジェクトを使用します。
// 例: $result = $mysqli->query("SELECT * FROM users");
?>