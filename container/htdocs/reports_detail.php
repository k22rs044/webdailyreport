<?php
session_start();
require_once 'db_config.php';

// ログインしていない場合はログインページにリダイレクト（開発中はコメントアウト）
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
// URLから日報IDを取得
$report_id = $_GET['id'] ?? null;
$report = null;


if ($report_id) {
    try {
        // プリペアドステートメントを使用して日報データを取得
        $sql = "SELECT report_date, task, detail, next_task, work_time FROM Report WHERE report_id = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('s', $report_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $db_report = $result->fetch_assoc();

        if ($db_report) {
            // 取得したデータを表示用に整形
            $date = new DateTime($db_report['report_date']);
            $work_time_minutes = (int)$db_report['work_time'];
            $hours = floor($work_time_minutes / 60);
            $minutes = $work_time_minutes % 60;

            $report = [
                'date' => $date->format('n月j日'),
                'summary' => $db_report['task'],
                'work_time' => "{$hours}時間{$minutes}分",
                'details' => $db_report['detail'],
                'next_summary' => $db_report['next_task']
            ];
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log($e->getMessage());
        // エラーが発生した場合は$reportをnullのままにする
    }
}


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
        a {
            text-decoration: none;
            color: inherit;
        }

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
            <div style="width: 100%;">
                <a href="reports_list.php" class="back-button">
                    <div class="back-button-arrow"></div>
                    <span>一覧</span>
                </a>
                <div class="content-wrapper">
                    <!-- Report Details -->
                    <section class="report-details">
                        <?php if ($report): ?>
                            <h2><?php echo htmlspecialchars($report['date'], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <div class="detail-item"><?php echo htmlspecialchars($report['summary'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="detail-item time"><?php echo htmlspecialchars($report['work_time'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="detail-item large"><?php echo nl2br(htmlspecialchars($report['details'], ENT_QUOTES, 'UTF-8')); ?></div>
                            <div class="detail-item"><?php echo htmlspecialchars($report['next_summary'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php else: ?>
                            <h2>日報が見つかりません</h2>
                            <div class="detail-item">指定された日報は存在しないか、表示できません。</div>
                        <?php endif; ?>
                    </section>

                    <!-- Comments Section -->
                    <?php if ($report): // 日報が存在する場合のみコメント欄を表示 ?>
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
                    <?php endif; ?>
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
