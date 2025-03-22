<?php
// 設定ファイルを読み込み
require_once '../config.php';

// セッション開始
session_start();

// 管理者認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// メッセージ初期化
$success = '';
$error = '';

// 通知設定テーブルがなければ作成
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notification_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            subject VARCHAR(255) NOT NULL,
            template TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // system_settingsテーブルも作成
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {
    $error = "通知設定テーブルの作成に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// システム設定のデフォルト値を確認・作成
try {
    // 管理者メールアドレスの設定が存在するか確認
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
    $stmt->execute(['admin_email']);
    $exists = $stmt->fetchColumn();
    
    if ($exists == 0) {
        // デフォルト値として設定ファイルから$site_emailを使用
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?)
        ");
        $stmt->execute(['admin_email', $site_email]);
    }
} catch (PDOException $e) {
    $error = "システム設定の初期化に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// デフォルト通知設定が存在するか確認し、なければ作成
$notification_types = [
    'guest_registration' => [
        'subject' => '【結婚式】ゲスト情報登録のお知らせ',
        'template' => "お世話になっております。\n\n結婚式の招待状サイトに新しいゲストが登録されました。\n\nゲスト名: {guest_name}\nグループ: {group_name}\nメールアドレス: {email}\n\n管理画面からご確認ください。\n{admin_url}\n\n----\nこのメールは自動送信されています。"
    ],
    'rsvp_received' => [
        'subject' => '【結婚式】出欠回答のお知らせ',
        'template' => "お世話になっております。\n\n結婚式の招待状サイトに新しい出欠回答が届きました。\n\nゲスト名: {guest_name}\nグループ: {group_name}\n出欠: {attendance_status}\n人数: {guest_count}名\nメッセージ: {message}\n\n管理画面からご確認ください。\n{admin_url}\n\n----\nこのメールは自動送信されています。"
    ],
    'new_guestbook' => [
        'subject' => '【結婚式】ゲストブックに新しいメッセージ',
        'template' => "お世話になっております。\n\n結婚式の招待状サイトのゲストブックに新しいメッセージが投稿されました。\n\n投稿者名: {name}\nメッセージ: {message}\n投稿日時: {date}\n\n管理画面から承認・確認してください。\n{admin_url}\n\n----\nこのメールは自動送信されています。"
    ],
    'guest_confirmation' => [
        'subject' => '【結婚式】ゲスト情報受付完了のお知らせ',
        'template' => "{guest_name} 様\n\nこの度は、私たちの結婚式にご出席いただけるとのこと、誠にありがとうございます。\n\n以下の内容で受付いたしました。\n\nお名前: {guest_name}\n出欠: {attendance_status}\n参加人数: {guest_count}名\n\n何かご不明な点がございましたら、お気軽にお問い合わせください。\n\n当日お会いできることを楽しみにしております。\n\n----\n新郎新婦: {bride_name} & {groom_name}\nウェディングサイト: {website_url}"
    ],
    'event_reminder' => [
        'subject' => '【結婚式】まもなく結婚式のご案内',
        'template' => "{guest_name} 様\n\nいよいよ私たちの結婚式が近づいてまいりました。\n\n◆ 日時: {wedding_date} {wedding_time}\n◆ 場所: {venue_name}\n◆ 住所: {venue_address}\n\nご出席の皆様に改めてご案内申し上げます。\n当日の詳細やタイムスケジュールはウェディングサイトでご確認いただけます。\n{website_url}\n\n皆様とお会いできることを心より楽しみにしております。\n\n----\n新郎新婦: {bride_name} & {groom_name}"
    ]
];

try {
    foreach ($notification_types as $type => $content) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notification_settings WHERE type = ?");
        $stmt->execute([$type]);
        $exists = $stmt->fetchColumn();
        
        if ($exists == 0) {
            $stmt = $pdo->prepare("
                INSERT INTO notification_settings (type, subject, template, is_enabled) 
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([$type, $content['subject'], $content['template']]);
        }
    }
} catch (PDOException $e) {
    $error = "デフォルト通知設定の初期化に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// データベースから結婚式設定を取得する関数
function get_wedding_settings() {
    global $pdo;
    $settings = [];
    
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM wedding_settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        // エラー処理（静かに失敗）
    }
    
    return $settings;
}

// 設定更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_settings') {
        try {
            // トランザクション開始
            $pdo->beginTransaction();
            
            foreach ($_POST['settings'] as $id => $setting) {
                $is_enabled = isset($setting['is_enabled']) ? 1 : 0;
                $subject = trim($setting['subject']);
                $template = trim($setting['template']);
                
                if (empty($subject)) {
                    throw new Exception("件名は必須です。ID: {$id}");
                }
                
                if (empty($template)) {
                    throw new Exception("メール本文は必須です。ID: {$id}");
                }
                
                $stmt = $pdo->prepare("
                    UPDATE notification_settings 
                    SET is_enabled = ?, subject = ?, template = ?
                    WHERE id = ?
                ");
                $stmt->execute([$is_enabled, $subject, $template, $id]);
            }
            
            // トランザクション完了
            $pdo->commit();
            $success = "通知設定を更新しました。";
        } catch (Exception $e) {
            // ロールバック
            $pdo->rollBack();
            $error = "設定の更新に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_admin_email') {
        // 管理者メールアドレス更新処理
        $admin_email = isset($_POST['admin_email']) ? trim($_POST['admin_email']) : '';
        
        if (empty($admin_email)) {
            $error = "管理者メールアドレスは必須です。";
        } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $error = "有効なメールアドレス形式で入力してください。";
        } else {
            try {
                // 管理者メールアドレスの更新
                $stmt = $pdo->prepare("
                    UPDATE system_settings 
                    SET setting_value = ?
                    WHERE setting_key = ?
                ");
                $stmt->execute([$admin_email, 'admin_email']);
                
                // 存在しない場合は挿入
                if ($stmt->rowCount() == 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO system_settings (setting_key, setting_value)
                        VALUES (?, ?)
                    ");
                    $stmt->execute(['admin_email', $admin_email]);
                }
                
                $success = "管理者メールアドレスを更新しました。";
            } catch (PDOException $e) {
                $error = "メールアドレスの更新に失敗しました。";
                if ($debug_mode) {
                    $error .= " エラー: " . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'test_mail') {
        $to = isset($_POST['test_email']) ? trim($_POST['test_email']) : '';
        $settings_id = isset($_POST['settings_id']) ? (int)$_POST['settings_id'] : 0;
        
        if (empty($to)) {
            $error = "テストメールの送信先を入力してください。";
        } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $error = "有効なメールアドレスを入力してください。";
        } elseif ($settings_id <= 0) {
            $error = "通知設定が見つかりません。";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM notification_settings WHERE id = ?");
                $stmt->execute([$settings_id]);
                $notification = $stmt->fetch();
                
                if (!$notification) {
                    throw new Exception("通知設定が見つかりません。");
                }
                
                // 結婚式設定を取得
                $wedding_settings = get_wedding_settings();
                
                // グローバル変数を確認してテストデータを準備
                $test_data = [
                    '{guest_name}' => 'テストゲスト',
                    '{group_name}' => 'テストグループ',
                    '{email}' => $to,
                    '{attendance_status}' => '出席',
                    '{guest_count}' => '2',
                    '{message}' => 'これはテストメッセージです。',
                    '{name}' => 'テスト投稿者',
                    '{date}' => date('Y-m-d H:i:s'),
                    '{admin_url}' => $site_url . 'admin/dashboard.php',
                    '{bride_name}' => isset($wedding_settings['bride_name']) ? $wedding_settings['bride_name'] : 'テスト新婦',
                    '{groom_name}' => isset($wedding_settings['groom_name']) ? $wedding_settings['groom_name'] : 'テスト新郎',
                    '{website_url}' => $site_url,
                    '{wedding_date}' => isset($wedding_settings['wedding_date']) ? $wedding_settings['wedding_date'] : '2024年1月1日',
                    '{wedding_time}' => isset($wedding_settings['wedding_time']) ? $wedding_settings['wedding_time'] : '13:00',
                    '{venue_name}' => isset($wedding_settings['venue_name']) ? $wedding_settings['venue_name'] : 'テスト会場',
                    '{venue_address}' => isset($wedding_settings['venue_address']) ? $wedding_settings['venue_address'] : 'テスト住所123',
                    '{venue_map_url}' => isset($wedding_settings['venue_map_url']) ? $wedding_settings['venue_map_url'] : 'https://maps.google.com/maps?q=テスト会場&output=embed',
                    '{venue_map_link}' => isset($wedding_settings['venue_map_link']) ? $wedding_settings['venue_map_link'] : 'https://maps.google.com/maps?q=テスト会場'
                ];
                
                // テンプレートの置き換え
                $subject = $notification['subject'];
                $message = $notification['template'];
                
                foreach ($test_data as $key => $value) {
                    $subject = str_replace($key, $value, $subject);
                    $message = str_replace($key, $value, $message);
                }
                
                // メール送信（PHPMailerを使用）
                $mail_result = send_system_mail($to, $subject, $message);
                
                if ($mail_result['success']) {
                    $success = "テストメールを送信しました。メールボックスをご確認ください。";
                } else {
                    throw new Exception("メール送信に失敗しました: " . $mail_result['message']);
                }
            } catch (Exception $e) {
                $error = "テストメール送信に失敗しました。";
                if ($debug_mode) {
                    $error .= " エラー: " . $e->getMessage();
                }
            }
        }
    }
}

// 通知設定の取得
$notifications = [];
try {
    $stmt = $pdo->query("SELECT * FROM notification_settings ORDER BY type");
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "通知設定の取得に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// 管理者メールアドレスの取得
$admin_email = '';
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute(['admin_email']);
    $admin_email = $stmt->fetchColumn();
} catch (PDOException $e) {
    // エラーは表示せず、デフォルト値のままにする
    if ($debug_mode) {
        $error .= " 管理者メールアドレスの取得に失敗しました: " . $e->getMessage();
    }
}

// 通知タイプの日本語表示
$notification_labels = [
    'guest_registration' => 'ゲスト登録通知',
    'rsvp_received' => '出欠回答通知',
    'new_guestbook' => 'ゲストブック投稿通知',
    'guest_confirmation' => 'ゲスト確認メール',
    'event_reminder' => 'イベントリマインダー'
];

// プレースホルダーの説明
$placeholders = [
    'guest_registration' => [
        '{guest_name}' => 'ゲスト名',
        '{group_name}' => 'グループ名',
        '{email}' => 'メールアドレス',
        '{admin_url}' => '管理画面URL'
    ],
    'rsvp_received' => [
        '{guest_name}' => 'ゲスト名',
        '{group_name}' => 'グループ名',
        '{attendance_status}' => '出欠ステータス',
        '{guest_count}' => 'ゲスト数',
        '{message}' => 'メッセージ',
        '{admin_url}' => '管理画面URL'
    ],
    'new_guestbook' => [
        '{name}' => '投稿者名',
        '{message}' => 'メッセージ',
        '{date}' => '投稿日時',
        '{admin_url}' => '管理画面URL'
    ],
    'guest_confirmation' => [
        '{guest_name}' => 'ゲスト名',
        '{attendance_status}' => '出欠ステータス',
        '{guest_count}' => 'ゲスト数',
        '{bride_name}' => '新婦名',
        '{groom_name}' => '新郎名',
        '{venue_name}' => '会場名',
        '{venue_address}' => '会場住所',
        '{venue_map_url}' => '会場のGoogleマップ埋め込みURL',
        '{venue_map_link}' => '会場のGoogleマップリンク',
        '{website_url}' => 'ウェブサイトURL'
    ],
    'event_reminder' => [
        '{guest_name}' => 'ゲスト名',
        '{wedding_date}' => '結婚式日付',
        '{wedding_time}' => '結婚式時間',
        '{venue_name}' => '会場名',
        '{venue_address}' => '会場住所',
        '{venue_map_url}' => '会場のGoogleマップ埋め込みURL',
        '{venue_map_link}' => '会場のGoogleマップリンク',
        '{website_url}' => 'ウェブサイトURL',
        '{bride_name}' => '新婦名',
        '{groom_name}' => '新郎名'
    ]
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>通知設定 - <?= $site_name ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .notification-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .notification-title {
            display: flex;
            align-items: center;
            font-size: 1.2rem;
            margin: 0;
        }
        
        .notification-title .enabled-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .notification-title .enabled-indicator.active {
            background-color: #4caf50;
        }
        
        .notification-title .enabled-indicator.inactive {
            background-color: #f44336;
        }
        
        .placeholder-list {
            background-color: #f5f5f5;
            border-radius: 5px;
            padding: 10px 15px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .placeholder-list h4 {
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 1rem;
        }
        
        .placeholder-item {
            display: inline-block;
            background-color: #e0e0e0;
            padding: 3px 8px;
            margin: 0 5px 5px 0;
            border-radius: 3px;
            font-family: monospace;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .placeholder-item:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .test-mail-form {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .save-all-btn {
            position: sticky;
            bottom: 20px;
            display: flex;
            justify-content: center;
            z-index: 100;
        }
        
        .save-all-btn button {
            padding: 12px 25px;
            font-size: 1.1rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .save-all-btn .admin-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.25);
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-dashboard">
        <header class="admin-dashboard-header">
            <div class="admin-logo">
                <h1><i class="fas fa-bell"></i> 通知設定</h1>
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
                
                <?php if (!empty($success)): ?>
                <div class="admin-success">
                    <?= $success ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                <div class="admin-error">
                    <?= $error ?>
                </div>
                <?php endif; ?>
                
                <section class="admin-section">
                    <div class="admin-section-header">
                        <h2><i class="fas fa-envelope"></i> 管理者通知メールアドレス設定</h2>
                        <p>通知メールを受信する管理者のメールアドレスを設定します。このアドレスに各種通知メールが送信されます。</p>
                    </div>
                    
                    <form method="post" action="" class="admin-form">
                        <input type="hidden" name="action" value="update_admin_email">
                        
                        <div class="notification-card">
                            <div class="admin-form-group">
                                <label for="admin_email">管理者通知メールアドレス <span class="required">*</span></label>
                                <input type="email" id="admin_email" name="admin_email" required value="<?= htmlspecialchars($admin_email) ?>" placeholder="例: admin@example.com">
                                <small>出欠回答通知やゲストブック投稿通知などが送信されるメールアドレスです。</small>
                            </div>
                            
                            <div class="admin-form-actions">
                                <button type="submit" class="admin-button">
                                    <i class="fas fa-save"></i> メールアドレスを保存
                                </button>
                            </div>
                        </div>
                    </form>
                </section>
                
                <section class="admin-section">
                    <div class="admin-section-header">
                        <h2>メール通知設定</h2>
                        <p>結婚式の招待状サイトから送信されるメール通知の設定を行います。各通知の有効/無効の切り替えや、内容のカスタマイズができます。</p>
                    </div>
                    
                    <form method="post" action="">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <?php if (empty($notifications)): ?>
                            <p>通知設定が見つかりません。</p>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-card">
                                    <div class="notification-header">
                                        <h3 class="notification-title">
                                            <span class="enabled-indicator <?= $notification['is_enabled'] ? 'active' : 'inactive' ?>"></span>
                                            <?= isset($notification_labels[$notification['type']]) ? $notification_labels[$notification['type']] : $notification['type'] ?>
                                        </h3>
                                        <label class="switch">
                                            <input type="checkbox" name="settings[<?= $notification['id'] ?>][is_enabled]" <?= $notification['is_enabled'] ? 'checked' : '' ?>>
                                            <span class="slider round"></span>
                                            <span class="switch-label"><?= $notification['is_enabled'] ? '有効' : '無効' ?></span>
                                        </label>
                                    </div>
                                    
                                    <?php if (isset($placeholders[$notification['type']])): ?>
                                        <div class="placeholder-list">
                                            <h4>使用可能なプレースホルダー:</h4>
                                            <?php foreach ($placeholders[$notification['type']] as $placeholder => $description): ?>
                                                <span class="placeholder-item" data-placeholder="<?= $placeholder ?>" data-type="<?= $notification['id'] ?>"><?= $placeholder ?></span>
                                            <?php endforeach; ?>
                                            <small>クリックするとテキストエリアに挿入されます</small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="admin-form-group">
                                        <label for="subject-<?= $notification['id'] ?>">メール件名</label>
                                        <input type="text" id="subject-<?= $notification['id'] ?>" name="settings[<?= $notification['id'] ?>][subject]" value="<?= htmlspecialchars($notification['subject']) ?>" required>
                                    </div>
                                    
                                    <div class="admin-form-group">
                                        <label for="template-<?= $notification['id'] ?>">メール本文</label>
                                        <textarea id="template-<?= $notification['id'] ?>" name="settings[<?= $notification['id'] ?>][template]" rows="8" required><?= htmlspecialchars($notification['template']) ?></textarea>
                                        <small>メール本文にはプレースホルダーを使用できます。送信時に実際の値に置き換えられます。</small>
                                    </div>
                                    
                                    <div class="test-mail-form">
                                        <div class="admin-form-group" style="flex-grow: 1; margin-bottom: 0;">
                                            <label for="test-email-<?= $notification['id'] ?>">テストメールの送信先</label>
                                            <input type="email" id="test-email-<?= $notification['id'] ?>" placeholder="test@example.com">
                                        </div>
                                        <button type="button" class="admin-button admin-button-secondary test-mail-btn" data-id="<?= $notification['id'] ?>">
                                            <i class="fas fa-paper-plane"></i> テストメール送信
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="save-all-btn">
                                <button type="submit" class="admin-button">
                                    <i class="fas fa-save"></i> すべての設定を保存
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
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // チェックボックスのラベル更新
        const toggleSwitches = document.querySelectorAll('input[type="checkbox"]');
        toggleSwitches.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const label = this.nextElementSibling.nextElementSibling;
                const indicator = this.closest('.notification-header').querySelector('.enabled-indicator');
                
                if (this.checked) {
                    label.textContent = '有効';
                    indicator.classList.remove('inactive');
                    indicator.classList.add('active');
                } else {
                    label.textContent = '無効';
                    indicator.classList.remove('active');
                    indicator.classList.add('inactive');
                }
            });
        });
        
        // プレースホルダーのクリックでテキストエリアに挿入
        const placeholders = document.querySelectorAll('.placeholder-item');
        placeholders.forEach(function(placeholder) {
            placeholder.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                const value = this.getAttribute('data-placeholder');
                const textarea = document.getElementById('template-' + type);
                
                // カーソル位置にプレースホルダーを挿入
                const startPos = textarea.selectionStart;
                const endPos = textarea.selectionEnd;
                const before = textarea.value.substring(0, startPos);
                const after = textarea.value.substring(endPos, textarea.value.length);
                
                textarea.value = before + value + after;
                
                // カーソル位置を更新
                textarea.selectionStart = startPos + value.length;
                textarea.selectionEnd = startPos + value.length;
                
                // テキストエリアにフォーカス
                textarea.focus();
            });
        });
        
        // テストメール送信
        const testMailButtons = document.querySelectorAll('.test-mail-btn');
        testMailButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const emailInput = document.getElementById('test-email-' + id);
                const email = emailInput.value.trim();
                
                if (!email) {
                    alert('テストメールの送信先を入力してください。');
                    emailInput.focus();
                    return;
                }
                
                // メール送信用のフォームを動的に作成して送信
                const form = document.createElement('form');
                form.method = 'post';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'test_mail';
                
                const emailFormInput = document.createElement('input');
                emailFormInput.type = 'hidden';
                emailFormInput.name = 'test_email';
                emailFormInput.value = email;
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'settings_id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(emailFormInput);
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            });
        });
    });
    </script>
</body>
</html> 