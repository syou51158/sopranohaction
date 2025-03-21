<?php
// セッションを開始
session_start();

// 設定ファイルを読み込み
require_once '../config.php';

// ログインエラーメッセージを初期化
$error = '';

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = isset($_POST['login_id']) ? $_POST['login_id'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    try {
        // ユーザー名またはメールアドレスでユーザーを検索
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE (username = ? OR email = ?) AND is_active = 1");
        $stmt->execute([$login_id, $login_id]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // ログイン成功
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_id'] = $user['id'];
            
            // 最終ログイン日時を更新
            $update_stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
            $update_stmt->execute([$user['id']]);
            
            // ダッシュボードにリダイレクト
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'ユーザー名/メールアドレスまたはパスワードが正しくありません。';
        }
    } catch (PDOException $e) {
        $error = 'ログイン処理中にエラーが発生しました。';
        if ($debug_mode) {
            $error .= ' エラー: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者ログイン - <?= $site_name ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-body">
    <div class="admin-container">
        <div class="admin-login-card">
            <div class="admin-header">
                <h1><i class="fas fa-lock"></i> 管理者ログイン</h1>
                <p>結婚式招待状管理システム</p>
            </div>
            
            <?php if ($error): ?>
            <div class="admin-error">
                <?= $error ?>
            </div>
            <?php endif; ?>
            
            <form class="admin-login-form" method="post" action="">
                <div class="admin-form-group">
                    <label for="login_id"><i class="fas fa-user"></i> ユーザー名またはメールアドレス</label>
                    <input type="text" id="login_id" name="login_id" required>
                </div>
                <div class="admin-form-group">
                    <label for="password"><i class="fas fa-key"></i> パスワード</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="admin-button">ログイン</button>
                <div class="admin-form-links">
                    <a href="forgot_password.php">パスワードをお忘れですか？</a>
                    <a href="register.php">新規アカウント登録</a>
                </div>
            </form>
            
            <div class="admin-footer">
                <p>&copy; 2023 結婚式管理システム</p>
                <p><a href="../index.php"><i class="fas fa-arrow-left"></i> 招待状に戻る</a></p>
            </div>
        </div>
    </div>
</body>
</html> 