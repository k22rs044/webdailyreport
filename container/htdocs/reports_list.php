<?php

session_start();
require_once 'db_config.php';

// ログインしていない場合はログインページにリダイレクト
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

</body>
</html>
