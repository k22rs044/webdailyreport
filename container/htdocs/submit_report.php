<?php
// submit_report.php

session_start();
// データベース接続設定ファイルをインクルードしてください
require_once 'db_config.php'; // $mysqli オブジェクトが利用可能であること

// ユーザーIDの取得
$user_id = $_SESSION['user_id'] ?? 100; // 開発中は仮の値を使用 (VARCHARに合わせる)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); 
    exit;
}

// フォームデータの取得 (現在のフォームのname属性に合わせる)
$task = $_POST['task'] ?? '';          // DB: task
$detail = $_POST['detail'] ?? '';          // DB: detail
$next_task = $_POST['next_task'] ?? '';// DB: next_task
$work_start = $_POST['work_start'] ?? '00:00:00'; // TIME型
$work_end = $_POST['work_end'] ?? '00:00:00';     // TIME型

// 総作業時間 (秒数で取得)
$work_time_seconds = (int)($_POST['work_time_seconds'] ?? 0); 
// DBに格納するための分単位に変換
$work_time_minutes = floor($work_time_seconds / 60); // INT型 (分単位)

$report_date = date('Y-m-d'); // 作成日 (DATE型)

// 主キー report_id の生成: "user_id" + "_" + "report_date"
$report_id = $user_id . '_' . $report_date;

// 簡易バリデーション (DB制約に合わせる)
if (empty($task) || empty($detail) || empty($user_id)) {
    // $_SESSION['error'] = '必須項目が不足しています。';
    header('Location: index.php'); 
    exit;
}

try {
    // プリペアドステートメントでデータを挿入 (カラム名はDB定義に合わせる)
    $sql = "INSERT INTO Report (
                report_id, 
                user_id, 
                report_date, 
                task, 
                detail, 
                next_task, 
                work_start, 
                work_end, 
                work_time
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) {
        throw new Exception("プリペアに失敗: " . $mysqli->error);
    }
    
    // 型のバインド: report_id(s), user_id(s), report_date(s), task(s), detail(s), next_task(s), work_start(s), work_end(s), work_time(i)
    // 9つの変数に対応する 'ssssssssi' に修正
    $stmt->bind_param('ssssssssi', 
                    $report_id, 
                    $user_id, 
                    $report_date, 
                    $task,        // task (DBカラム名) に対応
                    $detail,        // detail (DBカラム名) に対応
                    $next_task,   // next_task (DBカラム名) に対応
                    $work_start, 
                    $work_end, 
                    $work_time_minutes // 分単位に変換された値を使用
    );
                
    $stmt->execute();
    $stmt->close();

    // 登録成功後のリダイレクト
    // $_SESSION['success'] = '日報が正常に登録されました。';
    header('Location: success.php'); 
    exit;

} catch (mysqli_sql_exception $e) {
    // 主キー重複や外部キー制約エラーなど、SQL固有のエラーを捕捉
    error_log("SQL Error: " . $e->getMessage());
    // $_SESSION['error'] = '登録中にデータベースエラーが発生しました。';
    header('Location: index.php'); 
    exit;
} catch (Exception $e) {
    // その他のエラー処理
    error_log("Report Submission Error: " . $e->getMessage());
    // $_SESSION['error'] = '登録中にシステムエラーが発生しました。';
    header('Location: index.php');
    exit;
}