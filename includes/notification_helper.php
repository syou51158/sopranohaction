<?php
/**
 * 通知ヘルパー関数
 */
require_once __DIR__ . '/mail_helper.php';

/**
 * 通知を送信する関数
 *
 * @param string $type 通知タイプ (例: rsvp_received, guest_registration など)
 * @param array $data プレースホルダーに置き換えるデータ
 * @param string $recipient_email 通知先メールアドレス（指定しない場合は管理者メールアドレスを使用）
 * @return bool 送信成功・失敗
 */
function send_notification($type, $data, $recipient_email = null) {
    global $pdo;
    
    try {
        // 通知設定を取得
        $stmt = $pdo->prepare("SELECT * FROM notification_settings WHERE type = ? AND is_enabled = 1");
        $stmt->execute([$type]);
        $notification = $stmt->fetch();
        
        if (!$notification) {
            return false; // 通知が無効または見つからない
        }
        
        // 送信先メールアドレスが指定されていない場合は管理者メールアドレスを使用
        if (!$recipient_email) {
            // 管理者メールアドレスを取得
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'admin_email'");
            $stmt->execute();
            $admin_email = $stmt->fetchColumn();
            
            if (!$admin_email) {
                return false; // 管理者メールアドレスが設定されていない
            }
            
            $recipient_email = $admin_email;
        }
        
        // プレースホルダーの置換
        $subject = $notification['subject'];
        $message = $notification['template'];
        
        foreach ($data as $placeholder => $value) {
            $subject = str_replace($placeholder, $value, $subject);
            $message = str_replace($placeholder, $value, $message);
        }
        
        // メール送信
        $result = send_system_mail($recipient_email, $subject, $message);
        
        // 通知ログテーブルがあるか確認・作成
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS notification_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    notification_type VARCHAR(50) NOT NULL,
                    recipient_email VARCHAR(255) NOT NULL,
                    subject VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    error_message TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        } catch (PDOException $e) {
            // テーブル作成に失敗しても処理を続行
        }
        
        if ($result['success']) {
            // 通知ログを記録
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO notification_logs 
                    (notification_type, recipient_email, subject, message, status) 
                    VALUES (?, ?, ?, ?, 'success')
                ");
                $stmt->execute([$type, $recipient_email, $subject, $message]);
            } catch (PDOException $e) {
                // ログ記録に失敗しても送信は成功とみなす
            }
            
            return true;
        } else {
            // 失敗ログを記録
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO notification_logs 
                    (notification_type, recipient_email, subject, message, status, error_message) 
                    VALUES (?, ?, ?, ?, 'failed', ?)
                ");
                $stmt->execute([$type, $recipient_email, $subject, $message, $result['message']]);
            } catch (PDOException $e) {
                // ログ記録に失敗
            }
            
            return false;
        }
    } catch (Exception $e) {
        // エラーログを記録
        error_log("通知送信エラー: " . $e->getMessage());
        return false;
    }
}

/**
 * 出欠回答の通知を送信する関数
 *
 * @param array $response 出欠回答データ
 * @return bool 送信成功・失敗
 */
function send_rsvp_notification($response) {
    global $pdo;
    
    // ゲスト情報を取得（存在する場合）
    $guest_info = [];
    if (!empty($response['guest_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM guests WHERE id = ?");
            $stmt->execute([$response['guest_id']]);
            $guest_info = $stmt->fetch();
        } catch (PDOException $e) {
            // ゲスト情報の取得に失敗
        }
    }
    
    // 結婚式設定情報を取得
    $wedding_settings = [];
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM wedding_settings");
        while ($row = $stmt->fetch()) {
            $wedding_settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        // 結婚式設定の取得に失敗
    }
    
    // 通知データを準備
    $notification_data = [
        '{guest_name}' => $response['name'],
        '{group_name}' => isset($guest_info['group_name']) ? $guest_info['group_name'] : '未所属',
        '{attendance_status}' => $response['attending'] ? '出席' : '欠席',
        '{guest_count}' => $response['attending'] ? ($response['companions'] + 1) : 0,
        '{message}' => $response['message'] ?? '',
        '{admin_url}' => $GLOBALS['site_url'] . 'admin/dashboard.php',
        '{bride_name}' => $wedding_settings['bride_name'] ?? '新婦',
        '{groom_name}' => $wedding_settings['groom_name'] ?? '新郎',
        '{website_url}' => $GLOBALS['site_url'],
        '{wedding_date}' => $wedding_settings['wedding_date'] ?? '未設定',
        '{wedding_time}' => $wedding_settings['wedding_time'] ?? '未設定',
        '{venue_name}' => $wedding_settings['venue_name'] ?? '未設定',
        '{venue_address}' => $wedding_settings['venue_address'] ?? '未設定'
    ];
    
    // 管理者への通知
    $admin_result = send_notification('rsvp_received', $notification_data);
    
    // ゲスト自身への確認メール（メールアドレスが含まれている場合のみ）
    // ただし、出席する場合は別のQRコード付きメールが送信されるのでスキップ
    $guest_result = true;
    if (!empty($response['email']) && !$response['attending']) {
        $guest_result = send_notification('guest_confirmation', $notification_data, $response['email']);
    }
    
    return $admin_result && $guest_result;
}
?> 