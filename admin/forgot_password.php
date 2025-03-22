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

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    // 基本的なバリデーション
    if (empty($email)) {
        $error = 'メールアドレスを入力してください。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '有効なメールアドレスを入力してください。';
    } else {
        try {
            // メールアドレスが登録されているか確認
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // リセットトークンの生成
                $token = bin2hex(random_bytes(32));
                $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // 1時間有効
                
                // トークンをデータベースに保存
                $update_stmt = $pdo->prepare("
                    UPDATE admin_users 
                    SET verification_token = ?, 
                        token_expiry = ? 
                    WHERE id = ?
                ");
                $update_stmt->execute([$token, $token_expiry, $user['id']]);
                
                // パスワードリセットメールの送信
                $subject = '【結婚式管理システム】パスワードリセット';
                $reset_link = $site_url . 'admin/reset_password.php?token=' . $token;
                
                $message = "こんにちは、{$user['username']}様\n\n";
                $message .= "パスワードリセットのリクエストを受け付けました。\n\n";
                $message .= "パスワードをリセットするには、以下のリンクをクリックしてください：\n";
                $message .= $reset_link . "\n\n";
                $message .= "このリンクは1時間有効です。\n\n";
                $message .= "このリクエストに心当たりがない場合は、このメールを無視してください。\n\n";
                $message .= "よろしくお願いいたします。\n";
                $message .= "結婚式管理システム";
                
                // 新しいメール送信関数を使用
                $mail_result = send_system_mail($email, $subject, $message);
                
                if ($mail_result['success']) {
                    $success = 'パスワードリセットの手順をメールで送信しました。メールをご確認ください。';
                } else {
                    $error = 'メールの送信に失敗しました。もう一度お試しください。';
                    if ($debug_mode) {
                        $error .= ' エラー: ' . $mail_result['message'];
                    }
                }
            } else {
                // セキュリティのため、ユーザーが存在しない場合でも同じメッセージを表示
                $success = 'パスワードリセットの手順をメールで送信しました。メールをご確認ください。';
            }
        } catch (PDOException $e) {
            $error = 'パスワードリセット処理中にエラーが発生しました。';
            if ($debug_mode) {
                $error .= ' エラー: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $error = 'パスワードリセット処理中にエラーが発生しました。';
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
    <title>パスワードをお忘れの方 - <?= $site_name ?></title>
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
                <h1><i class="fas fa-key"></i> パスワードをお忘れの方</h1>
                <p>結婚式招待状管理システム</p>
            </div>
            
            <?php if ($error): ?>
            <div class="admin-error">
                <?= $error ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="admin-success">
                <?= $success ?>
                <p>ログインページに<a href="index.php">戻る</a></p>
            </div>
            <?php else: ?>
            <div class="admin-info">
                <p>登録したメールアドレスを入力してください。パスワードリセットのリンクをメールで送信します。</p>
            </div>
            
            <form class="admin-login-form" method="post" action="">
                <div class="admin-form-group">
                    <label for="email"><i class="fas fa-envelope"></i> メールアドレス</label>
                    <input type="email" id="email" name="email" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                </div>
                
                <button type="submit" class="admin-button">リセットリンクを送信</button>
                
                <div class="admin-form-links">
                    <p><a href="index.php">ログインページに戻る</a></p>
                </div>
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