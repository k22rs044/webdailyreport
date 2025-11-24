<?php
session_start();
require_once 'db_config.php';

// ログインしていない場合はログインページにリダイレクト（開発中はコメントアウト）

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];

$current_date = date("n月j日");

// --- 当日の日報が既に存在するかチェック ---
$todays_report = null;
$is_report_submitted = false;
try {
    $today_date_db = date('Y-m-d');
    $sql_today = "SELECT task, detail, next_task, work_time FROM Report WHERE user_id = ? AND report_date = ?";
    $stmt_today = $mysqli->prepare($sql_today);
    $stmt_today->bind_param('ss', $user_id, $today_date_db);
    $stmt_today->execute();
    $result_today = $stmt_today->get_result();
    if ($report = $result_today->fetch_assoc()) {
        $is_report_submitted = true;
        $todays_report = $report;
        // 作業時間を HH:MM:SS 形式に変換
        $work_minutes = (int)$todays_report['work_time'];
        $hours = floor($work_minutes / 60);
        $minutes = $work_minutes % 60;
        $todays_report['display_time'] = sprintf('%02d:%02d:00', $hours, $minutes);
    }
    $stmt_today->close();
} catch (Exception $e) {
    error_log("Error checking today's report for top.php: " . $e->getMessage());
}
// 前日の次回作業概要を取得
$yesterday_next_task = '';
try {
    $yesterday_date = date('Y-m-d', strtotime('-1 day'));
    $sql_yesterday = "SELECT next_task FROM Report WHERE user_id = ? AND report_date = ?";
    $stmt_yesterday = $mysqli->prepare($sql_yesterday);
    $stmt_yesterday->bind_param('ss', $user_id, $yesterday_date);
    $stmt_yesterday->execute();
    $result_yesterday = $stmt_yesterday->get_result();
    if ($report_yesterday = $result_yesterday->fetch_assoc()) {
        $yesterday_next_task = $report_yesterday['next_task'];
    }
    $stmt_yesterday->close();
} catch (Exception $e) {
    error_log("Error fetching yesterday's next task for top.php: " . $e->getMessage());
}
// 当日日報がなければ、前日の次回作業概要をセット
if (!$is_report_submitted) {
    $todays_report['task'] = $yesterday_next_task;
}

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
    error_log("Error fetching templates for top.php: " . $e->getMessage());
}

$next_tasks = [];
try {
    // ユーザーIDに基づいて次回作業概要を取得 (Task_Contentテーブルから)
    $sql_next_tasks = "SELECT task_id, task_content FROM Task_Content WHERE user_id = ? ORDER BY task_at DESC";
    $stmt_next_tasks = $mysqli->prepare($sql_next_tasks);
    $stmt_next_tasks->bind_param('s', $user_id);
    $stmt_next_tasks->execute();
    $result_next_tasks = $stmt_next_tasks->get_result();
    while ($row_next_task = $result_next_tasks->fetch_assoc()) {
        $next_tasks[] = $row_next_task;
    }
    $stmt_next_tasks->close();
} catch (Exception $e) {
    error_log("Error fetching next_tasks for top.php: " . $e->getMessage());
}

// 日報提出日数を取得
$submission_count = 0;
try {
    $sql_count = "SELECT COUNT(report_id) as count FROM Report WHERE user_id = ?";
    $stmt_count = $mysqli->prepare($sql_count);
    $stmt_count->bind_param('s', $user_id);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result()->fetch_assoc();
    $submission_count = $result_count['count'];
    $stmt_count->close();
} catch (Exception $e) {
    error_log("Error fetching submission count for top.php: " . $e->getMessage());
}

// 提出率を計算
$submission_rate = 0;
if ($submission_count > 0) {
    try {
        // 初めて日報を提出した日を取得
        $sql_first_date = "SELECT MIN(report_date) as first_date FROM Report WHERE user_id = ?";
        $stmt_first_date = $mysqli->prepare($sql_first_date);
        $stmt_first_date->bind_param('s', $user_id);
        $stmt_first_date->execute();
        $result_first_date = $stmt_first_date->get_result()->fetch_assoc();
        $first_report_date = $result_first_date['first_date'];
        $stmt_first_date->close();

        if ($first_report_date) {
            // 初回提出日から今日までの総日数を計算
            $first_date = new DateTime($first_report_date);
            $today = new DateTime();
            $total_days = $today->diff($first_date)->days + 1; // 当日も含むため+1

            if ($total_days > 0) {
                $submission_rate = round(($submission_count / $total_days) * 100);
            }
        }
    } catch (Exception $e) {
        error_log("Error calculating submission rate for top.php: " . $e->getMessage());
    }
}
// カウントダウンタイマー用のターゲット時刻
$target = new DateTime('tomorrow 4:00', new DateTimeZone('Asia/Tokyo')); // 日本時間の午前4時に設定
$target_timestamp = $target->getTimestamp();

