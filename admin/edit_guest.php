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
if ($guest_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

// ゲスト情報を取得
$guest = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM guests WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $guest_id]);
    $guest = $stmt->fetch();
    
    if (!$guest) {
        header('Location: dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    $error = "招待グループ情報の取得に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_name = isset($_POST['group_name']) ? htmlspecialchars($_POST['group_name']) : '';
    $group_id = isset($_POST['group_id']) ? htmlspecialchars($_POST['group_id']) : '';
    $arrival_time = isset($_POST['arrival_time']) ? htmlspecialchars($_POST['arrival_time']) : '';
    $custom_message = isset($_POST['custom_message']) ? htmlspecialchars($_POST['custom_message']) : '';
    $max_companions = isset($_POST['max_companions']) ? (int)$_POST['max_companions'] : 0;
    
    if (empty($group_name) || empty($group_id) || empty($arrival_time)) {
        $error = "招待状の宛名、URL識別子、到着時間は必須です。";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE guests 
                SET group_id = :group_id, 
                    group_name = :group_name, 
                    arrival_time = :arrival_time, 
                    custom_message = :custom_message, 
                    max_companions = :max_companions
                WHERE id = :id
            ");
            
            $stmt->execute([
                'group_id' => $group_id,
                'group_name' => $group_name,
                'arrival_time' => $arrival_time,
                'custom_message' => $custom_message,
                'max_companions' => $max_companions,
                'id' => $guest_id
            ]);
            
            // 成功メッセージを設定
            $success = "招待グループ情報を更新しました。";
            
            // 更新された情報を再取得
            $stmt = $pdo->prepare("SELECT * FROM guests WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $guest_id]);
            $guest = $stmt->fetch();
            
        } catch (PDOException $e) {
            $error = "招待グループ情報の更新に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
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
    <title>招待グループ編集 - <?= $site_name ?></title>
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
                        <h2>招待グループ情報編集</h2>
                        
                        <?php if (isset($error)): ?>
                        <div class="admin-error">
                            <?= $error ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($success)): ?>
                        <div class="admin-success">
                            <?= $success ?>
                        </div>
                        <?php endif; ?>
                        
                        <form class="admin-form" method="post" action="">
                            <div class="admin-form-row">
                                <div class="admin-form-group">
                                    <label for="group_name">招待状の宛名 <span class="required">*</span></label>
                                    <input type="text" id="group_name" name="group_name" required value="<?= htmlspecialchars($guest['group_name']) ?>">
                                    <small>例：山田様、田中家、会社の皆様</small>
                                </div>
                                
                                <div class="admin-form-group">
                                    <label for="group_id">招待URL識別子 <span class="required">*</span></label>
                                    <input type="text" id="group_id" name="group_id" required value="<?= htmlspecialchars($guest['group_id']) ?>">
                                    <small>URLに使用される識別子。英数字、ハイフン、アンダースコアのみ</small>
                                </div>
                            </div>
                            
                            <div class="admin-form-row">
                                <div class="admin-form-group">
                                    <label for="arrival_time">集合時間 <span class="required">*</span></label>
                                    <input type="text" id="arrival_time" name="arrival_time" required value="<?= htmlspecialchars($guest['arrival_time']) ?>">
                                    <small>例: 12:30</small>
                                </div>
                                
                                <div class="admin-form-group">
                                    <label for="max_companions">同伴者上限</label>
                                    <input type="number" id="max_companions" name="max_companions" min="0" value="<?= (int)$guest['max_companions'] ?>">
                                </div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="custom_message">カスタムメッセージ</label>
                                <textarea id="custom_message" name="custom_message" rows="4"><?= htmlspecialchars($guest['custom_message']) ?></textarea>
                                <small>このグループに表示する特別なメッセージ</small>
                            </div>
                            
                            <div class="admin-form-actions">
                                <button type="submit" class="admin-button">
                                    <i class="fas fa-save"></i> 変更を保存
                                </button>
                                <a href="dashboard.php" class="admin-button" style="background-color: var(--admin-gray); margin-top: 10px;">
                                    <i class="fas fa-arrow-left"></i> キャンセル
                                </a>
                            </div>
                        </form>
                    </section>
                    
                    <section class="admin-section">
                        <h2>招待URL</h2>
                        <div class="admin-info-box">
                            <p>このゲストグループの招待URLは：</p>
                            <div class="admin-url-box">
                                <code><?= $site_url ?>?group=<?= urlencode($guest['group_id']) ?></code>
                                <a href="../index.php?group=<?= urlencode($guest['group_id']) ?>" target="_blank" class="admin-button" style="margin-top: 10px;">
                                    <i class="fas fa-external-link-alt"></i> 招待状を確認
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