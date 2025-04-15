<?php
// 設定ファイルを読み込み
require_once 'config.php';

// URLからグループIDを取得
$group_id = isset($_GET['group']) ? trim($_GET['group']) : '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$auto_checkin = isset($_GET['auto_checkin']) && $_GET['auto_checkin'] === '1';
$checkin_complete = isset($_GET['checkin_complete']) && $_GET['checkin_complete'] === '1';
$response_complete = isset($_GET['r']) && $_GET['r'] === 'done';

// グループIDが存在し、checkin_completeパラメータがない場合は、
// データベースでチェックイン履歴を確認して必要に応じてリダイレクト
if ($group_id && !$checkin_complete && !$token) {
    try {
        // まずグループIDから関連するゲスト情報を取得
        $stmt = $pdo->prepare("SELECT id FROM guests WHERE group_id = :group_id LIMIT 1");
        $stmt->execute(['group_id' => $group_id]);
        $guest_data = $stmt->fetch();
        
        if ($guest_data) {
            $guest_id = $guest_data['id'];
            
            // 該当ゲストのチェックイン履歴を確認
            $check_stmt = $pdo->prepare("
                SELECT COUNT(*) FROM checkins 
                WHERE guest_id = ?
            ");
            $check_stmt->execute([$guest_id]);
            $has_checkin_history = $check_stmt->fetchColumn() > 0;
            
            // チェックイン履歴がある場合はリダイレクト
            if ($has_checkin_history) {
                error_log("データベースでチェックイン履歴を確認: グループID={$group_id}、ゲストID={$guest_id}、チェックイン済み");
                $redirectUrl = $site_url . 'index.php?group=' . urlencode($group_id) . '&checkin_complete=1';
                header('Location: ' . $redirectUrl);
                exit;
            } else {
                error_log("データベースでチェックイン履歴を確認: グループID={$group_id}、ゲストID={$guest_id}、未チェックイン");
            }
        }
    } catch (PDOException $e) {
        error_log("チェックイン履歴確認エラー: " . $e->getMessage());
    }
}

// ゲスト情報を初期化
$guest_info = [
    'group_name' => '親愛なるゲスト様',
    'arrival_time' => '13:00',
    'custom_message' => '',
    'max_companions' => 5
];

// グループIDが存在する場合、データベースからゲスト情報を取得
if ($group_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM guests WHERE group_id = :group_id LIMIT 1");
        $stmt->execute(['group_id' => $group_id]);
        $guest_data = $stmt->fetch();
        
        if ($guest_data) {
            $guest_info = [
                'id' => $guest_data['id'],
                'group_name' => $guest_data['group_name'],
                'arrival_time' => $guest_data['arrival_time'],
                'custom_message' => $guest_data['custom_message'],
                'max_companions' => $guest_data['max_companions']
            ];
        }
    } catch (PDOException $e) {
        if ($debug_mode) {
            echo "データベースエラー: " . $e->getMessage();
        }
    }
}

// 付箋があるか確認
$has_fusen = false;
$fusens = [];
if ($group_id && isset($guest_info['id'])) {
    try {
        // 付箋の存在確認
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM guest_fusen 
            WHERE guest_id = ?
        ");
        $stmt->execute([$guest_info['id']]);
        $has_fusen = ($stmt->fetchColumn() > 0);
        
        // 付箋があれば詳細データを取得
        if ($has_fusen) {
            $stmt = $pdo->prepare("
                SELECT gf.*, ft.type_name 
                FROM guest_fusen gf
                JOIN fusen_types ft ON gf.fusen_type_id = ft.id
                WHERE gf.guest_id = ?
                ORDER BY ft.sort_order, ft.type_name
            ");
            $stmt->execute([$guest_info['id']]);
            $fusens = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        // エラーが発生した場合は付箋なしとする
        if ($debug_mode) {
            echo "付箋データのカウントエラー: " . $e->getMessage();
        }
    }
}

// QRコードスキャンからの自動チェックイン処理
if (!empty($token) && $auto_checkin) {
    require_once 'includes/qr_helper.php';
    $guest_info = get_guest_by_qr_token($token);
    
    if ($guest_info) {
        $checkin_result = record_guest_checkin($guest_info['id'], 'QRスキャン', 'ゲスト自身によるスキャン');
        if ($checkin_result) {
            error_log("自動チェックイン成功 (index.php): ゲストID=" . $guest_info['id']);
            $checkin_complete = true;
            
            // リダイレクト処理を追加 - QRからのチェックイン後は必ずcheckin_complete=1のURLに変換
            if (!isset($_GET['checkin_complete']) || $_GET['checkin_complete'] !== '1') {
                $redirectUrl = $site_url . 'index.php?group=' . urlencode($group_id) . '&checkin_complete=1';
                header('Location: ' . $redirectUrl);
                exit;
            }
        } else {
            error_log("自動チェックイン失敗 (index.php): ゲストID=" . $guest_info['id']);
        }
    } else {
        error_log("無効なQRコード (index.php): token=$token");
    }
}

// venue_map.phpから会場情報取得機能を移植
function get_wedding_venue_info() {
    global $pdo;
    $venue_info = [
        'name' => '',
        'address' => '',
        'map_url' => '',
        'map_link' => ''
    ];
    
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM wedding_settings WHERE setting_key IN ('venue_name', 'venue_address', 'venue_map_url', 'venue_map_link')");
        $stmt->execute();
        
        while ($row = $stmt->fetch()) {
            switch ($row['setting_key']) {
                case 'venue_name':
                    $venue_info['name'] = $row['setting_value'];
                    break;
                case 'venue_address':
                    $venue_info['address'] = $row['setting_value'];
                    break;
                case 'venue_map_url':
                    // iframeタグが含まれている場合、src属性からURLを抽出
                    if (strpos($row['setting_value'], '<iframe') !== false) {
                        preg_match('/src=["\']([^"\']+)["\']/', $row['setting_value'], $matches);
                        $venue_info['map_url'] = isset($matches[1]) ? $matches[1] : '';
                    } else {
                        $venue_info['map_url'] = $row['setting_value'];
                    }
                    break;
                case 'venue_map_link':
                    // iframeタグが含まれている場合、href属性からURLを抽出
                    if (strpos($row['setting_value'], '<a') !== false) {
                        preg_match('/href=["\']([^"\']+)["\']/', $row['setting_value'], $matches);
                        $venue_info['map_link'] = isset($matches[1]) ? $matches[1] : '';
                    } else {
                        $venue_info['map_link'] = $row['setting_value'];
                    }
                    break;
            }
        }
    } catch (PDOException $e) {
        // エラー処理（静かに失敗）
    }
    
    return $venue_info;
}

// 結婚式の日時情報を取得する関数
function get_wedding_datetime() {
    global $pdo;
    $datetime_info = [
        'date' => '2024年4月30日',
        'time' => '12:00',
        'ceremony_time' => '12:00',
        'reception_time' => '13:00'
    ];
    
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM wedding_settings WHERE setting_key IN ('wedding_date', 'wedding_time', 'ceremony_time', 'reception_time')");
        $stmt->execute();
        
        while ($row = $stmt->fetch()) {
            if ($row['setting_key'] == 'wedding_date') {
                $datetime_info['date'] = $row['setting_value'];
            } elseif ($row['setting_key'] == 'wedding_time') {
                $datetime_info['time'] = $row['setting_value'];
            } elseif ($row['setting_key'] == 'ceremony_time') {
                $datetime_info['ceremony_time'] = $row['setting_value'];
            } elseif ($row['setting_key'] == 'reception_time') {
                $datetime_info['reception_time'] = $row['setting_value'];
            }
        }
    } catch (PDOException $e) {
        // エラー処理（静かに失敗）
    }
    
    return $datetime_info;
}

