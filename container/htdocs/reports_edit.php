<?php
session_start();
require_once 'db_config.php';

// ログインしていない場合はログインページにリダイレクト
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$report_id = $_GET['id'] ?? null;
$error_message = '';
$report = null;

if (!$report_id) {
    // IDが指定されていない場合は一覧にリダイレクト
    header('Location: reports_list.php');
    exit;
}

try {
    // 編集対象の日報データを取得
    $sql = "SELECT report_date, task, detail, next_task, work_time, user_id FROM Report WHERE report_id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $report_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $db_report = $result->fetch_assoc();
    $stmt->close();

    if (!$db_report) {
        $error_message = "指定された日報が見つかりません。";
    } else {
        // 編集権限のチェック
        $report_date = new DateTime($db_report['report_date'], new DateTimeZone('Asia/Tokyo'));
        $today = new DateTime('now', new DateTimeZone('Asia/Tokyo'));

        if ($db_report['user_id'] != $user_id || $report_date->format('Y-m-d') !== $today->format('Y-m-d')) {
            // 他人の日報、または過去の日報は編集不可
            $_SESSION['error_message'] = "この日報を編集する権限がありません。";
            header('Location: reports_detail.php?id=' . $report_id);
            exit;
        }
        $report = $db_report;
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    $error_message = "データベースエラーが発生しました。";
}

// POSTリクエスト処理 (フォームが送信された場合)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $report) {
    $summary = $_POST['summary'] ?? '';
    $details = $_POST['details'] ?? '';
    $next_summary = $_POST['next_summary'] ?? '';
    $work_time_total_minutes = (int)($_POST['work_time_minutes'] ?? 0);

    // バリデーション
    if (empty($summary) || empty($details) || empty($next_summary)) {
        $error_message = "すべての項目を入力してください。";
    } else {

        try {
            // データベースを更新
            // report_idに依存せず、user_idとreport_dateで確実に更新対象を特定する
            $update_sql = "UPDATE Report SET task = ?, detail = ?, next_task = ?, work_time = ? WHERE user_id = ? AND report_date = ?";
            $update_stmt = $mysqli->prepare($update_sql);
            $update_stmt->bind_param('sssiss', $summary, $details, $next_summary, $work_time_total_minutes, $user_id, $db_report['report_date']);
            
            if ($update_stmt->execute()) {
                // 更新成功後、詳細ページにリダイレクト
                header('Location: reports_detail.php?id=' . $report_id);
                exit;
            } else {
                $error_message = "日報の更新に失敗しました。";
            }
            $update_stmt->close();
        } catch (Exception $e) {
            error_log($e->getMessage());
            $error_message = "データベース更新中にエラーが発生しました。";
        }
    }
}

// 作業時間を時間と分に分解
$work_hours_val = 0;
$work_minutes_val = 0;
if ($report && $report['work_time']) {
    $total_minutes = (int)$report['work_time'];
    $work_hours_val = floor($total_minutes / 60);
    $work_minutes_val = $total_minutes % 60;
}

