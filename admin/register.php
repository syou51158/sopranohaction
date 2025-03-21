<?php
// セッションを開始
session_start();

// 設定ファイルを読み込み
require_once '../config.php';

// 管理者として既にログインしているかチェック
$admin_mode = false;
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // 管理者ページから来た場合は、管理者による新規ユーザー登録モードにする
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'manage_users.php') !== false) {
        $admin_mode = true;
    } else {
        // それ以外の場合はダッシュボードにリダイレクト
        header('Location: dashboard.php');
        exit;
    }
}

// エラーと成功メッセージの初期化
$error = '';
$success = '';

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
    
    // 基本的なバリデーション
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'すべての項目を入力してください。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '有効なメールアドレスを入力してください。';
    } elseif ($password !== $confirm_password) {
        $error = 'パスワードが一致しません。';
    } elseif (strlen($password) < 8) {
        $error = 'パスワードは8文字以上である必要があります。';
    } else {
        try {
            // ユーザー名とメールアドレスの重複チェック
            $check_stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? OR email = ?");
            $check_stmt->execute([$username, $email]);
            $existing_user = $check_stmt->fetch();
            
            if ($existing_user) {
                if ($existing_user['username'] === $username) {
                    $error = 'このユーザー名は既に使用されています。';
                } else {
                    $error = 'このメールアドレスは既に登録されています。';
                }
            } else {
                // 確認トークンの生成（管理者モードでアクティブにする場合は不要）
                $token = null;
                $token_expiry = null;
                
                if (!$admin_mode || $is_active === 0) {
                    $token = bin2hex(random_bytes(32));
                    $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours')); // 24時間有効
                }
                
                // パスワードのハッシュ化
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // データベースに新規ユーザーを登録
                $insert_stmt = $pdo->prepare("
                    INSERT INTO admin_users 
                    (username, email, password, verification_token, token_expiry, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                // 管理者モードかつアクティブ指定の場合は直接有効化
                $active_status = ($admin_mode && $is_active === 1) ? 1 : 0;
                
                $insert_stmt->execute([
                    $username,
                    $email,
                    $hashed_password,
                    $token,
                    $token_expiry,
                    $active_status
                ]);
                
                // 管理者モードで直接有効化した場合はメール送信をスキップ
                if ($admin_mode && $is_active === 1) {
                    $success = 'ユーザー「' . htmlspecialchars($username) . '」が正常に登録されました。';
                    
                    if (isset($_POST['redirect_to_users']) && $_POST['redirect_to_users'] === '1') {
                        // ユーザー管理ページに戻る
                        header('Location: manage_users.php?success=' . urlencode($success));
                        exit;
                    }
                } else {
                    // 確認メールの送信
                    $subject = '【結婚式管理システム】アカウント確認';
                    $verification_link = $site_url . 'admin/verify.php?token=' . $token;
                    
                    $message = "こんにちは、{$username}様\n\n";
                    $message .= "結婚式管理システムにご登録いただき、ありがとうございます。\n\n";
                    $message .= "アカウントを有効化するには、以下のリンクをクリックしてください：\n";
                    $message .= $verification_link . "\n\n";
                    $message .= "このリンクは24時間有効です。\n\n";
                    $message .= "このメールに心当たりがない場合は、無視していただいて構いません。\n\n";
                    $message .= "よろしくお願いいたします。\n";
                    $message .= "結婚式管理システム";
                    
                    // 新しいメール送信関数を使用
                    $mail_result = send_system_mail($email, $subject, $message);
                    
                    if ($mail_result['success']) {
                        $success = 'アカウントが登録されました。確認メールをご確認いただき、アカウントを有効化してください。';
                    } else {
                        $error = 'メールの送信に失敗しました。もう一度お試しください。';
                        if ($debug_mode) {
                            $error .= ' エラー: ' . $mail_result['message'];
                        }
                        
                        // メール送信失敗した場合、ユーザーを削除
                        $delete_stmt = $pdo->prepare("DELETE FROM admin_users WHERE email = ?");
                        $delete_stmt->execute([$email]);
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'アカウント登録中にエラーが発生しました。';
            if ($debug_mode) {
                $error .= ' エラー: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $error = 'アカウント登録中にエラーが発生しました。';
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
    <title><?= $admin_mode ? '新規ユーザー追加' : '管理者登録' ?> - <?= $site_name ?></title>
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
                <h1><i class="fas fa-user-plus"></i> <?= $admin_mode ? '新規ユーザー追加' : '管理者アカウント登録' ?></h1>
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
                <?php if ($admin_mode): ?>
                <p><a href="manage_users.php">ユーザー管理に戻る</a></p>
                <?php else: ?>
                <p>ログインページに<a href="index.php">戻る</a></p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <form class="admin-login-form" method="post" action="">
                <div class="admin-form-group">
                    <label for="username"><i class="fas fa-user"></i> ユーザー名</label>
                    <input type="text" id="username" name="username" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>" required>
                    <small>ログイン時に使用できるユーザー名です。後からは変更できません。</small>
                </div>
                
                <div class="admin-form-group">
                    <label for="email"><i class="fas fa-envelope"></i> メールアドレス</label>
                    <input type="email" id="email" name="email" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                    <small>ログイン時やパスワードリセット時に使用できます。</small>
                </div>
                
                <div class="admin-form-group">
                    <label for="password"><i class="fas fa-key"></i> パスワード</label>
                    <input type="password" id="password" name="password" required minlength="8">
                    <small>8文字以上で入力してください</small>
                </div>
                
                <div class="admin-form-group">
                    <label for="confirm_password"><i class="fas fa-check"></i> パスワード（確認）</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                
                <?php if ($admin_mode): ?>
                <div class="admin-form-group">
                    <label><i class="fas fa-toggle-on"></i> アカウント状態</label>
                    <div class="admin-radio-group">
                        <label>
                            <input type="radio" name="is_active" value="1" checked> 
                            <span>すぐに有効化（メール認証不要）</span>
                        </label>
                        <label>
                            <input type="radio" name="is_active" value="0"> 
                            <span>メール認証が必要</span>
                        </label>
                    </div>
                </div>
                <input type="hidden" name="redirect_to_users" value="1">
                <?php endif; ?>
                
                <button type="submit" class="admin-button">
                    <?= $admin_mode ? 'ユーザーを追加' : 'アカウント登録' ?>
                </button>
                
                <div class="admin-form-links">
                    <?php if ($admin_mode): ?>
                    <p><a href="manage_users.php"><i class="fas fa-arrow-left"></i> ユーザー管理に戻る</a></p>
                    <?php else: ?>
                    <p>既にアカウントをお持ちの方は<a href="index.php">こちら</a></p>
                    <?php endif; ?>
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