<?php
session_start();
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['error']); // エラーメッセージを一度表示したら削除
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン</title>
    <style>
        /* 基本スタイルとリセット */
        body, html {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: #FFFFFF;
            color: #000000;
            height: 100%;
        }

        /* ページ全体をFlexコンテナにして中央配置 */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .login-container {
            width: 1280px;
            /* 高さはコンテンツに応じて自動調整 */
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px; /* 要素間の隙間 */
        }

        .login-form {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
        }

        .form-group {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 800px; /* ラベルと入力欄の合計幅を確保 */
        }

        .form-label {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 155px;
            height: 46px;
            background: #8CBAE6;
            border-radius: 10px;
            font-size: 20px;
            font-weight: 400;
            margin-right: -1px; /* 入力欄と少し重ねる */
            z-index: 1;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .form-input {
            width: 341px;
            height: 46px;
            background: #D9D9D9;
            border: none;
            padding: 0 40px 0 20px; /* アイコンのスペースを確保 */
            box-sizing: border-box;
            font-size: 18px;
        }

        .form-input:focus {
            outline: 2px solid #5C9EDC;
        }

        .password-toggle-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            width: 24px;
            height: 24px;
        }

        .button-group {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 100px; /* ボタン間の隙間 */
            margin-top: 40px;
        }

        .login-button {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 155px;
            height: 46px;
            background: #8CBAE6;
            border-radius: 10px;
            border: none;
            font-size: 16px;
            font-weight: 400;
            cursor: pointer;
            color: #000000;
            text-decoration: none;
        }

        .login-button:hover {
            opacity: 0.9;
        }

        .admin-button {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 97px;
            height: 35px;
            background: #D9D9D9;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #000000;
            text-decoration: none;
        }

        .error-message {
            color: #D8000C; /* 赤色 */
            background-color: #FFD2D2; /* 薄い赤色 */
            border: 1px solid #D8000C;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            width: 480px; /* フォームの幅に合わせる */
            text-align: center;
            box-sizing: border-box;
        }

    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400&display=swap" rel="stylesheet">
</head>
<body>

    <div class="login-container">
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form action="login_process.php" method="post" class="login-form">
            <div class="form-group">
                <label for="user_id" class="form-label">ID</label>
                <div class="input-wrapper">
                    <input type="text" id="user_id" name="user_id" class="form-input">
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" class="form-input">
                    <svg class="password-toggle-icon" id="togglePassword" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 14C13.1046 14 14 13.1046 14 12C14 10.8954 13.1046 10 12 10C10.8954 10 10 10.8954 10 12C10 13.1046 10.8954 14 12 14Z" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M21 12C18.6 16 15.6 18 12 18C8.4 18 5.4 16 3 12C5.4 8 8.4 6 12 6C15.6 6 18.6 8 21 12Z" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" class="login-button">ログイン</button>
            </div>
        </form>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            // パスワード入力欄のタイプを切り替える
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
        });
    </script>

</body>
</html>