// 会場情報を取得
$venue_info = get_wedding_venue_info();

// 結婚式の日時情報を取得
$datetime_info = get_wedding_datetime();

// 既に回答済みかどうか（クエリパラメータまたはデータベース確認）
$already_responded = $response_complete;

// グループIDがある場合、回答済みかどうかをデータベースから確認
if ($group_id && isset($guest_info['id']) && !$already_responded) {
    try {
        // グループIDに紐づくゲストIDについて、responsesテーブルに回答があるか確認
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM responses 
            WHERE guest_id = :guest_id
        ");
        $stmt->execute(['guest_id' => $guest_info['id']]);
        $response_count = $stmt->fetchColumn();
        
        // 回答がある場合は回答済みとする
        if ($response_count > 0) {
            $already_responded = true;
        }
    } catch (PDOException $e) {
        // データベースエラーは静かに失敗
        if ($debug_mode) {
            error_log("回答確認エラー: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>翔 & あかね - 結婚式のご案内</title>
    
    <!-- パフォーマンス最適化 -->
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    
    <!-- リソースのプリロード -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="css/envelope.css" as="style" fetchpriority="high">
    <link rel="preload" href="css/style.css" as="style" fetchpriority="high">
    <link rel="preload" href="js/envelope.js" as="script">
    
    <!-- スタイルシートの読み込み -->
    <link rel="stylesheet" href="css/envelope.css">
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google reCAPTCHA v3 -->
    <script src="https://www.google.com/recaptcha/api.js?render=6LfXwg8rAAAAAO8tgbD74yqTFHK9ZW6Ns18M8GpF"></script>
    <script>
    function onSubmitForm(token) {
        document.getElementById("rsvp-form").submit();
    }
    
    // フォーム送信時にreCAPTCHA v3を実行
    document.addEventListener('DOMContentLoaded', function() {
        const rsvpForm = document.getElementById('rsvp-form');
        if (rsvpForm) {
            rsvpForm.addEventListener('submit', function(e) {
                e.preventDefault();
                grecaptcha.ready(function() {
                    grecaptcha.execute('6LfXwg8rAAAAAO8tgbD74yqTFHK9ZW6Ns18M8GpF', {action: 'submit'}).then(function(token) {
                        // トークンを隠しフィールドに追加
                        let recaptchaInput = document.createElement('input');
                        recaptchaInput.setAttribute('type', 'hidden');
                        recaptchaInput.setAttribute('name', 'g-recaptcha-response');
                        recaptchaInput.setAttribute('value', token);
                        rsvpForm.appendChild(recaptchaInput);
                        
                        // フォームを送信
                        rsvpForm.submit();
                    });
                });
            });
        }
    });
    </script>
    
    <!-- スクロールアニメーション用のCSS -->
    <style>
        .fade-in-section {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 1s ease, transform 1s ease;
            transition-delay: 0.2s;
            will-change: opacity, transform;
        }
        
        .fade-in-section.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .fade-sequence > * {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
            will-change: opacity, transform;
        }
        
        .fade-sequence.is-visible > * {
            opacity: 1;
            transform: translateY(0);
        }
        
        .fade-sequence.is-visible > *:nth-child(1) { transition-delay: 0.1s; }
        .fade-sequence.is-visible > *:nth-child(2) { transition-delay: 0.2s; }
        .fade-sequence.is-visible > *:nth-child(3) { transition-delay: 0.3s; }
        .fade-sequence.is-visible > *:nth-child(4) { transition-delay: 0.4s; }
        .fade-sequence.is-visible > *:nth-child(5) { transition-delay: 0.5s; }
        .fade-sequence.is-visible > *:nth-child(6) { transition-delay: 0.6s; }
        .fade-sequence.is-visible > *:nth-child(7) { transition-delay: 0.7s; }
        .fade-sequence.is-visible > *:nth-child(8) { transition-delay: 0.8s; }
        .fade-sequence.is-visible > *:nth-child(9) { transition-delay: 0.9s; }
        .fade-sequence.is-visible > *:nth-child(10) { transition-delay: 1.0s; }
        
        .scale-in {
            opacity: 0;
            transform: scale(0.9);
            transition: opacity 0.8s ease, transform 0.8s ease;
            will-change: opacity, transform;
        }
        
        .scale-in.is-visible {
            opacity: 1;
            transform: scale(1);
        }
        
        .slide-in-left {
            opacity: 0;
            transform: translateX(-50px);
            transition: opacity 0.8s ease, transform 0.8s ease;
            will-change: opacity, transform;
        }
        
        .slide-in-right {
            opacity: 0;
            transform: translateX(50px);
            transition: opacity 0.8s ease, transform 0.8s ease;
            will-change: opacity, transform;
        }
        
        .slide-in-left.is-visible,
        .slide-in-right.is-visible {
            opacity: 1;
            transform: translateX(0);
        }

        /* 回答完了メッセージのスタイル */
        .response-complete {
            background-color: #f8f4e6;
            border: 2px solid #4CAF50;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .response-complete p {
            margin: 10px 0;
            color: #333;
            font-size: 16px;
            line-height: 1.6;
        }
        
        .response-complete p:first-child {
            font-weight: bold;
            color: #4CAF50;
            font-size: 18px;
        }
    </style>
    
    <!-- スクリプトの遅延読み込み -->
    <script src="js/envelope.js" defer></script>
    <script src="js/main.js" defer></script>
    
    <!-- モバイル最適化 -->
    <meta name="theme-color" content="#f8f4e6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
</head>
<body>
    <!-- 装飾エフェクト（常時表示） -->
    <div class="decoration-effects">
        <!-- 10個のランダムなハート -->
        <?php for ($i = 1; $i <= 10; $i++): ?>
        <div class="floating-heart" style="left: <?= rand(5, 95) ?>%; top: <?= rand(5, 95) ?>%; animation-delay: <?= $i * 0.5 ?>s;"></div>
        <?php endfor; ?>
        
        <!-- 15個のランダムなキラキラ -->
        <?php for ($i = 1; $i <= 15; $i++): ?>
        <div class="floating-sparkle" style="left: <?= rand(5, 95) ?>%; top: <?= rand(5, 95) ?>%; animation-delay: <?= $i * 0.3 ?>s;"></div>
        <?php endfor; ?>
    </div>

    <!-- 封筒演出 -->
    <?php if (!$checkin_complete): ?>
    <div class="envelope-container">
        <div class="envelope-bg"></div>
        <div class="floating-petals">
            <div class="petal petal1"></div>
            <div class="petal petal2"></div>
            <div class="petal petal3"></div>
            <div class="petal petal4"></div>
            <div class="petal petal5"></div>
        </div>
        
        <div class="envelope">
            <div class="envelope-flap">
                <div class="wax-seal">
                    <div class="wax-seal-texture"></div>
                    <div class="wax-seal-highlight"></div>
                </div>
            </div>
            <div class="envelope-content">
                <p class="tap-instruction">クリックして開く</p>
            </div>
        </div>
        
        <div class="celebration-effects">
            <div class="heart heart1"></div>
            <div class="heart heart2"></div>
            <div class="heart heart3"></div>
            <div class="heart heart4"></div>
            <div class="heart heart5"></div>
            
            <div class="sparkle sparkle1"></div>
            <div class="sparkle sparkle2"></div>
            <div class="sparkle sparkle3"></div>
            <div class="sparkle sparkle4"></div>
            <div class="sparkle sparkle5"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 選択画面 -->
    <div class="choice-screen <?php echo !$checkin_complete ? 'hide' : ''; ?>">
        <!-- 選択画面用の装飾エフェクト -->
        <div class="choice-decoration-effects">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <div class="floating-heart" style="left: <?= rand(5, 95) ?>%; top: <?= rand(5, 95) ?>%; width: <?= rand(10, 20) ?>px; height: <?= rand(10, 20) ?>px; opacity: 0.15; animation-delay: <?= $i * 0.7 ?>s;"></div>
            <?php endfor; ?>
            
            <?php for ($i = 1; $i <= 8; $i++): ?>
            <div class="floating-sparkle" style="left: <?= rand(5, 95) ?>%; top: <?= rand(5, 95) ?>%; width: <?= rand(8, 15) ?>px; height: <?= rand(8, 15) ?>px; opacity: 0.1; animation-delay: <?= $i * 0.4 ?>s;"></div>
            <?php endfor; ?>
        </div>
        
        <div class="choice-header">
            <h2><?= htmlspecialchars($guest_info['group_name'] ?? '親愛なるゲスト様') ?>へ</h2>
            <p>下記のいずれかをお選びください</p>
        </div>
        
        <!-- 招待状カード -->
        <a href="#invitation-content" class="choice-card choice-invitation-card" style="text-decoration: none; color: inherit;">
            <div class="choice-card-icon">
                <i class="fas fa-envelope-open-text"></i>
            </div>
            <div class="choice-card-content">
                <h3>招待状</h3>
                <p>結婚式のご案内と詳細情報</p>
            </div>
        </a>
        
        <?php
        if ($has_fusen): 
            foreach ($fusens as $index => $fusen):
        ?>
        <!-- 付箋カード -->
        <a href="<?= ($group_id) ? 'fusen.php?group='.urlencode($group_id) : '#' ?>" 
           data-url="<?= ($group_id) ? 'fusen.php?group='.urlencode($group_id) : '#' ?>" 
           class="choice-card choice-fusen-card" style="text-decoration: none; color: inherit;">
            <div class="choice-card-icon">
                <i class="fas fa-sticky-note"></i>
            </div>
            <div class="choice-card-content">
                <h3><?= htmlspecialchars($fusen['type_name']) ?></h3>
                <p>重要なご案内があります</p>
            </div>
        </a>
        <?php 
            endforeach;
        endif; 
        ?>
    </div>

    <div class="invitation-content <?php echo !$checkin_complete ? 'hide' : ''; ?>" id="invitation-content">
        <!-- 招待状ページ用の装飾エフェクト -->
        <div class="invitation-decoration-effects">
            <?php for ($i = 1; $i <= 8; $i++): ?>
            <div class="floating-heart" style="left: <?= rand(5, 95) ?>%; top: <?= rand(5, 95) ?>%; width: <?= rand(8, 15) ?>px; height: <?= rand(8, 15) ?>px; opacity: 0.1; animation-delay: <?= $i * 0.8 ?>s;"></div>
            <?php endfor; ?>
            
            <?php for ($i = 1; $i <= 12; $i++): ?>
            <div class="floating-sparkle" style="left: <?= rand(5, 95) ?>%; top: <?= rand(5, 95) ?>%; width: <?= rand(5, 12) ?>px; height: <?= rand(5, 12) ?>px; opacity: 0.08; animation-delay: <?= $i * 0.5 ?>s;"></div>
            <?php endfor; ?>
        </div>
        
        <div class="floating-leaves">
            <div class="leaf leaf1"></div>
            <div class="leaf leaf2"></div>
            <div class="leaf leaf3"></div>
            <div class="leaf leaf4"></div>
            <div class="leaf leaf5"></div>
            <div class="leaf leaf6"></div>
            <div class="leaf leaf7"></div>
            <div class="leaf leaf8"></div>
        </div>

        <div class="container">
            <?php if ($checkin_complete): ?>
            <!-- チェックイン完了メッセージ -->
            <div style="background-color: #4CAF50; color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; box-shadow: 0 3px 10px rgba(0,0,0,0.1);">
                <div style="font-size: 50px; margin-bottom: 10px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 style="margin: 0 0 10px 0;">チェックイン完了</h2>
                <p style="margin: 0;">受付が完了しました。素敵な時間をお過ごしください。</p>
            </div>
            <?php endif; ?>
            
            <header class="main-header fade-in-section">
                <div class="header-inner">
                    <h1 class="title">翔 & あかね</h1>
                    <div class="title-decoration">
                        <span class="decoration-line"></span>
                        <i class="fas fa-leaf"></i>
                        <i class="fas fa-heart"></i>
                        <i class="fas fa-leaf"></i>
                        <span class="decoration-line"></span>
                    </div>
                    <p class="subtitle">Welcome to Our Wedding</p>
                    <p class="date">2025.4.30</p>
                    
                    <?php if ($group_id): ?>
                    <div class="personal-message">
                        <p class="guest-name"><?= $guest_info['group_name'] ?>へ</p>
                        <?php if ($guest_info['custom_message']): ?>
                        <p class="personal-note"><?= nl2br($guest_info['custom_message']) ?></p>
                        <?php endif; ?>
                        
                        <?php
                        if ($has_fusen): 
                        ?>
                        <div class="fusen-link-container">
                            <a href="<?= ($group_id) ? 'fusen.php?group='.urlencode($group_id) : '#' ?>" class="fusen-link">
                                <i class="fas fa-sticky-note"></i> 付箋を確認する
                            </a>
                            <p class="fusen-note">※付箋には重要なご案内が記載されています。必ずご確認ください。</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </header>

            <div class="video-container fade-in-section">
                <div class="video-wrapper">
                    <?php
                    // メイン動画を取得
                    $main_video = null;
                    try {
                        $stmt = $pdo->query("SELECT * FROM video_gallery WHERE is_main_video = 1 AND is_active = 1 LIMIT 1");
                        $main_video = $stmt->fetch();
                    } catch (PDOException $e) {
                        // エラー処理（静かに失敗）
                    }

                    // サムネイル画像のパス
                    $thumbnail_path = 'images/thumbnail.jpg'; // デフォルトのサムネイル
                    if ($main_video && $main_video['thumbnail']) {
                        $thumbnail_path = 'images/thumbnails/' . $main_video['thumbnail'];
                    }

                    // 動画のソースパス
                    $video_src = 'videos/wedding-invitation.mp4'; // デフォルトの動画
                    if ($main_video) {
                        $video_src = 'videos/' . $main_video['filename'];
                    }
                    
                    // 拡張子に基づいてMIMEタイプを判定する
                    $file_extension = pathinfo($video_src, PATHINFO_EXTENSION);
                    $mime_type = 'video/mp4'; // デフォルト
                    
                    switch (strtolower($file_extension)) {
                        case 'mp4':
                            $mime_type = 'video/mp4';
                            break;
                        case 'mov':
                            $mime_type = 'video/quicktime';
                            break;
                        case 'webm':
                            $mime_type = 'video/webm';
                            break;
                        case 'ogg':
                        case 'ogv':
                            $mime_type = 'video/ogg';
                            break;
                        case 'avi':
                            $mime_type = 'video/x-msvideo';
                            break;
                        case 'wmv':
                            $mime_type = 'video/x-ms-wmv';
                            break;
                        case 'mpg':
                        case 'mpeg':
                            $mime_type = 'video/mpeg';
                            break;
                        // その他の形式も必要に応じて追加
                    }
                    ?>
                    <video id="wedding-video" controls poster="<?= $thumbnail_path ?>" preload="none" playsinline>
                        <!-- プライマリソース - 判定されたMIMEタイプに基づく -->
                        <source src="<?= $video_src ?>" type="<?= $mime_type ?>">
                        
                        <!-- 代替ソース - 主要なビデオフォーマット -->
                        <source src="<?= $video_src ?>" type="video/mp4">
                        <source src="<?= $video_src ?>" type="video/quicktime">
                        <source src="<?= $video_src ?>" type="video/webm">
                        <source src="<?= $video_src ?>" type="video/ogg">
                        <source src="<?= $video_src ?>" type="video/x-msvideo">
                        <source src="<?= $video_src ?>" type="video/x-ms-wmv">
                        
                        <!-- フォールバックメッセージ -->
                        お使いのブラウザは動画の再生に対応していません。
                    </video>
                    <div class="video-overlay">
                        <button class="play-button"><i class="fas fa-play"></i></button>
                    </div>
                </div>
            </div>

            <section class="timeline-section fade-in-section">
                <div class="section-title">
                    <h2>ふたりの物語</h2>
                    <div class="title-underline"></div>
                </div>
                <div class="timeline fade-sequence">
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-date">
                            <span>遠距離から始まったふたりの旅</span>
                        </div>
                        <div class="timeline-content">
                            <p>遠く離れてなかなか会えない日々が続いたけれど、だからこそ会える時間を大切にしてきました。
                            車やバイクで旅行に出かけたり、横浜や九州を訪れたりして、ふたりでたくさんの思い出を作ってきました。<br>
                            </p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-date">
                            <span>共に歩んだ4年間</span>
                        </div>
                        <div class="timeline-content">
                            <p>みなとみらいで夜景を楽しんだり、<br>
                            ユニバーサルスタジオやディズニーで笑いあったり、<br>
                            愛猫「まりんちゃん」と運命的な出会いをしたり…。<br>
                            お互いの気持ちに寄り添いながら、絆を深めました。</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-date">
                            <span>夫婦としての新たなスタート</span>
                        </div>
                        <div class="timeline-content">
                            <p>4年間の交際を経て、2024年4月30日に入籍いたしました。<br>
                            これからは夫婦として、お互いを支え合いながら、<br>
                            新しい景色をたくさん見ていきたいと思います。</p>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-date">
                            <span>結婚式へのお誘い</span>
                        </div>
                        <div class="timeline-content">
                            <p>2025年4月30日<br>
                            大切な皆様と一緒に、新しい物語の始まりをお祝いできれば幸いです。</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="wedding-info fade-in-section">
                <div class="section-title">
                    <h2>結婚式のご案内</h2>
                    <div class="title-underline"></div>
                </div>
                
                <div class="info-card fade-sequence">
                    <div class="info-item date-time">
                        <div class="info-icon">
                            <i class="far fa-calendar-alt"></i>
                        </div>
                        <div class="info-details">
                            <h3>日時</h3>
                            <p><?= $datetime_info['date'] ?></p>
                            <p>挙式　　<?= $datetime_info['ceremony_time'] ?></p>
                            <p>披露宴　<?= $datetime_info['reception_time'] ?></p>
                            <?php if ($group_id): ?>
                            
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-item venue">
                        <div class="info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="info-details">
                            <h3>会場</h3>
                            <p><?= nl2br(htmlspecialchars($venue_info['name'])) ?></p>
                            <p class="address"><?= nl2br(htmlspecialchars($venue_info['address'])) ?></p>
                        </div>
                    </div>
                    <div class="info-item dress-code">
                        <div class="info-icon">
                            <i class="fas fa-tshirt"></i>
                        </div>
                        <div class="info-details">
                            <h3>ドレスコード</h3>
                            <p>自由 </p>
                        </div>
                    </div>
                    
                    <!-- 交通・宿泊情報へのリンク -->
                    <div class="info-item travel-accommodation">
                        <div class="info-icon">
                            <i class="fas fa-hotel"></i>
                        </div>
                        <div class="info-details">
                            <h3>交通・宿泊</h3>
                            <p>会場へのアクセスと宿泊施設</p>
                            <a href="<?= $group_id ? "travel.php?group=" . urlencode($group_id) : "travel.php" ?>" class="info-link">
                                詳細を見る <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Googleマップを表示 -->
                    <?php if (!empty($venue_info['map_url'])): ?>
                    <div class="map-container">
                        <iframe 
                            src="<?= htmlspecialchars($venue_info['map_url']) ?>" 
                            width="100%" 
                            height="400" 
                            style="border:0;" 
                            allowfullscreen="" 
                            loading="lazy" 
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Google Mapボタン -->
                    <?php if (!empty($venue_info['map_link'])): ?>
                    <div class="map-link-container" style="text-align: center; margin-top: 15px;">
                        <a href="<?= htmlspecialchars($venue_info['map_link']) ?>" target="_blank" class="map-link-button">
                            <i class="fas fa-directions"></i> Google マップで見る
                        </a>
                    </div>
                    
                    <style>
                    .map-link-button {
                        display: inline-block;
                        padding: 0.7rem 1.5rem;
                        margin-top: 0.5rem;
                        color: #fff;
                        background-color: #4285F4;
                        border-radius: 4px;
                        text-decoration: none;
                        transition: background-color 0.3s, transform 0.2s;
                        font-weight: 500;
                        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                    }

                    .map-link-button:hover {
                        background-color: #3367D6;
                        transform: translateY(-2px);
                        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                    }
                    
                    .map-container {
                        margin-top: 20px;
                        border-radius: 8px;
                        overflow: hidden;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    }
                    </style>
                    <?php endif; ?>
                    
                    <div class="rsvp-button-container">
                        <a href="#rsvp" class="rsvp-button"><i class="fas fa-envelope"></i> ご出欠を回答する</a>
                    </div>
                    
                    <!-- QRコードチェックイン情報 -->
                    <?php if ($group_id): ?>
                    <?php 
                    // 出席回答を確認
                    $attending = false;
                    try {
                        $stmt = $pdo->prepare("
                            SELECT attending FROM responses 
                            WHERE guest_id = ? 
                            ORDER BY created_at DESC 
                            LIMIT 1
                        ");
                        $stmt->execute([$guest_info['id']]);
                        $attending_result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $attending = ($attending_result && $attending_result['attending'] == 1);
                    } catch (PDOException $e) {
                        // エラー処理（静かに失敗）
                        if ($debug_mode) {
                            error_log("出席回答確認エラー: " . $e->getMessage());
                        }
                    }
                    
                    if ($attending): 
                    ?>
                    <div class="qr-checkin-info">
                        <h3><i class="fas fa-qrcode"></i> QRコードチェックイン</h3>
                        <p>結婚式当日の受付をスムーズに行うため、QRコードをご用意しています。</p>
                        <a href="<?= "my_qrcode.php?group=" . urlencode($group_id) ?>" class="qr-button">
                            マイQRコードを表示 <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                    
                    <style>
                    .qr-checkin-info {
                        margin-top: 30px;
                        padding: 20px;
                        background-color: #f0f8ff;
                        border-radius: 10px;
                        text-align: center;
                        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
                        border-left: 4px solid #4285F4;
                    }
                    
                    .qr-checkin-info h3 {
                        color: #4285F4;
                        margin-top: 0;
                    }
                    
                    .qr-checkin-info p {
                        margin: 10px 0;
                        color: #555;
                    }
                    
                    .qr-button {
                        display: inline-block;
                        padding: 10px 20px;
                        margin-top: 10px;
                        color: #fff;
                        background-color: #4285F4;
                        border-radius: 50px;
                        text-decoration: none;
                        transition: all 0.3s;
                        font-weight: 500;
                    }
                    
                    .qr-button:hover {
                        background-color: #3367D6;
                        transform: translateY(-2px);
                        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                    }
                    </style>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- 結婚式タイムスケジュール -->
            <section class="schedule-section fade-in-section">
                <div class="section-title">
                    <h2>当日のスケジュール</h2>
                    <div class="title-underline"></div>
                </div>
                <div class="schedule-intro">
                    <p>結婚式当日の流れをご案内します</p>
                </div>
                <div class="schedule-container fade-sequence">
                    <?php
                    try {
                        // スケジュール情報を取得
                        if ($group_id && isset($guest_info['id'])) {
                            // グループIDがある場合、グループタイプIDとグループタイプ名を取得
                            $stmt = $pdo->prepare("
                                SELECT g.group_type_id, gt.type_name
                                FROM guests g
                                JOIN group_types gt ON g.group_type_id = gt.id
                                WHERE g.id = ?
                            ");
                            $stmt->execute([$guest_info['id']]);
                            $group_info = $stmt->fetch();
                            $group_type_id = $group_info['group_type_id'];
                            $group_type_name = $group_info['type_name'];
                            
                            // グループタイプIDに対応するスケジュールと全員向けスケジュールを取得
                            $stmt = $pdo->prepare("
                                SELECT s.*, gt.type_name as group_type_name
                                FROM schedule s
                                LEFT JOIN group_types gt ON s.for_group_type_id = gt.id
                                WHERE s.for_group_type_id IS NULL OR s.for_group_type_id = ? 
                                ORDER BY s.event_time ASC
                            ");
                            $stmt->execute([$group_type_id]);
                        } else {
                            // グループIDがない場合は全員向けスケジュールのみ取得
                            $stmt = $pdo->query("
                                SELECT s.*, gt.type_name as group_type_name
                                FROM schedule s
                                LEFT JOIN group_types gt ON s.for_group_type_id = gt.id
                                WHERE s.for_group_type_id IS NULL 
                                ORDER BY s.event_time ASC
                            ");
                        }
                        
                        $events = $stmt->fetchAll();
                        
                        if (!empty($events)) {
                            foreach ($events as $event) {
                                // グループ固有のイベントかどうかをチェック
                                $isGroupSpecific = !empty($event['for_group_type_id']);
                                $groupSpecificClass = $isGroupSpecific ? 'group-specific-event' : '';
                                
                                echo '<div class="schedule-item ' . $groupSpecificClass . '">';
                                echo '<div class="schedule-time">' . date("H:i", strtotime($event['event_time'])) . '</div>';
                                echo '<div class="schedule-content">';
                                
                                // グループ固有のイベントの場合、特別なアイコンと表示を追加
                                if ($isGroupSpecific) {
                                    echo '<div class="group-specific-label">';
                                    echo '<i class="fas fa-users"></i> ' . htmlspecialchars($event['for_group_type'] ?? $guest_info['group_name']) . '向け';
                                    echo '</div>';
                                }
                                
                                echo '<h3>' . htmlspecialchars($event['event_name']) . '</h3>';
                                echo '<p>' . nl2br(htmlspecialchars($event['event_description'] ?? '')) . '</p>';
                                
                                // 場所情報があれば表示
                                if (!empty($event['location'])) {
                                    echo '<div class="schedule-location">';
                                    echo '<i class="fas fa-map-marker-alt"></i> ' . htmlspecialchars($event['location']);
                                    echo '</div>';
                                }
                                
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<p class="no-schedule">スケジュール情報は近日公開予定です。</p>';
                        }
                    } catch (PDOException $e) {
                        if ($debug_mode) {
                            echo "データベースエラー: " . $e->getMessage();
                        } else {
                            echo '<p class="no-schedule">スケジュール情報を読み込めませんでした。</p>';
                        }
                    }
                    ?>
                </div>
                
                <style>
                
                .group-specific-label {
                    display: inline-block;
                    padding: 4px 12px;
                    margin-bottom: 8px;
                    font-size: 0.85rem;
                    color: #fff;
                    background: linear-gradient(135deg, #6b9d61, #8bc34a);
                    border-radius: 20px;
                    box-shadow: 0 2px 5px rgba(107, 157, 97, 0.3);
                    position: relative;
                    overflow: hidden;
                }
                
                .group-specific-label::after {
                    content: '';
                    position: absolute;
                    top: -50%;
                    left: -50%;
                    width: 200%;
                    height: 200%;
                    background: linear-gradient(transparent, rgba(255, 255, 255, 0.3), transparent);
                    transform: rotate(30deg);
                    animation: sheen 3s infinite;
                }
                
                .group-specific-label i {
                    margin-right: 5px;
                }
                
                .schedule-location {
                    display: inline-block;
                    margin-top: 8px;
                    padding: 3px 10px;
                    font-size: 0.9rem;
                    color: #795548;
                    background-color: #f5f5f5;
                    border-radius: 15px;
                }
                
                .schedule-location i {
                    color: #8d6e63;
                    margin-right: 5px;
                }
                
                @keyframes sheen {
                    0%, 100% {
                        transform: translateX(-100%) rotate(30deg);
                    }
                    50% {
                        transform: translateX(100%) rotate(30deg);
                    }
                }
                </style>
            </section>

            <!-- 備考・お願い -->
            <section class="notes-section fade-in-section">
                <div class="section-title">
                    <h2>備考・お願い</h2>
                    <div class="title-underline"></div>
                </div>
                <div class="notes-container fade-sequence">
                    <?php
                    try {
                        // 備考・お願い情報を取得
                        $stmt = $pdo->query("SELECT * FROM remarks ORDER BY display_order ASC");
                        $remarks = $stmt->fetchAll();
                        
                        if (!empty($remarks)) {
                            echo '<ul class="notes-list">';
                            foreach ($remarks as $remark) {
                                // グループ情報のプレースホルダーを置換
                                $content = $remark['content'];
                                
                                if ($group_id && isset($guest_info)) {
                                    // グループ名プレースホルダー
                                    $content = str_replace('{group_name}', htmlspecialchars($guest_info['group_name']), $content);
                                    
                                    // グループ名 + 「の場合は」プレースホルダー
                                    $content = str_replace('{group_name_case}', htmlspecialchars($guest_info['group_name']) . 'の場合は', $content);
                                    
                                    // 集合時間プレースホルダー
                                    if (isset($guest_info['arrival_time']) && !empty($guest_info['arrival_time'])) {
                                        $arrival_time = $guest_info['arrival_time'];
                                        $content = str_replace('{arrival_time}', $arrival_time, $content);
                                        
                                        // 集合時間の10分前を計算
                                        if (strpos($content, '{arrival_time_minus10}') !== false) {
                                            $time_obj = new DateTime($arrival_time);
                                            $time_obj->modify('-10 minutes');
                                            $arrival_time_minus10 = $time_obj->format('H:i');
                                            $content = str_replace('{arrival_time_minus10}', $arrival_time_minus10, $content);
                                        }
                                    }
                                } else {
                                    // グループ情報がない場合はデフォルト値
                                    $content = str_replace('{arrival_time}', '11:30', $content);
                                    $content = str_replace('{arrival_time_minus10}', '11:20', $content);
                                    $content = str_replace('{group_name}', 'ゲスト', $content);
                                    $content = str_replace('{group_name_case}', 'ゲストの場合は', $content);
                                }
                                
                                echo '<li class="note-item">';
                                echo '<i class="fas fa-leaf note-icon"></i>';
                                echo '<div class="note-content">' . nl2br(htmlspecialchars($content)) . '</div>';
                                echo '</li>';
                            }
                            echo '</ul>';
                            
                            // 新郎新婦情報
                            echo '<div class="couple-info">';
                            echo '<p>新郎: 村岡 翔</p>';
                            echo '<p>新婦: 磯野 あかね</p>';
                            echo '</div>';
                        }
                    } catch (PDOException $e) {
                        if ($debug_mode) {
                            echo "データベースエラー: " . $e->getMessage();
                        }
                    }
                    ?>
                </div>
            </section>

            <!-- RSVP -->
            <section id="rsvp" class="rsvp-section">
                <div class="content-container">
                    <h2>ご出欠のお返事</h2>
                    
                    <?php if ($already_responded): ?>
                    <div class="response-complete">
                        <p>ご回答いただきありがとうございます。</p>
                        <p>すでに出欠のご回答をいただいております。</p>
                        <p>変更がある場合は、お問い合わせよりご連絡ください。</p>
                    </div>
                    <?php else: ?>
                    <form action="process_rsvp.php" method="post" id="rsvp-form">
                        <!-- フォームフィールド -->
                        <?php if ($group_id && !empty($guest_info['id'])): ?>
                        <input type="hidden" name="guest_id" value="<?= $guest_info['id'] ?>">
                        <input type="hidden" name="group_id" value="<?= $group_id ?>">
                        <?php endif; ?>
                        
                        <!-- 名前フィールド -->
                        <div class="form-group">
                            <label for="name">お名前</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        
                        <!-- メールアドレスフィールド -->
                        <div class="form-group">
                            <label for="email">メールアドレス</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <!-- 出欠選択 -->
                        <div class="form-group">
                            <label>ご出欠</label>
                            <div class="radio-group">
                                <input type="radio" id="attend-yes" name="attending" value="1" checked required>
                                <label for="attend-yes">出席します</label>
                                <input type="radio" id="attend-no" name="attending" value="0">
                                <label for="attend-no">欠席します</label>
                            </div>
                        </div>
                        <div class="form-group attendance-details">
                            <label for="guests">同伴者人数</label>
                            <select id="guests" name="companions">
                                <option value="0">0名</option>
                                <?php for ($i = 1; $i <= $guest_info['max_companions']; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?>名</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div id="companions-container" style="display: none;">
                            <div class="form-group">
                                <h4>同伴者情報</h4>
                                <p class="companions-note">座席表作成のため、同伴者様のお名前をご記入ください。</p>
                            </div>
                            <div id="companions-fields">
                                <!-- 同伴者フィールドがJSで動的に追加されます -->
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="message">メッセージ</label>
                            <textarea id="message" name="message" rows="4" placeholder="お二人へのお祝いのメッセージなど"></textarea>
                        </div>
                        
                        <div class="form-group" id="dietary-group">
                            <label for="dietary">アレルギー・食事制限等</label>
                            <textarea id="dietary" name="dietary" rows="2" placeholder="アレルギーや食事制限などがあればご記入ください"></textarea>
                        </div>
                        
                        <!-- reCAPTCHA -->
                        <div class="g-recaptcha" data-sitekey="6LfXwg8rAAAAAA-cI9mQ5Z3YJO7PKCeAuJXNK4vW" data-callback="enableSubmit"></div>
                        
                        <div class="form-actions">
                            <button type="submit" id="submit-btn" disabled>送信する</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </section>

            <section class="countdown-section fade-in-section">
                <div class="countdown-container">
                    <h2>結婚式まであと</h2>
                    <div class="countdown-timer" data-wedding-date="<?= date('Y-m-d', strtotime(str_replace(['年', '月', '日'], ['-', '-', ''], $datetime_info['date']))) ?>" data-wedding-time="<?= $datetime_info['time'] ?>">
                        <div class="countdown-item">
                            <span id="countdown-days">--</span>
                            <span class="countdown-label">Days</span>
                        </div>
                        <div class="countdown-item">
                            <span id="countdown-hours">--</span>
                            <span class="countdown-label">Hours</span>
                        </div>
                        <div class="countdown-item">
                            <span id="countdown-minutes">--</span>
                            <span class="countdown-label">Minutes</span>
                        </div>
                        <div class="countdown-item">
                            <span id="countdown-seconds">--</span>
                            <span class="countdown-label">Seconds</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="gallery fade-in-section">
                <div class="section-title">
                    <h2>ふたりの思い出</h2>
                    <div class="title-underline"></div>
                </div>
                <div class="gallery-intro">
                    <p>二人の思い出の写真をシェアします</p>
                </div>
                <div class="photos fade-sequence">
                    <?php
                    try {
                        // 承認済みの写真を取得
                        $stmt = $pdo->query("SELECT * FROM photo_gallery WHERE is_approved = 1 ORDER BY upload_date DESC LIMIT 6");
                        $gallery_photos = $stmt->fetchAll();
                        
                        if (!empty($gallery_photos)) {
                            foreach ($gallery_photos as $index => $photo) {
                                $main_class = ($index === 0) ? 'main-photo' : '';
                                $loading = ($index <= 1) ? 'eager' : 'lazy'; // 最初の2枚は即時読み込み、それ以降は遅延読み込み
                                $timestamp = time(); // キャッシュ回避用タイムスタンプ
                                echo '<div class="photo-item ' . $main_class . '">';
                                if ($index <= 1) {
                                    echo '<img src="uploads/photos/' . htmlspecialchars($photo['filename']) . '?v=' . $timestamp . '" alt="' . htmlspecialchars($photo['title']) . '" loading="' . $loading . '" width="300" height="200">';
                                } else {
                                    echo '<img class="lazy-load" data-src="uploads/photos/' . htmlspecialchars($photo['filename']) . '?v=' . $timestamp . '" alt="' . htmlspecialchars($photo['title']) . '" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=" loading="lazy" width="300" height="200">';
                                }
                                echo '</div>';
                            }
                        } else {
                            // データベースに写真がない場合はデフォルト写真を表示
                            echo '<div class="photo-item main-photo">';
                            echo '<img src="images/couple1.jpg" alt="翔とあかねの写真" width="300" height="200">';
                            echo '</div>';
                            echo '<div class="photo-item">';
                            echo '<img src="images/placeholders/couple2.jpg" alt="翔とあかねの写真2" loading="lazy" width="300" height="200">';
                            echo '</div>';
                            echo '<div class="photo-item">';
                            echo '<img class="lazy-load" data-src="images/placeholders/couple3.jpg" alt="翔とあかねの写真3" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=" loading="lazy" width="300" height="200">';
                            echo '</div>';
                            echo '<div class="photo-item">';
                            echo '<img class="lazy-load" data-src="images/placeholders/couple4.jpg" alt="翔とあかねの写真4" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=" loading="lazy" width="300" height="200">';
                            echo '</div>';
                            echo '<div class="photo-item">';
                            echo '<img class="lazy-load" data-src="images/placeholders/couple5.jpg" alt="翔とあかねの写真5" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=" loading="lazy" width="300" height="200">';
                            echo '</div>';
                            echo '<div class="photo-item">';
                            echo '<img class="lazy-load" data-src="images/placeholders/couple6.jpg" alt="翔とあかねの写真6" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=" loading="lazy" width="300" height="200">';
                            echo '</div>';
                        }
                    } catch (PDOException $e) {
                        if ($debug_mode) {
                            echo "データベースエラー: " . $e->getMessage();
                        } else {
                            // エラーの場合もデフォルト写真を表示
                            echo '<div class="photo-item main-photo">';
                            echo '<img src="images/couple1.jpg" alt="翔とあかねの写真" width="300" height="200">';
                            echo '</div>';
                            echo '<div class="photo-item">';
                            echo '<img src="images/placeholders/couple2.jpg" alt="翔とあかねの写真2" loading="lazy" width="300" height="200">';
                            echo '</div>';
                            // 追加の写真
                        }
                    }
                    ?>
                </div>
            </section>

            <section class="message-section fade-in-section">
                <div class="section-title">
                    <h2>新郎新婦からのメッセージ</h2>
                    <div class="title-underline"></div>
                </div>
                <div class="message-card scale-in">
                    <div class="message-icon">❝</div>
                    <p class="message-text">私たちにとって大切な皆様に、人生の新しい門出をお祝いいただけることを心から嬉しく思います。当日は「森の中」をテーマに、自然に囲まれた温かい雰囲気の中で、皆様と素敵な時間を過ごせることを楽しみにしています。ぜひお気軽な服装でお越しください。</p>
                    <p class="message-signature">翔 & あかね</p>
                </div>
            </section>

            <!-- よくある質問（FAQ）セクション -->
            <section class="faq-section fade-in-section">
                <div class="section-title">
                    <h2>よくある質問</h2>
                    <div class="title-underline"></div>
                </div>
                <div class="faq-intro">
                    <p>ゲストの皆様からよく寄せられる質問をまとめました</p>
                </div>
                <div class="faq-container fade-sequence">
                    <?php
                    try {
                        // FAQを取得
                        $stmt = $pdo->query("SELECT * FROM faq WHERE is_visible = 1 ORDER BY display_order ASC");
                        $faqs = $stmt->fetchAll();
                        
                        if (!empty($faqs)) {
                            foreach ($faqs as $faq) {
                                // グループIDがある場合、FAQのリンクにグループIDを含める
                                if ($group_id) {
                                    $faq['answer'] = str_replace('href="travel.php"', 'href="travel.php?group=' . urlencode($group_id) . '"', $faq['answer']);
                                }
                                
                                echo '<div class="faq-item">';
                                echo '<div class="faq-question">';
                                echo '<h3><i class="fas fa-question-circle"></i> ' . htmlspecialchars($faq['question']) . '</h3>';
                                echo '<span class="faq-toggle"><i class="fas fa-chevron-down"></i></span>';
                                echo '</div>';
                                echo '<div class="faq-answer">';
                                echo '<p>' . nl2br($faq['answer']) . '</p>';
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<p class="no-faqs">FAQ情報は近日公開予定です。</p>';
                        }
                    } catch (PDOException $e) {
                        if ($debug_mode) {
                            echo "データベースエラー: " . $e->getMessage();
                        } else {
                            echo '<p class="no-faqs">FAQ情報を読み込めませんでした。</p>';
                        }
                    }
                    ?>
                </div>
            </section>

            <!-- ゲストブックへのリンク -->
            <div class="guestbook-link-container fade-in-section">
                <a href="guestbook.php<?= $group_id ? '?group=' . urlencode($group_id) : '' ?>" class="guestbook-link-button">
                    <i class="fas fa-book-open"></i> ゲストブックを見る・書く
                </a>
                <p class="guestbook-description">お二人へのお祝いメッセージを残したり、他のゲストからのメッセージを見ることができます。</p>
            </div>
        </div>

        <!-- サイト名の説明を一番下に移動 -->
        <div class="soprano-note-container fade-in-section">
            <div class="soprano-note">
                <div class="note-content">
                    <div class="note-inner">
                        <div class="sneeze-icon-wrapper">
                            <span class="sneeze-icon">
                                <span class="soprano-title-char">ソ</span>
                                <span class="soprano-title-char">プ</span>
                                <span class="soprano-title-char">ラ</span>
                                <span class="soprano-title-char">ノ</span>
                                <span class="soprano-title-char">は</span>
                                <span class="soprano-title-char">く</span>
                                <span class="soprano-title-char">し</span>
                                <span class="soprano-title-char">ょ</span>
                                <span class="soprano-title-char">ん</span>
                                <span class="soprano-title-char">！</span>
                            </span>
                            <div class="note-music">
                                <i class="fas fa-music note1"></i>
                                <i class="fas fa-music note2"></i>
                                <i class="fas fa-music note3"></i>
                            </div>
                        </div>
                        <div class="note-text">
                            <p><small>※ちなみに「sopranohaction.fun」は、<strong>翔（しょう）</strong>の高音域のくしゃみ「ハクション！」と英語の"soprano"（ソプラノ）を組み合わせた造語です</small></p>
                        </div>
                    </div>
                    <div class="note-decoration left"></div>
                    <div class="note-decoration right"></div>
                </div>
            </div>
        </div>

        <footer class="fade-in-section">
            <div class="footer-decoration">
                <div class="leaf-decoration left"></div>
                <div class="heart-container">
                    <i class="fas fa-heart"></i>
                    <i class="fas fa-heart"></i>
                    <i class="fas fa-heart"></i>
                </div>
                <div class="leaf-decoration right"></div>
            </div>
            <p>&copy; 2023 翔 & あかね - Our Wedding</p>
            <p class="domain">sopranohaction.fun</p>
        </footer>
    </div>

    <style>
    .travel-accommodation-link {
        margin: 25px 0;
        text-align: center;
        padding: 15px;
        background-color: rgba(255, 255, 255, 0.7);
        border-radius: 8px;
        border: 1px dashed var(--primary-color);
    }

    .travel-link-button {
        display: inline-block;
        padding: 10px 20px;
        background-color: var(--primary-color);
        color: white;
        text-decoration: none;
        border-radius: 30px;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
    }

    .travel-link-button:hover {
        background-color: var(--accent-dark);
        transform: translateY(-2px);
        box-shadow: 0 5px 10px rgba(0, 0, 0, 0.15);
    }

    .travel-link-button i {
        margin-right: 5px;
    }

    .travel-link-description {
        margin-top: 10px;
        font-size: 0.9rem;
        color: #555;
    }

    .info-item .info-link {
        display: inline-block;
        margin-top: 8px;
        color: var(--accent-dark);
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border-bottom: 1px dashed var(--accent-light);
        padding-bottom: 2px;
    }

    .info-item .info-link:hover {
        color: var(--accent-light);
    }

    .info-item .info-link i {
        margin-left: 5px;
        font-size: 0.8rem;
        transition: transform 0.3s ease;
    }

    .info-item .info-link:hover i {
        transform: translateX(3px);
    }
    </style>

    <!-- スクロールアニメーション用のJavaScript -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Intersection Observerの設定
        const options = {
            root: null, // ビューポートをルートとして使用
            rootMargin: '0px', // マージンなし
            threshold: 0.1 // 要素の10%が見えたときに実行
        };
        
        // フェードイン要素を監視するオブザーバー
        const fadeObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target); // 一度表示されたら監視を解除
                }
            });
        }, options);
        
        // すべてのフェードイン要素を監視対象に追加
        document.querySelectorAll('.fade-in-section, .fade-sequence, .scale-in, .slide-in-left, .slide-in-right').forEach(el => {
            fadeObserver.observe(el);
        });
    });
    </script>
</body>
</html> 