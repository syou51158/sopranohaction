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
                $redirectUrl = $site_url . 'index.php?group=' . urlencode($group_id) . '&checkin_complete=1&t=' . time();
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

// チェックイン完了フラグの強制
$force_refresh = false;
if ($checkin_complete) {
    // 強制更新タイムスタンプがなければ追加
    if (!isset($_GET['t'])) {
        $redirectUrl = $site_url . 'index.php?group=' . urlencode($group_id) . '&checkin_complete=1&t=' . time();
        $force_refresh = true;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $site_name ?></title>
    
    <!-- OGP（Open Graph Protocol）タグ - SNS共有表示用 -->
    <?php 
    // OGP画像のタイムスタンプを取得（キャッシュ対策）
    $ogp_image_path = 'images/ogp-image.jpg';
    $ogp_timestamp = file_exists($ogp_image_path) ? '?' . filemtime($ogp_image_path) : '';
    ?>
    <meta property="og:title" content="<?= $site_name ?>">
    <meta property="og:description" content="<?= isset($guest_info['group_name']) ? htmlspecialchars($guest_info['group_name']) . 'さん、ご招待状です。' : '結婚式の招待状' ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $site_url . (isset($_GET['group']) ? '?group=' . urlencode($_GET['group']) : '') ?>">
    <meta property="og:image" content="<?= $site_url ?>images/ogp-image.jpg<?= $ogp_timestamp ?>">
    <meta property="og:image:secure_url" content="<?= $site_url ?>images/ogp-image.jpg<?= $ogp_timestamp ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:site_name" content="<?= $site_name ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $site_name ?>">
    <meta name="twitter:description" content="<?= isset($guest_info['group_name']) ? htmlspecialchars($guest_info['group_name']) . 'さん、ご招待状です。' : '結婚式の招待状' ?>">
    <meta name="twitter:image" content="<?= $site_url ?>images/ogp-image.jpg<?= $ogp_timestamp ?>">
    
    <!-- チェックイン後のキャッシュ対策 -->
    <?php if ($checkin_complete): ?>
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <?php endif; ?>
    
    <?php if ($force_refresh): ?>
    <meta http-equiv="refresh" content="0;url=<?= $redirectUrl ?>">
    <?php endif; ?>
    
    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" href="images/favicon.png">
    
    <!-- Favicon for various platforms -->
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="shortcut icon" href="images/favicon.ico">
    
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
    // reCAPTCHAが読み込まれて実行されたときに送信ボタンを有効化する関数
    function enableSubmit() {
        document.getElementById("submit-btn").disabled = false;
    }

    function onSubmitForm(token) {
        document.getElementById("rsvp-form").submit();
    }

    // フォーム送信時にreCAPTCHA v3を実行
    document.addEventListener('DOMContentLoaded', function() {
        const rsvpForm = document.getElementById('rsvp-form');
        if (rsvpForm) {
            rsvpForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // 送信ボタンを「送信中...」に変更
                const submitBtn = document.getElementById('submit-btn');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 送信中...';
                submitBtn.disabled = true;
                
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
        
        // 同伴者人数が変更されたときの処理
        const guestsSelect = document.getElementById('guests');
        const companionsContainer = document.getElementById('companions-container');
        const companionsFields = document.getElementById('companions-fields');
        
        if (guestsSelect && companionsContainer && companionsFields) {
            guestsSelect.addEventListener('change', function() {
                const count = parseInt(this.value);
                
                // 既存のフィールドをクリア
                companionsFields.innerHTML = '';
                
                if (count > 0) {
                    companionsContainer.style.display = 'block';
                    
                    // 選択された人数分のフィールドを追加
                    for (let i = 1; i <= count; i++) {
                        const fieldHtml = `
                            <div class="companion-field">
                                <h4 class="companion-title">同伴者 ${i}</h4>
                                <div class="form-group">
                                    <label for="companion_name_${i}">お名前</label>
                                    <input type="text" id="companion_name_${i}" name="companion_name[]" required>
                                </div>
                                <div class="form-group">
                                    <label for="companion_age_${i}">年齢区分</label>
                                    <select id="companion_age_${i}" name="companion_age[]">
                                        <option value="adult">大人</option>
                                        <option value="child">子供（小学生〜中学生）</option>
                                        <option value="infant">幼児（未就学児）</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="companion_dietary_${i}"><strong>同伴者 ${i}</strong> の食事に関するご要望（アレルギーなど）</label>
                                    <textarea id="companion_dietary_${i}" name="companion_dietary[]" rows="2" placeholder="この同伴者のアレルギーや食事制限をご記入ください"></textarea>
                                </div>
                            </div>
                        `;
                        companionsFields.innerHTML += fieldHtml;
                    }
                } else {
                    companionsContainer.style.display = 'none';
                }
            });
        }
        
        // ページ読み込み時にreCAPTCHAを実行して送信ボタンを有効化
        grecaptcha.ready(function() {
            grecaptcha.execute('6LfXwg8rAAAAAO8tgbD74yqTFHK9ZW6Ns18M8GpF', {action: 'homepage'}).then(function(token) {
                enableSubmit();
            });
        });
        
        // 出席・欠席の選択に応じて関連フィールドの表示/非表示を切り替え
        const attendYes = document.getElementById('attend-yes');
        const attendNo = document.getElementById('attend-no');
        const attendanceDetails = document.querySelector('.attendance-details');
        const dietaryGroup = document.getElementById('dietary-group');
        
        if (attendYes && attendNo && attendanceDetails && dietaryGroup) {
            // 初期状態の設定
            if (attendYes.checked) {
                attendanceDetails.style.display = 'block';
                dietaryGroup.style.display = 'block';
            } else {
                attendanceDetails.style.display = 'none';
                dietaryGroup.style.display = 'none';
            }
            
            // 出席選択時
            attendYes.addEventListener('change', function() {
                if (this.checked) {
                    attendanceDetails.style.display = 'block';
                    dietaryGroup.style.display = 'block';
                }
            });
            
            // 欠席選択時
            attendNo.addEventListener('change', function() {
                if (this.checked) {
                    attendanceDetails.style.display = 'none';
                    dietaryGroup.style.display = 'none';
                    // 同伴者数を0にリセット
                    if (guestsSelect) {
                        guestsSelect.value = '0';
                        // 同伴者フィールドを非表示
                        if (companionsContainer) {
                            companionsContainer.style.display = 'none';
                        }
                    }
                }
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
            pointer-events: none; /* スクロールイベントを妨げないように */
        }
        
        .fade-in-section.is-visible {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto; /* 表示後は通常の操作を許可 */
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
        
        /* RSVPフォームのスタイル */
        .rsvp-section {
            padding: 40px 0;
            margin-top: 30px;
            background-color: #f8f4e6;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }
        
        .rsvp-section h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #795548;
        }
        
        #rsvp-form {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            color: #333;
            transition: border-color 0.3s;
        }
        
        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #8d6e63;
            outline: none;
            box-shadow: 0 0 5px rgba(141, 110, 99, 0.3);
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }
        
        .radio-group input[type="radio"] {
            margin-right: 5px;
        }
        
        .companions-note {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
        
        .companion-field {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
        }
        
        .form-actions {
            text-align: center;
            margin-top: 30px;
        }
        
        #submit-btn {
            padding: 12px 30px;
            background-color: #8d6e63;
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        }
        
        #submit-btn:hover {
            background-color: #6d4c41;
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.15);
        }
        
        #submit-btn:disabled {
            background-color: #cccccc;
            color: #666666;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* Google reCAPTCHAのバッジを非表示にする代わりに別途プライバシーポリシーへのリンクを表示 */
        .grecaptcha-badge {
            visibility: hidden;
        }
        
        .recaptcha-notice {
            font-size: 0.75rem;
            color: #777;
            text-align: center;
            margin-top: 15px;
        }
    </style>
    
    <!-- スクリプトの遅延読み込み -->
    <script src="js/envelope.js" defer></script>
    <script src="js/main.js" defer></script>
    
    <!-- モバイル最適化 -->
    <meta name="theme-color" content="#f8f4e6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <style>
        /* モバイルでのスクロール体験を改善 */
        html, body {
            overflow-x: hidden;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch; /* iOSでのスムーズスクロール */
        }
        
        /* アニメーションの遅延を短くして体験を改善 */
        @media (max-width: 768px) {
            .fade-in-section {
                transition: opacity 0.5s ease, transform 0.5s ease;
                transition-delay: 0.1s;
            }
            
            .fade-sequence > * {
                transition-delay: 0.1s !important;
            }
            
            /* モバイルではスクロール優先モード */
            .reduce-animation {
                opacity: 1 !important;
                transform: none !important;
                transition: none !important;
            }
        }
        
        /* スクロール中はアニメーションを一時停止 */
        .is-scrolling * {
            animation-play-state: paused !important;
            transition: none !important;
        }
        
        /* チェックイン完了時の選択画面を完全に非表示 */
        .hide-completely {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            position: absolute !important;
            z-index: -999 !important;
            pointer-events: none !important;
            height: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
        }
        
        /* 出席・欠席カードスタイルを追加 */
        .attendance-cards {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .attendance-card {
            flex: 1;
            min-width: 130px;
            position: relative;
            cursor: pointer;
        }
        
        .attendance-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .card-content {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 20px 15px;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
        }
        
        .card-icon {
            font-size: 28px;
            margin-bottom: 10px;
            color: #888;
        }
        
        .card-text {
            font-weight: 500;
            font-size: 16px;
        }
        
        /* 出席カードのスタイル */
        #attend-yes:checked + .card-content {
            border-color: #4CAF50;
            background-color: rgba(76, 175, 80, 0.1);
        }
        
        #attend-yes:checked + .card-content .card-icon,
        #attend-yes:checked + .card-content .card-text {
            color: #4CAF50;
        }
        
        /* 欠席カードのスタイル */
        #attend-no:checked + .card-content {
            border-color: #F44336;
            background-color: rgba(244, 67, 54, 0.1);
        }
        
        #attend-no:checked + .card-content .card-icon,
        #attend-no:checked + .card-content .card-text {
            color: #F44336;
        }
        
        /* ホバー効果 */
        .card-content:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.12);
        }
        
        /* モバイル最適化 */
        @media (max-width: 480px) {
            .attendance-cards {
                gap: 10px;
            }
            
            .card-content {
                padding: 15px 10px;
            }
            
            .card-icon {
                font-size: 24px;
                margin-bottom: 8px;
            }
            
            .card-text {
                font-size: 15px;
            }
        }
    </style>
    
    <script>
        // スクロール中はアニメーションを一時停止するフラグ
        let scrollTimeout;
        window.addEventListener('scroll', function() {
            document.body.classList.add('is-scrolling');
            
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function() {
                document.body.classList.remove('is-scrolling');
            }, 150); // スクロール停止後150msでアニメーション再開
        });
        
        // モバイルでのパフォーマンス最適化
        if (window.innerWidth <= 768) {
            // 連続的なスクロールを検出
            let scrollCount = 0;
            let lastScrollTime = Date.now();
            
            window.addEventListener('scroll', function() {
                const now = Date.now();
                if (now - lastScrollTime < 100) { // 連続的なスクロール検出
                    scrollCount++;
                    if (scrollCount > 5) { // 素早く5回以上スクロールを検出
                        // アニメーションを一時的に無効化
                        document.querySelectorAll('.fade-in-section, .fade-sequence > *').forEach(el => {
                            el.classList.add('reduce-animation');
                        });
                    }
                } else {
                    scrollCount = 0;
                }
                lastScrollTime = now;
            });
        }
    </script>

    <style>
        /* 追加スタイル */
        .postal-code-container {
            display: flex;
            gap: 10px;
        }
        
        .search-address-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .search-address-btn:hover {
            background-color: #45a049;
        }
        
        .hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 出欠回答フォームのJSコード
            // ... 既存のコード ...
            
            // 郵便番号から住所を検索する機能
            const postalCodeInput = document.getElementById('postal_code');
            const addressInput = document.getElementById('address');
            const searchBtn = document.getElementById('search_address_btn');
            
            if (searchBtn) {
                searchBtn.addEventListener('click', function() {
                    const postalCode = postalCodeInput.value.replace(/-/g, ''); // ハイフンを削除
                    
                    if (postalCode.length < 7) {
                        alert('7桁の郵便番号を入力してください');
                        return;
                    }
                    
                    // 検索中の表示
                    searchBtn.textContent = '検索中...';
                    searchBtn.disabled = true;
                    
                    // 郵便番号APIを使用して住所を検索
                    fetch(`https://zipcloud.ibsnet.co.jp/api/search?zipcode=${postalCode}`)
                        .then(response => response.json())
                        .then(data => {
                            searchBtn.textContent = '住所検索';
                            searchBtn.disabled = false;
                            
                            if (data.status === 200 && data.results) {
                                const result = data.results[0];
                                const address = `${result.address1}${result.address2}${result.address3}`;
                                addressInput.value = address;
                            } else {
                                alert('住所が見つかりませんでした。郵便番号を確認してください。');
                            }
                        })
                        .catch(error => {
                            searchBtn.textContent = '住所検索';
                            searchBtn.disabled = false;
                            alert('住所の検索中にエラーが発生しました。');
                            console.error('住所検索エラー:', error);
                        });
                });
                
                // 郵便番号入力欄でEnterキーを押したときも検索実行
                postalCodeInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault(); // フォーム送信を防止
                        searchBtn.click();
                    }
                });
                
                // 郵便番号の入力形式を整える（3桁入力後に自動的にハイフンを挿入）
                postalCodeInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/[^0-9]/g, ''); // 数字以外を削除
                    
                    if (value.length > 3) {
                        value = value.slice(0, 3) + '-' + value.slice(3, 7);
                    }
                    
                    // 最大8文字（7桁の数字+ハイフン）
                    if (value.length > 8) {
                        value = value.slice(0, 8);
                    }
                    
                    e.target.value = value;
                });
            }
        });
    </script>
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
    <div class="choice-screen <?php echo $checkin_complete ? 'hide-completely' : 'hide'; ?>">
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

            <?php
            // 動画プレイヤーの表示設定を取得
            $show_video_player = true; // デフォルトは表示
            try {
                $stmt = $pdo->prepare("SELECT setting_value FROM wedding_settings WHERE setting_key = 'show_video_player'");
                $stmt->execute();
                $video_setting = $stmt->fetch();
                if ($video_setting && isset($video_setting['setting_value'])) {
                    $show_video_player = (bool)$video_setting['setting_value'];
                }
            } catch (PDOException $e) {
                error_log("動画プレイヤー設定の取得エラー: " . $e->getMessage());
            }
            
            // 動画プレイヤーの表示設定がオンの場合のみ表示
            if ($show_video_player):
            ?>
            <div class="video-container fade-in-section">
                <div class="video-wrapper">
                    <?php
                    // メイン動画を取得
                    $main_video = null;
                    try {
                        $stmt = $pdo->query("SELECT * FROM video_gallery WHERE is_main_video = 1 AND is_active = 1 LIMIT 1");
                        $main_video = $stmt->fetch();
                        
                        if (!$main_video) {
                            error_log("メイン動画が見つかりません。is_main_video=1のレコードがないか、全て非アクティブになっています。");
                            // 代替としてアクティブな最新の動画を取得
                            $stmt = $pdo->query("SELECT * FROM video_gallery WHERE is_active = 1 ORDER BY upload_date DESC LIMIT 1");
                            $main_video = $stmt->fetch();
                        }
                    } catch (PDOException $e) {
                        error_log("メイン動画の取得エラー: " . $e->getMessage());
                    }

                    if ($debug_mode && $main_video) {
                        error_log("読み込まれる動画情報: ID=" . $main_video['id'] . ", ファイル名=" . $main_video['filename'] . ", メイン動画？=" . $main_video['is_main_video']);
                    }

                    // サムネイル画像のパス
                    $thumbnail_path = 'images/thumbnail.jpg'; // デフォルトのサムネイル
                    if ($main_video && $main_video['thumbnail']) {
                        $thumbnail_path = 'images/thumbnails/' . $main_video['thumbnail'];
                        if (!file_exists($thumbnail_path)) {
                            error_log("サムネイル画像が見つかりません: " . $thumbnail_path);
                            $thumbnail_path = 'images/thumbnail.jpg'; // デフォルトに戻す
                        }
                    }

                    // 動画のソースパス
                    $video_src = 'videos/wedding-invitation.mp4'; // デフォルトの動画
                    $video_exists = false;
                    
                    if ($main_video) {
                        $video_src = 'videos/' . $main_video['filename'];
                        $video_exists = file_exists($video_src);
                        if (!$video_exists) {
                            error_log("動画ファイルが見つかりません: " . $video_src);
                            $video_src = 'videos/wedding-invitation.mp4'; // デフォルトに戻す
                        }
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
                    
                    if ($debug_mode) {
                        error_log("使用する動画ファイル: " . $video_src . ", MIMEタイプ: " . $mime_type . ", 存在: " . ($video_exists ? 'はい' : 'いいえ'));
                    }
                    ?>
                    <video id="wedding-video" controls poster="<?= $thumbnail_path ?>" preload="none" playsinline>
                        <!-- プライマリソース - 判定されたMIMEタイプに基づく -->
                        <source src="<?= $video_src ?>" type="<?= $mime_type ?>">
                        
                        <!-- 異なるMIMEタイプでの代替ソース - ブラウザ互換性のため -->
                        <?php if (strtolower($file_extension) !== 'mp4'): ?>
                        <source src="<?= $video_src ?>" type="video/mp4">
                        <?php endif; ?>
                        
                        <?php if (strtolower($file_extension) !== 'mov'): ?>
                        <source src="<?= $video_src ?>" type="video/quicktime">
                        <?php endif; ?>
                        
                        <?php if (strtolower($file_extension) !== 'webm'): ?>
                        <source src="<?= $video_src ?>" type="video/webm">
                        <?php endif; ?>
                        
                        <!-- フォールバックメッセージ -->
                        お使いのブラウザは動画の再生に対応していません。
                    </video>
                    <div class="video-overlay">
                        <button class="play-button"><i class="fas fa-play"></i></button>
                    </div>
                    
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const videoPlayer = document.getElementById('wedding-video');
                        const playButton = document.querySelector('.play-button');
                        const videoOverlay = document.querySelector('.video-overlay');
                        
                        playButton.addEventListener('click', function() {
                            videoPlayer.play().catch(e => {
                                console.error('Video playback error:', e);
                                alert('動画の再生中にエラーが発生しました。別のブラウザで試すか、管理者にお問い合わせください。');
                            });
                            
                            videoOverlay.style.display = 'none';
                        });
                        
                        videoPlayer.addEventListener('play', function() {
                            videoOverlay.style.display = 'none';
                        });
                        
                        videoPlayer.addEventListener('pause', function() {
                            videoOverlay.style.display = 'flex';
                        });
                        
                        videoPlayer.addEventListener('ended', function() {
                            videoOverlay.style.display = 'flex';
                        });
                        
                        videoPlayer.addEventListener('error', function(e) {
                            console.error('Video error event:', e);
                            alert('動画の読み込み中にエラーが発生しました。管理者にお問い合わせください。');
                        });
                    });
                    </script>
                </div>
            </div>
            <?php endif; // show_video_player の条件終了 ?>

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
                        
                        <!-- 郵便番号フィールド -->
                        <div class="form-group">
                            <label for="postal_code">郵便番号</label>
                            <div class="postal-code-container">
                                <input type="text" id="postal_code" name="postal_code" placeholder="例: 123-4567" maxlength="8">
                                <button type="button" id="search_address_btn" class="search-address-btn">住所検索</button>
                            </div>
                            <p class="hint">郵便番号を入力して「住所検索」をクリックすると、住所が自動入力されます</p>
                        </div>
                        
                        <!-- 住所フィールド -->
                        <div class="form-group">
                            <label for="address">住所</label>
                            <textarea id="address" name="address" rows="2" placeholder="例: 東京都渋谷区〇〇町1-2-3 〇〇マンション101号室"></textarea>
                        </div>
                        
                        <!-- 出欠選択 -->
                        <div class="form-group">
                            <label>ご出欠</label>
                            <div class="attendance-cards">
                                <label class="attendance-card" for="attend-yes">
                                    <input type="radio" id="attend-yes" name="attending" value="1" checked required>
                                    <div class="card-content">
                                        <div class="card-icon">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="card-text">出席します</div>
                                    </div>
                                </label>
                                <label class="attendance-card" for="attend-no">
                                    <input type="radio" id="attend-no" name="attending" value="0">
                                    <div class="card-content">
                                        <div class="card-icon">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                        <div class="card-text">欠席します</div>
                                    </div>
                                </label>
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
                        <div id="companions-container" style="display: none;" class="companions-section">
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
                            <label for="dietary"><strong>ご本人様</strong>のアレルギー・食事制限等</label>
                            <textarea id="dietary" name="dietary" rows="2" placeholder="ご本人様のアレルギーや食事制限をご記入ください"></textarea>
                        </div>
                        
                        <!-- reCAPTCHA -->
                        <div class="g-recaptcha" data-sitekey="6LfXwg8rAAAAAA-cI9mQ5Z3YJO7PKCeAuJXNK4vW" data-callback="enableSubmit" data-size="invisible"></div>
                        
                        <div class="form-actions">
                            <button type="submit" id="submit-btn" disabled>送信する</button>
                        </div>
                        
                        <div class="recaptcha-notice">
                            このフォームはGoogle reCAPTCHAによって保護されています。<br>
                            <a href="https://policies.google.com/privacy" target="_blank">プライバシーポリシー</a> と 
                            <a href="https://policies.google.com/terms" target="_blank">利用規約</a> が適用されます。
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

        <!-- フッター -->
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
            <p>&copy; <?= date('Y') ?> <?= $site_name ?></p>
            <p class="domain"><?= str_replace(['http://', 'https://'], '', $site_url) ?></p>
            
            <!-- ソーシャル共有ボタン -->
            <div class="social-share" style="margin-top:15px;">
                <?php 
                // 現在のURL（グループIDを含む）
                $current_url = $site_url . (isset($_GET['group']) ? '?group=' . urlencode($_GET['group']) : '');
                // LINE共有用URL（キャッシュ回避用）
                $line_share_url = $site_url . 'line_share.php?nocache=' . time() . (isset($_GET['group']) ? '&group=' . urlencode($_GET['group']) : '');
                ?>
                <!-- LINEの専用共有リンク -->
                <a href="https://line.me/R/msg/text/?<?= urlencode($site_name . ' ' . $line_share_url) ?>" class="social-btn" style="background-color: #06C755; color: white; padding: 8px 15px; border-radius: 5px; margin: 5px; display: inline-block; text-decoration: none;" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-line"></i> <span>LINEで共有</span>
                </a>
                <!-- Twitter/X共有ボタン -->
                <a href="https://twitter.com/intent/tweet?url=<?= urlencode($current_url) ?>&text=<?= urlencode($site_name) ?>" class="social-btn" style="background-color: #1DA1F2; color: white; padding: 8px 15px; border-radius: 5px; margin: 5px; display: inline-block; text-decoration: none;" target="_blank" rel="noopener noreferrer">
                    <i class="fab fa-twitter"></i> <span>Xで共有</span>
                </a>
            </div>
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

    /* 同伴者セクションのスタイル改善 */
    .companions-section {
        margin-top: 25px;
        padding-top: 20px;
        border-top: 2px dashed #ddd;
    }
    
    .companion-field {
        background-color: #f9f9f9;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
        border-left: 3px solid #4CAF50;
    }
    
    .companion-title {
        background-color: #4CAF50;
        color: white;
        padding: 5px 10px;
        display: inline-block;
        border-radius: 4px;
        margin-top: 0;
        margin-bottom: 15px;
    }
    
    .companions-note {
        color: #666;
        font-style: italic;
        margin-bottom: 15px;
    }
    </style>

    <!-- スクロールアニメーション用のJavaScript -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // モバイルデバイスかどうかを検出
        const isMobile = window.innerWidth <= 768;
        
        // モバイルでは異なる設定を使用
        const options = {
            root: null,
            rootMargin: isMobile ? '100px' : '0px', // モバイルでは広めのマージンを設定
            threshold: isMobile ? 0.01 : 0.1 // モバイルではより早く検出
        };
        
        // フェードイン要素を監視するオブザーバー
        const fadeObserver = new IntersectionObserver((entries, observer) => {
            // モバイルでは複数要素を一度に処理して負荷を軽減
            if (isMobile && entries.length > 3) {
                entries.forEach(entry => {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            } else {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }
        }, options);
        
        // すべてのフェードイン要素を監視対象に追加
        document.querySelectorAll('.fade-in-section, .fade-sequence, .scale-in, .slide-in-left, .slide-in-right').forEach(el => {
            fadeObserver.observe(el);
        });
        
        // フェードシーケンスの各アイテムに遅延を設定
        document.querySelectorAll('.fade-sequence').forEach(sequence => {
            const items = Array.from(sequence.children);
            items.forEach((item, index) => {
                item.style.transitionDelay = `${0.2 + index * 0.1}s`;
            });
        });
        
        // スケールインアニメーション
        const scaleObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-scaled');
                    observer.unobserve(entry.target);
                }
            });
        }, options);
        
        document.querySelectorAll('.scale-in').forEach(el => {
            scaleObserver.observe(el);
        });
        
        // ページ内リンクのスムーススクロール
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    });
    </script>
</body>
</html> 