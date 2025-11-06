<?php
session_start();
require_once 'db_config.php';

// ログインしていない場合はログインページにリダイレクト
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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
        .switch {
            width: 40px;
            height: 24px;
            background: #5C9EDC;
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
            border-radius: 50%;
        }
        .user-actions {
            display: flex;
            gap: 14px;
        }
        .user-actions .action-button {
            width: 154px;
            height: 35px;
            background: #5C9EDC;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 15px;
            line-height: 140%;
            color: #FFFFFF;
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
            background: #FFFFFF;
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
            background: #E0E7ED;
            border-radius: 10px;
        }

        /* --- Right Column --- */
        .progress-card {
            width: 332px;
            height: 172px;
            background: #E0E7ED;
            border-radius: 10px;
            position: relative;
        }
        .progress-circle {
            position: absolute;
            width: 150px;
            height: 150px;
            left: 22px;
            top: 11px;
            border-radius: 50%;
            background: conic-gradient(#8CBAE6 0% 75%, #E0E7ED 75% 100%);
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
            position: absolute;
            right: 20px;
            top: 89px;
            text-align: center;
        }
        .progress-text .label { font-size: 24px; }
        .progress-text .days { font-size: 28.6px; }

        .chart-card {
            width: 332px;
            height: 394px;
            background: #E0E7ED;
            border-radius: 10px;
            padding: 15px;
            box-sizing: border-box;
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            padding: 0 5px;
        }
        .chart-header-item {
            font-size: 22px;
            font-weight: 700;
            color: #1E1B39;
        }
        .chart-divider {
            border-top: 1px solid #E0E7ED;
            margin: 10px 0;
        }
        .chart-body {
            width: 309px;
            height: 281px;
            background: #FFFFFF;
            border-radius: 10px;
            margin-top: 10px;
            position: relative;
            display: flex;
        }
        .y-axis {
            display: flex;
            flex-direction: column-reverse;
            justify-content: space-between;
            padding: 10px 5px;
            font-size: 14px;
            color: #615E83;
            text-align: right;
        }
        .chart-area {
            flex-grow: 1;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 10px 0;
        }
        .chart-lines {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            display: flex;
            flex-direction: column-reverse;
            justify-content: space-between;
            padding: 10px 0;
        }
        .chart-lines div { border-top: 1.5px dashed #E0E7ED; }
        .chart-lines div:first-child { border-top: 1.5px solid #E0E7ED; }
        .chart-bars {
            position: absolute;
            bottom: 36px; /* height of x-axis */
            left: 0; right: 0;
            height: calc(100% - 36px);
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            padding: 0 10px;
        }
        .bar {
            width: 17px;
            background: #F0E5FC;
            border-radius: 7px 7px 0 0;
        }
        .bar.active { background: #962DFF; }
        .x-axis {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            display: flex;
            justify-content: space-around;
            padding: 10px;
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

    <main>
        <div class="column-left">
            <div class="user-info-card">
                <p class="name"><?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="id"><?php echo htmlspecialchars($user_student_id, ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="switch-field">
                    <span class="label">Label</span>
                    <div class="switch"><div class="knob"></div></div>
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
                        <svg width="34" height="34" viewBox="0 0 34 34" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20.925 9.425L13.075 17L20.925 24.575" stroke="#AFAFAF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div class="calendar-month">September 2025</div>
                    <div class="calendar-nav">
                        <svg width="34" height="34" viewBox="0 0 34 34" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13.075 24.575L20.925 17L13.075 9.425" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                </div>
                <div class="calendar-grid">
                    <div class="day-name">Su</div><div class="day-name">Mo</div><div class="day-name">Tu</div><div class="day-name">We</div><div class="day-name">Th</div><div class="day-name">Fr</div><div class="day-name">Sa</div>
                    <div class="day-number day-inactive">1</div><div class="day-number">2</div><div class="day-number">3</div><div class="day-number">4</div><div class="day-number">5</div><div class="day-number">6</div><div class="day-number">7</div>
                    <div class="day-number">8</div><div class="day-number">9</div><div class="day-number">10</div><div class="day-number">11</div><div class="day-number">12</div><div class="day-number">13</div><div class="day-number">14</div>
                    <div class="day-number">15</div><div class="day-number">16</div><div class="day-number">17</div><div class="day-number day-active">18</div><div class="day-number">19</div><div class="day-number">20</div><div class="day-number">21</div>
                    <div class="day-number">22</div><div class="day-number">23</div><div class="day-number">24</div><div class="day-number">25</div><div class="day-number">26</div><div class="day-number">27</div><div class="day-number">28</div>
                    <div class="day-number">29</div><div class="day-number">30</div><div class="day-number day-inactive">1</div><div class="day-number day-inactive">2</div><div class="day-number day-inactive">3</div><div class="day-number day-inactive">4</div><div class="day-number day-inactive">5</div>
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
                <div class="progress-circle">
                    <div class="progress-inner-circle">75％</div>
                </div>
                <div class="progress-text">
                    <div class="label">提出日数</div>
                    <div class="days">○○日</div>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-header-item">Card Title</div>
                    <div class="chart-header-item">Card Title</div>
                </div>
                <div class="chart-divider"></div>
                <div class="chart-header">
                    <div class="chart-header-item">Card Title</div>
                    <div class="chart-header-item">Card Title</div>
                </div>
                <div class="chart-divider"></div>
                <div class="chart-body">
                    <div class="y-axis">
                        <span>0h</span><span>2h</span><span>4h</span><span>6h</span>
                    </div>
                    <div class="chart-area">
                        <div class="chart-lines">
                            <div></div><div></div><div></div><div></div>
                        </div>
                        <div class="chart-bars">
                            <div class="bar" style="height: 57%"></div>
                            <div class="bar" style="height: 30%"></div>
                            <div class="bar" style="height: 60%"></div>
                            <div class="bar" style="height: 36%"></div>
                            <div class="bar active" style="height: 83%"></div>
                            <div class="bar" style="height: 0%"></div>
                            <div class="bar" style="height: 0%"></div>
                        </div>
                    </div>
                </div>
                <div class="x-axis">
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

            // Email Popup
            const emailPopupOverlay = document.getElementById('email-popup-overlay');
            const showEmailPopupBtn = document.getElementById('show-email-popup');
            const cancelEmailChangeBtn = document.getElementById('cancel-email-change');
            const emailChangeForm = document.getElementById('email-change-form');

            showEmailPopupBtn.addEventListener('click', (e) => { e.preventDefault(); emailPopupOverlay.style.display = 'flex'; });
            cancelEmailChangeBtn.addEventListener('click', () => { emailPopupOverlay.style.display = 'none'; });
            emailPopupOverlay.addEventListener('click', (e) => { if (e.target === emailPopupOverlay) { emailPopupOverlay.style.display = 'none'; } });
            emailChangeForm.addEventListener('submit', (e) => { e.preventDefault(); alert('メールアドレス変更処理を実装します。'); emailPopupOverlay.style.display = 'none'; });
        });
    </script>
</body>
</html>
