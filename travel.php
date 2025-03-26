<?php
// 設定ファイルを読み込み
require_once 'config.php';

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
            $error = "データベースエラー: " . $e->getMessage();
        }
    }
}

// 交通情報と宿泊情報を取得
$transportation_info = [];
$accommodation_info = [];

try {
    $stmt = $pdo->query("SELECT * FROM travel_info WHERE type = 'transportation' AND is_visible = 1 ORDER BY display_order, id");
    $transportation_info = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT * FROM travel_info WHERE type = 'accommodation' AND is_visible = 1 ORDER BY display_order, id");
    $accommodation_info = $stmt->fetchAll();
} catch (PDOException $e) {
    if ($debug_mode) {
        $error = "情報の取得に失敗しました。 エラー: " . $e->getMessage();
    } else {
        $error = "情報の取得に失敗しました。";
    }
}

// 宿泊予約管理テーブルの確認
$has_bookings_table = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'accommodation_bookings'");
    $has_bookings_table = ($stmt->rowCount() > 0);
} catch (PDOException $e) {
    // エラー処理（静かに失敗）
}

// 宿泊予約データの取得
$bookings = [];
if ($has_bookings_table && isset($guest_info['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM accommodation_bookings WHERE guest_id = ? ORDER BY check_in");
        $stmt->execute([$guest_info['id']]);
        $bookings = $stmt->fetchAll();
    } catch (PDOException $e) {
        // エラー処理（静かに失敗）
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>交通・宿泊情報 - <?= $site_name ?></title>
    
    <!-- スタイルシートの読み込み -->
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* 交通・宿泊ページ専用スタイル */
        .travel-page {
            padding: 40px 0;
            background: transparent;
        }
        
        .travel-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .travel-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(to right, var(--accent-light), var(--accent-dark));
            z-index: 2;
        }
        
        .page-title {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            padding-bottom: 15px;
        }
        
        .page-title h1 {
            font-family: 'Noto Serif JP', serif;
            font-size: 2.2rem;
            color: var(--accent-dark);
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
        }
        
        .page-title h1::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background-color: var(--accent-light);
        }
        
        .page-title .subtitle {
            font-size: 1rem;
            color: #666;
            font-family: 'Noto Sans JP', sans-serif;
        }
        
        .section-title {
            border-left: 4px solid var(--accent-dark);
            padding-left: 15px;
            margin: 40px 0 20px;
            background-color: rgba(248, 244, 240, 0.7);
            padding: 10px 15px;
            border-radius: 0 5px 5px 0;
            position: relative;
        }
        
        .section-title h2 {
            font-size: 1.5rem;
            font-weight: 500;
            margin: 0;
            color: var(--accent-dark);
            display: flex;
            align-items: center;
        }
        
        .section-title h2 i {
            margin-right: 10px;
            font-size: 1.3rem;
        }
        
        /* カードレイアウトの改善 */
        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }
        
        /* 情報カードの最適化 */
        .info-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        .info-card-image {
            height: 220px;
            overflow: hidden;
            position: relative;
        }
        
        .info-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .info-card:hover .info-card-image img {
            transform: scale(1.05);
        }
        
        /* 画像がない場合のプレースホルダー */
        .no-image-placeholder {
            height: 140px;
            background: linear-gradient(45deg, #f5f5f5, #e0e0e0);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .no-image-placeholder i {
            font-size: 2.5rem;
            color: #bbb;
        }
        
        .info-card-content {
            padding: 25px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .info-card-title {
            font-size: 1.3rem;
            font-weight: 500;
            margin: 0 0 15px;
            color: var(--accent-dark);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            padding-bottom: 10px;
        }
        
        .info-card-description {
            font-size: 0.95rem;
            color: #444;
            line-height: 1.7;
            flex-grow: 1;
            word-break: break-word;
            /* 長いテキストの改善 */
            max-height: 350px;
            overflow-y: auto;
            padding-right: 5px;
        }
        
        /* スクロールバーのスタイル */
        .info-card-description::-webkit-scrollbar {
            width: 6px;
            background-color: #f5f5f5;
        }
        
        .info-card-description::-webkit-scrollbar-thumb {
            background-color: #ddd;
            border-radius: 3px;
        }
        
        .info-card-description::-webkit-scrollbar-thumb:hover {
            background-color: #ccc;
        }
        
        /* ホテル料金表のスタイル */
        .price-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 0.9rem;
        }
        
        .price-table th, .price-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        
        .price-table th {
            background-color: rgba(248, 244, 240, 0.7);
            font-weight: 500;
        }
        
        .price-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .back-to-home {
            display: inline-block;
            margin-top: 40px;
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            background-color: var(--accent-dark);
            padding: 10px 25px;
            border-radius: 30px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .back-to-home::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.2);
            transition: width 0.3s ease;
            z-index: -1;
        }
        
        .back-to-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }
        
        .back-to-home:hover::before {
            width: 100%;
        }
        
        .back-to-home i {
            margin-right: 8px;
        }
        
        .intro-text {
            margin-bottom: 30px;
            line-height: 1.7;
            color: #444;
            background-color: rgba(248, 244, 240, 0.7);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--accent-dark);
        }
        
        .intro-text p {
            margin: 0 0 10px;
        }
        
        .intro-text p:last-child {
            margin-bottom: 0;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #f5c6cb;
        }
        
        /* 宿泊予約情報のスタイルを改善 */
        .bookings-container {
            margin-top: 25px;
        }
        
        .booking-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .booking-header {
            background-color: var(--accent-dark);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .booking-header h3 {
            margin: 0;
            font-size: 1.2rem;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .booking-status {
            background-color: #ffffff;
            color: var(--accent-dark);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .booking-details {
            padding: 25px;
        }
        
        .booking-dates {
            display: flex;
            margin-bottom: 20px;
            gap: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }
        
        .booking-info {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .label {
            font-weight: bold;
            color: #555;
            display: block;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .value {
            font-size: 1.05rem;
            color: #333;
        }
        
        .special-requests, .notes {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .special-requests p, .notes p {
            margin: 5px 0 0;
            font-size: 0.95rem;
            line-height: 1.7;
            color: #444;
            word-break: break-word;
        }
        
        /* 装飾要素 */
        .travel-decoration {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: -1;
            overflow: hidden;
        }
        
        /* 画像拡大表示機能 */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.8);
            transition: 0.3s;
        }
        
        .modal-content {
            display: block;
            position: relative;
            margin: auto;
            padding: 0;
            width: 80%;
            max-width: 900px;
            max-height: 80vh;
            object-fit: contain;
        }
        
        .close {
            color: #fff;
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2rem;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
            z-index: 1001;
        }
        
        .close:hover,
        .close:focus {
            color: #bbb;
            text-decoration: none;
            cursor: pointer;
        }
        
        /* 拡大表示が可能な画像のカーソル設定 */
        .zoomable {
            cursor: zoom-in;
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .travel-container {
                padding: 20px;
                margin: 0 10px;
            }
            
            .info-cards {
                grid-template-columns: 1fr;
            }
            
            .page-title h1 {
                font-size: 1.8rem;
            }
            
            .section-title h2 {
                font-size: 1.4rem;
            }
            
            .info-card-content {
                padding: 20px;
            }
            
            .booking-dates {
                flex-direction: column;
                gap: 15px;
            }
            
            .info-card-image {
                height: 180px;
            }
            
            .modal-content {
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <!-- 装飾効果（常時表示） -->
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
    
    <div class="travel-page">
        <div class="travel-container">
            <div class="page-title">
                <h1>交通・宿泊情報</h1>
                <p class="subtitle">Travel & Accommodation Information</p>
            </div>
            
            <?php if (isset($error)): ?>
            <div class="error-message">
                <?= $error ?>
            </div>
            <?php endif; ?>
            
            <div class="intro-text">
                <p>結婚式にご参加いただく皆様に、会場までの交通手段や宿泊施設に関する情報をご案内します。ご不明な点がございましたら、お気軽にお問い合わせください。</p>
                <?php if ($group_id): ?>
                <p><?= htmlspecialchars($guest_info['group_name']) ?>の皆様、どうぞお気をつけてお越しください。</p>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($transportation_info)): ?>
            <div class="section-title">
                <h2><i class="fas fa-route"></i> 交通情報</h2>
            </div>
            
            <div class="info-cards">
                <?php foreach ($transportation_info as $info): ?>
                <div class="info-card">
                    <?php if (!empty($info['image_filename'])): ?>
                    <div class="info-card-image">
                        <img src="uploads/travel/<?= htmlspecialchars($info['image_filename']) ?>" alt="<?= htmlspecialchars($info['title']) ?>" class="zoomable" onclick="openModal(this.src)">
                    </div>
                    <?php else: ?>
                    <div class="no-image-placeholder">
                        <i class="fas fa-route"></i>
                    </div>
                    <?php endif; ?>
                    <div class="info-card-content">
                        <h3 class="info-card-title"><?= htmlspecialchars($info['title']) ?></h3>
                        <div class="travel-description">
                            <?= nl2br($info['description']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($accommodation_info)): ?>
            <div class="section-title">
                <h2><i class="fas fa-hotel"></i> 宿泊情報</h2>
            </div>
            
            <div class="info-cards">
                <?php foreach ($accommodation_info as $info): ?>
                <div class="info-card">
                    <?php if (!empty($info['image_filename'])): ?>
                    <div class="info-card-image">
                        <img src="uploads/travel/<?= htmlspecialchars($info['image_filename']) ?>" alt="<?= htmlspecialchars($info['title']) ?>" class="zoomable" onclick="openModal(this.src)">
                    </div>
                    <?php else: ?>
                    <div class="no-image-placeholder">
                        <i class="fas fa-hotel"></i>
                    </div>
                    <?php endif; ?>
                    <div class="info-card-content">
                        <h3 class="info-card-title"><?= htmlspecialchars($info['title']) ?></h3>
                        <div class="travel-description">
                            <?= nl2br($info['description']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($bookings)): ?>
            <div class="section-title">
                <h2><i class="fas fa-calendar-check"></i> あなたの宿泊予約</h2>
            </div>
            
            <div class="bookings-container">
                <?php foreach ($bookings as $booking): ?>
                <div class="booking-card">
                    <div class="booking-header">
                        <h3><?= htmlspecialchars($booking['accommodation_name']) ?></h3>
                        <span class="booking-status"><?= htmlspecialchars($booking['booking_status']) ?></span>
                    </div>
                    <div class="booking-details">
                        <div class="booking-dates">
                            <div class="check-in">
                                <span class="label">チェックイン:</span>
                                <span class="value"><?= date('Y年m月d日', strtotime($booking['check_in'])) ?></span>
                            </div>
                            <div class="check-out">
                                <span class="label">チェックアウト:</span>
                                <span class="value"><?= date('Y年m月d日', strtotime($booking['check_out'])) ?></span>
                            </div>
                        </div>
                        <div class="booking-info">
                            <div class="room-type">
                                <span class="label">部屋タイプ:</span>
                                <span class="value"><?= htmlspecialchars($booking['room_type']) ?></span>
                            </div>
                            <div class="guests">
                                <span class="label">ご宿泊人数:</span>
                                <span class="value"><?= htmlspecialchars($booking['number_of_guests']) ?> 名</span>
                            </div>
                            <div class="rooms">
                                <span class="label">ご予約部屋数:</span>
                                <span class="value"><?= htmlspecialchars($booking['number_of_rooms']) ?> 部屋</span>
                            </div>
                        </div>
                        <?php if (!empty($booking['special_requests'])): ?>
                        <div class="special-requests">
                            <span class="label">特別リクエスト:</span>
                            <p><?= nl2br(htmlspecialchars($booking['special_requests'])) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($booking['notes'])): ?>
                        <div class="notes">
                            <span class="label">備考:</span>
                            <p><?= nl2br(htmlspecialchars($booking['notes'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <a href="<?= $group_id ? "index.php?group=" . urlencode($group_id) : "index.php" ?>" class="back-to-home">
                <i class="fas fa-arrow-left"></i> 招待状ページに戻る
            </a>
        </div>
    </div>
    
    <!-- 画像モーダル -->
    <div id="imageModal" class="modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>
    
    <script>
        // 画像拡大モーダル機能
        function openModal(src) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            
            modal.style.display = "flex";
            modalImg.src = src;
            
            // スクロール禁止
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.getElementById('imageModal').style.display = "none";
            // スクロール許可
            document.body.style.overflow = '';
        }
        
        // モーダルの外側クリックで閉じる
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html> 