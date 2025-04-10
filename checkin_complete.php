<?php
/**
 * チェックイン完了後の案内表示ページ
 * 
 * QRコードをスキャンしてチェックインが完了した後に表示される
 * 席次案内、会場マップ、イベント情報などを表示するページです。
 */

// 設定ファイルの読み込み
require_once 'config.php';
require_once 'includes/qr_helper.php';

// トークンの取得
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$guest_info = null;
$seating_info = null;
$error = '';

// ゲスト情報の取得
if (!empty($token)) {
    $guest_info = get_guest_by_qr_token($token);
    
    if (!$guest_info) {
        $error = '無効なQRコードです。このトークンに対応するゲスト情報が見つかりません。';
        error_log("無効なQRコード（案内表示）: token=$token");
    }
} else {
    $error = 'QRコードトークンが指定されていません。';
}

// 席次情報の取得
if ($guest_info) {
    try {
        // 自分自身の席情報を取得
        $stmt = $pdo->prepare("
            SELECT sg.*, st.table_name, st.table_type
            FROM seating_guidance sg
            LEFT JOIN seating_tables st ON sg.table_id = st.id
            WHERE sg.guest_id = ?
        ");
        $stmt->execute([$guest_info['id']]);
        $seating_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // もし席次案内テーブルに情報がなければ、seat_assignmentsテーブルから取得を試みる
        if (!$seating_info) {
            // まず回答情報を取得
            $stmt = $pdo->prepare("
                SELECT id FROM responses 
                WHERE guest_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$guest_info['id']]);
            $response = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($response) {
                $stmt = $pdo->prepare("
                    SELECT sa.*, st.table_name, st.table_type
                    FROM seat_assignments sa
                    JOIN seating_tables st ON sa.table_id = st.id
                    WHERE sa.response_id = ? AND sa.is_companion = 0
                ");
                $stmt->execute([$response['id']]);
                $seat_assignment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($seat_assignment) {
                    // seat_assignmentsからの情報をseating_guidance形式に変換
                    $seating_info = [
                        'guest_id' => $guest_info['id'],
                        'table_id' => $seat_assignment['table_id'],
                        'seat_number' => $seat_assignment['seat_number'],
                        'custom_message' => null,
                        'table_name' => $seat_assignment['table_name'],
                        'table_type' => $seat_assignment['table_type']
                    ];
                    
                    // 席次案内テーブルに自動的に保存（今後の利用のため）
                    $insert = $pdo->prepare("
                        INSERT INTO seating_guidance 
                        (guest_id, table_id, seat_number, custom_message)
                        VALUES (?, ?, ?, ?)
                    ");
                    $insert->execute([
                        $guest_info['id'],
                        $seat_assignment['table_id'],
                        $seat_assignment['seat_number'],
                        null
                    ]);
                }
            }
        }
    } catch (PDOException $e) {
        error_log('席次情報取得エラー: ' . $e->getMessage());
    }
}

// イベント案内の取得
$event_notices = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM event_notices 
        WHERE active = 1 
        ORDER BY priority DESC, created_at DESC
    ");
    $stmt->execute();
    $event_notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('イベント案内取得エラー: ' . $e->getMessage());
}

// 会場マップの取得
$venue_map = null;
try {
    $stmt = $pdo->prepare("
        SELECT * FROM venue_maps 
        WHERE is_default = 1 
        LIMIT 1
    ");
    $stmt->execute();
    $venue_map = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('会場マップ取得エラー: ' . $e->getMessage());
}

// イベントスケジュールの取得
$event_schedule = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM event_schedule 
        ORDER BY event_time ASC
    ");
    $stmt->execute();
    $event_schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('イベントスケジュール取得エラー: ' . $e->getMessage());
}

// ページタイトル
$page_title = 'チェックイン完了 - ご案内';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= $site_name ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .guidance-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .welcome-message {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .welcome-message h1 {
            font-size: 1.8rem;
            color: #6d4c41;
        }
        
        .welcome-message p {
            font-size: 1.1rem;
            color: #795548;
        }
        
        .guidance-sections {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .guidance-section {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .guidance-section h2 {
            color: #5d4037;
            border-bottom: 2px solid #d7ccc8;
            padding-bottom: 10px;
            margin-top: 0;
            display: flex;
            align-items: center;
        }
        
        .guidance-section h2 i {
            margin-right: 10px;
            color: #8d6e63;
        }
        
        .seating-info {
            margin-top: 20px;
            text-align: center;
        }
        
        .table-seat-info {
            font-size: 1.5rem;
            font-weight: bold;
            color: #4e342e;
            margin: 15px 0;
        }
        
        .table-name {
            font-size: 2rem;
            color: #5d4037;
            margin-right: 15px;
        }
        
        .seat-number {
            font-size: 1.8rem;
            color: #6d4c41;
        }
        
        .event-notices {
            margin-top: 15px;
        }
        
        .notice-item {
            background-color: #f5f5f5;
            border-left: 4px solid #8d6e63;
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 5px;
        }
        
        .notice-item h3 {
            margin-top: 0;
            color: #5d4037;
        }
        
        .map-container {
            margin-top: 15px;
            text-align: center;
        }
        
        .map-image {
            max-width: 100%;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .schedule-container {
            margin-top: 15px;
        }
        
        .schedule-item {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 15px;
        }
        
        .schedule-time {
            flex: 0 0 140px;
            font-weight: bold;
            color: #5d4037;
        }
        
        .schedule-details {
            flex: 1;
        }
        
        .schedule-details h3 {
            margin-top: 0;
            color: #5d4037;
        }
        
        .schedule-location {
            font-style: italic;
            color: #8d6e63;
        }
        
        .error-container {
            background-color: #ffebee;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .error-container i {
            font-size: 3rem;
            color: #d50000;
            margin-bottom: 15px;
        }
        
        .error-message {
            color: #b71c1c;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .guidance-container {
                padding: 15px;
            }
            
            .welcome-message h1 {
                font-size: 1.5rem;
            }
            
            .table-name {
                font-size: 1.8rem;
            }
            
            .seat-number {
                font-size: 1.6rem;
            }
            
            .schedule-item {
                flex-direction: column;
            }
            
            .schedule-time {
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="guidance-container">
        <?php if ($error): ?>
            <div class="error-container">
                <i class="fas fa-exclamation-circle"></i>
                <p class="error-message"><?= htmlspecialchars($error) ?></p>
                <p>受付スタッフまでお声がけください。</p>
            </div>
        <?php elseif ($guest_info): ?>
            <div class="welcome-message">
                <h1><?= htmlspecialchars($guest_info['group_name']) ?> 様</h1>
                <p>ご来場ありがとうございます。以下にご案内情報を表示します。</p>
            </div>
            
            <div class="guidance-sections">
                <!-- 席次情報 -->
                <div class="guidance-section">
                    <h2><i class="fas fa-chair"></i> お席のご案内</h2>
                    <?php if ($seating_info): ?>
                        <div class="seating-info">
                            <p>お席は以下になります</p>
                            <div class="table-seat-info">
                                <span class="table-name"><?= htmlspecialchars($seating_info['table_name']) ?></span>
                                <span class="seat-number">席 <?= htmlspecialchars($seating_info['seat_number']) ?></span>
                            </div>
                            <?php if (!empty($seating_info['custom_message'])): ?>
                                <p><?= nl2br(htmlspecialchars($seating_info['custom_message'])) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center">お席情報はございません。受付スタッフにお尋ねください。</p>
                    <?php endif; ?>
                </div>
                
                <!-- イベントスケジュール -->
                <?php if (!empty($event_schedule)): ?>
                <div class="guidance-section">
                    <h2><i class="fas fa-calendar-day"></i> タイムスケジュール</h2>
                    <div class="schedule-container">
                        <?php foreach ($event_schedule as $event): ?>
                            <div class="schedule-item">
                                <div class="schedule-time">
                                    <?= date('H:i', strtotime($event['event_time'])) ?>
                                </div>
                                <div class="schedule-details">
                                    <h3><?= htmlspecialchars($event['event_name']) ?></h3>
                                    <p><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                                    <?php if (!empty($event['location'])): ?>
                                        <p class="schedule-location">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?= htmlspecialchars($event['location']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- 会場マップ -->
                <?php if ($venue_map): ?>
                <div class="guidance-section">
                    <h2><i class="fas fa-map"></i> 会場マップ</h2>
                    <div class="map-container">
                        <?php if (!empty($venue_map['image_path'])): ?>
                            <img src="<?= htmlspecialchars($venue_map['image_path']) ?>" alt="会場マップ" class="map-image">
                        <?php endif; ?>
                        <h3><?= htmlspecialchars($venue_map['name']) ?></h3>
                        <p><?= nl2br(htmlspecialchars($venue_map['description'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- 重要なお知らせ -->
                <?php if (!empty($event_notices)): ?>
                <div class="guidance-section">
                    <h2><i class="fas fa-bullhorn"></i> お知らせ</h2>
                    <div class="event-notices">
                        <?php foreach ($event_notices as $notice): ?>
                            <div class="notice-item">
                                <h3><?= htmlspecialchars($notice['title']) ?></h3>
                                <p><?= nl2br(htmlspecialchars($notice['content'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 