<?php
session_start();
require_once 'db_config.php';

// 管理者でない場合はTOPページにリダイレクト
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header('Location: top.php');
//     exit;
// }

// 並び替え条件の取得
$sort_order = $_GET['sort'] ?? 'asc'; // デフォルトは昇順
$order_by_clause = 'ORDER BY user_id ' . ($sort_order === 'desc' ? 'DESC' : 'ASC');


$users = [];
try {
    // Userテーブルから全ユーザー情報を取得
    $sql = "SELECT user_id, name, email FROM User " . $order_by_clause;
    $stmt = $mysqli->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Admin user fetch error: " . $e->getMessage());
    // エラーハンドリング: ユーザーにはエラーメッセージを表示するなど
    $error_message = "ユーザー情報の取得に失敗しました。";
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者画面</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: #FFFFFF;
            color: #000000;
        }
        a { text-decoration: none; color: inherit; }

        /* Header (全ページ共通化を推奨) */
        header {
            background: #5C9EDC;
            height: 50px;
            color: #FFFFFF;
        }
        .header-container {
            width: 1208px;
            height: 100%;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-left a, .header-right a {
            font-size: 24px;
            color: #FFFFFF;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 50px;
        }
        .header-nav {
            display: flex;
            align-items: center;
            gap: 50px;
        }

        /* Main Content */
        .main-container {
            width: 1200px;
            margin: 0 auto;
            padding: 30px 0;
        }

        /* Control Bar */
        .control-bar {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-left: 100px;
        }
        .delete-button {
            background-color: #DC5C5E;
            color: #000000;
            width: 100px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
        }
        .sort-bar {
            background-color: #E0E7ED;
            border-radius: 10px;
            padding: 5px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 16px;
        }
        .sort-bar label {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .new-user-button {
            background-color: #5C9EDC;
            color: #FFFFFF;
            width: 130px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 20px;
            margin-left: auto;
        }

        /* User List */
        .user-list-container {
            padding: 0 100px;
        }
        .user-list-header, .user-list-row {
            display: flex;
            align-items: center;
            width: 800px;
            height: 36px;
            background: #E0E7ED;
            border-radius: 10px;
            margin: 0 auto 2px; /* 中央寄せと行間の調整 */
        }
        .user-list-header {
            font-size: 16px;
            font-weight: bold;
        }
        .user-list-row {
            font-size: 14px;
        }

        .col {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            box-sizing: border-box;
        }
        .col-checkbox {
            flex-basis: 60px;
        }
        .col-id {
            flex-basis: 151px;
        }
        .col-name {
            flex-basis: 163px;
            border-left: 1px solid #FFFFFF;
            border-right: 1px solid #FFFFFF;
        }
        .col-email {
            flex-grow: 1;
            justify-content: flex-start;
            padding-left: 20px;
        }

        .user-list-header .col-id,
        .user-list-header .col-name,
        .user-list-header .col-email {
            justify-content: center;
            padding-left: 0;
        }

        input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }

        /* Popup Styles */
        .popup-overlay {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        /* New User Popup Styles */
        .new-user-popup-window {
            width: 600px;
            height: auto;
            background: #E0E7ED;
            border: 5px solid #5CDC69;
            border-radius: 10px;
            box-sizing: border-box;
            padding: 30px 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .new-user-popup-window h2 {
            font-size: 20px;
            font-weight: 400;
            margin: 0 0 25px 0;
            text-align: center;
        }
        .new-user-popup-window form {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .popup-form-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .popup-form-group label {
            font-size: 24px;
            flex-shrink: 0;
            width: 120px;
            text-align: right;
            margin-right: 20px;
        }
        .popup-form-group input, .popup-form-group select {
            width: 310px;
            height: 50px;
            background: #FFFFFF;
            border-radius: 10px;
            border: none;
            padding: 0 15px;
            font-size: 18px;
            box-sizing: border-box;
        }
        .popup-buttons {
            display: flex;
            justify-content: center;
            gap: 80px;
            margin-top: 25px;
        }
        .popup-button {
            width: 150px; 
            height: 50px; 
            border-radius: 10px; 
            border: none; 
            font-size: 24px; 
            color: #FFFFFF; 
            cursor: pointer; 
        }
        .popup-cancel-button { background: #5C9EDC; }
        .popup-submit-button { background: #34B717; }

        /* User Delete Popup Styles */
        .delete-user-popup-window {
            width: 600px;
            height: 400px;
            background: #E0E7ED;
            border: 5px solid #DC5C5E;
            border-radius: 10px;
            box-sizing: border-box;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .delete-user-popup-window .popup-title {
            font-size: 24px;
            font-weight: 400;
            color: #B70303;
            margin: 10px 0 20px 0;
        }
        .delete-user-list {
            width: 428px;
            height: 223px;
            background: #FFFFFF;
            border-radius: 10px;
            padding: 15px;
            box-sizing: border-box;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .delete-user-item {
            display: grid;
            grid-template-columns: 1fr 1fr;
            font-size: 16px;
            text-align: center;
        }
        .delete-user-item span:first-child {
            text-align: left;
            padding-left: 60px;
        }
        .delete-user-item span:last-child {
            text-align: left;
            padding-left: 40px;
        }
        .delete-user-popup-window .popup-buttons {
            gap: 120px;
        }
        .delete-user-popup-window .popup-button {
            width: 150px;
        }
        .delete-user-popup-window .popup-submit-button {
            background-color: #DC5C5C;
        }

        /* 削除ボタンのデフォルトの挙動を無効化 */
        .delete-button {
            cursor: pointer;
        }
        /* フォームをインライン表示にするためのスタイル */
        #delete-user-form {
            display: inline;
        }

    </style>
</head>
<body>
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

    <div class="main-container">
        <div class="control-bar">
            <a href="#" id="show-delete-popup" class="delete-button">削除</a>
            <form id="sort-form" action="admin.php" method="GET">
                <div class="sort-bar">
                    <span>並び替え</span>
                    <label>
                        <input type="radio" name="sort" value="asc" onchange="this.form.submit()" <?php if ($sort_order === 'asc') echo 'checked'; ?>> 昇順
                    </label>
                    <label>
                        <input type="radio" name="sort" value="desc" onchange="this.form.submit()" <?php if ($sort_order === 'desc') echo 'checked'; ?>> 降順
                    </label>
                </div>
            </form>
            <a href="#" id="show-new-user-popup" class="new-user-button">新規登録</a>
        </div>

        <div class="user-list-container">
            <!-- List Header -->
            <div class="user-list-header">
                <div class="col col-checkbox">
                    <input type="checkbox" id="select-all">
                </div>
                <div class="col col-id">学籍番号</div>
                <div class="col col-name">氏名</div>
                <div class="col col-email">メールアドレス</div>
            </div>

            <!-- User Rows -->
            <div id="user-list-form">
                <?php if (isset($error_message)): ?>
                    <p style="color: red; text-align: center;"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php elseif (empty($users)): ?>
                    <p style="text-align: center;">登録されているユーザーがいません。</p>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <div class="user-list-row">
                            <div class="col col-checkbox">
                                <input type="checkbox" name="user_ids[]" value="<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col col-id"><?php echo htmlspecialchars($user['user_id'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="col col-name"><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="col col-email"><?php echo $user['email'] ? htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') : '登録されていません'; ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- New User Popup -->
    <div id="new-user-popup" class="popup-overlay">
        <div class="new-user-popup-window">
            <h2>ユーザー 新規登録</h2>
            <form id="new-user-form">
                <div class="popup-form-group">
                    <label for="new-user-id">学籍番号</label>
                    <input type="text" id="new-user-id" name="user_id" required>
                </div>
                <div class="popup-form-group">
                    <label for="new-user-name">氏名</label>
                    <input type="text" id="new-user-name" name="name" required>
                </div>
                <div class="popup-form-group">
                    <label for="new-user-password">パスワード</label>
                    <input type="password" id="new-user-password" name="password" required>
                </div>
                <div class="popup-form-group">
                    <label for="new-user-role">権限</label>
                    <select id="new-user-role" name="role" required>
                        <option value="user">学生</option>
                        <option value="admin">管理者</option>
                    </select>
                </div>
                <div class="popup-buttons">
                    <button type="button" id="cancel-new-user" class="popup-button popup-cancel-button">キャンセル</button>
                    <button type="submit" class="popup-button popup-submit-button">登録</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete User Popup -->
    <div id="delete-user-popup" class="popup-overlay">
        <div class="delete-user-popup-window">
            <h3 class="popup-title">以下のユーザーを削除します</h3>
            <div id="delete-user-list" class="delete-user-list">
                <!-- 削除対象ユーザーがここに挿入されます -->
            </div>
            <form id="delete-user-form" class="popup-buttons">
                <!-- 削除対象のIDがここに挿入されます -->
                <button type="button" id="cancel-delete-user" class="popup-button popup-cancel-button">キャンセル</button>
                <button type="submit" class="popup-button popup-submit-button">削除</button>
            </form>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('select-all');
            const userCheckboxes = document.querySelectorAll('input[name="user_ids[]"]');

            // 「すべて選択」チェックボックスの処理
            selectAllCheckbox.addEventListener('change', function() {
                userCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });

            // New User Popup
            const newUserPopup = document.getElementById('new-user-popup');
            const showNewUserPopupBtn = document.getElementById('show-new-user-popup');
            const cancelNewUserBtn = document.getElementById('cancel-new-user');
            const newUserForm = document.getElementById('new-user-form');

            showNewUserPopupBtn.addEventListener('click', (e) => {
                e.preventDefault();
                newUserForm.reset();
                newUserPopup.style.display = 'flex';
            });

            const closeNewUserPopup = () => { newUserPopup.style.display = 'none'; };
            cancelNewUserBtn.addEventListener('click', closeNewUserPopup);
            newUserPopup.addEventListener('click', (e) => {
                if (e.target === newUserPopup) closeNewUserPopup();
            });

            newUserForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(newUserForm);

                try {
                    const response = await fetch('user_create_process.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        alert('新しいユーザーを登録しました。');
                        window.location.reload(); // ページをリロードしてリストを更新
                    } else {
                        alert('エラー: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('登録中にエラーが発生しました。');
                }
            });

            // Delete User Popup
            const deleteUserPopup = document.getElementById('delete-user-popup');
            const showDeletePopupBtn = document.getElementById('show-delete-popup');
            const cancelDeleteUserBtn = document.getElementById('cancel-delete-user');
            const deleteUserForm = document.getElementById('delete-user-form');
            const deleteUserListContainer = document.getElementById('delete-user-list');

            showDeletePopupBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const checkedCheckboxes = document.querySelectorAll('input[name="user_ids[]"]:checked');

                if (checkedCheckboxes.length === 0) {
                    alert('削除するユーザーを選択してください。');
                    return;
                }

                // ポップアップの中身をクリア
                deleteUserListContainer.innerHTML = '';
                // フォームの中身をクリア
                Array.from(deleteUserForm.querySelectorAll('input[type="hidden"]')).forEach(input => input.remove());

                checkedCheckboxes.forEach(checkbox => {
                    const userRow = checkbox.closest('.user-list-row');
                    const userId = userRow.querySelector('.col-id').textContent;
                    const userName = userRow.querySelector('.col-name').textContent;

                    // ポップアップに表示するユーザー情報を追加
                    const userItem = document.createElement('div');
                    userItem.className = 'delete-user-item';
                    userItem.innerHTML = `<span>${userId}</span><span>${userName}</span>`;
                    deleteUserListContainer.appendChild(userItem);

                    // フォームに削除対象のIDを追加
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'user_ids[]';
                    hiddenInput.value = checkbox.value;
                    deleteUserForm.appendChild(hiddenInput);
                });

                deleteUserPopup.style.display = 'flex';
            });

            const closeDeleteUserPopup = () => { deleteUserPopup.style.display = 'none'; };
            cancelDeleteUserBtn.addEventListener('click', closeDeleteUserPopup);
            deleteUserPopup.addEventListener('click', (e) => {
                if (e.target === deleteUserPopup) closeDeleteUserPopup();
            });

            deleteUserForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(deleteUserForm);
                const response = await fetch('user_delete_process.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    alert('選択したユーザーを削除しました。');
                    window.location.reload();
                } else {
                    alert('エラー: ' + result.message);
                }
            });
        });
    </script>

</body>
</html>
