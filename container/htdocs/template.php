<?php
session_start();
require_once 'db_config.php';

// ログインしていない場合はログインページにリダイレクト（開発中はコメントアウト）
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id']; // 開発中は仮の値を使用

// --- 並び替え順の取得 ---
$sort_order = $_GET['sort_order'] ?? 'new'; // デフォルトは新しい順
$order_by = '';
if ($sort_order === 'old') {
    $order_by = 'ORDER BY created_at ASC, template_id ASC'; // 古い順。作成日が同じ場合はIDの昇順
} else {
    $order_by = 'ORDER BY created_at DESC, template_id DESC'; // 新しい順。作成日が同じ場合はIDの降順
}

$templates = [];
try {
    // ユーザーIDに基づいてテンプレートを取得
    $sql = "SELECT template_id, title, content, created_at FROM Detail_Template WHERE user_id = ? " . $order_by;
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $templates[] = ['id' => $row['template_id'], 'title' => $row['title'], 'content' => $row['content']];
    }
} catch (Exception $e) {
    error_log($e->getMessage());
}

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
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 100;
            box-sizing: border-box;
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
            flex-wrap: wrap; /* 折り返し */
            gap: 20px;
            padding: 70px 20px 20px 20px; /* ヘッダーの高さ(50px)を考慮 */
            justify-content: center;
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
            width: 300px;
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
            justify-content: center; /* タイトルを中央揃えに戻す */
        }
        .detail-content {
            height: 355px;
            box-sizing: border-box;
            white-space: pre-wrap; /* To respect newlines */
            overflow-y: auto;
            text-align: left; /* 内容を左寄せ */
        }

        /* Popup Styles */
        .popup-overlay {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .popup-window {
            width: 580px;
            background: #E0E7ED;
            border-radius: 10px;
            padding: 20px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }
        .popup-window h2 {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 10px 0;
        }
        .popup-form-group { width: 100%; }
        .popup-form-group label { font-size: 16px; margin-bottom: 8px; display: block; }
        .popup-input, .popup-textarea {
            width: 100%;
            background: #FFFFFF;
            border: 1px solid #ccc;
            border-radius: 7px;
            padding: 10px 15px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .popup-textarea { height: 300px; resize: vertical; }
        .popup-buttons { display: flex; gap: 20px; margin-top: 10px; }
        .popup-button {
            width: 130px;
            height: 42px;
            border-radius: 10px;
            border: none;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 20px;
            color: #FFFFFF;
            cursor: pointer;
        }
        .popup-cancel-button { background-color: #8E8B8B; }
        .popup-submit-button { background-color: #5C9EDC; }

        /* 削除確認ポップアップのスタイル */
        .delete-popup-window {
            width: 542px;
            height: 252px;
            background: #E0E7ED;
            border: 5px solid #DC5C5E;
            border-radius: 10px;
            box-sizing: border-box;
            padding: 25px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
        }
        .delete-popup-title {
            font-size: 24px;
            line-height: 1.4;
            color: #000000;
            margin: 0;
            text-align: center;
        }
        .delete-popup-template-name {
            width: 312px;
            height: 50px;
            background: #FFFFFF;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #8E8B8B;
            padding: 0 15px;
            box-sizing: border-box;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .delete-popup-window .popup-buttons {
            width: 100%;
            justify-content: center;
            gap: 100px;
        }
        .delete-popup-window .popup-button {
            width: 150px;
            height: 50px;
            font-size: 24px;
        }
        .popup-delete-button {
            background-color: #DC5C5C;
        }
        .delete-popup-window .popup-buttons {
            display: flex;
            justify-content: center;
            width: 100%; /* 親要素の幅いっぱいに広げる */
            gap: 100px; /* ボタン間の隙間 */
        }
        .popup-cancel-button {
            background-color: #5C9EDC;
        }


         /* Custom Scrollbar for Notification List (Seek Bar) */
        .notification-popup-window .popup-list::-webkit-scrollbar {
            width: 8px; /* スクロールバーの幅 */
        }

        .notification-popup-window .popup-list::-webkit-scrollbar-track {
            background: #f1f1f1; /* トラックの背景色 */
            border-radius: 10px;
        }

        .notification-popup-window .popup-list::-webkit-scrollbar-thumb {
            background: #888; /* サム（ドラッグする部分）の色 */
            border-radius: 10px;
        }

        .notification-popup-window .popup-list::-webkit-scrollbar-thumb:hover {
            background: #555; /* サムのホバー時の色 */
        }

        /* Notification Popup */
        /* Based on "通知p" and "Rectangle 5563" */
        .notification-popup-window {
            width: 460px;
            height: 500px; /* From Rectangle 5563 */
            
            /* Existing background, border, border-radius are correct */
            /* background: #FFFFFF; */
            /* border: 5px solid #5C9EDC; */
            /* border-radius: 10px; */

            /* Override default popup-window padding for absolute positioning of children */
            padding: 0; 
            
            /* Ensure children are positioned relative to this */
            position: relative; /* Already set by .popup-window */
            
            /* Remove flex properties from parent as children are absolutely positioned */
            display: block; /* Override display: flex */
            align-items: unset; /* Override align-items */
            flex-direction: unset; /* Override flex-direction */
        }

        .notification-popup-window .popup-title {
            /* From "通知" */
            position: absolute;
            width: 100px;
            height: 24px;
            left: 160px;
            top: 10px;
            font-family: 'Inter';
            font-style: normal;
            font-weight: 400;
            font-size: 20px;
            line-height: 24px;
            text-align: center;
            color: #000000;
            margin-bottom: 0; /* Remove default margin */
        }

        .notification-popup-window .popup-list {
            /* From "Group 60" */
            position: absolute;
            width: 400px;
            height: 350px;
            left: calc(50% - 400px/2); /* Centered horizontally */
            top: 72px;
            
            /* Keep existing flex properties for list items */
            display: flex;
            flex-direction: column;
            gap: 10px;
            overflow-y: auto;
            padding: 0; /* Remove default padding */
        }
        /* Popup Styles (from top.php) */
        .popup-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; }
        .popup-window { position: relative; background: #FFFFFF; border: 5px solid #5C9EDC; border-radius: 10px; box-sizing: border-box; padding: 20px; display: flex; flex-direction: column; align-items: center; }
        .popup-title { font-size: 16px; line-height: 19px; text-align: center; color: #000000; margin-bottom: 20px; }
        .popup-list { width: 100%; display: flex; flex-direction: column; gap: 10px; overflow-y: auto; padding: 0 10px; }
        .popup-list-item { 
            width: 100%; 
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
        .popup-list-item:hover { background-color: #d1d9e0; }
        .popup-close-button { margin-top: auto; padding: 8px 25px; background: #8CBAE6; border: none; border-radius: 7px; font-size: 16px; cursor: pointer; }
        
        .notification-popup-window .popup-list-item { 
            /* From "Rectangle 5489", "Rectangle 5490" */
            width: 400px; /* Fixed width for each item */
            height: 50px;
            background: #E0E7ED;
            border-radius: 10px;
            
            /* Text styles from "コメントがきた日報の日付を表示" */
            font-family: 'Inter';
            font-style: normal;
            font-weight: 400;
            font-size: 13px;
            line-height: 140%; /* または22px */
            display: flex;
            align-items: center;
            text-align: center;
            color: #8E8B8B;

            /* Override existing styles */
            justify-content: center; /* Center content horizontally within the item */
            padding: 0; /* Remove default padding */
            cursor: pointer; /* Already there */
        }
        .notification-popup-window .popup-list-item span { font-weight: bold; color: #5C9EDC; margin: 0 5px; }
        .notification-popup-window .popup-close-button {
            position: absolute;
            bottom: 10px; /* Adjust as needed */
            left: 50%;
            transform: translateX(-50%);
            margin-top: 0; /* Remove default margin-top: auto */
        }


        .popup-list-item:hover { background-color: #d1d9e0; }
        .popup-close-button { margin-top: auto; padding: 8px 25px; background: #8CBAE6; border: none; border-radius: 7px; font-size: 16px; cursor: pointer; }

        /* Password Change Popup Styles */
        .password-change-popup {
            width: 538px;
            height: 353px;
            background: #E0E7ED;
            border: 5px solid #D04141;
            border-radius: 10px;
            box-sizing: border-box;
            padding: 20px;
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
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="admin.php">管理者画面</a>
                    <?php endif; ?>
                </nav>
                <div id="notification-bell-icon" class="notification-bell" style="cursor: pointer; position: relative;">
                    <svg width="25" height="28" viewBox="0 0 25 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12.5 2.8C15.8152 2.8 18.9946 4.10678 21.3891 6.50126C23.7835 8.89574 25.0903 12.0752 25.0903 15.3903C25.0903 20.3903 25.0903 22.5903 25.0903 22.5903H-0.090332C-0.090332 22.5903 -0.090332 20.3903 -0.090332 15.3903C-0.090332 12.0752 1.21645 8.89574 3.61093 6.50126C6.00541 4.10678 9.18484 2.8 12.5 2.8Z" fill="white"/>
                        <path d="M16.5 24.8C16.5 25.5935 16.1839 26.3529 15.6213 26.9155C15.0587 27.4781 14.2993 27.8 13.5 27.8C12.7007 27.8 11.9413 27.4781 11.3787 26.9155C10.8161 26.3529 10.5 25.5935 10.5 24.8H16.5Z" fill="white"/>
                        <path d="M12.5 0C13.5625 0.4375 13.5625 1.5625 12.5 2.1875C11.4375 1.5625 11.4375 0.4375 12.5 0Z" fill="white"/>
                    </svg>
                    <span class="notification-badge" style="display: none; position: absolute; top: -2px; right: -2px; width: 10px; height: 10px; background-color: red; border-radius: 50%;"></span>
                </div>
            </div>
        </div>
    </header>

        <main class="main-content">
            <aside class="template-list-column">
                <!-- Left Column -->
                <form action="template.php" method="GET" class="filter-bar">
                    <div class="sort-options">
                        <span>並び替え</span>
                        <div class="radio-group">
                            <input type="radio" id="sort-new" name="sort_order" value="new" <?php if ($sort_order === 'new') echo 'checked'; ?> onchange="this.form.submit()">
                            <label for="sort-new">新しい順</label>
                        </div>
                        <div class="radio-group">
                            <input type="radio" id="sort-old" name="sort_order" value="old" <?php if ($sort_order === 'old') echo 'checked'; ?> onchange="this.form.submit()">
                            <label for="sort-old">古い順</label>
                        </div>
                    </div>
                </form>
                <section id="template-list" class="template-list">
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
                    <a href="#" id="show-delete-popup" class="action-button delete-button">削除</a>
                    <a href="#" id="show-new-template-popup" class="action-button new-button">新規登録</a>
                </div>

                <?php if ($selected_template): ?>
                    <div class="detail-title">
                        <?php echo htmlspecialchars($selected_template['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="detail-content"><?php echo htmlspecialchars($selected_template['content'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                    <div class="detail-title">テンプレートを選択してください</div>
                    <div class="detail-content"></div>
                <?php endif; ?>

                <a href="#" id="show-edit-popup" class="action-button edit-button">編集</a>
            </section>
        </main>

        <!-- New Template Popup -->
        <div id="new-template-popup" class="popup-overlay">
            <div class="popup-window">
                <h2>新規テンプレート登録</h2>
                <form id="new-template-form" style="width: 100%;">
                    <div class="popup-form-group">
                        <label for="new-title">テンプレート名</label>
                        <input type="text" id="new-title" name="title" class="popup-input" required>
                    </div>
                    <div class="popup-form-group">
                        <label for="new-content">内容</label>
                        <textarea id="new-content" name="content" class="popup-textarea" required></textarea>
                    </div>
                    <div class="popup-buttons">
                        <button type="button" id="cancel-new-template" class="popup-button popup-cancel-button">キャンセル</button>
                        <button type="submit" class="popup-button popup-submit-button">登録</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Popup -->
        <div id="delete-confirm-popup" class="popup-overlay">
            <div class="delete-popup-window">
                <p class="delete-popup-title">以下のテンプレートを削除します</p>
                <div id="delete-template-name" class="delete-popup-template-name"></div>
                <form id="delete-template-form" class="popup-buttons">
                    <input type="hidden" id="delete-template-id" name="template_id">
                    <button type="button" id="cancel-delete" class="popup-button popup-cancel-button">キャンセル</button>
                    <button type="submit" class="popup-button popup-delete-button">削除</button>
                </form>
            </div>
        </div>

        <!-- Edit Template Popup -->
        <div id="edit-template-popup" class="popup-overlay">
            <div class="popup-window">
                <h2>テンプレート編集</h2>
                <form id="edit-template-form" style="width: 100%;">
                    <input type="hidden" id="edit-template-id" name="template_id">
                    <div class="popup-form-group">
                        <label for="edit-title">テンプレート名</label>
                        <input type="text" id="edit-title" name="title" class="popup-input" required>
                    </div>
                    <div class="popup-form-group">
                        <label for="edit-content">内容</label>
                        <textarea id="edit-content" name="content" class="popup-textarea" required></textarea>
                    </div>
                    <div class="popup-buttons">
                        <button type="button" id="cancel-edit-template" class="popup-button popup-cancel-button">キャンセル</button>
                        <button type="submit" class="popup-button popup-submit-button">更新</button>
                    </div>
                </form>
            </div>
        </div>

    <!-- Notification Popup -->
    <div id="notification-popup-overlay" class="popup-overlay">
        <div class="popup-window notification-popup-window">
            <h3 class="popup-title">新しい通知</h3>
            <div id="notification-list" class="popup-list">
                <div class="popup-list-item">通知はありません</div>
            </div>
            <button class="popup-close-button">閉じる</button>
        </div>
    </div>

    <!-- Notification Popup -->
    <div id="notification-popup-overlay" class="popup-overlay">
        <div class="popup-window notification-popup-window">
            <h3 class="popup-title">新しい通知</h3>
            <div id="notification-list" class="popup-list">
                <div class="popup-list-item">通知はありません</div>
            </div>
            <button class="popup-close-button">閉じる</button>
        </div>
    </div>

    <script>
    const templateCount = <?php echo count($templates); ?>;

    document.addEventListener('DOMContentLoaded', function() { // 1. DOMContentLoadedを一つに統合
        const popup = document.getElementById('new-template-popup');
        const showPopupBtn = document.getElementById('show-new-template-popup');
        const cancelBtn = document.getElementById('cancel-new-template');
        const newTemplateForm = document.getElementById('new-template-form');
        const templateList = document.getElementById('template-list');
        const deletePopup = document.getElementById('delete-confirm-popup');
        const showDeletePopupBtn = document.getElementById('show-delete-popup');
        const cancelDeleteBtn = document.getElementById('cancel-delete');
        const deleteForm = document.getElementById('delete-template-form');

        const editPopup = document.getElementById('edit-template-popup');
        const showEditPopupBtn = document.getElementById('show-edit-popup');
        const cancelEditBtn = document.getElementById('cancel-edit-template');
        const editForm = document.getElementById('edit-template-form');

        // --- 新規登録機能 ---
        if (showPopupBtn) {
            showPopupBtn.addEventListener('click', (e) => {
                e.preventDefault();
                // テンプレート数が10以上の場合、アラートを表示して処理を中断
                if (templateCount >= 10) {
                    alert('テンプレートの登録は10個までです。');
                    return;
                }
                newTemplateForm.reset();
                popup.style.display = 'flex';
            });
        }
        // 新規登録ポップアップ非表示
        const closeNewPopup = () => { popup.style.display = 'none'; };
        cancelBtn.addEventListener('click', closeNewPopup);
        popup.addEventListener('click', (e) => {
            if (e.target === popup) closeNewPopup();
        });

        // ポップアップ非表示（オーバーレイをクリック）
        popup.addEventListener('click', (e) => {
            if (e.target === popup) {
                popup.style.display = 'none';
            }
        });

        // フォーム送信処理
        newTemplateForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(newTemplateForm);

            try {
                const response = await fetch('template_create_process.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // 新しいテンプレート要素を作成
                    const newTemplate = document.createElement('a');
                    newTemplate.href = `?id=${result.new_template.id}`;
                    newTemplate.className = 'template-item';
                    newTemplate.textContent = result.new_template.title;

                    // リストの先頭に追加
                    templateList.prepend(newTemplate);

                    popup.style.display = 'none';
                    // 必要であれば、ページをリロードして新しいテンプレートを選択状態にする
                    window.location.href = `template.php?id=${result.new_template.id}`;
                } else {
                    alert('エラー: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('テンプレートの登録中にエラーが発生しました。');
            }
        });

        // --- 削除機能 ---
        // 削除ポップアップ表示
        showDeletePopupBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const selectedTemplateItem = document.querySelector('.template-item.active');
            if (!selectedTemplateItem) {
                alert('削除するテンプレートを選択してください。');
                return;
            }

            const templateTitle = selectedTemplateItem.textContent.trim();
            const urlParams = new URLSearchParams(window.location.search);
            const templateId = urlParams.get('id');

            document.getElementById('delete-template-name').textContent = templateTitle;
            document.getElementById('delete-template-id').value = templateId;
            deletePopup.style.display = 'flex';
        });

        // 削除ポップアップ非表示
        const closeDeletePopup = () => { deletePopup.style.display = 'none'; };
        cancelDeleteBtn.addEventListener('click', closeDeletePopup);
        deletePopup.addEventListener('click', (e) => {
            if (e.target === deletePopup) closeDeletePopup();
        });

        // 削除フォーム送信処理
        deleteForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(deleteForm);

            try {
                const response = await fetch('template_delete_process.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    alert('テンプレートを削除しました。');
                    window.location.href = 'template.php'; // 一覧のトップにリダイレクト
                } else {
                    alert('エラー: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('削除中にエラーが発生しました。');
            }
        });

        // 編集ポップアップ非表示
        const closeEditPopup = () => { editPopup.style.display = 'none'; };
        cancelEditBtn.addEventListener('click', closeEditPopup);
        editPopup.addEventListener('click', (e) => {
            if (e.target === editPopup) closeEditPopup();
        });

        // 編集フォーム送信処理
        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(editForm);

            try {
                const response = await fetch('template_edit_process.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    alert('テンプレートを更新しました。');
                    // ページをリロードして変更を反映
                    window.location.reload();
                } else {
                    alert('エラー: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('更新中にエラーが発生しました。');
            }
        });

        // --- 編集機能 ---
        if (showEditPopupBtn) {
            showEditPopupBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const selectedTemplateItem = document.querySelector('.template-item.active');
                if (!selectedTemplateItem) {
                    alert('編集するテンプレートを選択してください。');
                    return;
                }

                // PHPから埋め込まれたデータを使用
                const templateId = '<?php echo $selected_template['id'] ?? ''; ?>';
                const templateTitle = '<?php echo htmlspecialchars($selected_template['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>';
                const templateContent = '<?php echo htmlspecialchars(str_replace(["\r\n", "\r", "\n"], "\\n", $selected_template['content'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>';

                document.getElementById('edit-template-id').value = templateId;
                document.getElementById('edit-title').value = templateTitle;
                document.getElementById('edit-content').value = templateContent.replace(/\\n/g, '\n');

                editPopup.style.display = 'flex';
            });
        }

        // --- 通知ポップアップ機能 ---
        const notificationBell = document.getElementById('notification-bell-icon');
        const notificationPopup = document.getElementById('notification-popup-overlay');
        const notificationList = document.getElementById('notification-list');
        const closeNotificationBtn = notificationPopup.querySelector('.popup-close-button');
        const notificationBadge = document.querySelector('.notification-badge');

        // Close the notification popup
        closeNotificationBtn.addEventListener('click', () => {
            notificationPopup.style.display = 'none';
        });

        // ポップアップの外側（オーバーレイ）をクリックしたときに閉じる
        notificationPopup.addEventListener('click', (e) => {
            if (e.target === notificationPopup) {
                notificationPopup.style.display = 'none';
            }
        });

        if (notificationBell) {
            notificationBell.addEventListener('click', async () => {
                notificationPopup.style.display = 'flex';
                
                try {
                    const response = await fetch('get_notifications.php');
                    if (!response.ok) throw new Error(`サーバーエラー: ${response.status}`);
                    
                    const result = await response.json();

                    if (result.success && result.notifications.length > 0) {
                        notificationList.innerHTML = '';
                        const commentIds = [];

                        result.notifications.forEach(n => {
                            const item = document.createElement('a');
                            item.href = `reports_detail.php?id=${n.report_id}`;
                            item.className = 'popup-list-item';
                            const reportDate = new Date(n.report_date).toLocaleDateString('ja-JP', { month: 'long', day: 'numeric' });

                            if (n.is_admin_view) {
                                item.innerHTML = `<span>${n.report_owner_name}</span>さんの日報に<span>${n.commenter_name}</span>さんがコメントしました。`;
                            } else {
                                item.innerHTML = `${reportDate}の日報に<span>${n.commenter_name}</span>さんからコメントがありました。`;
                            }

                            item.addEventListener('click', async (e) => {
                                e.preventDefault();
                                await markNotificationsAsRead([n.comment_id]);
                                item.remove(); // 通知をDOMから削除
                                if (notificationList.children.length === 0) { // 通知がなくなったらメッセージを表示
                                    notificationBadge.style.display = 'none';
                                    notificationList.innerHTML = '<div class="popup-list-item">新しい通知はありません</div>';
                                }
                                window.location.href = item.href;
                            });

                            notificationList.appendChild(item);
                            commentIds.push(n.comment_id);
                        });
                    } else {
                        notificationBadge.style.display = 'none';
                        notificationList.innerHTML = '<div class="popup-list-item">新しい通知はありません</div>';
                    }
                } catch (error) {
                    console.error('通知の取得に失敗しました:', error);
                    notificationList.innerHTML = '<div class="popup-list-item">通知の取得に失敗しました</div>';
                }
            });
        }

        // ページ読み込み時に未読通知をチェックしてバッジを表示
        async function checkUnreadNotifications() {
            try {
                const response = await fetch('get_notifications.php');
                const result = await response.json();
                if (result.success && result.notifications.length > 0) {
                    notificationBadge.style.display = 'block';
                } else {
                    notificationBadge.style.display = 'none';
                }
            } catch (error) {
                console.error('未読通知のチェックに失敗しました:', error);
            }
        }

        async function markNotificationsAsRead(commentIds) {
            if (commentIds.length === 0) return;
            const formData = new FormData();
            formData.append('comment_ids', JSON.stringify(commentIds));
            try {
                await fetch('mark_notifications_read.php', {
                    method: 'POST',
                    body: formData,
                    keepalive: true
                });
            } catch (error) { console.error('通知の既読化に失敗しました:', error); }
        }

        // ページが読み込まれたときに未読通知をチェック
        checkUnreadNotifications();
    });
    </script>
</body>
</html>

<!--
[PROMPT_SUGGESTION]「新規登録」ボタンを押したときに、新しいテンプレートを作成するフォームページを作成してください。[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]認証機能を再度有効にして、正しいIDとパスワードでないとログインできないように戻してください。[/PROMPT_SUGGESTION]
-->
