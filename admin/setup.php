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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>セットアップガイド - <?= $site_name ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .step-container {
            margin: 20px 0 30px;
        }
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background-color: #f8c9d4;
            color: #333;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            margin-right: 10px;
            font-weight: bold;
        }
        .step-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        .step-content {
            margin-left: 40px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
            border-left: 3px solid #f8c9d4;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.8rem;
            margin-left: 10px;
        }
        .status-done {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .status-pending {
            background-color: #fcf8e3;
            color: #8a6d3b;
        }
        .setup-button {
            display: inline-block;
            margin: 10px 0;
            padding: 8px 15px;
            background-color: #f8c9d4;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .setup-button:hover {
            background-color: #f5a6ba;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .setup-button i {
            margin-right: 5px;
        }
    </style>
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
                        <h2><i class="fas fa-cogs"></i> セットアップガイド</h2>
                        
                        <div class="admin-info-box">
                            <p><i class="fas fa-info-circle"></i> <strong>初期セットアップ手順</strong></p>
                            <p>結婚式ウェブサイトを正しく動作させるために、以下の手順を実行してください。</p>
                        </div>
                        
                        <?php
                        // ディレクトリの存在確認
                        $required_dirs = ['../uploads', '../uploads/photos', '../videos', '../images/thumbnails'];
                        $dirs_exist = true;
                        
                        foreach ($required_dirs as $dir) {
                            if (!file_exists($dir) || !is_writable($dir)) {
                                $dirs_exist = false;
                                break;
                            }
                        }
                        
                        // テーブルの存在確認
                        $tables_exist = false;
                        try {
                            $stmt = $pdo->query("SHOW TABLES LIKE 'photo_gallery'");
                            $photo_table = $stmt->fetch();
                            
                            $stmt = $pdo->query("SHOW TABLES LIKE 'video_gallery'");
                            $video_table = $stmt->fetch();
                            
                            $tables_exist = ($photo_table && $video_table);
                        } catch (PDOException $e) {
                            $tables_exist = false;
                        }
                        ?>
                        
                        <div class="step-container">
                            <div class="step-title">
                                <span class="step-number">1</span> 必要なディレクトリの作成
                                <?php if ($dirs_exist): ?>
                                <span class="status-badge status-done">完了</span>
                                <?php else: ?>
                                <span class="status-badge status-pending">未完了</span>
                                <?php endif; ?>
                            </div>
                            <div class="step-content">
                                <p>写真や動画をアップロードするために必要なディレクトリを作成します。</p>
                                <p>以下のディレクトリが必要です：</p>
                                <ul>
                                    <li>/uploads/photos/ - 写真ギャラリー用</li>
                                    <li>/videos/ - 動画ファイル用</li>
                                    <li>/images/thumbnails/ - 動画のサムネイル用</li>
                                </ul>
                                <a href="create_directories.php" class="setup-button">
                                    <i class="fas fa-folder-plus"></i> ディレクトリを作成する
                                </a>
                            </div>
                        </div>
                        
                        <div class="step-container">
                            <div class="step-title">
                                <span class="step-number">2</span> データベーステーブルの作成
                                <?php if ($tables_exist): ?>
                                <span class="status-badge status-done">完了</span>
                                <?php else: ?>
                                <span class="status-badge status-pending">未完了</span>
                                <?php endif; ?>
                            </div>
                            <div class="step-content">
                                <p>写真や動画の管理に必要なデータベーステーブルを作成します。</p>
                                <p>以下のテーブルが作成されます：</p>
                                <ul>
                                    <li>photo_gallery - 写真ギャラリーデータ</li>
                                    <li>video_gallery - 動画ギャラリーデータ</li>
                                </ul>
                                <a href="install_tables.php" class="setup-button">
                                    <i class="fas fa-database"></i> テーブルをインストールする
                                </a>
                            </div>
                        </div>
                        
                        <div class="step-container">
                            <div class="step-title">
                                <span class="step-number">3</span> サイト設定の確認
                            </div>
                            <div class="step-content">
                                <p>サイトの基本設定を確認・更新します。</p>
                                <a href="wedding_settings.php" class="setup-button">
                                    <i class="fas fa-cog"></i> 設定ページへ
                                </a>
                            </div>
                        </div>
                        
                        <div class="step-container">
                            <div class="step-title">
                                <span class="step-number">4</span> コンテンツの追加
                            </div>
                            <div class="step-content">
                                <p>サイトに写真や動画を追加します。</p>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <a href="photos.php" class="setup-button">
                                        <i class="fas fa-images"></i> 写真管理
                                    </a>
                                    <a href="videos.php" class="setup-button">
                                        <i class="fas fa-video"></i> 動画管理
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="admin-actions" style="margin-top: 30px;">
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