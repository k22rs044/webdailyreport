<?php

session_start();
require_once 'db_config.php';

// ログインしていない場合はログインページにリダイレクト
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// --- 絞り込み条件の取得 ---
$keyword = $_GET['keyword'] ?? '';
$period = $_GET['period'] ?? 'all';
$start_date_input = $_GET['start_date'] ?? '';
$end_date_input = $_GET['end_date'] ?? '';

// 期間選択に応じた日付範囲の計算
$start_date_sql = '';
$end_date_sql = '';

if ($period !== 'all') {
    $today = new DateTime();
    switch ($period) {
        case 'this_week':
            $start_date_sql = (new DateTime('this week'))->format('Y-m-d');
            $end_date_sql = (new DateTime('this week +6 days'))->format('Y-m-d');
            break;
        case 'last_week':
            $start_date_sql = (new DateTime('last week'))->format('Y-m-d');
            $end_date_sql = (new DateTime('last week +6 days'))->format('Y-m-d');
            break;
        case 'this_month':
            $start_date_sql = $today->format('Y-m-01');
            $end_date_sql = $today->format('Y-m-t');
            break;
        case 'half_year':
            $start_date_sql = (new DateTime('-6 months'))->format('Y-m-d');
            $end_date_sql = $today->format('Y-m-d');
            break;
    }
} else {
    // 期間指定がない場合は、入力された日付を使用
    $start_date_sql = $start_date_input;
    $end_date_sql = $end_date_input;
}

$reports = [];

// SQLのベース部分を管理者かどうかで切り替える
if ($is_admin) {
    // 管理者の場合: UserテーブルとJOINして氏名を取得
    $sql_base = "SELECT r.report_id, r.report_date, r.task, u.name 
                FROM Report r 
                JOIN User u ON r.user_id = u.user_id 
                 WHERE 1=1"; // 条件句の開始
    $params = [];
    $types = '';
} else {
    // 一般ユーザーの場合: 自分の日報のみ
    $sql_base = "SELECT report_id, report_date, task 
                FROM Report 
                WHERE user_id = ?";
    $params = [$user_id];
    $types = 's';
}

if (!empty($keyword)) {
    if ($is_admin) {
        $sql_base .= " AND (task LIKE ? OR u.name LIKE ?)";
        $params[] = '%' . $keyword . '%';
        $params[] = '%' . $keyword . '%';
        $types .= 'ss';
    } else {
        $sql_base .= " AND task LIKE ?";
        $params[] = '%' . $keyword . '%';
        $types .= 's';
    }
}
if (!empty($start_date_sql)) {
    $sql_base .= " AND " . ($is_admin ? "r." : "") . "report_date >= ?";
    $params[] = $start_date_sql;
    $types .= 's';
}
if (!empty($end_date_sql)) {
    $sql_base .= " AND " . ($is_admin ? "r." : "") . "report_date <= ?";
    $params[] = $end_date_sql;
    $types .= 's';
}

$sql_base .= " ORDER BY " . ($is_admin ? "r." : "") . "report_date DESC" . ($is_admin ? ", u.name ASC" : "");

