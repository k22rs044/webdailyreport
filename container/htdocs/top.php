<?php
session_start();

// ログインしていない場合はログインページにリダイレクト（開発中はコメントアウト）
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }

$current_date = date("n月j日");

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
        a { text-decoration: none; color: inherit; }
        .container {
            width: 1280px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 50px;
            padding: 0 40px;
            background-color: #5C9EDC;
            color: #FFFFFF;
            font-size: 24px;
        }
        .header-nav { display: flex; gap: 50px; }
        .header-right { display: flex; align-items: center; gap: 50px; }

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
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .calendar-month { font-weight: 900; font-size: 18px; }
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
            display: grid;
            place-items: center;
            background: conic-gradient(#8CBAE6 75%, #dcdcdc 0);
        }
        .progress-circle-inner {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #E0E7ED;
            display: grid;
            place-items: center;
            font-size: 26px;
        }
        .progress-text {
            text-align: center;
        }
        .progress-text .label { font-size: 24px; }
        .progress-text .days { font-size: 29px; margin-top: 10px; }

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
            display: flex;
            padding: 10px;
        }
        .y-axis { display: flex; flex-direction: column-reverse; justify-content: space-between; font-size: 14px; color: #615E83; padding-right: 5px; }
        .chart-area { flex-grow: 1; position: relative; }
        .chart-lines { position: absolute; inset: 0; display: flex; flex-direction: column-reverse; justify-content: space-between; }
        .chart-lines div { border-top: 1.5px dashed #E0E7ED; }
        .chart-lines div:first-child { border-top: 1.5px solid #E0E7ED; }
        .chart-bars { position: absolute; bottom: 0; left: 0; right: 0; height: 100%; display: flex; justify-content: space-around; align-items: flex-end; padding: 0 5px; }
        .bar { width: 17px; background: #F0E5FC; border-radius: 7px 7px 0 0; }
        .bar.active { background: #962DFF; }
        .x-axis { display: flex; justify-content: space-around; font-size: 12px; color: #615E83; padding-top: 5px; }

    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-left">
                <a href="logout.php">ログアウト</a>
            </div>
            <nav class="header-nav">
                <a href="top.php">TOP</a>
                <a href="reports_list.php">日報一覧</a>
                <a href="#">仮週報作成</a>
            </nav>
            <div class="header-right">
                <a href="mypage.php">マイページ</a>
                <!-- Icon placeholder -->
            </div>
        </header>

        <main class="main-content">
            <!-- Left Column -->
            <aside class="left-column">
                <div class="deadline-card">
                    提出期限まで残り<br>○○:○○
                </div>
                <a href="#" class="list-link-card">作業詳細テンプレート一覧</a>
                <a href="#" class="list-link-card">作業概要リスト一覧</a>
                <div class="calendar-card">
                    <div class="calendar-header">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="#AFAFAF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <div class="calendar-month">September 2025</div>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 18L15 12L9 6" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
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
            </aside>

            <!-- Center Column -->
            <section class="center-column">
                <div class="report-form-card">
                    <h2><?php echo htmlspecialchars($current_date, ENT_QUOTES, 'UTF-8'); ?></h2>
                    <form action="submit_report.php" method="post" style="width: 100%; display: flex; flex-direction: column; align-items: center; gap: 12px;">
                        <div class="form-row">
                            <input type="text" class="form-input" placeholder="作業概要を入力">
                            <button type="button" class="form-button small">リスト</button>
                        </div>
                        <div class="form-row">
                            <input type="text" class="form-input" placeholder="作業時間">
                            <button type="button" class="form-button small">開始</button>
                        </div>
                        <div class="form-row">
                            <textarea class="form-textarea" placeholder="作業詳細を入力"></textarea>
                            <button type="button" class="form-button small" style="align-self: flex-start;">テンプレート</button>
                        </div>
                        <div class="form-row">
                            <input type="text" class="form-input" placeholder="次回作業概要を入力">
                            <button type="button" class="form-button small">リスト</button>
                        </div>
                        <button type="submit" class="form-button large">登録</button>
                    </form>
                </div>
            </section>

            <!-- Right Column -->
            <aside class="right-column">
                <div class="progress-card">
                    <div class="progress-circle">
                        <div class="progress-circle-inner">75%</div>
                    </div>
                    <div class="progress-text">
                        <div class="label">提出日数</div>
                        <div class="days">○○日</div>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-card-header">
                        <span>Card Title</span>
                        <span>Card Title</span>
                    </div>
                    <div class="chart-card-header second">
                        <span>Card Title</span>
                        <span>Card Title</span>
                    </div>
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
            </aside>
        </main>
    </div>
</body>
</html>

```

<!--
[PROMPT_SUGGESTION]「登録」ボタンを押したときに、フォームの内容をデータベースに保存する`submit_report.php`を作成してください。[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]「作業時間」の「開始」ボタンを押したらタイマーが作動するJavaScript機能を追加してください。[/PROMPT_SUGGESTION]
