<?php

session_start();
require_once 'db_config.php';

// ユーザーIDの取得（ログイン機能が実装されたらセッションから取得）
$user_id = $_SESSION['user_id'] ?? 100; // 開発中は仮の値を使用

$reports = [];

try {
    // ユーザーIDに基づいて日報を取得
    $sql = "SELECT report_id, report_date, task FROM Report WHERE user_id = ? ORDER BY report_date DESC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // 日付のフォーマットを変更
        $date = new DateTime($row['report_date']);
        $reports[] = [
            'id' => $row['report_id'],
            'date' => $date->format('n月j日'),
            'task' => $row['task'],
        ];
    }
} catch (Exception $e) {
    // エラーが発生した場合、ログに記録するなどして対処
    error_log($e->getMessage());
    // ユーザーにはエラーメッセージを表示することも可能
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>日報一覧</title>
    <style>
        /* 基本スタイルとリセット */
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: #FFFFFF;
            color: #000000;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .container {
            width: 1280px;
            margin: 0 auto;
            position: relative;
        }

        /* ヘッダー */
                header {
            background: #5C9EDC;
            height: 50px;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0 36px;
            color: #FFFFFF;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 1208px; /* 1280 - 36*2 */
        }

        .header-left a, .header-right a {
            font-size: 24px;
            line-height: 29px;
            color: #FFFFFF;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 50px; /* Approximate spacing */
        }

        .header-nav {
            display: flex;
            align-items: center;
            gap: 50px;
        }
        .notification-bell {
            /* SVGアイコン用のスタイルプレースホルダー */
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 50px;
        }

        /* メインコンテンツ */
        .main-content {
            padding: 20px 40px;
        }

        /* フィルターバー */
        .filter-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            padding: 15px 0;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .filter-bar label {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-bar input[type="text"],
        .filter-bar select {
            border: 1px solid #D9D9D9;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 16px;
        }

        .filter-bar input[type="text"] {
            width: 300px;
        }

        .filter-bar .date-input {
            width: 80px;
        }

        .filter-bar .search-button {
            background-color: #5C9EDC;
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            padding: 6px 12px;
            width: 80px;
            cursor: pointer;
        }

        /* 日報グリッド */
        .report-grid {
            display: grid;
            /* 7列のグリッドを作成 */
            grid-template-columns: repeat(7, 1fr);
            gap: 8px; /* カード間の隙間 */
        }

        .report-card {
            background-color: #E0E7ED;
            border-radius: 10px;
            padding: 8px;
            height: 82px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-sizing: border-box;
        }

        .report-card-date {
            font-size: 14px;
            text-align: center;
            font-weight: 400;
        }

        .report-card-summary {
            background-color: #FFFFFF;
            border: 1px solid #5C9EDC;
            border-radius: 10px;
            padding: 8px;
            text-align: center;
            font-size: 16px;
            line-height: 1.2;
            cursor: pointer;
            /* テキストがはみ出た場合に省略記号を表示 */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block; /* aタグをブロック要素にする */
        }

        .report-card-summary:hover {
            background-color: #f0f8ff;
        }

    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>

    <div class="container">
            <header>
        <div class="header-container">
            <div class="header-left">
                <a href="logout.php">ログアウト</a>
            </div>
            <div class="header-right">
                <nav class="header-nav">
                    <a href="top.php">TOP</a>
                    <a href="reports_list.php">日報一覧</a>
                    <a href="weekly_report.php">仮週報作成</a>
                    <a href="mypage.php">マイページ</a>
                </nav>
                <div class="notification-bell">
                    <svg width="25" height="28" viewBox="0 0 25 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12.5 2.8C15.8152 2.8 18.9946 4.10678 21.3891 6.50126C23.7835 8.89574 25.0903 12.0752 25.0903 15.3903C25.0903 20.3903 25.0903 22.5903 25.0903 22.5903H-0.090332C-0.090332 22.5903 -0.090332 20.3903 -0.090332 15.3903C-0.090332 12.0752 1.21645 8.89574 3.61093 6.50126C6.00541 4.10678 9.18484 2.8 12.5 2.8Z" fill="white"/>
                        <path d="M16.5 24.8C16.5 25.5935 16.1839 26.3529 15.6213 26.9155C15.0587 27.4781 14.2993 27.8 13.5 27.8C12.7007 27.8 11.9413 27.4781 11.3787 26.9155C10.8161 26.3529 10.5 25.5935 10.5 24.8H16.5Z" fill="white"/>
                        <path d="M12.5 0C13.5625 0.4375 13.5625 1.5625 12.5 2.1875C11.4375 1.5625 11.4375 0.4375 12.5 0Z" fill="white"/>
                    </svg>
                </div>
            </div>
        </div>
    </header>

        <main class="main-content">
            <section class="filter-bar">
                <label>
                    作業概要
                    <input type="text" placeholder="キーワードを入力">
                </label>

                <select name="period">
                    <option value="all">全部</option>
                    <option value="this_week">今週</option>
                    <option value="last_week">先週</option>
                    <option value="this_month">今月</option>
                    <option value="half_year">半年</option>
                </select>

                <label>
                    開始日
                    <input type="text" class="date-input" placeholder="yyyy/mm/dd">
                </label>
                <span>～</span>
                <label>
                    終了日
                    <input type="text" class="date-input" placeholder="yyyy/mm/dd">
                </label>

                <button type="button" class="search-button">絞り込み</button>
            </section>

            <section class="report-grid">
                <?php foreach ($reports as $report): ?>
                    <div class="report-card">
                        <div class="report-card-date"><?php echo htmlspecialchars($report['date'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <a href="reports_detail.php?id=<?php echo htmlspecialchars($report['id'], ENT_QUOTES, 'UTF-8'); ?>" class="report-card-summary" title="<?php echo htmlspecialchars($report['task'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($report['task'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </section>
        </main>
    </div>

</body>
</html>