try {
    $stmt = $mysqli->prepare($sql_base);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $date = new DateTime($row['report_date']);
        $reports[] = [
            'id'   => $row['report_id'],
            'date' => $date->format('n月j日'),
            'task' => $row['task'],
            'name' => $row['name'] ?? null // 管理者でない場合はnull
        ];
    }
} catch (Exception $e) {
    // エラーが発生した場合、ログに記録するなどして対処
    error_log($e->getMessage());
    // ユーザーにはエラーメッセージを表示することも可能
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>日報一覧</title>
    <style>
        /* 基本スタイルとリセット */
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: #FFFFFF;
            color: #000000;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .container {
            width: 1280px;
            margin: 0 auto;
            position: relative;
        }

        /* ヘッダー */
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
        .notification-bell {
            /* SVGアイコン用のスタイルプレースホルダー */
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 50px;
        }

        /* メインコンテンツ */
        .main-content {
            padding: 20px 40px;
        }

        /* フィルターバー */
        .filter-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            padding: 15px 0;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .filter-bar label {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-bar input[type="text"],
        .filter-bar select {
            border: 1px solid #D9D9D9;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 16px;
        }

        .filter-bar input[type="text"] {
            width: 300px;
        }

        .filter-bar .date-input {
            width: 120px;
            height: 40px;
        }

        .filter-bar .search-button {
            background-color: #5C9EDC;
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            padding: 6px 12px;
            width: 100px;
            height: 40px;
            cursor: pointer;
            font-size: large;            
            text-align: center;
        }

        .filter-bar .reset-button {
            background-color: #8E8B8B;
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            padding: 8px 12px; /* search-buttonと高さを合わせる */
            width: 80px;
            cursor: pointer;
            text-align: center;
        }

        /* 日付入力のカレンダーアイコンを大きくする */
        .filter-bar input[type="date"]::-webkit-calendar-picker-indicator {
            transform: scale(1.5); /* アイコンを1.5倍に拡大 */
            cursor: pointer;
            margin-right: 5px; /* アイコンの右側に少し余白を追加 */
        }

        /* 日報グリッド */
        .report-grid {
            display: grid;
            /* 7列のグリッドを作成 */
            grid-template-columns: repeat(7, 1fr);
            gap: 8px; /* カード間の隙間 */
        }

        .report-card {
            background-color: #E0E7ED;
            border-radius: 10px;
            padding: 8px;
            height: 82px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-sizing: border-box;
        }

        .report-card-date {
            font-size: 14px;
            text-align: left;
            font-weight: 400;
            padding-left: 5px;
        }

        .report-card-name {
            font-size: 12px;
            text-align: right;
            padding-right: 5px;
        }

        .report-card-summary {
            background-color: #FFFFFF;
            border: 1px solid #5C9EDC;
            border-radius: 10px;
            padding: 8px;
            text-align: center;
            font-size: 16px;
            line-height: 1.2;
            cursor: pointer;
            /* テキストがはみ出た場合に省略記号を表示 */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block; /* aタグをブロック要素にする */
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
        /* 提出完了ポップアップ */
        .registration-popup-window {
            width: 358px; height: 107px; border: 5px solid #5CDC69; justify-content: center;
            font-size: 24px;
            text-align: center;
        }

        .report-card-summary:hover {
            background-color: #f0f8ff;
        }

    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
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
            <form action="reports_list.php" method="GET" class="filter-bar">
                <label>
                    作業概要
                    <input type="text" name="keyword" placeholder="キーワードを入力" value="<?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>">
                </label>

                <select name="period" onchange="this.form.submit()">
                    <option value="all" <?php if ($period === 'all') echo 'selected'; ?>>全部</option>
                    <option value="this_week" <?php if ($period === 'this_week') echo 'selected'; ?>>今週</option>
                    <option value="last_week" <?php if ($period === 'last_week') echo 'selected'; ?>>先週</option>
                    <option value="this_month" <?php if ($period === 'this_month') echo 'selected'; ?>>今月</option>
                    <option value="half_year" <?php if ($period === 'half_year') echo 'selected'; ?>>半年</option>
                </select>

                <label>
                    開始日
                    <input type="date" name="start_date" class="date-input" value="<?php echo htmlspecialchars($start_date_input, ENT_QUOTES, 'UTF-8'); ?>">
                </label>
                <span>～</span>
                <label>
                    終了日
                    <input type="date" name="end_date" class="date-input" value="<?php echo htmlspecialchars($end_date_input, ENT_QUOTES, 'UTF-8'); ?>">
                </label>

                <button type="submit" class="search-button">絞り込み</button>
                <a href="reports_list.php" class="reset-button">リセット</a>
            </form>

            <section class="report-grid">
                <?php foreach ($reports as $report): ?>
                    <div class="report-card">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="report-card-date"><?php echo htmlspecialchars($report['date'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php if ($is_admin && $report['name']): ?>
                                <div class="report-card-name"><?php echo htmlspecialchars($report['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </div>
                        <a href="reports_detail.php?id=<?php echo htmlspecialchars($report['id'], ENT_QUOTES, 'UTF-8'); ?>" class="report-card-summary" title="<?php echo htmlspecialchars($report['task'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($report['task'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </section>
        </main>
    </div>
    </div>
    <!-- Notification Popup (from top.php) -->
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

                            // 通知アイテムクリック時に、その通知を既読にする
                            item.addEventListener('click', (e) => {
                                e.preventDefault(); // 即座のページ遷移を一旦停止
                                markNotificationsAsRead([n.comment_id]); // この通知だけを既読にする
                                window.location.href = item.href; // ページ遷移を実行
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

            // navigator.sendBeaconを使用して、ページ遷移をブロックせずにデータを送信する
            if (navigator.sendBeacon) {
                navigator.sendBeacon('mark_notifications_read.php', formData);
            } else {
                // sendBeaconが使えない古いブラウザのためのフォールバック
                try {
                    await fetch('mark_notifications_read.php', {
                        method: 'POST',
                        body: formData,
                        keepalive: true // ページがアンロードされてもリクエストを継続させる
                    });
                } catch (error) { console.error('通知の既読化に失敗しました:', error); }
            }
        }
    });
    </script>
</body>
</html>