$display_time_val = "{$work_hours_val}時間{$work_minutes_val}分";

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>日報編集</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body, html { margin: 0; padding: 0; font-family: 'Inter', sans-serif; background-color: #FFFFFF; color: #000000; }
        .container { width: 1000px; margin: 0 auto; }
        header { background: #5C9EDC; height: 50px; display: flex; justify-content: center; align-items: center; padding: 0 36px; color: #FFFFFF; position: fixed; top: 0; left: 0; width: 100%; z-index: 100; }
        .header-container { display: flex; justify-content: space-between; align-items: center; width: 1208px; }
        .header-left a, .header-right a { font-size: 24px; line-height: 29px; color: #FFFFFF; text-decoration: none; }
        .header-right { display: flex; align-items: center; gap: 50px; }
        .header-nav { display: flex; align-items: center; gap: 50px; }
        .main-content { padding: 75px 40px 25px 40px; }
        .back-button { display: inline-flex; align-items: center; gap: 8px; background: #8CBAE6; border-radius: 8px; padding: 5px 18px 5px 12px; font-size: 18px; margin-bottom: 15px; color: #000; text-decoration: none; }
        .back-button-arrow { width: 0; height: 0; border-top: 8px solid transparent; border-bottom: 8px solid transparent; border-right: 12px solid #FFFFFF; }
        .form-container { background: #E0E7ED; border-radius: 10px; padding: 25px; max-width: 650px; margin: 0 auto; }
        .form-container h1 { font-size: 28px; font-weight: 400; text-align: center; margin-top: 0; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 18px; margin-bottom: 8px; }
        .form-group input[type="text"], .form-group textarea { width: 100%; background: #FFFFFF; border-radius: 7px; border: 1px solid #ccc; padding: 8px 12px; box-sizing: border-box; font-size: 18px; font-family: 'Inter', sans-serif; }
        .form-group textarea { height: 200px; resize: vertical; }
        .time-input-group { display: flex; align-items: center; gap: 10px; }
        .time-input-group input { width: 100px; }
        .time-input-group span { font-size: 20px; }
        .button-group { display: flex; justify-content: center; gap: 20px; margin-top: 30px; }
        .submit-button, .cancel-button { padding: 8px 30px; border: none; border-radius: 7px; font-size: 18px; cursor: pointer; text-decoration: none; }
        .submit-button { background: #5C9EDC; color: white; }
        .cancel-button { background: #B0B0B0; color: white; }
        .error-message { color: red; text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <div class="header-left"><a href="logout.php">ログアウト</a></div>
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
            </div>
        </div>
    </header>

    <div class="container">
        <main class="main-content">
            <a href="reports_detail.php?id=<?php echo htmlspecialchars($report_id, ENT_QUOTES, 'UTF-8'); ?>" class="back-button">
                <div class="back-button-arrow"></div>
                <span>戻る</span>
            </a>

            <div class="form-container">
                <h1>
                    <?php if ($report && isset($report_date)): ?>
                        <?php echo htmlspecialchars($report_date->format('n月j日'), ENT_QUOTES, 'UTF-8'); ?>&nbsp;
                    <?php endif; ?>
                    日報編集
                </h1>

                <?php if ($error_message): ?>
                    <p class="error-message"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>

                <?php if ($report): ?>
                <form action="reports_edit.php?id=<?php echo htmlspecialchars($report_id, ENT_QUOTES, 'UTF-8'); ?>" method="post">
                    <div class="form-group">
                        <label for="summary">作業概要</label>
                        <input type="text" id="summary" name="summary" value="<?php echo htmlspecialchars($report['task'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="work_hours">作業時間</label>
                        <!-- top.phpと同様の自由入力形式に変更 -->
                        <input type="text" id="work-time-input" name="display_time" value="<?php echo htmlspecialchars($display_time_val, ENT_QUOTES, 'UTF-8'); ?>" placeholder="例: 1:30" required>
                        <input type="hidden" id="work-time-minutes" name="work_time_minutes" value="<?php echo htmlspecialchars($report['work_time'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="details">作業詳細</label>
                        <textarea id="details" name="details" required><?php echo htmlspecialchars($report['detail'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="next_summary">次回作業概要</label>
                        <input type="text" id="next_summary" name="next_summary" value="<?php echo htmlspecialchars($report['next_task'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="button-group">
                        <a href="reports_detail.php?id=<?php echo htmlspecialchars($report_id, ENT_QUOTES, 'UTF-8'); ?>" class="cancel-button">キャンセル</a>
                        <button type="submit" class="submit-button">更新</button>
                    </div>
                </form>
                <?php elseif (!$error_message): ?>
                    <p style="text-align: center;">日報の読み込み中にエラーが発生しました。</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const workTimeInput = document.getElementById('work-time-input');
        const workTimeMinutesHidden = document.getElementById('work-time-minutes');
        const form = document.querySelector('form');

        function updateTotalMinutes() {
            const input = workTimeInput.value.trim();
            if (input === '') {
                workTimeMinutesHidden.value = 0;
                workTimeInput.value = '0時間0分';
                return;
            }

            let totalMinutes = 0;

            // "X時間Y分" 形式の解析
            const timeMatch = input.match(/(?:(\d+)\s*時間)?\s*(?:(\d+)\s*分)?/);
            if (timeMatch && (timeMatch[1] || timeMatch[2])) {
                const hours = parseInt(timeMatch[1], 10) || 0;
                const minutes = parseInt(timeMatch[2], 10) || 0;
                totalMinutes = (hours * 60) + minutes;
            }
            // "HH:MM" または "H:M" 形式の処理
            else if (input.includes(':')) {
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

            workTimeMinutesHidden.value = totalMinutes;
            workTimeInput.value = `${Math.floor(totalMinutes / 60)}時間${totalMinutes % 60}分`;
        }

        workTimeInput.addEventListener('blur', updateTotalMinutes);

        if (form) {
            form.addEventListener('submit', updateTotalMinutes);
        }
    });
    </script>
</body>
</html>
