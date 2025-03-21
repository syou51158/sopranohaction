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

// 実行状態とメッセージを管理する変数
$result = [
    'success' => false,
    'message' => ''
];

// SQLファイルからクエリを読み込む
$sql_file = file_get_contents('../fusen_tables.sql');

if ($sql_file === false) {
    $result['message'] = 'SQLファイルの読み込みに失敗しました。';
} else {
    try {
        // 複数のSQLステートメントを実行するため、セミコロンで分割
        $queries = explode(';', $sql_file);
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $pdo->exec($query);
            }
        }
        
        $result['success'] = true;
        $result['message'] = '付箋機能のテーブルが正常に作成されました。';
    } catch (PDOException $e) {
        $result['message'] = '付箋テーブルの作成中にエラーが発生しました。';
        if ($debug_mode) {
            $result['message'] .= '<br>エラー詳細: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>付箋機能テーブルインストール - <?= $site_name ?></title>
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
                        <h2>付箋機能テーブルインストール</h2>
                        
                        <div class="admin-result-box <?= $result['success'] ? 'admin-success' : 'admin-error' ?>">
                            <p><i class="fas <?= $result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i> <?= $result['message'] ?></p>
                        </div>
                        
                        <div class="admin-actions">
                            <a href="dashboard.php" class="admin-button">
                                <i class="fas fa-arrow-left"></i> ダッシュボードに戻る
                            </a>
                            <a href="fusen_settings.php" class="admin-button">
                                <i class="fas fa-sticky-note"></i> 付箋設定ページへ
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