<?php
session_start();
require_once 'db_config.php';

// 管理者でない場合はTOPページにリダイレクト
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: top.php');
    exit;
}

// 並び替え条件の取得
$sort_by = $_GET['sort_by'] ?? 'user_id'; // デフォルトの並び替え項目
$sort_order = $_GET['sort_order'] ?? 'asc'; // デフォルトの並び替え順序

// 並び替え項目が許可されたリストに含まれているか検証（SQLインジェクション対策）
$allowed_sort_columns = ['user_id', 'submission_count', 'submission_rate', 'days_since_last_report'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'user_id'; // 不正な値の場合はデフォルトに戻す
}

// 並び替え順序が asc または desc であることを確認
$sort_order = strtolower($sort_order) === 'desc' ? 'DESC' : 'ASC';

// ORDER BY句を構築
$order_by_clause = "ORDER BY {$sort_by} {$sort_order}";

// 提出率や最終提出日がNULLの場合の並び順を制御
if ($sort_by === 'submission_rate' || $sort_by === 'days_since_last_report') {
    $order_by_clause = "ORDER BY CASE WHEN {$sort_by} IS NULL THEN 1 ELSE 0 END, {$sort_by} {$sort_order}";
}


$users = [];
try {
    // Userテーブルから全ユーザー情報を取得し、提出日数と提出率を計算するサブクエリを追加
    $sql = "SELECT 
                u.user_id, 
                u.name, 
                u.email,
                u.role,
                DATEDIFF(CURDATE(), (SELECT MAX(report_date) FROM Report WHERE user_id = u.user_id)) AS days_since_last_report,
                (SELECT COUNT(report_id) FROM Report WHERE user_id = u.user_id) AS submission_count,
                (
                    SELECT
                        ROUND((COUNT(report_id) / (
                            SELECT DATEDIFF(CURDATE(), MIN(report_date)) + 1
                            FROM Report 
                            WHERE user_id = u.user_id
                        )) * 100) 
                    FROM Report 
                    WHERE user_id = u.user_id
                ) AS submission_rate
            FROM User u
            " . $order_by_clause;

    $stmt = $mysqli->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // 提出率がNULLの場合に0を設定
        if ($row['submission_rate'] === null) {
            $row['submission_rate'] = 0;
        }
        if ($row['days_since_last_report'] === null) {
            $row['days_since_last_report'] = 'N/A';
        }

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
        .notification-bell { cursor: pointer; position: relative; }
        .user-role-select {
            width: 80px; /* ドロップダウンの幅を調整 */
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
            width: 1200px;
            margin: 0 auto 30px; /* 中央寄せと下マージン */
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
            /* paddingを削除し、リスト自体で幅とマージンを管理 */
        }
        .user-list-header, .user-list-row {
            display: flex;
            align-items: center;
            width: 1080px; /* 幅を調整 */
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
            flex-basis: 50px;
        }
        .col-id {
            flex-basis: 120px;
        }
        .col-name {
            flex-basis: 150px; /* 以前の変更を元に戻し、メールアドレスの幅を確保 */
            border-left: 1px solid #FFFFFF;
            border-right: 1px solid #FFFFFF;
        }
        .col-email {
            /* flex-grow: 1; を削除 */
            flex-basis: 400px; /* 固定幅を指定 */
            justify-content: flex-start;
            padding-left: 15px;
            border-right: 1px solid #FFFFFF;
        }
        .col-role {
            flex-basis: 100px;
            border-right: 1px solid #FFFFFF;
        }
        .col-submission-count {
            flex-basis: 100px;
            border-right: 1px solid #FFFFFF;
        }
        .col-submission-rate {
            flex-basis: 80px;
            border-left: 1px solid #FFFFFF;
        }
        .col-last-report {
            flex-basis: 120px;
            border-left: 1px solid #FFFFFF;
        }

        .user-list-header .col-id,
        .user-list-header .col-name,
        .user-list-header .col-email,
        .user-list-header .col-submission-count,
        .user-list-header .col-submission-rate,
        .user-list-header .col-last-report {
            justify-content: center;
            padding-left: 0;
        }
        .user-list-row .col-name, .user-list-row .col-email {
            justify-content: flex-start;
            padding-left: 15px;
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

        /* Notification Popup Styles (from other pages) */
        .popup-overlay {
            display: none;
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
        .popup-window {
            position: relative;
            background: #FFFFFF;
            border: 5px solid #5C9EDC;
            border-radius: 10px;
            box-sizing: border-box;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .notification-popup-window {
            width: 460px;
            height: 500px;
            padding: 0;
            display: block;
            align-items: unset;
            flex-direction: unset;
        }
        .notification-popup-window .popup-title {
            position: absolute;
            width: 100px;
            height: 24px;
            left: 165px;
            top: 10px;
            font-family: 'Inter';
            font-style: normal;
            font-weight: 400;
            font-size: 20px;
            line-height: 24px;
            text-align: center;
            color: #000000;
            margin-bottom: 0;
        }
        .notification-popup-window .popup-list {
            position: absolute;
            width: 400px;
            height: 350px;
            left: calc(50% - 400px/2);
            top: 72px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            overflow-y: auto;
            padding: 0;
        }
        .notification-popup-window .popup-list-item {
            width: 400px;
            height: 50px;
            background: #E0E7ED;
            border-radius: 10px;
            font-family: 'Inter';
            font-style: normal;
            font-weight: 400;
            font-size: 13px;
            line-height: 140%;
            display: flex;
            align-items: center;
            text-align: center;
            color: #8E8B8B;
            justify-content: center;
            padding: 0;
            cursor: pointer;
        }
        .notification-popup-window .popup-list-item:hover {
            background-color: #d1d9e0;
        }
        .notification-popup-window .popup-list-item span {
            font-weight: bold;
            color: #5C9EDC;
            margin: 0 5px;
        }
        .notification-popup-window .popup-close-button {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            margin-top: 0;
            padding: 8px 25px; background: #8CBAE6; border: none; border-radius: 7px; font-size: 16px; cursor: pointer;
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
                <div id="notification-bell-icon" class="notification-bell">
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
            <form id="sort-form" action="admin.php" method="GET" class="sort-bar">
                <div class="sort-bar">
                    <span>並び替え</span>
                    <select name="sort_by" onchange="this.form.submit()">
                        <option value="user_id" <?php if ($sort_by === 'user_id') echo 'selected'; ?>>学籍番号</option>
                        <option value="submission_count" <?php if ($sort_by === 'submission_count') echo 'selected'; ?>>提出日数</option>
                        <option value="submission_rate" <?php if ($sort_by === 'submission_rate') echo 'selected'; ?>>提出率</option>
                        <option value="days_since_last_report" <?php if ($sort_by === 'days_since_last_report') echo 'selected'; ?>>最終提出日</option>
                    </select>
                    <label>
                        <input type="radio" name="sort_order" value="asc" onchange="this.form.submit()" <?php if ($sort_order === 'ASC') echo 'checked'; ?>> 昇順
                    </label>
                    <label>
                        <input type="radio" name="sort_order" value="desc" onchange="this.form.submit()" <?php if ($sort_order === 'DESC') echo 'checked'; ?>> 降順
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
                <div class="col col-role">権限</div>
                <div class="col col-submission-count">提出日数</div>
                <div class="col col-submission-rate">提出率</div>
                <div class="col col-last-report">最終提出日</div>
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
                            <div class="col col-role">
                                <select class="user-role-select" data-user-id="<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES, 'UTF-8'); ?>" data-original-value="<?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <option value="user" <?php if ($user['role'] === 'user') echo 'selected'; ?>>学生</option>
                                    <option value="admin" <?php if ($user['role'] === 'admin') echo 'selected'; ?>>管理者</option>
                                </select>
                            </div>
                            <div class="col col-submission-count"><?php echo htmlspecialchars($user['submission_count'] ?? 0, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="col col-submission-rate"><?php echo htmlspecialchars($user['submission_rate'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>%</div>
                            <div class="col col-last-report"><?php echo ($user['days_since_last_report'] !== 'N/A') ? htmlspecialchars($user['days_since_last_report'], ENT_QUOTES, 'UTF-8') . '日前' : '未提出'; ?></div>
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

    <!-- Notification Popup -->
    <div id="notification-popup-overlay" class="popup-overlay">
        <div class="popup-window notification-popup-window">
            <h3 class="popup-title">新しい通知</h3>
            <div id="notification-list" class="popup-list">
                <!-- 通知がここに動的に挿入されます -->
                <div class="popup-list-item">通知はありません</div>
            </div>
            <button class="popup-close-button">閉じる</button>
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

            // --- ユーザー権限変更機能 ---
            const userRoleSelects = document.querySelectorAll('.user-role-select');
            userRoleSelects.forEach(selectElement => {
                selectElement.addEventListener('change', async (e) => {
                    const userId = e.target.dataset.userId;
                    const newRole = e.target.value;

                    if (!confirm(`ユーザーID: ${userId} の権限を ${newRole === 'admin' ? '管理者' : '学生'} に変更しますか？`)) {
                        // ユーザーがキャンセルした場合、元の値に戻す
                        e.target.value = e.target.dataset.originalValue; 
                        return;
                    }

                    const formData = new FormData();
                    formData.append('user_id', userId);
                    formData.append('new_role', newRole);

                    try {
                        const response = await fetch('update_user_role.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.success) {
                            alert('権限が正常に更新されました。');
                        } else {
                            alert('権限の更新に失敗しました: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Error updating user role:', error);
                        alert('権限の更新中にエラーが発生しました。');
                    }
                });
                // 初期値を保存しておく
                selectElement.dataset.originalValue = selectElement.value;
            });

            // --- 通知ポップアップ機能 ---
            const notificationBell = document.getElementById('notification-bell-icon');
            const notificationPopup = document.getElementById('notification-popup-overlay');
            const notificationList = document.getElementById('notification-list');
            const closeNotificationBtn = notificationPopup.querySelector('.popup-close-button');

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
                                    notificationList.innerHTML = '<div class="popup-list-item">新しい通知はありません</div>';
                                }
                                window.location.href = item.href;
                            });

                            notificationList.appendChild(item);
                            commentIds.push(n.comment_id);
                        });
                    } else {
                        notificationList.innerHTML = '<div class="popup-list-item">新しい通知はありません</div>';
                    }
                } catch (error) {
                    console.error('通知の取得に失敗しました:', error);
                    notificationList.innerHTML = '<div class="popup-list-item">通知の取得に失敗しました</div>';
                }
            });
        }

        async function markNotificationsAsRead(commentIds) {
            if (commentIds.length === 0) return;
            const formData = new FormData();
            formData.append('comment_ids', JSON.stringify(commentIds));
            try { await fetch('mark_notifications_read.php', { method: 'POST', body: formData, keepalive: true }); } catch (error) { console.error('通知の既読化に失敗しました:', error); }
        }
        });
    </script>

</body>
</html>
