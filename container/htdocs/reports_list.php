<?php
// 将来的にデータベースから取得することを想定したサンプルデータ
$reports = [];
$today = new DateTime();
// 35日分（5週間 x 7日）のダミーデータを生成
for ($i = 0; $i < 35; $i++) {
    $date = (clone $today)->modify("-$i day");
    $reports[] = [
        'date' => $date->format('n月j日'),
        'summary' => '作業概要テキストサンプル' . ($i + 1),
    ];
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
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 50px;
            padding: 0 40px;
            background-color: #5C9EDC;
            color: #FFFFFF;
            font-size: 24px;
        }

        .header-nav {
            display: flex;
            gap: 50px;
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
        <header class="header">
            <div class="header-left">
                <a href="#">ログアウト</a>
            </div>
            <nav class="header-nav">
                <a href="#">TOP</a>
                <a href="#">日報一覧</a>
                <a href="#">仮週報作成</a>
            </nav>
            <div class="header-right">
                <a href="#">マイページ</a>
                <!-- アイコンはSVGや画像で配置するのが一般的です -->
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
                        <a href="#" class="report-card-summary" title="<?php echo htmlspecialchars($report['summary'], ENT_QUOTES, 'UTF-8'); ?>">
                            作業概要
                        </a>
                    </div>
                <?php endforeach; ?>
            </section>
        </main>
    </div>

</body>
</html>