// --- 総作業時間を取得 ---
$total_work_time_minutes = 0;
try {
    $sql_total_work_time = "SELECT SUM(work_time) as total_minutes FROM Report WHERE user_id = ?";
    $stmt_total_work_time = $mysqli->prepare($sql_total_work_time);
    $stmt_total_work_time->bind_param('s', $user_id);
    $stmt_total_work_time->execute();
    $result_total_work_time = $stmt_total_work_time->get_result()->fetch_assoc();
    $total_work_time_minutes = $result_total_work_time['total_minutes'] ?? 0;
    $stmt_total_work_time->close();
} catch (Exception $e) {
    error_log("Error fetching total work time for top.php: " . $e->getMessage());
}
$total_work_time_hours = round($total_work_time_minutes / 60, 1); // 時間に変換し、小数点以下1桁に丸める

// --- 週間作業時間を取得 ---
// 週の開始日（火曜日）を計算 (weekly_report.php と同じロジック)
$today_for_week = new DateTime();
$day_of_week_for_week = (int)$today_for_week->format('w'); // 0:日曜, 1:月曜, 2:火曜...
// 火曜日(2)を週の始まりとする
$days_to_subtract_for_week = ($day_of_week_for_week < 2) ? ($day_of_week_for_week + 7 - 2) : ($day_of_week_for_week - 2);
$start_of_week = (new DateTime())->sub(new DateInterval("P{$days_to_subtract_for_week}D"))->format('Y-m-d');
$end_of_week = (new DateTime($start_of_week))->add(new DateInterval('P6D'))->format('Y-m-d');

$weekly_work_time_minutes = 0;
try {
    $sql_weekly_work_time = "SELECT SUM(work_time) as weekly_minutes FROM Report WHERE user_id = ? AND report_date BETWEEN ? AND ?";
    $stmt_weekly_work_time = $mysqli->prepare($sql_weekly_work_time);
    $stmt_weekly_work_time->bind_param('sss', $user_id, $start_of_week, $end_of_week);
    $stmt_weekly_work_time->execute();
    $result_weekly_work_time = $stmt_weekly_work_time->get_result()->fetch_assoc();
    $weekly_work_time_minutes = $result_weekly_work_time['weekly_minutes'] ?? 0;
    $stmt_weekly_work_time->close();
} catch (Exception $e) {
    error_log("Error fetching weekly work time for top.php: " . $e->getMessage());
}
$weekly_work_time_hours = round($weekly_work_time_minutes / 60, 1); // 時間に変換し、小数点以下1桁に丸める

// --- 週間作業時間（日別）をグラフ用に取得 ---
$chart_labels = [];
$chart_values_minutes = [];
$max_work_minutes_in_week = 0; // グラフの高さ計算用
$today_day_of_week = (int)(new DateTime())->format('w');

$current_day_for_chart = new DateTime($start_of_week);
$weekdays_jp_short = ['日', '月', '火', '水', '木', '金', '土'];

for ($i = 0; $i < 7; $i++) {
    $date_key = $current_day_for_chart->format('Y-m-d');
    $day_of_week_index = (int)$current_day_for_chart->format('w');

    // X軸ラベル (火, 水, 木...)
    $chart_labels[] = $weekdays_jp_short[$day_of_week_index];

    // その日の作業時間を取得
    $daily_minutes = 0;
    try {
        $sql_daily = "SELECT SUM(work_time) as minutes FROM Report WHERE user_id = ? AND report_date = ?";
        $stmt_daily = $mysqli->prepare($sql_daily);
        $stmt_daily->bind_param('ss', $user_id, $date_key);
        $stmt_daily->execute();
        $result_daily = $stmt_daily->get_result()->fetch_assoc();
        $daily_minutes = (int)($result_daily['minutes'] ?? 0);
        $stmt_daily->close();
    } catch (Exception $e) {
        error_log("Error fetching daily work time for chart: " . $e->getMessage());
    }

    $chart_values_minutes[] = $daily_minutes;

    if ($daily_minutes > $max_work_minutes_in_week) {
        $max_work_minutes_in_week = $daily_minutes;
    }

    $current_day_for_chart->add(new DateInterval('P1D'));
}

// Y軸の最大値を決める (最低でも4時間=240分、最大作業時間がそれ以上ならそれに合わせる)
$y_axis_max_minutes = max(240, $max_work_minutes_in_week);

// --- カレンダー生成ロジック ---
$today_for_calendar = new DateTime();
$year = (int)$today_for_calendar->format('Y');
$month = (int)$today_for_calendar->format('m');
$today_day = (int)$today_for_calendar->format('d');

$calendar_month_str = $today_for_calendar->format('F Y'); // "September 2025"

$first_day_of_month = new DateTime("$year-$month-01");
$first_day_weekday = (int)$first_day_of_month->format('w'); // 0 (Sun) - 6 (Sat)
$days_in_month = (int)$first_day_of_month->format('t');

