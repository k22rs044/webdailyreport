<?php
session_start();
require_once 'db_config.php';

// ログインしていない場合はログインページにリダイレクト（開発中はコメントアウト）
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id']; // 開発中は仮の値を使用


$templates = [];
try {
    // ユーザーIDに基づいてテンプレートを取得
    $sql = "SELECT template_id, title, content FROM Detail_Template WHERE user_id = ? ORDER BY created_at DESC";
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
        .popup-cancel-button {
            background-color: #5C9EDC;
        }
        /* フォームをインライン表示にするためのスタイル */
        #delete-template-form {
            display: inline;
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

    <div class="container">
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
                    <div class="detail-content">
                        <?php echo htmlspecialchars($selected_template['content'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
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
                <div class="popup-buttons">
                    <button type="button" id="cancel-delete" class="popup-button popup-cancel-button">キャンセル</button>
                    <form id="delete-template-form">
                        <input type="hidden" id="delete-template-id" name="template_id">
                        <button type="submit" class="popup-button popup-delete-button">削除</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

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
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const popup = document.getElementById('new-template-popup');
        const showPopupBtn = document.getElementById('show-new-template-popup');
        const cancelBtn = document.getElementById('cancel-new-template');
        const form = document.getElementById('new-template-form');
        const templateList = document.getElementById('template-list');

        const deletePopup = document.getElementById('delete-confirm-popup');
        const showDeletePopupBtn = document.getElementById('show-delete-popup');
        const cancelDeleteBtn = document.getElementById('cancel-delete');
        const deleteForm = document.getElementById('delete-template-form');

        const editPopup = document.getElementById('edit-template-popup');
        const showEditPopupBtn = document.getElementById('show-edit-popup');
        const cancelEditBtn = document.getElementById('cancel-edit-template');
        const editForm = document.getElementById('edit-template-form');


        // ポップアップ表示
        showPopupBtn.addEventListener('click', (e) => {
            e.preventDefault();
            form.reset();
            popup.style.display = 'flex';
        });

        // ポップアップ非表示（キャンセルボタン）
        cancelBtn.addEventListener('click', () => {
            popup.style.display = 'none';
        });

        // ポップアップ非表示（オーバーレイをクリック）
        popup.addEventListener('click', (e) => {
            if (e.target === popup) {
                popup.style.display = 'none';
            }
        });

        // フォーム送信処理
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(form);

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

        // --- 編集機能 ---
        // 編集ポップアップ表示
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
            const templateContent = '<?php echo htmlspecialchars($selected_template['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?>';

            document.getElementById('edit-template-id').value = templateId;
            document.getElementById('edit-title').value = templateTitle;
            document.getElementById('edit-content').value = templateContent;

            editPopup.style.display = 'flex';
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

    });
    </script>
    </div>
</body>
</html>
```

<!--
[PROMPT_SUGGESTION]「新規登録」ボタンを押したときに、新しいテンプレートを作成するフォームページを作成してください。[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]認証機能を再度有効にして、正しいIDとパスワードでないとログインできないように戻してください。[/PROMPT_SUGGESTION]
