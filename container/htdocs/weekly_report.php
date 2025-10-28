<?php
session_start();

// ログインしていない場合はログインページにリダイレクト（開発中はコメントアウト）
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }

// サンプルデータ - 本来はDBから指定された週のデータを取得します
$weekly_reports = [
    [
        'date' => '9月15日(月)',
        'title' => '【設計】ログイン画面作成',
        'details' => 'ログイン画面のUI設計とコンポーネント分割の検討。'
    ],
    [
        'date' => '9月16日(火)',
        'title' => '【実装】ログイン画面フロントエンド',
        'details' => 'Figmaデザインを基にHTML/CSSでコーディング。'
    ],
    [
        'date' => '9月17日(水)',
        'title' => '【実装】ログイン処理バックエンド',
        'details' => 'PHPでのセッション管理と認証ロジックを実装。'
    ],
    [
        'date' => '9月18日(木)',
        'title' => '【テスト】ログイン機能単体テスト',
        'details' => '正常系・異常系のテストケースを作成し、動作確認を実施。'
    ],
    [
        'date' => '9月19日(金)',
        'title' => '【修正】軽微なバグ修正とリファクタリング',
        'details' => 'テストで発見された軽微なバグの修正と、コードの可読性向上のためのリファクタリング。'
    ],
    [
        'date' => '9月20日(土)',
        'title' => '休日',
        'details' => ''
    ],
    [
        'date' => '9月21日(日)',
        'title' => '休日',
        'details' => ''
    ],
];

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>仮週報</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reset and Base Styles */
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: #FFFFFF;
            color: #000000;
        }
        a { text-decoration: none; color: inherit; }
        .container {
            width: 1280px;
            margin: 0 auto;
        }

        /* Header */
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
        /* Main Content */
        .main-content {
            padding: 20px 40px;
        }

        .report-list {
            display: flex;
            flex-direction: column;
            gap: 13px; /* Spacing between rows */
            align-items: center;
        }

        .report-row, .report-header {
            width: 1061px;
            height: 78px;
            background: #E0E7ED;
            border-radius: 10px;
            display: flex;
            align-items: stretch; /* Make columns full height */
        }

        .report-header {
            font-weight: 400;
            font-size: 16px;
        }

        .report-col {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 15px;
            text-align: center;
        }

        .col-date {
            flex-basis: 90px; /* width: 203 - 113 */
            font-size: 14px;
        }

        .col-title {
            flex-basis: 225px; /* width: 428 - 203 */
            border-left: 1px solid #FFFFFF;
            border-right: 1px solid #FFFFFF;
            font-size: 16px;
        }

        .col-details {
            flex-grow: 1;
            justify-content: flex-start; /* Align text to the left */
            text-align: left;
            font-size: 16px;
        }

    </style>
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
            <section class="report-list">
                <!-- Header Row -->
                <div class="report-header">
                    <div class="report-col col-date">日付</div>
                    <div class="report-col col-title">タイトル</div>
                    <div class="report-col col-details">作業詳細</div>
                </div>

                <!-- Data Rows -->
                <?php foreach ($weekly_reports as $report): ?>
                    <div class="report-row">
                        <div class="report-col col-date"><?php echo htmlspecialchars($report['date'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="report-col col-title"><?php echo htmlspecialchars($report['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="report-col col-details"><?php echo htmlspecialchars($report['details'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                <?php endforeach; ?>
            </section>
        </main>
    </div>
</body>
</html>
```

<!--
[PROMPT_SUGGESTION]この週報をPDFとしてエクスポートする機能を追加してください。[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]週を選択するためのドロップダウンメニューをページ上部に追加してください。[/PROMPT_SUGGESTION]
