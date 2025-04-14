<?php
/**
 * ゲスト用案内ページ
 * 
 * ゲストがQRコードをスキャンすると表示されるページです。
 * 席次案内、会場マップ、イベント情報などを表示します。
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
    } else {
        // デバッグ情報を記録
        $guest_name = isset($guest_info['name']) && !empty($guest_info['name']) ? $guest_info['name'] : 'unknown';
        error_log("ゲスト情報取得成功: ID=" . $guest_info['id'] . ", 名前=" . $guest_name);
        
        // チェックイン自動記録（オプション）
        $auto_checkin = isset($_GET['auto_checkin']) && $_GET['auto_checkin'] === '1';
        if ($auto_checkin) {
            $checkin_result = record_guest_checkin($guest_info['id'], 'QRスキャン', 'ゲスト自身によるスキャン');
            if ($checkin_result) {
                error_log("自動チェックイン成功: ゲストID=" . $guest_info['id']);
            } else {
                error_log("自動チェックイン失敗: ゲストID=" . $guest_info['id']);
            }
        }
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
        SELECT * FROM schedule 
        ORDER BY event_time ASC
    ");
    $stmt->execute();
    $event_schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('イベントスケジュール取得エラー: ' . $e->getMessage());
}

// タイムスケジュール表示用のヘルパー関数
function safe_string($value) {
    // nullや空文字の場合は空文字を返す
    if ($value === null || $value === '') {
        return '';
    }
    // それ以外は安全にエスケープ
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ページタイトル
$page_title = 'ご案内';
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
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #d7ccc8;
        }
        
        .schedule-time {
            min-width: 80px;
            padding: 10px;
            font-weight: bold;
            color: #ffffff;
            background-color: #8d6e63;
            border-radius: 30px;
            text-align: center;
            margin-right: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .schedule-details {
            flex: 1;
            padding: 5px 0;
        }
        
        .schedule-title {
            font-weight: bold;
            color: #4e342e;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }
        
        .schedule-description {
            color: #6d4c41;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .schedule-location {
            color: #795548;
            font-size: 0.9rem;
            margin-top: 5px;
            display: inline-block;
            background-color: #f5f5f5;
            padding: 3px 10px;
            border-radius: 15px;
        }
        
        .schedule-location i {
            color: #8d6e63;
            margin-right: 5px;
        }
        
        /* スケジュールの強調表示 */
        .schedule-item:nth-child(odd) .schedule-time {
            background-color: #a1887f;
        }
        
        .schedule-item:nth-child(even) .schedule-time {
            background-color: #8d6e63;
        }
        
        .error-container {
            text-align: center;
            padding: 50px 20px;
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .return-link {
            margin-top: 30px;
        }
        
        .return-link a {
            display: inline-block;
            padding: 10px 20px;
            background-color: #8d6e63;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .return-link a:hover {
            background-color: #6d4c41;
        }
        
        @media (max-width: 600px) {
            .table-seat-info {
                font-size: 1.2rem;
            }
            
            .table-name {
                font-size: 1.5rem;
            }
            
            .seat-number {
                font-size: 1.3rem;
            }
            
            .schedule-time {
                width: 80px;
            }
        }
    </style>
</head>
<body>
    <div class="guidance-container">
        <?php if ($error): ?>
        <!-- エラーメッセージ -->
        <div class="error-container">
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
            <div class="return-link">
                <a href="index.php"><i class="fas fa-arrow-left"></i> トップページに戻る</a>
            </div>
        </div>
        <?php else: ?>
        <!-- ウェルカムメッセージ -->
        <div class="welcome-message">
            <h1>ようこそ、<?= safe_string($guest_info['group_name'] ?? '') ?></h1>
            <p>結婚式の案内情報をご確認ください</p>
        </div>
        
        <!-- チェックイン完了通知 -->
        <?php if (isset($_GET['auto_checkin']) && $_GET['auto_checkin'] === '1'): ?>
        <div style="background-color: #4CAF50; color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; box-shadow: 0 3px 10px rgba(0,0,0,0.1);">
            <div style="font-size: 50px; margin-bottom: 10px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 style="margin: 0 0 10px 0;">チェックイン完了</h2>
            <p style="margin: 0;">受付が完了しました。素敵な時間をお過ごしください。</p>
        </div>
        <?php endif; ?>
        
        <div class="guidance-sections">
            <?php if ($seating_info): ?>
            <!-- 席次案内 -->
            <div class="guidance-section">
                <h2><i class="fas fa-chair"></i> 席次のご案内</h2>
                <div class="seating-info">
                    <p class="table-seat-info">
                        <span class="table-name"><?= safe_string($seating_info['table_name'] ?? '') ?></span>
                        <?php if (!empty($seating_info['seat_number'])): ?>
                        <span class="seat-number"><?= safe_string($seating_info['seat_number'] ?? '') ?> 席</span>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($seating_info['custom_message'])): ?>
                    <div class="custom-message">
                        <?= nl2br(safe_string($seating_info['custom_message'] ?? '')) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($event_notices)): ?>
            <!-- イベント案内 -->
            <div class="guidance-section">
                <h2><i class="fas fa-bell"></i> お知らせ</h2>
                <div class="event-notices">
                    <?php foreach ($event_notices as $notice): ?>
                    <div class="notice-item">
                        <h3><?= safe_string($notice['title'] ?? '') ?></h3>
                        <div class="notice-content">
                            <?= nl2br(safe_string($notice['content'] ?? '')) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($venue_map): ?>
            <!-- 会場マップ -->
            <div class="guidance-section">
                <h2><i class="fas fa-map-marked-alt"></i> 会場マップ</h2>
                <div class="map-container">
                    <img src="<?= safe_string($venue_map['image_url'] ?? '') ?>" alt="会場マップ" class="map-image">
                    <?php if (!empty($venue_map['description'])): ?>
                    <div class="map-description">
                        <?= nl2br(safe_string($venue_map['description'] ?? '')) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($event_schedule)): ?>
            <!-- タイムスケジュール -->
            <div class="guidance-section">
                <h2><i class="fas fa-clock"></i> タイムスケジュール</h2>
                <div class="schedule-container">
                    <?php foreach ($event_schedule as $item): ?>
                    <div class="schedule-item">
                        <div class="schedule-time">
                            <?= date('H:i', strtotime($item['event_time'])) ?>
                        </div>
                        <div class="schedule-details">
                            <div class="schedule-title"><?= safe_string($item['event_name'] ?? '') ?></div>
                            <?php if (!empty($item['event_description'])): ?>
                            <div class="schedule-description">
                                <?= nl2br(safe_string($item['event_description'] ?? '')) ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($item['location'])): ?>
                            <div class="schedule-location">
                                <i class="fas fa-map-marker-alt"></i> <?= safe_string($item['location'] ?? '') ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 戻るリンク -->
            <div class="return-link" style="text-align: center; margin-top: 20px;">
                <a href="index.php?group=<?= urlencode($guest_info['group_id'] ?? '') ?>">
                    <i class="fas fa-arrow-left"></i> 招待状に戻る
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
