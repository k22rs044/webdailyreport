<?php
session_start();
require_once 'db_config.php';

// ログインしていない場合はログインページにリダイレクト
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = "取得失敗";
$user_student_id = "取得失敗";
$user_email = "取得失敗";

try {
    $stmt = $mysqli->prepare("SELECT name, email FROM User WHERE user_id = ?");
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $user_name = $result['name'] ?? '名前なし';
    $user_email = $result['email'] ?? 'メール未登録';
    // Add fetching for notification setting
    $user_receive_notifications = 0; // Default
    try {
        $stmt_notif = $mysqli->prepare("SELECT receive_notifications FROM User WHERE user_id = ?");
        $stmt_notif->bind_param('s', $user_id);
        $stmt_notif->execute();
        $result_notif = $stmt_notif->get_result()->fetch_assoc();
        $user_receive_notifications = $result_notif['receive_notifications'] ?? 0;
        $stmt_notif->close();
    } catch (Exception $e) { error_log("Mypage notification fetch error: " . $e->getMessage()); }
    $user_email = $result['email'] ?? 'メール未登録';
    $user_student_id = $user_id; // user_idはセッションから取得
} catch (Exception $e) {
    error_log("Mypage user fetch error: " . $e->getMessage());
}

// 作業詳細テンプレートリストを取得
$templates = [];
try {
    $sql = "SELECT template_id, title FROM Detail_Template WHERE user_id = ? ORDER BY created_at DESC LIMIT 4";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $templates[] = $row;
    }
} catch (Exception $e) {
    error_log("Mypage template fetch error: " . $e->getMessage());
}

