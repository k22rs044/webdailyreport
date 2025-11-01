<?php
session_start();

require_once 'db_config.php'; // ここで $mysqli オブジェクトが利用可能になっている

// POSTリクエストでない場合はログインページにリダイレクト
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// 入力値の取得
$user_id = $_POST['user_id'] ?? '';
$password = $_POST['password'] ?? '';

// バリデーション
if (empty($user_id) || empty($password)) {
    $_SESSION['error'] = 'IDとパスワードを入力してください。';
    header('Location: login.php');
    exit;
}

// 接続オブジェクトは $mysqli を使用し、例外処理は mysqli_sql_exception で行う
try {
    // ユーザー情報をデータベースから取得
    $stmt = $mysqli->prepare("SELECT * FROM User WHERE user_id = ?"); 

    // バインドパラメータの処理 (PDO::bindParam -> mysqli::bind_param)
    $stmt->bind_param('s', $user_id); 
    
    // 実行
    $stmt->execute();
    
    // 結果の取得 (PDO::fetch() -> mysqli::get_result()->fetch_assoc())
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // ステートメントを閉じる (メモリ解放)
    $stmt->close();
    
    // ユーザーが存在し、パスワードが一致するか確認
   // if ($user && password_verify($password, $user['password'])) {
        //  開発用の一時的な修正: 平文のパスワードを直接比較する
    if ($user && $password === $user['password']) {
        // ログイン成功
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        header('Location: top.php');
        exit;
    } else {
        // ログイン失敗
        $_SESSION['error'] = 'IDまたはパスワードが正しくありません。';
        header('Location: login.php');
        exit;
    }
} catch (mysqli_sql_exception $e) { // 👈 PDOException -> mysqli_sql_exception に変更
    error_log("Login Error: " . $e->getMessage());
    $_SESSION['error'] = 'データベースエラーが発生しました。';
    header('Location: login.php');
    exit;
}