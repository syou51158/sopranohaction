<?php
require_once "../../config.php";
session_start();

// 管理者のみアクセス可能
if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
    header("Location: ../index.php");
    exit;
}

$success_messages = [];
$error_messages = [];

// 対象ディレクトリの設定
$directories = [
    '../uploads/photos/' => 0755,
    '../uploads/travel/' => 0755,
    '../uploads/gallery/' => 0755,
    '../uploads/' => 0755
];

// ディレクトリ修正関数
function fix_directory_permissions($directory, $permission) {
    global $success_messages, $error_messages;
    
    $full_path = realpath($directory);
    if (!$full_path) {
        // ディレクトリが存在しない場合は作成
        if (!mkdir($directory, $permission, true)) {
            $error_messages[] = "ディレクトリの作成に失敗しました: $directory";
            return false;
        }
        $full_path = realpath($directory);
        $success_messages[] = "ディレクトリを作成しました: $directory";
    }
    
    if (!is_writable($full_path)) {
        if (!chmod($full_path, $permission)) {
            $error_messages[] = "ディレクトリのパーミッション変更に失敗しました: $directory";
            return false;
        }
        $success_messages[] = "ディレクトリのパーミッションを設定しました: $directory → " . decoct($permission);
    } else {
        $success_messages[] = "ディレクトリは既に書き込み可能です: $directory";
    }
    
    return true;
}

// ファイルの修正関数
function fix_file_permissions($directory, $file_permission = 0644) {
    global $success_messages, $error_messages;
    
    $count = 0;
    $failed = 0;
    
    $dir = realpath($directory);
    if (!$dir || !is_dir($dir)) {
        $error_messages[] = "ディレクトリが存在しません: $directory";
        return [0, 0];
    }
    
    $files = glob("$dir/*");
    foreach ($files as $file) {
        if (is_file($file)) {
            if (chmod($file, $file_permission)) {
                $count++;
            } else {
                $failed++;
            }
        }
    }
    
    if ($count > 0) {
        $success_messages[] = "$directory 内の $count 個のファイルのパーミッションを設定しました";
    }
    
    if ($failed > 0) {
        $error_messages[] = "$directory 内の $failed 個のファイルのパーミッション設定に失敗しました";
    }
    
    return [$count, $failed];
}

// 処理実行
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'fix_permissions') {
        foreach ($directories as $dir => $permission) {
            fix_directory_permissions($dir, $permission);
            fix_file_permissions($dir);
        }
        
        $success_messages[] = "全ディレクトリとファイルのパーミッション修正を完了しました";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パーミッション修正 - <?= $site_name ?></title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-body">
    <div class="admin-dashboard">
        <header class="admin-dashboard-header">
            <div class="admin-logo">
                <h1><i class="fas fa-tools"></i> パーミッション修正</h1>
            </div>
            <div class="admin-user">
                <span>ようこそ、<?= htmlspecialchars($_SESSION['admin_username']) ?> さん</span>
                <a href="../logout.php" class="admin-logout"><i class="fas fa-sign-out-alt"></i> ログアウト</a>
            </div>
        </header>
        
        <div class="admin-dashboard-content">
            <?php include 'sidebar.php'; ?>
            
            <div class="admin-main">
                <div class="admin-content-wrapper">
                
                <?php if (!empty($success_messages)): ?>
                    <?php foreach($success_messages as $message): ?>
                    <div class="admin-success">
                        <?= $message ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (!empty($error_messages)): ?>
                    <?php foreach($error_messages as $message): ?>
                    <div class="admin-error">
                        <?= $message ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <section class="admin-section">
                    <h2>サーバーパーミッション修正ツール</h2>
                    
                    <div class="admin-info-box">
                        <p>このツールは、アップロードディレクトリとファイルのパーミッションを修正します。</p>
                        <p>ロリポップサーバー上で写真が表示されなくなる問題を解決するために使用してください。</p>
                    </div>
                    
                    <div class="permission-status">
                        <h3>現在のディレクトリ状態</h3>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ディレクトリ</th>
                                    <th>存在</th>
                                    <th>書込権限</th>
                                    <th>パーミッション</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($directories as $dir => $permission): ?>
                                    <?php 
                                    $exists = file_exists($dir);
                                    $writable = $exists && is_writable($dir);
                                    $perms = $exists ? substr(sprintf('%o', fileperms($dir)), -4) : '-';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($dir) ?></td>
                                        <td><?= $exists ? '<span class="status-ok">はい</span>' : '<span class="status-error">いいえ</span>' ?></td>
                                        <td><?= $writable ? '<span class="status-ok">はい</span>' : '<span class="status-error">いいえ</span>' ?></td>
                                        <td><?= $perms ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <form class="admin-form" method="post" action="">
                        <input type="hidden" name="action" value="fix_permissions">
                        
                        <div class="admin-form-actions">
                            <button type="submit" class="admin-button">
                                <i class="fas fa-wrench"></i> パーミッションを修正する
                            </button>
                        </div>
                    </form>
                </section>
                
                </div>
                <?php include 'footer.php'; ?>
            </div>
        </div>
    </div>
</body>
</html> 