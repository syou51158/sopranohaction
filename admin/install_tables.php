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

// SQLファイルの読み込み
$sql_file = file_get_contents('db_tables.sql');

// 複数のSQL文に分割する
$queries = explode(';', $sql_file);

$success_count = 0;
$error_messages = [];

// SQLクエリを実行
foreach ($queries as $query) {
    $query = trim($query);
    if (empty($query)) {
        continue;
    }
    
    try {
        $pdo->exec($query);
        $success_count++;
    } catch (PDOException $e) {
        $error_messages[] = $query . " - エラー: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>テーブルインストール - <?= $site_name ?></title>
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
                        <h2><i class="fas fa-database"></i> データベーステーブルのインストール</h2>
                        
                        <div class="admin-info-box">
                            <p><i class="fas fa-info-circle"></i> <strong>インストール処理の結果</strong></p>
                            <p>実行クエリ数: <?= $success_count ?></p>
                            
                            <?php if (empty($error_messages)): ?>
                                <div class="admin-success">
                                    <p>すべてのテーブルが正常にインストールされました。</p>
                                </div>
                            <?php else: ?>
                                <div class="admin-error">
                                    <p>一部のテーブルのインストールに失敗しました。</p>
                                    <ul>
                                        <?php foreach ($error_messages as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
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