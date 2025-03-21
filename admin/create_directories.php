<?php
// セッションを開始
session_start();

// 設定ファイルを読み込み
require_once '../config.php';

// 認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// 作成するディレクトリのリスト
$directories = [
    '../uploads',
    '../uploads/photos',
    '../videos',
    '../images/thumbnails'
];

$results = [];

// ディレクトリを作成
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0755, true)) {
            $results[$dir] = '作成成功';
        } else {
            $results[$dir] = '作成失敗';
        }
    } else {
        $results[$dir] = '既に存在しています';
    }
}

// パーミッションのチェック
foreach ($directories as $dir) {
    if (file_exists($dir)) {
        if (is_writable($dir)) {
            $results[$dir] .= ' (書き込み権限あり)';
        } else {
            $results[$dir] .= ' (書き込み権限なし - chmod 755 が必要です)';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ディレクトリ作成 - <?= $site_name ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-body">
    <div class="admin-dashboard">
        <header class="admin-dashboard-header">
            <div class="admin-logo">
                <h1><i class="fas fa-heart"></i> 結婚式管理システム</h1>
            </div>
            <div class="admin-user">
                <span>ようこそ、<?= htmlspecialchars($_SESSION['admin_username']) ?> さん</span>
                <a href="logout.php" class="admin-logout"><i class="fas fa-sign-out-alt"></i> ログアウト</a>
            </div>
        </header>
        
        <div class="admin-dashboard-content">
            <?php include 'inc/sidebar.php'; ?>
            
            <div class="admin-main">
                <div class="admin-content-wrapper">
                    <section class="admin-section">
                        <h2><i class="fas fa-folder-plus"></i> ディレクトリ作成結果</h2>
                        
                        <div class="admin-info-box">
                            <p><i class="fas fa-info-circle"></i> <strong>ディレクトリ作成処理の結果</strong></p>
                            
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ディレクトリパス</th>
                                        <th>結果</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $dir => $result): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($dir) ?></td>
                                        <td><?= htmlspecialchars($result) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <div class="admin-note" style="margin-top: 15px;">
                                <p><strong>注意:</strong> XAMPPでは、ディレクトリに適切な書き込み権限が必要です。もし「書き込み権限なし」と表示されている場合は、サーバーにログインして以下のコマンドを実行してください：</p>
                                <pre>chmod -R 755 ディレクトリパス</pre>
                                <p>または、FTPクライアントを使用して権限を設定することもできます。</p>
                            </div>
                        </div>
                        
                        <div class="admin-actions" style="margin-top: 20px;">
                            <a href="dashboard.php" class="admin-button">
                                <i class="fas fa-arrow-left"></i> ダッシュボードに戻る
                            </a>
                        </div>
                    </section>
                </div>
                
                <?php include 'inc/footer.php'; ?>
            </div>
        </div>
    </div>
</body>
</html> 