<?php
// 設定ファイルを読み込み
require_once '../../config.php';

// URLからグループIDを取得
$group_id = isset($_GET['group']) ? htmlspecialchars($_GET['group']) : null;

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

// 会場情報を取得
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
                    if (strpos($row['setting_value'], '<iframe') !== false) {
                        preg_match('/src=["\']([^"\']+)["\']/', $row['setting_value'], $matches);
                        $venue_info['map_url'] = isset($matches[1]) ? $matches[1] : '';
                    } else {
                        $venue_info['map_url'] = $row['setting_value'];
                    }
                    break;
                case 'venue_map_link':
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
        'date' => '2025年11月22日',
        'day' => '土',
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
                
                // 曜日を取得
                $date_obj = new DateTime(str_replace(['年', '月', '日'], ['-', '-', ''], $row['setting_value']));
                $weekday = $date_obj->format('w');
                $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
                $datetime_info['day'] = $weekdays[$weekday];
                
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

// 新郎新婦の名前を取得する関数
function get_couple_names() {
    global $pdo;
    $couple_names = [
        'groom' => 'Haruto',
        'bride' => 'Yui',
        'groom_ja' => '陽翔',
        'bride_ja' => '結衣'
    ];
    
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM wedding_settings WHERE setting_key IN ('groom_name', 'bride_name', 'groom_name_ja', 'bride_name_ja')");
        $stmt->execute();
        
        while ($row = $stmt->fetch()) {
            if ($row['setting_key'] == 'groom_name') {
                $couple_names['groom'] = $row['setting_value'];
            } elseif ($row['setting_key'] == 'bride_name') {
                $couple_names['bride'] = $row['setting_value'];
            } elseif ($row['setting_key'] == 'groom_name_ja') {
                $couple_names['groom_ja'] = $row['setting_value'];
            } elseif ($row['setting_key'] == 'bride_name_ja') {
                $couple_names['bride_ja'] = $row['setting_value'];
            }
        }
    } catch (PDOException $e) {
        // エラー処理（静かに失敗）
    }
    
    return $couple_names;
}

// 会場情報を取得
$venue_info = get_wedding_venue_info();

// 結婚式の日時情報を取得
$datetime_info = get_wedding_datetime();

// 新郎新婦の名前を取得
$couple_names = get_couple_names();

