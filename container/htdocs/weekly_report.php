<?php
session_start();
require_once 'db_config.php';

// ログインしていない場合はログインページにリダイレクト（開発中はコメントアウト）
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// 週の開始日（火曜日）を計算
$today = new DateTime();
$day_of_week = (int)$today->format('w'); // 0:日曜, 1:月曜, 2:火曜...
$days_to_subtract = ($day_of_week < 2) ? ($day_of_week + 7 - 2) : ($day_of_week - 2);
$start_date = (new DateTime())->sub(new DateInterval("P{$days_to_subtract}D"));

$weekly_reports = [];
$current_date = clone $start_date;
$weekdays_jp = ['日', '月', '火', '水', '木', '金', '土'];

// 7日間分のデータを取得
for ($i = 0; $i < 7; $i++) {
    $date_key = $current_date->format('Y-m-d');
    $day_index = (int)$current_date->format('w');
    $display_date = $current_date->format('n月j日') . '(' . $weekdays_jp[$day_index] . ')';

    try {
        $sql = "SELECT task, detail FROM Report WHERE user_id = ? AND report_date = ?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('ss', $user_id, $date_key);
        $stmt->execute();
        $result = $stmt->get_result();
        $report = $result->fetch_assoc();
        $stmt->close();

        if ($report) {
            $weekly_reports[] = [
                'date' => $display_date,
                'title' => $report['task'],
                'details' => $report['detail']
            ];
        } else {
            $weekly_reports[] = ['date' => $display_date, 'title' => '該当データなし', 'details' => ''];
        }
    } catch (Exception $e) {
        error_log("Weekly report fetch error: " . $e->getMessage());
        $weekly_reports[] = ['date' => $display_date, 'title' => 'エラー', 'details' => 'データの取得に失敗しました。'];
    }

    $current_date->add(new DateInterval('P1D'));
}

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
            font-size: 13px;
            justify-content: center;
        }
        .notification-popup-window .popup-list-item span { font-weight: bold; color: #5C9EDC; margin: 0 5px; }
        .notification-popup-window .popup-close-button {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            margin-top: 0;
        }
        .popup-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; }
        .popup-window { position: relative; background: #FFFFFF; border: 5px solid #5C9EDC; border-radius: 10px; box-sizing: border-box; padding: 20px; display: flex; flex-direction: column; align-items: center; }
        .popup-list-item { width: 100%; height: 50px; background: #E0E7ED; border-radius: 10px; display: flex; justify-content: center; align-items: center; font-size: 16px; color: #8E8B8B; cursor: pointer; }
        .popup-list-item:hover { background-color: #d1d9e0; }
        .popup-close-button { margin-top: auto; padding: 8px 25px; background: #8CBAE6; border: none; border-radius: 7px; font-size: 16px; cursor: pointer; }

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

        async function markNotificationsAsRead(commentIds) { if (commentIds.length === 0) return; const formData = new FormData(); formData.append('comment_ids', JSON.stringify(commentIds)); if (navigator.sendBeacon) { navigator.sendBeacon('mark_notifications_read.php', formData); } else { try { await fetch('mark_notifications_read.php', { method: 'POST', body: formData, keepalive: true }); } catch (error) { console.error('通知の既読化に失敗しました:', error); } } }
    });
    </script>
</body>
</html>

<!--
[PROMPT_SUGGESTION]この週報をPDFとしてエクスポートする機能を追加してください。[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]週を選択するためのドロップダウンメニューをページ上部に追加してください。[/PROMPT_SUGGESTION]
