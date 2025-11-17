<?php
session_start();
require_once 'db_config.php';

// ログインしていない場合はログインページにリダイレクト（開発中はコメントアウト）
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
// URLから日報IDを取得
$report_id = $_GET['id'] ?? null; // report_id を取得

// 現在ログインしているユーザーのIDを取得
$user_id = $_SESSION['user_id'] ?? null;

// 現在ログインしているユーザーの名前を取得
$current_user_name = "不明なユーザー";
if ($user_id) {
    try {
        $sql_user = "SELECT name FROM User WHERE user_id = ?";
        $stmt_user = $mysqli->prepare($sql_user);
        $stmt_user->bind_param('s', $user_id);
        $stmt_user->execute();
        $current_user_name = $stmt_user->get_result()->fetch_assoc()['name'] ?? '不明なユーザー';
        $stmt_user->close();
    } catch (Exception $e) { error_log("Error fetching current user name: " . $e->getMessage()); }
}
$report = null;


if ($report_id) {
    try {
        // プリペアドステートメントを使用して日報データを取得
        $is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

        if ($is_admin) {
            // 管理者の場合はreport_idのみで検索
            $sql = "SELECT report_date, task, detail, next_task, work_time FROM Report WHERE report_id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('s', $report_id);
        } else {
            // 一般ユーザーの場合は自分の日報のみ
            $sql = "SELECT report_date, task, detail, next_task, work_time FROM Report WHERE report_id = ? AND user_id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('ss', $report_id, $user_id);
        }
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

// コメントをデータベースから取得
$comments = [];
if ($report_id) {
    try {
        $sql_comments = "SELECT c.comment_content, c.comment_at, u.name AS author_name 
                        FROM Comment c
                        JOIN User u ON c.user_id = u.user_id
                        WHERE c.report_id = ?
                        ORDER BY c.comment_at DESC";
        $stmt_comments = $mysqli->prepare($sql_comments);
        $stmt_comments->bind_param('s', $report_id);
        $stmt_comments->execute();
        $result_comments = $stmt_comments->get_result();
        while ($row = $result_comments->fetch_assoc()) {
            $comments[] = $row;
        }
        $stmt_comments->close();
    } catch (Exception $e) {
        error_log("Error fetching comments: " . $e->getMessage());
    }
}


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
        .detail-item.large { /*作業詳細*/
            height: 260px;
            white-space: pre-wrap; /* To respect newlines */
            overflow-y: auto;
            line-height: 0.7; /* フォントサイズのn倍の行間 */
        }

        /* Comments Section (Right) */
        .comments-section {
            width: 386px;
            background: #E0E7ED;
            border-radius: 10px;
            padding: 15px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column; /* 修正 */
            height: 600px; /* 高さを固定 */
        }
        .comments-list {
            flex-grow: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column-reverse; /* 新しいコメントが下に来るように逆順にする */
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
            /*min-height: 72px;*/
            font-size: 20px;
            color: #333;
            line-height: 0.5; /* フォントサイズのn倍の行間 */
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
            left: 165px;
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
                <div id="notification-bell-icon" class="notification-bell" style="cursor: pointer; position: relative;">
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

                    <!-- Comments Section -->
                    <?php if ($report): // 日報が存在する場合のみコメント欄を表示 ?>
                    <aside class="comments-section">
                        <div class="comments-list">
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment-card">
                                    <div class="comment-header">
                                        <?php
                                            // DBの時刻(UTCと仮定)を日本時間に変換して表示
                                            $comment_date = new DateTime($comment['comment_at'], new DateTimeZone('UTC'));
                                            $comment_date->setTimezone(new DateTimeZone('Asia/Tokyo'));
                                            echo htmlspecialchars($comment_date->format('Y/m/d H:i'), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <?php echo htmlspecialchars($comment['author_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <div class="comment-body">
                                        <?php echo nl2br(htmlspecialchars($comment['comment_content'], ENT_QUOTES, 'UTF-8')); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form id="comment-form" action="submit_comment.php" method="post" class="comment-form">
                            <input type="hidden" name="report_id" value="<?php echo htmlspecialchars($report_id, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="text" name="comment" class="comment-input" placeholder="コメントを入力">
                            <button type="submit" class="comment-submit-button">送信</button>
                        </form>
                    </aside>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    </div>
</body>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const commentForm = document.getElementById('comment-form');
        const commentInput = document.querySelector('input[name="comment"]');
        const commentsList = document.querySelector('.comments-list');

        if (commentForm) {
            commentForm.addEventListener('submit', async function(e) {
                e.preventDefault(); // フォームのデフォルト送信を防止

                const formData = new FormData(commentForm);
                const commentText = formData.get('comment').trim();

                if (!commentText) {
                    alert('コメントを入力してください。');
                    return;
                }

                try {
                    const response = await fetch('submit_comment.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        // 新しいコメントをリストに動的に追加
                        const newCommentDiv = document.createElement('div');
                        newCommentDiv.classList.add('comment-card');

                        // PHPのnl2br相当の処理をJSで行う
                        const commentBody = result.comment.comment_content.replace(/\n/g, '<br>');

                        newCommentDiv.innerHTML = `
                            <div class="comment-header">
                                ${result.comment.comment_at}
                                ${result.comment.author_name}
                            </div>
                            <div class="comment-body">
                                ${commentBody}
                            </div>
                        `;
                        commentsList.prepend(newCommentDiv); // column-reverseのため、prependで末尾に追加される
                        commentInput.value = ''; // 入力フィールドをクリア
                        // column-reverseなのでスクロールは不要
                    } else {
                        alert('コメントの投稿に失敗しました: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('コメントの送信中にエラーが発生しました。');
                }
            });
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
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
                console.log("ベルアイコンがクリックされました。");
                notificationPopup.style.display = 'flex';
                
                try {
                    const response = await fetch('get_notifications.php');
                    if (!response.ok) throw new Error(`サーバーエラー: ${response.status}`);
                    
                    const result = await response.json();
                    console.log("通知APIからのレスポンス:", result);

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

                            item.addEventListener('click', (e) => {
                                e.preventDefault();
                                markNotificationsAsRead([n.comment_id]);
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
            if (navigator.sendBeacon) { navigator.sendBeacon('mark_notifications_read.php', formData); } else { try { await fetch('mark_notifications_read.php', { method: 'POST', body: formData, keepalive: true }); } catch (error) { console.error('通知の既読化に失敗しました:', error); } }
        }
    });
</script>
</html>



<!--
[PROMPT_SUGGESTION]「送信」ボタンを押したときにコメントを保存する`submit_comment.php`を作成してください。[/PROMPT_SUGGESTION]
