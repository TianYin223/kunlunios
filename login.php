<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect('index.php');
}


$siteName = getSiteName();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        $result = login($username, $password);
        
        if ($result['success']) {
            redirect('index.php');
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?= h($siteName) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
        }
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .error {
            background: #fee;
            color: #c00;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .info {
            background: #e8f4fd;
            color: #1976d2;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        @media (max-width: 768px) {
            body {
                align-items: flex-start;
                padding: 12px;
                padding-top: 6vh;
            }
            .login-container {
                max-width: none;
                border-radius: 12px;
                padding: 24px 18px;
            }
            .login-header {
                margin-bottom: 20px;
            }
            .login-header h1 {
                font-size: 22px;
            }
            .form-group {
                margin-bottom: 16px;
            }
            .form-group input {
                font-size: 16px;
            }
            .btn {
                min-height: 44px;
                font-size: 16px;
            }
        }
        @media (max-width: 480px) {
            body {
                padding: 10px;
                padding-top: 4vh;
            }
            .login-container {
                padding: 20px 14px;
            }
            .login-header h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><?= h($siteName) ?></h1>
            <p>请登录您的账号</p>
        </div>
        
        <?php if (isset($_GET['timeout'])): ?>
            <div class="info">会话已过期，请重新登录</div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required autofocus 
                       value="<?= h($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">登 录</button>
        </form>
    </div>
</body>
</html>
