<?php
// セッションを開始
session_start();

// 設定ファイルを読み込み
require_once '../config.php';

// 既にログインしている場合はダッシュボードへリダイレクト
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// エラーと成功メッセージの初期化
$error = '';
$success = '';
$token = '';
$valid_token = false;

// トークンのチェック
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        // トークンをデータベースから検索
        $stmt = $pdo->prepare("
            SELECT * FROM admin_users 
            WHERE verification_token = ? 
            AND token_expiry > NOW() 
            AND is_active = 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            $valid_token = true;
        } else {
            $error = '無効なトークンまたは期限切れです。再度パスワードリセットをリクエストしてください。';
        }
    } catch (PDOException $e) {
        $error = 'トークン検証中にエラーが発生しました。';
        if ($debug_mode) {
            $error .= ' エラー: ' . $e->getMessage();
        }
    }
} else {
    $error = 'リセットトークンが不足しています。';
}

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // 基本的なバリデーション
    if (empty($password) || empty($confirm_password)) {
        $error = 'すべての項目を入力してください。';
    } elseif ($password !== $confirm_password) {
        $error = 'パスワードが一致しません。';
    } elseif (strlen($password) < 8) {
        $error = 'パスワードは8文字以上である必要があります。';
    } else {
        try {
            // パスワードのハッシュ化
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // パスワードを更新し、トークンをクリア
            $update_stmt = $pdo->prepare("
                UPDATE admin_users 
                SET password = ?, 
                    verification_token = NULL, 
                    token_expiry = NULL 
                WHERE verification_token = ?
            ");
            $update_stmt->execute([$hashed_password, $token]);
            
            $success = 'パスワードが正常にリセットされました。新しいパスワードでログインしてください。';
        } catch (PDOException $e) {
            $error = 'パスワードリセット中にエラーが発生しました。';
            if ($debug_mode) {
                $error .= ' エラー: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワードリセット - <?= $site_name ?></title>
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
                <h1><i class="fas fa-key"></i> パスワードリセット</h1>
                <p>結婚式招待状管理システム</p>
            </div>
            
            <?php if ($error): ?>
            <div class="admin-error">
                <?= $error ?>
                <?php if (!$valid_token): ?>
                <p><a href="forgot_password.php">パスワードリセットに戻る</a></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="admin-success">
                <?= $success ?>
                <p><a href="index.php">ログインページに進む</a></p>
            </div>
            <?php elseif ($valid_token): ?>
            <div class="admin-info">
                <p>新しいパスワードを設定してください。</p>
            </div>
            
            <form class="admin-login-form" method="post" action="reset_password.php?token=<?= htmlspecialchars($token) ?>">
                <div class="admin-form-group">
                    <label for="password"><i class="fas fa-key"></i> 新しいパスワード</label>
                    <input type="password" id="password" name="password" required minlength="8">
                    <small>8文字以上で入力してください</small>
                </div>
                
                <div class="admin-form-group">
                    <label for="confirm_password"><i class="fas fa-check"></i> 新しいパスワード（確認）</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                
                <button type="submit" class="admin-button">パスワードを変更</button>
            </form>
            <?php endif; ?>
            
            <div class="admin-footer">
                <p>&copy; 2023 結婚式管理システム</p>
                <p><a href="../index.php"><i class="fas fa-arrow-left"></i> 招待状に戻る</a></p>
            </div>
        </div>
    </div>
</body>
</html> 