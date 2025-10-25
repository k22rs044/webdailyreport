<?php
session_start();

// ログインしていない場合はログインページにリダイレクト（開発中はコメントアウト）
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }

// --- サンプルデータ ---
// 本来はGETパラメータなどから日報IDを受け取り、DBからデータを取得します。
$report = [
    'date' => '9月18日',
    'summary' => '作業概要のテキストがここに表示されます。',
    'work_time' => '8時間30分',
    'details' => '本日の作業詳細テキストがここに表示されます。複数行にわたるテキストも問題なく表示されるように設定されています。',
    'next_summary' => '次回作業概要のテキストがここに表示されます。'
];

$comments = [
    [
        'author' => '田中 太郎',
        'timestamp' => '2023/09/18 18:30',
        'body' => 'お疲れ様です。この件、承知いたしました。'
    ],
    [
        'author' => '鈴木 花子',
        'timestamp' => '2023/09/18 19:00',
        'body' => '確認しました。ありがとうございます。'
    ]
];

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>日報詳細</title>
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
        .header-nav { display: flex; gap: 50px; }
        .header-right { display: flex; align-items: center; gap: 50px; }

        /* Main Content Layout */
        .main-content {
            padding: 25px 40px;
        }
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #8CBAE6;
            border-radius: 10px;
            padding: 6px 20px 6px 15px;
            font-size: 20px;
            margin-bottom: 10px;
        }
        .back-button-arrow {
            width: 0;
            height: 0;
            border-top: 8px solid transparent;
            border-bottom: 8px solid transparent;
            border-right: 12px solid #FFFFFF;
        }

        .content-wrapper {
            display: flex;
            gap: 20px;
        }

        /* Report Details (Left) */
        .report-details {
            width: 483px;
            background: #E0E7ED;
            border-radius: 10px;
            padding: 20px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        .report-details h2 {
            font-size: 32px;
            font-weight: 400;
            margin: 5px 0 10px 0;
        }
        .detail-item {
            background: #FFFFFF;
            border-radius: 7px;
            width: 342px;
            padding: 10px 15px;
            box-sizing: border-box;
            font-size: 20px;
            color: #333;
            min-height: 40px;
        }
        .detail-item.time {
            width: auto;
            min-width: 133px;
            align-self: flex-start;
            margin-left: calc((100% - 342px) / 2);
        }
        .detail-item.large {
            height: 260px;
            white-space: pre-wrap; /* To respect newlines */
            overflow-y: auto;
        }

        /* Comments Section (Right) */
        .comments-section {
            width: 386px;
            background: #E0E7ED;
            border-radius: 10px;
            padding: 15px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }
        .comments-list {
            flex-grow: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }
        .comment-card {
            display: flex;
            flex-direction: column;
        }
        .comment-header {
            font-size: 16px;
            color: #8E8B8B;
            margin-bottom: 5px;
        }
        .comment-body {
            background: #FFFFFF;
            border-radius: 10px;
            padding: 15px;
            min-height: 72px;
            font-size: 20px;
            color: #333;
        }
        .comment-form {
            display: flex;
            gap: 10px;
            margin-top: auto; /* Pushes form to the bottom */
        }
        .comment-input {
            flex-grow: 1;
            background: #FFFFFF;
            border-radius: 10px;
            border: none;
            padding: 10px 15px;
            font-size: 20px;
        }
        .comment-input::placeholder {
            color: #8E8B8B;
        }
        .comment-submit-button {
            background: #5C9EDC;
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            width: 70px;
            height: 47px;
            font-size: 20px;
            cursor: pointer;
        }

    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-left">
                <a href="logout.php">ログアウト</a>
            </div>
            <nav class="header-nav">
                <a href="top.php">TOP</a>
                <a href="reports_list.php">日報一覧</a>
                <a href="#">仮週報作成</a>
            </nav>
            <div class="header-right">
                <a href="mypage.php">マイページ</a>
            </div>
        </header>

        <main class="main-content">
            <div style="width: 100%;">
                <a href="reports_list.php" class="back-button">
                    <div class="back-button-arrow"></div>
                    <span>一覧</span>
                </a>
                <div class="content-wrapper">
                    <!-- Report Details -->
                    <section class="report-details">
                        <h2><?php echo htmlspecialchars($report['date'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <div class="detail-item"><?php echo htmlspecialchars($report['summary'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="detail-item time"><?php echo htmlspecialchars($report['work_time'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="detail-item large"><?php echo htmlspecialchars($report['details'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="detail-item"><?php echo htmlspecialchars($report['next_summary'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </section>

                    <!-- Comments Section -->
                    <aside class="comments-section">
                        <div class="comments-list">
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment-card">
                                    <div class="comment-header">
                                        <?php echo htmlspecialchars($comment['timestamp'], ENT_QUOTES, 'UTF-8'); ?>　
                                        <?php echo htmlspecialchars($comment['author'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <div class="comment-body">
                                        <?php echo nl2br(htmlspecialchars($comment['body'], ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form action="submit_comment.php" method="post" class="comment-form">
                            <input type="text" name="comment" class="comment-input" placeholder="コメントを入力">
                            <button type="submit" class="comment-submit-button">送信</button>
                        </form>
                    </aside>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
```

<!--
[PROMPT_SUGGESTION]「送信」ボタンを押したときにコメントを保存する`submit_comment.php`を作成してください。[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]日報一覧ページで各日報カードをクリックしたら、その日報のIDを渡してこの詳細ページに遷移するようにしてください。[/PROMPT_SUGGESTION]
