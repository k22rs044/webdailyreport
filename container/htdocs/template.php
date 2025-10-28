<?php
session_start();

// ログインしていない場合はログインページにリダイレクト（開発中はコメントアウト）
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }

// --- サンプルデータ ---
$templates = [
    ['id' => 1, 'title' => '定例会議議事録', 'content' => "■決定事項\n\n\n■確認事項\n\n\n■TODO\n- [ ] 〇〇さん：〜の件\n- [ ] 自分：〜の調査"],
    ['id' => 2, 'title' => '障害一次対応', 'content' => "■発生日時\n\n\n■事象\n\n\n■原因調査\n\n\n■対応\n\n\n■恒久対応案"],
    ['id' => 3, 'title' => '新規機能開発', 'content' => "■背景・目的\n\n\n■実装内容\n\n\n■課題・懸念点"],
    ['id' => 4, 'title' => '問い合わせ調査', 'content' => "■問い合わせ内容\n\n\n■調査内容\n\n\n■回答"],
];

// URLから選択されたテンプレートIDを取得。なければ最初のものを選択
$selected_id = $_GET['id'] ?? $templates[0]['id'] ?? null;
$selected_template = null;
if ($selected_id) {
    foreach ($templates as $template) {
        if ($template['id'] == $selected_id) {
            $selected_template = $template;
            break;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>作業詳細テンプレート</title>
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
        /* Main Content Layout */
        .main-content {
            display: flex;
            gap: 20px;
            padding: 20px 40px;
        }

        /* Left Column: Template List */
        .template-list-column {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .filter-bar {
            display: flex;
            align-items: center;
            background-color: #E0E7ED;
            border-radius: 10px;
            padding: 10px 20px;
            font-size: 16px;
            width: 500px;
        }
        .sort-options {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .radio-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .template-list {
            display: flex;
            flex-direction: column;
            gap: 13px;
        }
        .template-item {
            width: 312px;
            height: 50px;
            background: #E0E7ED;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 16px;
            color: #8E8B8B;
            cursor: pointer;
        }
        .template-item.active, .template-item:hover {
            background-color: #d1d9e0;
            color: #333;
            border: 1px solid #5C9EDC;
        }

        /* Right Column: Template Detail */
        .template-detail-column {
            width: 380px;
            background: #E0E7ED;
            border-radius: 10px;
            padding: 15px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .detail-header {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .action-button {
            width: 130px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 20px;
            color: #FFFFFF;
        }
        .new-button { background-color: #5C9EDC; }
        .delete-button { background-color: #DC5C5E; }
        .edit-button { background-color: #5C9EDC; margin-top: auto; align-self: center; }

        .detail-title, .detail-content {
            background: #FFFFFF;
            border-radius: 10px;
            padding: 15px;
            color: #333;
            font-size: 16px;
        }
        .detail-title {
            height: 50px;
            box-sizing: border-box;
            display: flex;
            align-items: center;
        }
        .detail-content {
            height: 355px;
            box-sizing: border-box;
            white-space: pre-wrap; /* To respect newlines */
            overflow-y: auto;
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
            <!-- Left Column -->
            <aside class="template-list-column">
                <section class="filter-bar">
                    <div class="sort-options">
                        <span>並び替え</span>
                        <div class="radio-group">
                            <input type="radio" id="sort-new" name="sort_order" value="new" checked>
                            <label for="sort-new">新しい順</label>
                        </div>
                        <div class="radio-group">
                            <input type="radio" id="sort-old" name="sort_order" value="old">
                            <label for="sort-old">古い順</label>
                        </div>
                    </div>
                </section>
                <section class="template-list">
                    <?php foreach ($templates as $template): ?>
                        <a href="?id=<?php echo $template['id']; ?>" class="template-item <?php echo ($template['id'] == $selected_id) ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($template['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endforeach; ?>
                </section>
            </aside>

            <!-- Right Column -->
            <section class="template-detail-column">
                <div class="detail-header">
                    <a href="#" class="action-button delete-button">削除</a>
                    <a href="#" class="action-button new-button">新規登録</a>
                </div>

                <?php if ($selected_template): ?>
                    <div class="detail-title">
                        <?php echo htmlspecialchars($selected_template['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="detail-content">
                        <?php echo htmlspecialchars($selected_template['content'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php else: ?>
                    <div class="detail-title">テンプレートを選択してください</div>
                    <div class="detail-content"></div>
                <?php endif; ?>

                <a href="#" class="action-button edit-button">編集</a>
            </section>
        </main>
    </div>
</body>
</html>
```

<!--
[PROMPT_SUGGESTION]「新規登録」ボタンを押したときに、新しいテンプレートを作成するフォームページを作成してください。[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]認証機能を再度有効にして、正しいIDとパスワードでないとログインできないように戻してください。[/PROMPT_SUGGESTION]
