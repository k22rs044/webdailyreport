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
            justify-content: center; /* 中央揃えに変更 */
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
            width: 360px; /* Ensure consistent width for alignment */
        }

        .form-input {
            width: 360px;
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

        /*
        ID入力欄専用のスタイル（右パディングを調整） 
        .id-input {
            padding-right: 20px;  アイコンがないため、右パディングを左と合わせる 
        }
        */

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

        /* New Eye Icon Styles */
        .password-toggle-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
        }
        
        .eye-icon-container {
            width: 30px;
            height: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* 目の輪郭 (上まぶた) */
        .eye-outline {
            position: absolute;
            top: 0px; /* アイコン全体の位置を調整 */
            width: 30px;
            height: 15px;
            border: 2px solid black;
            border-bottom: none;
            border-top-left-radius: 30px;
            border-top-right-radius: 30px;
            box-sizing: border-box; /* paddingとborderをwidth/heightに含める */
            z-index: 2;
        }


        /* 瞳の基本スタイル */
        .eye-pupil {
            position: absolute;
            width: 10px; /* 線画のデフォルトサイズ */
            height: 10px;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%); /* 中央に配置 */
            z-index: 10;
            transition: all 0.2s ease;
        }

        /* 1. パスワード表示 ON (塗りつぶし瞳) */
        .is-visible .eye-pupil {
            background-color: black; /* 瞳を塗りつぶし */
            border: none;
            width: 12px; /* 塗りつぶしアイコンのサイズに調整 */
            height: 12px;
        }

        /* 2. パスワード非表示 OFF (線画瞳 + 斜線) */
        .is-hidden .eye-pupil {
            background-color: transparent; /* 瞳を透明に */
            border: 2px solid black; /* 瞳を線画の円にする */
        }

        /* 3. 斜線 (パスワード非表示時のシンボル) */
        .is-hidden::after {
            content: '';
            position: absolute;
            width: 28px;
            height: 2px;
            background: black;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg); 
            border-radius: 2px;
            z-index: 15;
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
                    <input type="text" id="user_id" name="user_id" class="form-input id-input">
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" class="form-input">
                    <div class="password-toggle-icon eye-icon-container is-hidden" id="togglePassword">
                        <div class="eye-outline"></div>
                        <div class="eye-pupil"></div>
                    </div>
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

        // 初期状態を設定
        togglePassword.classList.add('is-hidden');

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);

            // アイコンの表示を切り替える
            const isPasswordVisible = type === 'text';
            togglePassword.classList.toggle('is-visible', isPasswordVisible);
            togglePassword.classList.toggle('is-hidden', !isPasswordVisible);
        });
    </script>

</body>
</html>