// 作業概要リストを取得
$next_tasks = [];
try {
    $sql = "SELECT task_id, task_content FROM Task_Content WHERE user_id = ? ORDER BY task_at DESC LIMIT 4";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $next_tasks[] = $row;
    }
} catch (Exception $e) {
    error_log("Mypage next_tasks fetch error: " . $e->getMessage());
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
    error_log("Error fetching submission count for mypage.php: " . $e->getMessage());
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
        error_log("Error calculating submission rate for mypage.php: " . $e->getMessage());
    }
}

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
    error_log("Error fetching total work time for mypage.php: " . $e->getMessage());
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
    error_log("Error fetching weekly work time for mypage.php: " . $e->getMessage());
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
    <title>マイページ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            background: #FFFFFF;
            font-family: 'Inter', sans-serif;
            color: #000000;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .container {
            width: 1280px;
            margin: 0 auto;
            position: relative;
        }

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

        main {
            display: flex;
            justify-content: center;
            gap: 20px; /* Spacing between columns */
            padding: 12px 0; /* top: 62px - header:50px */
            max-width: 1280px;
            margin: 0 auto;
        }

        .column-left, .column-center, .column-right {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* --- Left Column --- */
        .user-info-card {
            width: 362px;
            height: 234px;
            background: #E0E7ED;
            border-radius: 10px;
            padding: 9px 13px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-around;
        }
        .user-info-card p {
            margin: 0;
            font-size: 24px;
            line-height: 29px;
        }
        .user-info-card .email {
            line-height: 140%;
        }
        .switch-field {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .switch-field .label {
            font-size: 24px;
            line-height: 140%;
            color: #1E1E1E;
        }
        /* Notification Switch Styles */
        .switch {
            width: 40px;
            height: 24px;
            background: #b2b8bdff; /* Default OFF color */
            border-radius: 9999px;
            position: relative;
            cursor: pointer;
            transition: background-color 0.3s ease; /* Smooth transition for background */
        }
        .switch .knob {
            position: absolute;
            width: 18px;
            height: 18px;
            left: 3px; /* Default OFF position */
            top: 3px;
            background: #F5F5F5;
            border-radius: 50%;
            transition: left 0.3s ease; /* Smooth transition for knob */
        }
        .switch.is-on {
            background: #34B717; /* ON color */
        }
        .switch.is-on .knob {
            left: calc(100% - 18px - 3px); /* ON position */
        }

        /* Original knob style, removed as it's now handled by .switch .knob and .switch.is-on .knob */
        /*
        .switch .knob {
            position: absolute;
            width: 18px;
            height: 18px;
            border-radius: 9999px;
            position: relative;
            cursor: pointer;
        }
        .switch .knob {
            position: absolute;
            width: 18px;
            height: 18px;
            left: 19px;
            top: 3px;
            background: #F5F5F5;
            border-radius: 9999px;
        }
        */
        .user-actions {
            display: flex;
            justify-content: space-between; /* ボタンを均等に配置 */
        }
        .user-actions .action-button {
            width: 154px;
            height: 35px; /* 修正 */
            background: #5C9EDC;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 15px;
            line-height: 140%;
            color: #FFFFFF;
        }
        .name-container {
            margin-bottom: 10px; /* 下の要素との間隔 */
        }
        
        
        .calendar-card {
            width: 362px;
            height: 318px;
            background: #E0E7ED;
            border-radius: 11.731px;
            padding: 10px 22px;
            box-sizing: border-box;
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .calendar-month {
            font-weight: 900;
            font-size: 17.6px;
        }
        .calendar-nav { display: flex; }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
        }
        .calendar-grid div {
            height: 40px; /* Adjusted for smaller height */
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 10.26px;
            box-sizing: border-box;
        }
        .day-name { font-weight: 600; height: 27px !important; }
        .day-number { border: 0.73px solid #D5D4DF; }
        .day-active { background: #45539D; color: #FFFFFF; font-weight: 600; }
        .day-inactive { background: #F2F3F7; color: #A8A8A8; }

        /* --- Center Column --- */
        .list-card {
            width: 401px;
            height: 260px;
            background: #E0E7ED;
            border-radius: 10px;
            padding: 13px 23px;
            box-sizing: border-box;
            border: 1px solid #E0E7ED; /* Added for visibility */
        }
        .list-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .list-card-header h3 {
            font-size: 18px;
            font-weight: 400;
            margin: 0;
        }
        .list-card-header .list-button {
            width: 90px;
            height: 30px;
            background: #5C9EDC;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
            color: #FFFFFF;
        }
        .list-items {
            display: flex;
            flex-direction: column;
            gap: 9px;
        }
        .list-item {
            width: 350px;
            height: 40px;
            background: #ffffffff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            padding-left: 20px;
            box-sizing: border-box;
        }

        /* --- Right Column --- */
        .progress-card {
            width: 332px;
            height: 172px;
            background: #E0E7ED;
            border-radius: 10px;
            position: relative;
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
        }
        .progress-inner-circle {
            width: 100px;
            height: 100px;
            background: #E0E7ED;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 26px;
        }
        .progress-text {
            text-align: center;
            margin-top: 10px;
        }
        .progress-text .label { font-size: 24px; }
        .progress-text .days { font-size: 28.6px; }

        .chart-card {
            width: 332px;
            height: auto; /* 高さを自動に変更 */
            background: #E0E7ED;
            border-radius: 10px;
            padding: 15px; /* 修正 */
            box-sizing: border-box;
        }
        .chart-card-header { /* top.phpに合わせる */
            display: flex;
            justify-content: space-between;
            font-size: 22px;
            font-weight: 700;
            color: #1E1B39;
            padding: 5px;
            border-bottom: 1px solid #d5d4df;
        }
        .chart-card-header.second { /* top.phpに合わせる */
            border-bottom: none;
            padding-bottom: 15px;
        }
        .chart-body { /* 修正 */
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
            flex-grow: 1; /* 修正 */
            position: relative;
            padding: 10px 10px 0 10px; /* 上左右のパディング、下はx-axisがあるので0 */
        }
        .chart-lines {
            position: absolute; /* 修正 */
            inset: 10px 10px 0 10px; /* chart-areaのpaddingに合わせる */
            display: flex;
            flex-direction: column-reverse;
            justify-content: space-between;
        }
        .chart-lines div { border-top: 1.5px dashed #E0E7ED; }
        .chart-lines div:first-child { border-top: 1.5px solid #E0E7ED; }
        .chart-bars {
            height: 100%; /* 修正 */
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            padding-left: 10px; /* バー全体を右にずらす */
        }
        .bar {
            width: 15px; /* top.phpに合わせる */
            background: #F0E5FC;
            border-radius: 7px 7px 0 0;
        }
        .bar.active { background: #962DFF; }
        .x-axis { /* 修正 */
            display: flex;
            justify-content: space-around;
            padding: 5px 10px 0 55px; /* 左側のY軸分を考慮してパディングを設定 */
            font-size: 12px;
            color: #615E83;
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

        /* Password Change Popup Styles */
        .password-change-popup {
            width: 538px;
            height: 353px;
            background: #E0E7ED;
            border: 5px solid #D04141;
            border-radius: 10px;
            box-sizing: border-box;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .password-change-popup .popup-title, .email-change-popup .popup-title {
            font-size: 20px;
            font-weight: 400;
            color: #000000;
            margin-bottom: 30px;
        }
        .password-change-popup .form-group, .email-change-popup .form-group {
            width: 400px;
            margin-bottom: 25px;
            position: relative; /* アイコン配置のため */
        }
        .password-change-popup .form-group label, .email-change-popup .form-group label {
            font-size: 12px;
            color: #000000;
            display: block;
            margin-bottom: 5px;
        }
        .password-change-popup .input-wrapper {
            position: relative;
        }
        .password-change-popup .form-group input, .email-change-popup .form-group input {
            width: 100%;
            height: 45px;
            background: #FFFFFF;
            border-radius: 10px;
            border: none;
            padding: 0 45px 0 15px; /* アイコンのスペースを確保 */
            box-sizing: border-box;
            font-size: 16px;
        }
        .password-toggle-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            width: 24px;
            height: 24px;
        }
        .password-change-popup .popup-buttons, .email-change-popup .popup-buttons {
            display: flex;
            justify-content: space-between;
            width: 311px;
            margin-top: 30px;
        }
        .password-change-popup .popup-button, .email-change-popup .popup-button {
            width: 100px;
            height: 30px;
            border-radius: 10px;
            border: none;
            font-size: 16px;
            color: #FFFFFF;
            cursor: pointer;
        }

        /* Email Change Popup Styles */
        .email-change-popup {
            width: 538px;
            height: 246px;
            background: #E0E7ED;
            border: 5px solid #34B717; /* Green border */
            border-radius: 10px;
            box-sizing: border-box;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
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

    <main>
        <div class="column-left">
            <div class="user-info-card">
                <div class="name-container">
                    <p class="name"><?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <p class="id"><?php echo htmlspecialchars($user_student_id, ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="switch-field"> <!-- 締め切り通知スイッチ -->
                    <span class="label">締め切り通知</span>
                    <div class="switch" id="notification-switch" data-initial-status="<?php echo $user_receive_notifications ? '1' : '0'; ?>">
                        <div class="knob"></div>
                        <input type="hidden" id="notification-status" name="receive_notifications" value="<?php echo $user_receive_notifications ? '1' : '0'; ?>">
                    </div>
                </div>
                <p class="email"><?php echo htmlspecialchars($user_email, ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="user-actions">
                    <a href="#" id="show-password-popup" class="action-button">パスワード変更</a>
                    <a href="#" id="show-email-popup" class="action-button">メールアドレス変更</a>
                </div>
            </div>
            <div class="calendar-card">
                <div class="calendar-header">
                    <div class="calendar-nav">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="#AFAFAF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div class="calendar-month"><?php echo htmlspecialchars($calendar_month_str, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="calendar-nav">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 18L15 12L9 6" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                </div>
                <div class="calendar-grid">
                    <div class="day-name">Su</div><div class="day-name">Mo</div><div class="day-name">Tu</div><div class="day-name">We</div><div class="day-name">Th</div><div class="day-name">Fr</div><div class="day-name">Sa</div>
                    <?php
                        $day_count = count($calendar_days);
                        for ($i = 0; $i < $day_count; $i++) {
                            echo '<div class="day-number ' . $calendar_days[$i]['class'] . '">' . $calendar_days[$i]['day'] . '</div>';
                        }    
                    ?>
                </div>
            </div>
        </div>
        <div class="column-center">
            <div class="list-card">
                <div class="list-card-header">
                    <h3>作業詳細テンプレートリスト</h3>
                    <a href="template.php" class="list-button">一覧</a>
                </div>
                <div class="list-items">
                    <?php if (empty($templates)): ?>
                        <div class="list-item">登録されていません</div>
                    <?php else: ?>
                        <?php foreach ($templates as $template): ?>
                            <a href="template.php?id=<?php echo htmlspecialchars($template['template_id'], ENT_QUOTES, 'UTF-8'); ?>" class="list-item">
                                <?php echo htmlspecialchars($template['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="list-card">
                <div class="list-card-header">
                    <h3>作業概要リスト</h3>
                    <a href="next_tasks.php" class="list-button">一覧</a>
                </div>                
                <div class="list-items">                    
                    <?php if (empty($next_tasks)): ?>
                        <div class="list-item">登録されていません</div>
                    <?php else: ?>
                        <?php foreach ($next_tasks as $task): ?>
                            <a href="next_tasks.php" class="list-item">
                                <?php echo htmlspecialchars($task['task_content'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="column-right">
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
        </div>
    </main>

    <!-- Password Change Popup -->
    <div id="password-popup-overlay" class="popup-overlay">
        <div class="password-change-popup">
            <h3 class="popup-title">パスワード変更</h3>
            <form id="password-change-form" style="width: 100%; display: flex; flex-direction: column; align-items: center;">
                <div class="form-group">
                    <label for="current-password">現在のパスワードを入力</label>
                    <div class="input-wrapper">
                        <input type="password" id="current-password" name="current_password">
                        <svg class="password-toggle-icon" id="toggle-current-password" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 14C13.1046 14 14 13.1046 14 12C14 10.8954 13.1046 10 12 10C10.8954 10 10 10.8954 10 12C10 13.1046 10.8954 14 12 14Z" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12C18.6 16 15.6 18 12 18C8.4 18 5.4 16 3 12C5.4 8 8.4 6 12 6C15.6 6 18.6 8 21 12Z" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                </div>
                <div class="form-group">
                    <label for="new-password">新しいパスワードを入力</label>
                    <div class="input-wrapper">
                        <input type="password" id="new-password" name="new_password">
                        <svg class="password-toggle-icon" id="toggle-new-password" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 14C13.1046 14 14 13.1046 14 12C14 10.8954 13.1046 10 12 10C10.8954 10 10 10.8954 10 12C10 13.1046 10.8954 14 12 14Z" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12C18.6 16 15.6 18 12 18C8.4 18 5.4 16 3 12C5.4 8 8.4 6 12 6C15.6 6 18.6 8 21 12Z" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                </div>
                <div class="popup-buttons">
                    <button type="button" id="cancel-password-change" class="popup-button" style="background: #5C9EDC;">キャンセル</button>
                    <button type="submit" class="popup-button" style="background: #34B717;">変更</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Email Change Popup -->
    <div id="email-popup-overlay" class="popup-overlay">
        <div class="email-change-popup">
            <h3 class="popup-title">メールアドレス変更</h3>
            <form id="email-change-form" style="width: 100%; display: flex; flex-direction: column; align-items: center;">
                <div class="form-group">
                    <label for="new-email">登録するメールアドレスを入力</label>
                    <input type="email" id="new-email" name="new_email">
                </div>
                <div class="popup-buttons">
                    <button type="button" id="cancel-email-change" class="popup-button" style="background: #5C9EDC;">キャンセル</button>
                    <button type="submit" class="popup-button" style="background: #34B717;">変更</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Name Change Popup -->
    <div id="name-popup-overlay" class="popup-overlay">
        <div class="name-change-popup">
            <h3 class="popup-title">氏名変更</h3>
            <form id="name-change-form" style="width: 100%; display: flex; flex-direction: column; align-items: center;">
                <div class="form-group">
                    <label for="new-name">登録する氏名を入力</label>
                    <input type="text" id="new-name" name="new_name" value="<?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="popup-buttons">
                    <button type="button" id="cancel-name-change" class="popup-button" style="background: #5C9EDC;">キャンセル</button>
                    <button type="submit" class="popup-button" style="background: #34B717;">変更</button>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password Popup
            const passwordPopupOverlay = document.getElementById('password-popup-overlay');
            const showPasswordPopupBtn = document.getElementById('show-password-popup');
            const cancelPasswordChangeBtn = document.getElementById('cancel-password-change');
            const passwordChangeForm = document.getElementById('password-change-form');

            showPasswordPopupBtn.addEventListener('click', (e) => { e.preventDefault(); passwordPopupOverlay.style.display = 'flex'; });
            cancelPasswordChangeBtn.addEventListener('click', () => {
                passwordPopupOverlay.style.display = 'none';
                passwordChangeForm.reset(); // 入力内容をリセット
            });
            passwordPopupOverlay.addEventListener('click', (e) => { if (e.target === passwordPopupOverlay) { passwordPopupOverlay.style.display = 'none'; } });
            passwordChangeForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(passwordChangeForm);

                try {
                    const response = await fetch('password_change_process.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    alert(result.message); // 結果をアラートで表示
                    if (result.success) {
                        passwordPopupOverlay.style.display = 'none';
                        passwordChangeForm.reset();
                    }
                } catch (error) {
                    alert('通信中にエラーが発生しました。');
                }
            });

            // Password visibility toggle
            const togglePasswordVisibility = (toggleBtn, passwordInput) => {
                toggleBtn.addEventListener('click', () => {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                });
            };

            const currentPasswordInput = document.getElementById('current-password');
            const toggleCurrentPasswordBtn = document.getElementById('toggle-current-password');
            togglePasswordVisibility(toggleCurrentPasswordBtn, currentPasswordInput);

            const newPasswordInput = document.getElementById('new-password');
            const toggleNewPasswordBtn = document.getElementById('toggle-new-password');
            togglePasswordVisibility(toggleNewPasswordBtn, newPasswordInput);

            // Notification Switch Logic
            const notificationSwitch = document.getElementById('notification-switch');
            const notificationStatusInput = document.getElementById('notification-status');

            // Initialize switch state based on PHP value
            const initialStatus = notificationSwitch.dataset.initialStatus === '1';
            if (initialStatus) {
                notificationSwitch.classList.add('is-on');
                notificationStatusInput.value = '1';
            } else {
                notificationSwitch.classList.remove('is-on');
                notificationStatusInput.value = '0';
            }

            notificationSwitch.addEventListener('click', async () => {
                let currentStatus = notificationStatusInput.value === '1';
                currentStatus = !currentStatus; // Toggle status

                if (currentStatus) {
                    notificationSwitch.classList.add('is-on');
                    notificationStatusInput.value = '1';
                } else {
                    notificationSwitch.classList.remove('is-on');
                    notificationStatusInput.value = '0';
                }

                // Send AJAX request to update setting
                const formData = new FormData();
                formData.append('receive_notifications', notificationStatusInput.value);

                try {
                    const response = await fetch('update_notification_setting.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (!result.success) {
                        alert('通知設定の更新に失敗しました: ' + result.message);
                        // Revert UI if update failed
                        notificationSwitch.classList.toggle('is-on'); // Toggle back
                        notificationStatusInput.value = notificationStatusInput.value === '1' ? '0' : '1'; // Revert value

                    }
                } catch (error) {
                    console.error('Error updating notification setting:', error);
                    alert('通信エラーが発生しました。');
                }
            });


            // Email Popup
            const emailPopupOverlay = document.getElementById('email-popup-overlay');
            const showEmailPopupBtn = document.getElementById('show-email-popup');
            const cancelEmailChangeBtn = document.getElementById('cancel-email-change');
            const emailChangeForm = document.getElementById('email-change-form');

            showEmailPopupBtn.addEventListener('click', (e) => { e.preventDefault(); emailPopupOverlay.style.display = 'flex'; });
            cancelEmailChangeBtn.addEventListener('click', () => { emailPopupOverlay.style.display = 'none'; });
            emailPopupOverlay.addEventListener('click', (e) => { if (e.target === emailPopupOverlay) { emailPopupOverlay.style.display = 'none'; } });
            emailChangeForm.addEventListener('submit', async (e) => { 
                e.preventDefault(); 
                const formData = new FormData(emailChangeForm);
                const response = await fetch('email_change_process.php', { method: 'POST', body: formData });
                const result = await response.json();
                alert(result.message);
                if (result.success) {
                    window.location.reload();
                }
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
    
    </script>
</body>
</html>