$calendar_days = [];
// 前月の日付
$last_day_of_prev_month = (clone $first_day_of_month)->modify('-1 day');
$prev_month_days_to_show = $first_day_weekday;
for ($i = 0; $i < $prev_month_days_to_show; $i++) {
    $day = $last_day_of_prev_month->format('d') - ($prev_month_days_to_show - 1 - $i);
    $calendar_days[] = ['day' => $day, 'class' => 'day-inactive'];
}
// 今月の日付
for ($day = 1; $day <= $days_in_month; $day++) {
    $class = ($day == $today_day) ? 'day-active' : '';
    $calendar_days[] = ['day' => $day, 'class' => $class];
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TOP - 日報管理</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
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
            position: relative;/**/ 
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
            justify-content: space-between;
            padding: 20px 40px;
            gap: 20px;
        }

        /* Left Column */
        .left-column {
            width: 362px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .deadline-card {
            background: #E2F7FF;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            font-size: 24px;
            line-height: 1.3;
        }
        .list-link-card {
            background: #8CBAE6;
            border-radius: 10px;
            padding: 11px;
            text-align: center;
            font-size: 16px;
        }
        .calendar-card {
            background: #E0E7ED;
            border-radius: 12px;
            padding: 22px;
        }
        .calendar-header {
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 10px; 
        }

        .calendar-month { 
            font-weight: 900; 
            font-size: 18px; 
        }

        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; gap: 1px; }
        .calendar-grid div { padding: 8px 0; font-size: 11px; }
        .calendar-grid .day-name { font-weight: 600; }
        .calendar-grid .day-number { border: 0.7px solid #D5D4DF; }
        .calendar-grid .day-inactive { background: #F2F3F7; color: #A8A8A8; }
        .calendar-grid .day-active { background: #45539D; color: #FFFFFF; font-weight: 600; }

        /* Center Column (Report Form) */
        .center-column {
            width: 483px;
        }
        .report-form-card {
            background: #E0E7ED;
            border-radius: 10px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        .report-form-card h2 {
            font-size: 32px;
            font-weight: 400;
            margin: 10px 0;
        }
        .form-row {
            display: flex;
            gap: 10px;
            width: 100%;
            justify-content: center;
            align-items: center;
            line-height: 0.7; /* フォントサイズのn倍の行間 */
        }
        .form-input, .form-textarea {
            background: #FFFFFF;
            border: none;
            border-radius: 7px;
            padding: 0 15px;
            font-size: 20px;
            color: #000000;
        }
        .form-input::placeholder, .form-textarea::placeholder {
            color: #8E8B8B;
        }
        .form-input {
            width: 342px;
            height: 40px;
        }
        .form-textarea {
            width: 342px;
            height: 262px;
            padding: 15px;
            resize: vertical;
        }
        .form-button {
            background: #8CBAE6;
            border-radius: 7px;
            border: none;
            color: #000000;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .form-button.small {
            width: 64px;
            height: 40px;
        }
        .form-button.large {
            width: 109px;
            height: 42px;
            font-size: 20px;
            margin-top: 10px;
        }
        .form-button:disabled {
            background-color: #B0B0B0;
            color: #666666;
            cursor: not-allowed;
        }

        /* Right Column */
        .right-column {
            width: 332px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .progress-card {
            background: #E0E7ED;
            border-radius: 10px;
            height: 172px;
            display: flex;
            align-items: center;
            justify-content: space-evenly;
            padding: 10px;
        }
        .progress-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-shrink: 0; /* Flexアイテムが縮小しないようにする */
            flex-grow: 0;   /* Flexアイテムが拡大しないようにする */
        }
        .progress-inner-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #E0E7ED;
            display: grid;
            place-items: center;
            flex-shrink: 0; /* Flexアイテムが縮小しないようにする */
            flex-grow: 0;   /* Flexアイテムが拡大しないようにする */
            font-size: 26px;
        }
        .progress-text {
            text-align: center;
        }
        .progress-text .label { font-size: clamp(18px, 2vw, 24px); }
        .progress-text .days { font-size: clamp(22px, 2.5vw, 29px); margin-top: 10px; }

        .chart-card {
            background: #E0E7ED;
            border-radius: 10px;
            padding: 15px;
        }
        .chart-card-header {
            display: flex;
            justify-content: space-between;
            font-size: 22px;
            font-weight: 700;
            color: #1E1B39;
            padding: 5px;
            border-bottom: 1px solid #d5d4df;
        }
        .chart-card-header.second {
            border-bottom: none;
            padding-bottom: 15px;
        }
        .chart-body {
            background: #FFFFFF;
            border-radius: 10px;
            height: 281px;
            display: flex; /* y-axis と chart-area を横に並べる */
        }
        .y-axis {
            display: flex;
            flex-direction: column-reverse;
            justify-content: space-between;
            font-size: 14px;
            color: #615E83;
            padding: 10px 5px 10px 10px; /* 上下左右のパディング */
            text-align: right;
        }
        .chart-area {
            flex-grow: 1;
            position: relative;
            padding: 10px 10px 0 10px; /* 上左右のパディング、下はx-axisがあるので0 */
        }
        .chart-lines {
            position: absolute;
            inset: 10px 10px 0 10px; /* chart-areaのpaddingに合わせる */
            display: flex;
            flex-direction: column-reverse;
            justify-content: space-between;
        }
        .chart-lines div { 
            border-top: 1.5px dashed #E0E7ED; 
        }

        .chart-lines div:first-child { 
            border-top: 1.5px solid #E0E7ED; 
        }

        .chart-bars {
            height: 100%;
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            padding-left: 10px; /* バー全体を右にpxずらす */
        }

        .bar {
            width: 15px;
            background: #F0E5FC;
            border-radius: 7px 7px 0 0;
            /*padding: 0 1px; /* 左右に1pxのpaddingを追加 */
        }

        .bar.active {
            background: #962DFF;
        }
        
        .x-axis {
            display: flex;
            justify-content: space-around;
            font-size: 12px;
            color: #615E83;
            padding: 5px 10px 0 55px; /* 左側のY軸分を考慮してパディングを設定 */
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
        .popup-title {
            font-size: 16px;
            line-height: 19px;
            text-align: center;
            color: #000000;
            margin-bottom: 20px;
        }
        .popup-list {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 10px;
            overflow-y: auto;
            padding: 0 10px;
        }
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
        .popup-list-item:hover {
            background-color: #d1d9e0;
        }
        .popup-close-button {
            margin-top: auto; /* Pushes button to the bottom */
            padding: 8px 25px;
            background: #8CBAE6;
            border: none;
            border-radius: 7px;
            font-size: 16px;
            cursor: pointer;
        }

        /* Template/Summary Popup */
        .template-summary-popup {
            width: 384px;
            height: 474px;
        }

        /* Timer Popup Specific Styles */
        .timer-popup-window {
            width: 402px;
            height: 203px;
        }
        #timer-display {
            font-size: 32px;
            line-height: 39px;
            margin: 20px 0;
        }
        .timer-buttons {
            display: flex;
            gap: 50px;
            margin-top: 20px;
        }
        .timer-button {
            width: 100px;
            height: 35px;
            border-radius: 10px;
            border: none;
            font-size: 20px;
            color: #FFFFFF;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        #timer-pause-btn { background: #8E8B8B; }
        #timer-end-btn { background: #5C9EDC; }

        /* Registration Complete Popup Styles */
        .registration-popup-window { width: 358px; height: 107px; border: 5px solid #5CDC69; justify-content: center; }
        .registration-popup-message { font-size: 24px; text-align: center; color: #000000; }

                /* Notification Popup */
        /* Based on "通知p" */
        .notification-popup-window {
            width: 460px; 
            height: 500px; 
            
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
            left: calc(50% - 400px/2);
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
            width: 400px; 
            height: 50px;
            background: #E0E7ED;
            border-radius: 10px;
            
            /* Text styles from "コメントがきた日報の日付を表示" */
            font-family: 'Inter';
            font-style: normal;
            font-weight: 400;
            font-size: 13px; /* reports_list.php に合わせる */
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
        
        /* Adjust span inside list item for bold/color */
        .notification-popup-window .popup-list-item span {
            font-weight: bold;
            color: #5C9EDC;
            margin: 0 5px;
        }

        /* Close button positioning */
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
        <main class="main-content">
            <!-- Left Column -->
            <aside class="left-column">
                <div class="deadline-card">
                    提出期限まで残り<br><span id="countdown-timer">--:--:--</span>
                </div>
                <a href="template.php" class="list-link-card">作業詳細テンプレート一覧</a>
                <a href="next_tasks.php" class="list-link-card">作業概要リスト一覧</a>
                <div class="calendar-card">
                    <div class="calendar-header">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="#AFAFAF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <div class="calendar-month"><?php echo htmlspecialchars($calendar_month_str, ENT_QUOTES, 'UTF-8'); ?></div>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 18L15 12L9 6" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div class="calendar-grid">
                        <div class="day-name">Su</div><div class="day-name">Mo</div><div class="day-name">Tu</div><div class="day-name">We</div><div class="day-name">Th</div><div class="day-name">Fr</div><div class="day-name">Sa</div>
                        <?php
                            $day_count = count($calendar_days);
                            for ($i = 0; $i < $day_count; $i++) {
                                echo '<div class="day-number ' . $calendar_days[$i]['class'] . '">' . $calendar_days[$i]['day'] . '</div>';
                            }
                        ?></div>
                </div>
            </aside>

            <!-- Center Column -->
            <section class="center-column">
                <div class="report-form-card">
                    <!-- メッセージ表示エリア -->
                    <div id="message-box" style="width: 100%; text-align: center; min-height: 20px; font-weight: bold;"></div>

                    <h2><?php echo htmlspecialchars($current_date, ENT_QUOTES, 'UTF-8'); ?></h2>

                    <?php if ($is_report_submitted): ?>
                        <p style="color: #d9534f; font-weight: bold;">本日の日報は登録済みです。</p>
                    <?php endif; ?>

                    <form id="report-form" action="submit_report.php" method="post" style="width: 100%; display: flex; flex-direction: column; align-items: center; gap: 12px;">
                        <div class="form-row">
                            <input type="text" id="work-summary" name="task" class="form-input" placeholder="作業概要を入力" value="<?php echo htmlspecialchars($todays_report['task'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php if ($is_report_submitted) echo 'readonly'; ?>>
                            <button type="button" id="show-summary-popup" class="form-button small" <?php if ($is_report_submitted) echo 'disabled'; ?>>リスト</button>
                        </div>
                        <div class="form-row">
                            <input type="text" id="work-time-input" name="display_time" class="form-input" placeholder="○○:○○" value="<?php echo htmlspecialchars($todays_report['display_time'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php if ($is_report_submitted) echo 'readonly'; ?>>
                            <button type="button" id="start-timer-btn" class="form-button small" <?php if ($is_report_submitted) echo 'disabled'; ?>>開始</button>
                        </div>
                        <div class="form-row">
                            <textarea id="work-details" name="detail" class="form-textarea" placeholder="作業詳細を入力" <?php if ($is_report_submitted) echo 'readonly'; ?>><?php echo htmlspecialchars($todays_report['detail'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <button type="button" id="show-template-popup" class="form-button small" style="align-self: flex-start;" <?php if ($is_report_submitted) echo 'disabled'; ?>>テンプレート</button>
                        </div>
                        <div class="form-row">
                            <input type="text" id="next-work-summary" name="next_task" class="form-input" placeholder="次回作業概要を入力" value="<?php echo htmlspecialchars($todays_report['next_task'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php if ($is_report_submitted) echo 'readonly'; ?>>
                            <button type="button" id="show-next-summary-popup" class="form-button small" <?php if ($is_report_submitted) echo 'disabled'; ?>>リスト</button>
                        </div>

                        <!-- 隠しフィールド -->
                        <input type="hidden" id="work-start-time" name="work_start" value="00:00:00">
                        <input type="hidden" id="work-end-time" name="work_end" value="00:00:00">
                        <input type="hidden" id="work-time-seconds" name="work_time_seconds" value="0">

                        <!-- 登録ボタンを追加 -->
                        <button type="submit" class="form-button large" <?php if ($is_report_submitted) echo 'disabled'; ?>>登録</button>

                </div>
            </section>

            <!-- Right Column -->
            <aside class="right-column">
                <div class="progress-card">
                    <div class="progress-circle" style="background: conic-gradient(#8CBAE6 0% <?php echo $submission_rate; ?>%, #dcdcdc <?php echo $submission_rate; ?>% 100%);">
                        <div class="progress-inner-circle"><?php echo $submission_rate; ?>％</div>
                    </div>
                    <div class="progress-text">
                        <div class="label">提出日数</div>
                        <div class="days"><?php echo htmlspecialchars($submission_count, ENT_QUOTES, 'UTF-8'); ?>日</div>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">
                        <span>総作業時間</span>
                        <span><?php echo htmlspecialchars($total_work_time_hours, ENT_QUOTES, 'UTF-8'); ?>時間</span>
                    </div>
                    <div class="chart-card-header second">
                        <span>週間作業時間</span>
                        <span><?php echo htmlspecialchars($weekly_work_time_hours, ENT_QUOTES, 'UTF-8'); ?>時間</span>
                    </div>
                    <div class="chart-body">
                        <?php
                            // Y軸のラベルを動的に生成 (0h, 2h, 4h, 6h...)
                            $y_axis_hours = ceil($y_axis_max_minutes / 60);
                            $y_step = ceil($y_axis_hours / 4) * 2; // 2の倍数で切り上げ
                            if ($y_step == 0) $y_step = 2; // 最小でも2h刻み
                        ?>
                        <div class="y-axis">
                            <span>0h</span>
                            <span><?php echo $y_step / 2; ?>h</span>
                            <span><?php echo $y_step; ?>h</span>
                            <span><?php echo $y_step * 1.5; ?>h</span>
                        </div>
                        <div class="chart-area">
                            <div class="chart-lines">
                                <div></div><div></div><div></div><div></div>
                            </div>
                            <div class="chart-bars">
                                <?php foreach ($chart_values_minutes as $index => $minutes): ?>
                                    <?php
                                        $height_percentage = ($y_axis_max_minutes > 0) ? ($minutes / $y_axis_max_minutes) * 100 : 0;
                                        $is_today_bar = ($weekdays_jp_short[$today_day_of_week] === $chart_labels[$index]);
                                        $active_class = $is_today_bar ? 'active' : '';
                                    ?>
                                    <div class="bar <?php echo $active_class; ?>" style="height: <?php echo $height_percentage; ?>%"></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="x-axis">
                        <?php foreach ($chart_labels as $label): ?>
                            <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>
        </main>

        <!-- 登録完了ポップアップ -->
        <div id="registration-popup-overlay" class="popup-overlay">
            <div class="popup-window registration-popup-window">
                <p class="registration-popup-message">登録完了しました</p>
            </div>
        </div>

        <!-- Template Popup -->
        <div id="template-popup-overlay" class="popup-overlay">
            <div class="popup-window template-summary-popup">
                <h3 class="popup-title">作業詳細テンプレートリスト</h3>
                <div class="popup-list">
                    <?php foreach ($templates as $template): ?>
                        <div class="popup-list-item" data-content="<?php echo htmlspecialchars($template['content'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($template['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="popup-close-button">閉じる</button>
            </div>
        </div>

        <!-- Summary Popup -->
        <div id="summary-popup-overlay" class="popup-overlay">
            <div class="popup-window template-summary-popup">
                <h3 class="popup-title">作業概要リスト</h3>
                <div class="popup-list">
                    <?php foreach ($next_tasks as $task): ?>
                        <div class="popup-list-item" data-content="<?php echo htmlspecialchars($task['task_content'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($task['task_content'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="popup-close-button">閉じる</button>
            </div>
        </div>

        <!-- Timer Popup -->
        <div id="timer-popup-overlay" class="popup-overlay">
            <div class="popup-window timer-popup-window">
                <h3 class="popup-title">作業時間記録中</h3>
                <div id="timer-display">00:00:00</div>
                <div class="timer-buttons">
                    <button id="timer-pause-btn" class="timer-button">一時停止</button>
                    <button id="timer-end-btn" class="timer-button">終了</button>
                </div>
            </div>
        </div>

        <!-- Notification Popup -->
        <div id="notification-popup-overlay" class="popup-overlay">
            <div class="popup-window notification-popup-window">
                <h3 class="popup-title">新しい通知</h3>
                <div id="notification-list" class="popup-list">
                    <!-- 通知がここに動的に挿入 -->
                    <div class="popup-list-item">通知はありません</div>
                </div>
                <button class="popup-close-button">閉じる</button>
            </div>
        </div>

    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- ユーティリティ関数 ---
            // 秒数を HH:MM:SS 形式にフォーマット
            const formatTime = s => new Date(s * 1000).toISOString().substr(11, 8);
            
            // 現在時刻を HH:MM:SS 形式で取得 (DBのTIME型に合わせる)
            const getCurrentTimeFormatted = () => {
                const now = new Date();
                const h = String(now.getHours()).padStart(2, '0');
                const m = String(now.getMinutes()).padStart(2, '0');
                const s = String(now.getSeconds()).padStart(2, '0');
                return `${h}:${m}:${s}`;
            };
            

            // メッセージ表示関数
            function displayMessage(message, type) {
                const messageBox = document.getElementById('message-box');
                if (messageBox) {
                    messageBox.textContent = message;
                    messageBox.style.color = type === 'error' ? 'red' : 'green';
                    setTimeout(() => { 
                        messageBox.textContent = ''; 
                    }, 3000);
                }
            }


            // --- 要素の取得 (必要な要素を追加) ---
            const workDetailsTextarea = document.getElementById('work-details');
            const workSummaryInput = document.getElementById('work-summary');
            const nextWorkSummaryInput = document.getElementById('next-work-summary');
            const workTimeInput = document.getElementById('work-time-input'); // メイン画面の表示用
            const reportForm = document.getElementById('report-form');
            const submitButton = reportForm.querySelector('button[type="submit"]');

            // サーバーへ送信するための隠しフィールド
            const startHidden = document.getElementById('work-start-time'); // HTMLに追加
            const endHidden = document.getElementById('work-end-time');     // HTMLに追加
            const secondsHidden = document.getElementById('work-time-seconds'); // HTMLに追加
            
            // ポップアップ要素
            const templatePopup = document.getElementById('template-popup-overlay');
            const summaryPopup = document.getElementById('summary-popup-overlay');
            const startTimerBtn = document.getElementById('start-timer-btn');
            const timerPopup = document.getElementById('timer-popup-overlay');
            
            // タイマー要素
            const timerDisplay = document.getElementById('timer-display');
            const pauseBtn = document.getElementById('timer-pause-btn');
            const endBtn = document.getElementById('timer-end-btn');

            // --- 登録ボタンの有効/無効化 ---
            const checkFormValidity = () => {
                const task = workSummaryInput.value.trim();
                const detail = workDetailsTextarea.value.trim();
                const nextTask = nextWorkSummaryInput.value.trim();

                // 3つの必須項目がすべて入力されているかチェック
                if (task !== '' && detail !== '' && nextTask !== '') {
                    submitButton.disabled = false;
                } else {
                    submitButton.disabled = true;
                }
            };

            // 各入力フィールドの入力イベントを監視
            workSummaryInput.addEventListener('input', checkFormValidity);
            workDetailsTextarea.addEventListener('input', checkFormValidity);
            nextWorkSummaryInput.addEventListener('input', checkFormValidity);
            // ページ読み込み時にもチェックを実行
            checkFormValidity();


            // --- タイマー機能の状態管理 ---
            let timerInterval = null, totalSeconds = 0, isPaused = false, startTime = null;
            let activeSummaryInput = null; // リストポップアップのターゲット

            const startTimer = () => {
                isPaused = false;
                pauseBtn.textContent = '一時停止';                
                
                // 必須修正: 初回開始時にwork_startを記録
                if (startHidden.value === '00:00:00') {
                    startHidden.value = getCurrentTimeFormatted();
                    displayMessage('作業時間の計測を開始しました。', 'info');
                } else {
                    displayMessage('作業時間の計測を再開しました。', 'info');
                }
                startTime = Date.now();
                
                timerInterval = setInterval(() => { 
                    totalSeconds++; 
                    timerDisplay.textContent = formatTime(totalSeconds); 
                }, 1000);
            };
            
            const stopTimer = () => clearInterval(timerInterval);
            
            const resetTimer = () => {
                totalSeconds = 0;
            };

            // --- Template Popup ---
            const showTemplateBtn = document.getElementById('show-template-popup');
            showTemplateBtn.addEventListener('click', () => { templatePopup.style.display = 'flex'; });

            templatePopup.querySelectorAll('.popup-list-item').forEach(item => {
                item.addEventListener('click', () => {
                    workDetailsTextarea.value = item.getAttribute('data-content');
                    templatePopup.style.display = 'none';
                });
            });

            // --- Summary Popup ---
            const openSummaryPopup = (targetInput) => {
                activeSummaryInput = targetInput;
                summaryPopup.style.display = 'flex';
            };
            const showSummaryBtn = document.getElementById('show-summary-popup');
            const showNextSummaryBtn = document.getElementById('show-next-summary-popup');
            showSummaryBtn.addEventListener('click', () => openSummaryPopup(workSummaryInput));
            showNextSummaryBtn.addEventListener('click', () => openSummaryPopup(nextWorkSummaryInput));

            summaryPopup.querySelectorAll('.popup-list-item').forEach(item => {
                item.addEventListener('click', () => {
                    if (activeSummaryInput) {
                        activeSummaryInput.value = item.getAttribute('data-content');
                    }
                    summaryPopup.style.display = 'none';
                });
            });

            // --- Timer Popup ---
            startTimerBtn.addEventListener('click', () => {
                // タイマーが動いていない場合のみリセット
                if (!timerInterval) resetTimer();
                timerDisplay.textContent = formatTime(totalSeconds);
                startTimer();
                timerPopup.style.display = 'flex';
            });
            
            pauseBtn.addEventListener('click', () => {
                isPaused = !isPaused;
                pauseBtn.textContent = isPaused ? '再開' : '一時停止';
                isPaused ? stopTimer() : startTimer();
                
                if (isPaused) {
                    displayMessage(`計測を一時停止しました。現在: ${formatTime(totalSeconds)}`, 'info');
                } else {
                    displayMessage('計測を再開しました。', 'info');
                }
            });
            
            endBtn.addEventListener('click', () => {
                stopTimer();
                timerPopup.style.display = 'none';
                
                // 必須修正: work_end と work_time_seconds を記録
                endHidden.value = getCurrentTimeFormatted();
                secondsHidden.value = totalSeconds;
                
                // メイン画面の表示用入力欄にも反映
                workTimeInput.value = formatTime(totalSeconds);
                
                // タイマーの状態をリセット
                timerInterval = null;
                isPaused = false;
                
                displayMessage(`作業時間 ${workTimeInput.value} を記録しました。`, 'info');
            });

            // --- 手動での時間入力処理 ---
            workTimeInput.addEventListener('blur', () => {
                const input = workTimeInput.value.trim();
                if (input === '') {
                    // 入力が空の場合はタイマーもリセット
                    secondsHidden.value = 0;
                    startHidden.value = '00:00:00';
                    endHidden.value = '00:00:00';
                    resetTimer();
                    return;
                }

                let totalMinutes = 0;
                // "HH:MM" または "H:M" 形式の処理
                if (input.includes(':')) {
                    const parts = input.split(':');
                    const hours = parseInt(parts[0], 10) || 0;
                    const minutes = parseInt(parts[1], 10) || 0;
                    totalMinutes = (hours * 60) + minutes;
                } 
                // 小数点を含む時間形式 (例: 1.5時間)
                else if (input.includes('.')) {
                    const hours = parseFloat(input) || 0;
                    totalMinutes = Math.round(hours * 60);
                }
                // 数字のみ（分として解釈）
                else {
                    totalMinutes = parseInt(input, 10) || 0;
                }

                const newTotalSeconds = totalMinutes * 60;
                secondsHidden.value = newTotalSeconds; // 隠しフィールドに秒数を設定
                workTimeInput.value = formatTime(newTotalSeconds); // 表示を HH:MM:SS 形式に統一
                totalSeconds = newTotalSeconds; // 内部のタイマー状態も更新
                displayMessage(`作業時間 ${workTimeInput.value} を設定しました。`, 'info');
            });

            // --- 日報登録処理 ---
            const registrationPopup = document.getElementById('registration-popup-overlay');
            const submitReport = async () => {
                const formData = new FormData(reportForm);

                try {
                    const response = await fetch('submit_report.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (response.ok) {
                        // 登録完了ポップアップを表示
                        registrationPopup.style.display = 'flex';
                        submitButton.disabled = true; // 登録ボタンを無効化
                        // フォームをリセット
                        reportForm.reset(); // 表示されているフォームの値をリセット
                        // 内部の状態もリセット
                        startHidden.value = '00:00:00';
                        endHidden.value = '00:00:00';
                        secondsHidden.value = 0;
                        resetTimer(); // タイマーの秒数もリセット
                        // 2秒後にポップアップを閉じる
                        setTimeout(() => {
                            registrationPopup.style.display = 'none';
                            window.location.reload(); // 画面をリロード
                        }, 2000);
                    } else {
                        displayMessage('登録に失敗しました。', 'error');
                    }
                } catch (error) {
                    displayMessage('通信エラーが発生しました。', 'error');
                }
            };

            // --- イベントリスナー ---

            // 「登録」ボタンクリック時
            reportForm.addEventListener('submit', async e => {
                e.preventDefault(); // デフォルトのフォーム送信をキャンセル

                // 必須項目チェック
                const task = workSummaryInput.value.trim();
                const detail = workDetailsTextarea.value.trim();
                if (task === '' || detail === '') {
                    displayMessage('必須項目が未記入です', 'error');
                    return;
                }

                // タイマーが動いている場合は強制的に停止し、値を確定させる
                if (timerInterval) {
                    stopTimer();
                    endHidden.value = getCurrentTimeFormatted();
                    secondsHidden.value = totalSeconds;
                    workTimeInput.value = formatTime(totalSeconds);
                    timerInterval = null;
                    isPaused = false;
                    timerPopup.style.display = 'none'; 
                    displayMessage(`登録前にタイマーを停止し、作業時間 ${workTimeInput.value} を確定しました。`, 'info');
                }
                
                await submitReport();
            });

            // タイマーの「終了」ボタンクリック時
            endBtn.addEventListener('click', async () => {
                // タイマーを停止し、ポップアップを閉じる
                stopTimer();
                timerPopup.style.display = 'none';

                // 終了時刻と作業時間を隠しフィールドに設定
                endHidden.value = getCurrentTimeFormatted();
                secondsHidden.value = totalSeconds;
                workTimeInput.value = formatTime(totalSeconds); // 表示にも反映
                displayMessage(`作業時間 ${workTimeInput.value} を記録しました。`, 'info');

                // タイマーの状態をリセット
                timerInterval = null;
                isPaused = false;

                // 必須項目が入力されていれば登録処理を実行
                const task = workSummaryInput.value.trim();
                const detail = workDetailsTextarea.value.trim();
                if (task !== '' && detail !== '') {
                    await submitReport(); // 登録処理を実行
                }
            });

            // --- Generic Popup Close Logic ---
            document.querySelectorAll('.popup-overlay').forEach(popup => {
                popup.addEventListener('click', e => { 
                    if (e.target === popup) popup.style.display = 'none'; 
                });
                const closeButton = popup.querySelector('.popup-close-button');
                if (closeButton) closeButton.addEventListener('click', () => popup.style.display = 'none');
            });

            // --- カウントダウンタイマー ---
            const countdownElement = document.getElementById('countdown-timer');
            if (countdownElement) {
                const targetTimestamp = <?php echo $target_timestamp; ?> * 1000; // PHPからタイムスタンプを受け取り、ミリ秒に変換

                const updateCountdown = () => {
                    const now = new Date().getTime();
                    const distance = targetTimestamp - now;

                    if (distance < 0) {
                        countdownElement.textContent = "00:00:00";
                        clearInterval(countdownInterval);
                        return;
                    }

                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    countdownElement.textContent = 
                        String(hours).padStart(2, '0') + ':' + 
                        String(minutes).padStart(2, '0') + ':' + 
                        String(seconds).padStart(2, '0');
                };
                const countdownInterval = setInterval(updateCountdown, 1000);
                updateCountdown(); // ページ読み込み時に即時実行
            }
        });
        // --- Generic Popup Close Logic ---
        document.querySelectorAll('.popup-overlay').forEach(popup => {
            popup.addEventListener('click', e => { 
                if (e.target === popup) popup.style.display = 'none'; 
            });
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

        // ベルアイコンがクリックされたときの処理
        if (notificationBell) {
            notificationBell.addEventListener('click', async () => {
                console.log("ベルアイコンがクリックされました。"); // デバッグ用
                notificationPopup.style.display = 'flex';
                
                try {
                    const response = await fetch('get_notifications.php');
                    if (!response.ok) {
                        throw new Error(`サーバーエラー: ${response.status}`);
                    }
                    const result = await response.json();
                    console.log("通知APIからのレスポンス:", result); // デバッグ用

                    if (result.success && result.notifications.length > 0) {
                        notificationList.innerHTML = ''; // リストをクリア
                        const commentIds = [];

                        result.notifications.forEach(n => {
                            const item = document.createElement('a');
                            item.href = `reports_detail.php?id=${n.report_id}`;
                            item.className = 'popup-list-item';
                            
                            const reportDate = new Date(n.report_date).toLocaleDateString('ja-JP', { month: 'long', day: 'numeric' });

                            if (n.is_admin_view) {
                                item.innerHTML = `<span>${n.report_owner_name}</span>さんの日報に<span>${n.commenter_name}</span>さんがコメントしました。`;
                            } else {
                                item.innerHTML = `${reportDate}<span>${n.commenter_name}</span>さんからコメントがありました。`;
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

        // 通知を既読としてマークする関数
        async function markNotificationsAsRead(commentIds) {
            if (commentIds.length === 0) return;

            const formData = new FormData();
            formData.append('comment_ids', JSON.stringify(commentIds));

            try {
                await fetch('mark_notifications_read.php', {
                    method: 'POST',
                    body: formData
                });
            } catch (error) {
                console.error('通知の既読化に失敗しました:', error);
            }
        }

    </script>
</body>
</html>