// 日付をフォーマット（2025.11.22形式）
$formatted_date = str_replace(['年', '月', '日'], ['.', '.', ''], $datetime_info['date']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $couple_names['groom'] ?> & <?= $couple_names['bride'] ?> - 結婚式のご案内</title>
    
    <!-- パフォーマンス最適化 -->
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    
    <!-- リソースのプリロード -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- フォント -->
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- スタイルシート -->
    <style>
        :root {
            --primary-color: #6b9d61;
            --primary-light: #8bc34a;
            --primary-dark: #5d8b4f;
            --secondary-color: #f8f4e6;
            --text-color: #333;
            --accent-color: #e0c9a6;
            --accent-dark: #c8b28a;
            --accent-light: #f4ebd9;
            --background-color: #f8f4e6;
            --border-color: #e0d5c1;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --error-color: #e74c3c;
            --success-color: #27ae60;
            --info-color: #3498db;
            --warning-color: #f39c12;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Sans JP', sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--background-color);
            overflow-x: hidden;
        }
        
        /* アイコン */
        .fa-circle-left, .fa-user-plus, .fa-question, .fa-check-on, .fa-check-off {
            margin: 0 10px;
            color: var(--primary-color);
        }
        
        /* ヘッダー */
        .header {
            text-align: center;
            padding: 50px 20px;
            background-color: #fff;
            border-bottom: 1px solid var(--border-color);
        }
        
        .header h1 {
            font-family: 'Noto Serif JP', serif;
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 400;
            color: var(--primary-dark);
        }
        
        .header p {
            font-size: 1.2rem;
            color: var(--accent-dark);
            margin-bottom: 20px;
        }
        
        /* メインコンテンツ */
        .main-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* 招待状メッセージ */
        .invitation-message {
            text-align: center;
            font-family: 'Noto Serif JP', serif;
            line-height: 2;
            margin: 30px 0;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px var(--shadow-color);
        }
        
        /* カウントダウン */
        .countdown {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background-color: var(--primary-color);
            color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px var(--shadow-color);
        }
        
        .countdown h3 {
            margin-bottom: 15px;
            font-size: 1.3rem;
            font-weight: 400;
        }
        
        .countdown-timer {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .countdown-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .countdown-number {
            font-size: 1.8rem;
            font-weight: 500;
            background-color: rgba(255,255,255,0.2);
            width: 50px;
            height: 50px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 5px;
        }
        
        .countdown-label {
            font-size: 0.8rem;
        }
        
        /* 情報カード */
        .info-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px var(--shadow-color);
            padding: 25px;
            margin: 30px 0;
        }
        
        .card-title {
            text-align: center;
            margin-bottom: 20px;
            font-family: 'Noto Serif JP', serif;
            position: relative;
            padding-bottom: 10px;
            color: var(--primary-dark);
        }
        
        .card-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 2px;
            background-color: var(--primary-color);
        }
        
        .info-group {
            margin-bottom: 20px;
        }
        
        .info-label {
            font-weight: 500;
            color: var(--primary-dark);
            margin-bottom: 5px;
            display: block;
        }
        
        /* 出欠ボタン */
        .rsvp-button {
            display: block;
            width: 100%;
            background-color: var(--primary-color);
            color: #fff;
            padding: 15px;
            text-align: center;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            margin-top: 20px;
            transition: background-color 0.3s;
        }
        
        .rsvp-button:hover {
            background-color: var(--primary-dark);
        }
        
        /* フッター */
        .footer {
            text-align: center;
            padding: 30px 20px;
            background-color: #fff;
            border-top: 1px solid var(--border-color);
            margin-top: 50px;
        }
        
        .footer p {
            font-size: 0.9rem;
            color: var(--accent-dark);
        }
        
        /* 自然をテーマにした装飾 */
        .natural-decoration {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
            z-index: -1;
        }
        
        .leaf {
            position: absolute;
            width: 30px;
            height: 30px;
            background-image: url('../../images/leaf1.png');
            background-size: contain;
            background-repeat: no-repeat;
            opacity: 0.2;
            animation: falling linear infinite;
        }
        
        @keyframes falling {
            0% {
                transform: translateY(-10%) rotate(0deg);
            }
            100% {
                transform: translateY(1000%) rotate(360deg);
            }
        }
        
        /* メディアクエリ */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .countdown-timer {
                gap: 10px;
            }
            
            .countdown-number {
                width: 40px;
                height: 40px;
                font-size: 1.5rem;
            }
        }
        
        /* 新しいナチュラルグリーンスタイル */
        .natural-green {
            background-color: #f9f7f4;
            color: #444;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .couple-names {
            font-family: 'Noto Serif JP', serif;
            text-align: center;
            padding: 60px 0 30px;
        }
        
        .couple-names h1 {
            font-size: 2.2rem;
            font-weight: normal;
            margin-bottom: 20px;
            color: var(--primary-dark);
        }
        
        .wedding-date {
            font-size: 1.2rem;
            color: #666;
        }
        
        .formal-message {
            font-family: 'Noto Serif JP', serif;
            text-align: center;
            line-height: 2.2;
            margin: 40px 0;
            padding: 20px;
        }
        
        .todays-date {
            text-align: center;
            margin: 30px 0;
        }
        
        .date-display {
            font-size: 1.3rem;
            font-weight: normal;
            color: var(--primary-dark);
        }
        
        .ceremony-reception-info {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin: 30px 0;
        }
        
        .info-section {
            margin-bottom: 20px;
        }
        
        .info-section h3 {
            font-weight: normal;
            color: var(--primary-dark);
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .info-section p {
            margin-bottom: 10px;
        }
        
        .venue-info {
            margin: 30px 0;
        }
        
        .venue-name {
            font-size: 1.2rem;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .venue-address {
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .transportation-note {
            font-size: 0.95rem;
            line-height: 1.8;
            margin: 20px 0;
            padding: 15px;
            background-color: rgba(107, 157, 97, 0.1);
            border-radius: 5px;
        }
        
        .rsvp-section {
            text-align: center;
            margin: 40px 0;
        }
        
        .rsvp-text {
            margin-bottom: 20px;
        }
        
        .rsvp-deadline {
            font-weight: 500;
            color: var(--primary-dark);
        }
        
        .rsvp-link {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 12px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            margin-top: 15px;
            transition: all 0.3s ease;
        }
        
        .rsvp-link:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(107, 157, 97, 0.3);
        }
        
        .footer-branding {
            margin-top: 50px;
            text-align: center;
            padding: 20px 0;
            font-size: 0.9rem;
            color: #888;
        }
        
        /* テキスト装飾や空白 */
        .text-center {
            text-align: center;
        }
        
        .mb-10 {
            margin-bottom: 10px;
        }
        
        .mb-20 {
            margin-bottom: 20px;
        }
        
        .mb-30 {
            margin-bottom: 30px;
        }
        
        /* レスポンシブデザイン */
        @media (max-width: 576px) {
            .couple-names h1 {
                font-size: 1.8rem;
            }
            
            .formal-message {
                padding: 10px;
                font-size: 0.95rem;
            }
            
            .ceremony-reception-info {
                padding: 20px;
            }
        }
    </style>
</head>
<body class="natural-green">
    <!-- 背景装飾 -->
    <div class="natural-decoration">
        <?php for ($i = 0; $i < 10; $i++): ?>
            <div class="leaf" style="left: <?= rand(0, 100) ?>%; animation-duration: <?= rand(15, 30) ?>s; animation-delay: <?= rand(0, 15) ?>s;"></div>
        <?php endfor; ?>
    </div>
    
    <div class="container">
        <!-- ヘッダー：カップル名と日付 -->
        <header class="couple-names">
            <h1><?= $couple_names['groom'] ?> & <?= $couple_names['bride'] ?></h1>
            <p class="wedding-date"><?= $formatted_date ?> <?= $datetime_info['day'] ?></p>
        </header>
        
        <!-- 正式な招待状メッセージ -->
        <div class="formal-message">
            <p>謹啓</p>
            <p>皆様におかれましては<br>
            ますますご清祥のこととお慶び申し上げます</p>
            <p>このたび 私たちは結婚をすることになりました</p>
            <p>つきましては 日頃お世話になっております皆様に<br>
            感謝を込めて ささやかな小宴を催したく存じます</p>
            <p>ご多用中 誠に恐縮ではございますが<br>
            ぜひご出席をいただきたく ご案内申し上げます</p>
            <p>謹白</p>
        </div>
        
        <!-- 日付 -->
        <div class="todays-date">
            <h2 class="date-display">Today is</h2>
            <p class="date-display"><?= $datetime_info['date'] ?>（<?= $datetime_info['day'] ?>）</p>
        </div>
        
        <!-- 挙式・披露宴情報 -->
        <div class="ceremony-reception-info">
            <div class="info-section">
                <h3>挙式</h3>
                <p><?= $datetime_info['ceremony_time'] ?> (受付 <?= date('H:i', strtotime($datetime_info['ceremony_time']) - 30*60) ?>)</p>
            </div>
            
            <div class="info-section">
                <h3>披露宴</h3>
                <p><?= $datetime_info['reception_time'] ?> (受付 <?= date('H:i', strtotime($datetime_info['reception_time']) - 30*60) ?>)</p>
            </div>
            
            <div class="venue-info">
                <p class="venue-name"><?= htmlspecialchars($venue_info['name']) ?></p>
                <p class="venue-address"><?= nl2br(htmlspecialchars($venue_info['address'])) ?></p>
                
                <!-- Google マップリンク -->
                <?php if (!empty($venue_info['map_link'])): ?>
                <a href="<?= htmlspecialchars($venue_info['map_link']) ?>" target="_blank" class="rsvp-link" style="background-color: #4285F4; margin-top: 10px; display: inline-block;">
                    <i class="fas fa-map-marker-alt"></i> Googleマップで見る
                </a>
                <?php endif; ?>
            </div>
            
            <div class="transportation-note">
                <p>〇〇駅より送迎バスをご用意しております<br>
                ご利用の方は出欠の回答画面にて<br>
                お知らせくださいますよう<br>
                お願い申し上げます</p>
            </div>
        </div>
        
        <!-- 回答期限と招待状回答リンク -->
        <div class="rsvp-section">
            <div class="rsvp-text">
                <p>お手数ではございますが<br>
                <span class="rsvp-deadline"><?= date('n月j日', strtotime($datetime_info['date']) - 30*24*60*60) ?></span> までにご返信くださいますよう<br>
                お願い申し上げます</p>
            </div>
            
            <a href="<?= $group_id ? "../../process_rsvp.php?group=" . urlencode($group_id) : "../../process_rsvp.php" ?>" class="rsvp-link">
                招待状に回答する <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        
        <!-- フッター -->
        <footer class="footer-branding">
            <p>Weddingday 結婚式Web招待状 Weddingday</p>
        </footer>
    </div>
    
    <!-- JavaScriptのカウントダウン機能 -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 結婚式の日時を設定
            const weddingDate = "<?= date('Y-m-d', strtotime(str_replace(['年', '月', '日'], ['-', '-', ''], $datetime_info['date']))) ?>";
            const weddingTime = "<?= $datetime_info['ceremony_time'] ?>";
            const weddingDateTime = new Date(`${weddingDate}T${weddingTime}`);
            
            function updateCountdown() {
                const now = new Date();
                const diff = weddingDateTime - now;
                
                // 結婚式が過ぎた場合
                if (diff <= 0) {
                    document.querySelector('.countdown').innerHTML = '<h3>結婚式は終了しました</h3><p>ご参加いただいた皆様、ありがとうございました。</p>';
                    return;
                }
                
                // 残り日数などを計算
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                
                // カウントダウン表示があれば更新
                if (document.getElementById('countdown-days')) {
                    document.getElementById('countdown-days').textContent = days;
                    document.getElementById('countdown-hours').textContent = hours;
                    document.getElementById('countdown-minutes').textContent = minutes;
                    document.getElementById('countdown-seconds').textContent = seconds;
                }
            }
            
            // 初回実行
            updateCountdown();
            
            // 1秒ごとに更新
            setInterval(updateCountdown, 1000);
        });
    </script>
</body>
</html> 