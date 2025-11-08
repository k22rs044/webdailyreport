<?php
session_start();
require_once 'db_config.php';

// ログインしていない場合はログインページにリダイレクト（開発中はコメントアウト）

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// --- 並び替え順の取得 ---
$sort_order = $_GET['sort_order'] ?? 'new'; // デフォルトは新しい順
$order_by = '';
if ($sort_order === 'old') {
    $order_by = 'ORDER BY task_at ASC'; // 古い順
} else {
    $order_by = 'ORDER BY task_at DESC'; // 新しい順 (デフォルト)
}

$next_tasks = [];
try {
    // ユーザーIDに基づいて次回作業概要を取得 (Task_Contentテーブルから)
    $sql = "SELECT task_id, task_content FROM Task_Content WHERE user_id = ? " . $order_by;
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $next_tasks[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log($e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>次回作業概要一覧</title>
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

        /* Filter Bar */
        .filter-bar {
            display: flex;
            align-items: center;
            background-color: #E0E7ED;
            border-radius: 10px;
            padding: 10px 20px;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .sort-options {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .sort-options .sort-label {
            font-weight: 400;
        }
        .radio-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .new-task-button {
            margin-left: auto;
            background-color: #5C9EDC;
            color: #FFFFFF;
            padding: 8px 20px;
            border-radius: 10px;
            font-size: 20px;
            font-weight: 400;
        }

        /* Task List */
        .task-list {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 13px; /* 322 - 309, 385 - 372, etc. */
        }
        .task-item {
            width: 530px;
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
        .task-item:hover {
            background-color: #d1d9e0;
        }

        /* Popup Styles (template.phpから流用) */
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
        .popup-input {
            width: 100%;
            background: #FFFFFF;
            border: 1px solid #ccc;
            border-radius: 7px;
            padding: 10px 15px;
            font-size: 16px;
            box-sizing: border-box;
        }
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
            <form action="next_tasks.php" method="GET" class="filter-bar">
                <div class="sort-options">
                    <span class="sort-label">並び替え</span>
                    <div class="radio-group">
                        <input type="radio" id="sort-new" name="sort_order" value="new" <?php if ($sort_order === 'new') echo 'checked'; ?> onchange="this.form.submit()">
                        <label for="sort-new">新しい順</label>
                    </div>
                    <div class="radio-group">
                        <input type="radio" id="sort-old" name="sort_order" value="old" <?php if ($sort_order === 'old') echo 'checked'; ?> onchange="this.form.submit()">
                        <label for="sort-old">古い順</label>
                    </div>
                </div>
                <a href="#" id="show-new-task-popup" class="new-task-button">新規登録</a>
            </form>

            <section id="task-list" class="task-list">
                <?php foreach ($next_tasks as $task): ?>
                    <a href="#" class="task-item" data-id="<?php echo htmlspecialchars($task['task_id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($task['task_content'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endforeach; ?>
            </section>
        </main>

        <!-- New Task Popup -->
        <div id="new-task-popup" class="popup-overlay">
            <div class="popup-window">
                <h2>新規作業概要登録</h2>
                <form id="new-task-form" style="width: 100%;">
                    <div class="popup-form-group">
                        <label for="new-task-summary">作業概要</label>
                        <input type="text" id="new-task-summary" name="task_summary" class="popup-input" required>
                    </div>
                    <div class="popup-buttons">
                        <button type="button" id="cancel-new-task" class="popup-button popup-cancel-button">キャンセル</button>
                        <button type="submit" class="popup-button popup-submit-button">登録</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const popup = document.getElementById('new-task-popup');
        const showPopupBtn = document.getElementById('show-new-task-popup');
        const cancelBtn = document.getElementById('cancel-new-task');
        const form = document.getElementById('new-task-form');
        const taskList = document.getElementById('task-list');

        // ポップアップ表示
        showPopupBtn.addEventListener('click', (e) => {
            e.preventDefault();
            form.reset();
            popup.style.display = 'flex';
        });

        // ポップアップ非表示
        const closePopup = () => { popup.style.display = 'none'; };
        cancelBtn.addEventListener('click', closePopup);
        popup.addEventListener('click', (e) => {
            if (e.target === popup) closePopup();
        });

        // フォーム送信処理
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);

            try {
                const response = await fetch('next_task_create_process.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    const newTask = document.createElement('a');
                    newTask.href = '#';
                    newTask.className = 'task-item';
                    newTask.dataset.id = result.new_task.id;
                    newTask.textContent = result.new_task.summary;

                    taskList.prepend(newTask); // リストの先頭に追加
                    closePopup();
                } else {
                    alert('エラー: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('登録中にエラーが発生しました。');
            }
        });
    });
    </script>
</body>
</html>


<!--
[PROMPT_SUGGESTION]「新規登録」ボタンを押したときに、新しい作業概要を登録するフォームページを作成してください。[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]認証機能を再度有効にして、正しいIDとパスワードでないとログインできないように戻してください。[/PROMPT_SUGGESTION]
