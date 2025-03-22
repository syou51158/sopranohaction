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

// URLパラメータからゲストIDを取得
$guest_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 確認パラメータを取得
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

// 招待グループ情報を取得
if ($guest_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM guests WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $guest_id]);
        $guest = $stmt->fetch();
        
        if (!$guest) {
            // 招待グループが見つからない場合
            $_SESSION['admin_message'] = [
                'type' => 'error',
                'text' => '指定された招待グループが見つかりませんでした。'
            ];
            header('Location: dashboard.php');
            exit;
        }
        
        // 確認後の削除処理
        if ($confirmed) {
            // 関連する回答を削除
            $stmt = $pdo->prepare("DELETE FROM responses WHERE guest_id = :guest_id");
            $stmt->execute(['guest_id' => $guest_id]);
            
            // 招待グループを削除
            $stmt = $pdo->prepare("DELETE FROM guests WHERE id = :id");
            $stmt->execute(['id' => $guest_id]);
            
            // 成功メッセージをセット
            $_SESSION['admin_message'] = [
                'type' => 'success',
                'text' => '招待グループ「' . htmlspecialchars($guest['group_name']) . '」を削除しました。'
            ];
            
            // ダッシュボードにリダイレクト
            header('Location: dashboard.php');
            exit;
        }
        
    } catch (PDOException $e) {
        $_SESSION['admin_message'] = [
            'type' => 'error',
            'text' => 'データベースエラーが発生しました。'
        ];
        if ($debug_mode) {
            $_SESSION['admin_message']['text'] .= ' エラー: ' . $e->getMessage();
        }
        header('Location: dashboard.php');
        exit;
    }
} else {
    // IDが不正な場合
    $_SESSION['admin_message'] = [
        'type' => 'error',
        'text' => '不正なIDが指定されました。'
    ];
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>招待グループ削除 - <?= $site_name ?></title>
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
                        <h2>招待グループ削除の確認</h2>
                        
                        <div class="admin-confirm-delete">
                            <div class="admin-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h3>警告: この操作は取り消せません</h3>
                                <p>招待グループ「<strong><?= htmlspecialchars($guest['group_name']) ?></strong>」を削除しようとしています。</p>
                                <p>このグループの全ての情報と、関連する回答データも削除されます。</p>
                                <p>本当に削除しますか？</p>
                            </div>
                            
                            <div class="admin-confirm-actions">
                                <a href="delete_guest.php?id=<?= $guest_id ?>&confirm=yes" class="admin-button" style="background-color: var(--admin-danger);">
                                    <i class="fas fa-trash"></i> はい、削除します
                                </a>
                                <a href="dashboard.php" class="admin-button" style="background-color: var(--admin-gray); margin-top: 10px;">
                                    <i class="fas fa-times"></i> いいえ、キャンセルします
                                </a>
                            </div>
                        </div>
                    </section>
                </div>
                
                <?php include 'inc/footer.php'; ?>
            </div>
        </div>
    </div>
</body>
</html> 