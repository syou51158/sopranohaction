<?php
// セッションを開始
session_start();

// 設定ファイルを読み込み
require_once '../config.php';

// 初期化
$error = '';
$success = '';

// トークンのチェック
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        // トークンをデータベースから検索
        $stmt = $pdo->prepare("
            SELECT * FROM admin_users 
            WHERE verification_token = ? 
            AND token_expiry > NOW() 
            AND is_active = 0
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // アカウントを有効化
            $update_stmt = $pdo->prepare("
                UPDATE admin_users 
                SET is_active = 1, 
                    verification_token = NULL, 
                    token_expiry = NULL 
                WHERE id = ?
            ");
            $update_stmt->execute([$user['id']]);
            
            $success = 'アカウントが有効化されました。ログインしてご利用ください。';
        } else {
            // 無効なトークンまたは期限切れ
            $error = '無効なトークンまたは期限切れです。再度登録を行ってください。';
        }
    } catch (PDOException $e) {
        $error = 'アカウント認証中にエラーが発生しました。';
        if ($debug_mode) {
            $error .= ' エラー: ' . $e->getMessage();
        }
    }
} else {
    $error = '認証トークンが不足しています。';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>アカウント認証 - <?= $site_name ?></title>
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
                <h1><i class="fas fa-check-circle"></i> アカウント認証</h1>
                <p>結婚式招待状管理システム</p>
            </div>
            
            <?php if ($error): ?>
            <div class="admin-error">
                <?= $error ?>
                <p><a href="register.php">新規登録に戻る</a></p>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="admin-success">
                <?= $success ?>
                <p><a href="index.php">ログインページに進む</a></p>
            </div>
            <?php endif; ?>
            
            <div class="admin-footer">
                <p>&copy; 2023 結婚式管理システム</p>
                <p><a href="../index.php"><i class="fas fa-arrow-left"></i> 招待状に戻る</a></p>
            </div>
        </div>
    </div>
</body>
</html> 