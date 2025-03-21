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

// エラーと成功メッセージの初期化
$error = '';
$success = '';

// 結婚式設定テーブルがなければ作成
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wedding_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) NOT NULL UNIQUE,
            setting_value TEXT,
            display_name VARCHAR(100) NOT NULL,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {
    $error = "結婚式設定テーブルの作成に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// デフォルト設定が存在するか確認し、なければ作成
$default_settings = [
    'bride_name' => [
        'value' => 'あかね', 
        'display_name' => '新婦の名前', 
        'description' => '新婦の名前を入力してください。'
    ],
    'groom_name' => [
        'value' => '村岡 翔', 
        'display_name' => '新郎の名前', 
        'description' => '新郎の名前を入力してください。'
    ],
    'wedding_date' => [
        'value' => '2024年5月25日', 
        'display_name' => '結婚式の日付', 
        'description' => '結婚式の日付を入力してください。例: 2024年5月25日'
    ],
    'wedding_time' => [
        'value' => '13:00', 
        'display_name' => '結婚式の開始時間', 
        'description' => '結婚式の開始時間を入力してください。例: 13:00'
    ],
    'venue_name' => [
        'value' => 'ホテルニューオータニ', 
        'display_name' => '会場名', 
        'description' => '結婚式の会場名を入力してください。'
    ],
    'venue_address' => [
        'value' => '東京都千代田区紀尾井町4-1', 
        'display_name' => '会場住所', 
        'description' => '会場の住所を入力してください。'
    ],
    'venue_map_url' => [
        'value' => 'https://maps.google.com/maps?q=ホテルニューオータニ&output=embed', 
        'display_name' => '会場のGoogleマップURL', 
        'description' => 'Googleマップの埋め込みコードまたはURLを入力してください。埋め込みHTMLコード（&lt;iframe src="..."&gt;）をそのまま貼り付けても自動的に処理されます。'
    ],
    'venue_map_link' => [
        'value' => 'https://maps.google.com/maps?q=ホテルニューオータニ', 
        'display_name' => '会場のGoogleマップリンク', 
        'description' => 'スマートフォンなどで開くためのGoogleマップリンクを入力してください。'
    ]
];

try {
    foreach ($default_settings as $key => $setting) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wedding_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $exists = $stmt->fetchColumn();
        
        if ($exists == 0) {
            $stmt = $pdo->prepare("
                INSERT INTO wedding_settings (setting_key, setting_value, display_name, description) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$key, $setting['value'], $setting['display_name'], $setting['description']]);
        }
    }
} catch (PDOException $e) {
    $error = "デフォルト設定の初期化に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// 設定更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    try {
        // トランザクション開始
        $pdo->beginTransaction();
        
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $pdo->prepare("
                UPDATE wedding_settings 
                SET setting_value = ?
                WHERE setting_key = ?
            ");
            $stmt->execute([trim($value), $key]);
        }
        
        // トランザクション完了
        $pdo->commit();
        $success = "結婚式の設定を更新しました。";
    } catch (PDOException $e) {
        // ロールバック
        $pdo->rollBack();
        $error = "設定の更新に失敗しました。";
        if ($debug_mode) {
            $error .= " エラー: " . $e->getMessage();
        }
    }
}

// 設定データの取得
$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM wedding_settings ORDER BY id");
    $settings = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "設定の取得に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>結婚式設定 - <?= $site_name ?></title>
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
                        <h2><i class="fas fa-cog"></i> 結婚式の基本設定</h2>
                        
                        <?php if (!empty($error)): ?>
                        <div class="admin-error">
                            <?= $error ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                        <div class="admin-success">
                            <?= $success ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="admin-info-box">
                            <p><i class="fas fa-info-circle"></i> <strong>結婚式の基本情報</strong></p>
                            <p>結婚式の基本情報を設定します。これらの情報は招待状やメール通知などで使用されます。</p>
                        </div>
                        
                        <form class="admin-form" method="post" action="">
                            <input type="hidden" name="action" value="update_settings">
                            
                            <?php if (empty($settings)): ?>
                                <p>設定情報が見つかりません。</p>
                            <?php else: ?>
                                <?php foreach ($settings as $setting): ?>
                                    <div class="admin-form-group">
                                        <label for="<?= $setting['setting_key'] ?>">
                                            <?= htmlspecialchars($setting['display_name']) ?>
                                        </label>
                                        <input 
                                            type="text" 
                                            id="<?= $setting['setting_key'] ?>" 
                                            name="settings[<?= $setting['setting_key'] ?>]" 
                                            value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                            required
                                        >
                                        <?php if (!empty($setting['description'])): ?>
                                            <small><?= htmlspecialchars($setting['description']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="admin-form-actions">
                                    <button type="submit" class="admin-button">
                                        <i class="fas fa-save"></i> 設定を保存
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                    </section>
                </div>
                
                <?php include 'inc/footer.php'; ?>
            </div>
        </div>
    </div>
</body>
</html> 