<?php
/**
 * QRコード生成と管理のためのヘルパー関数
 * 
 * このファイルは結婚式招待状サイトのQRコードチェックイン機能をサポートします。
 * - QRコードトークンの生成
 * - QRコード画像の生成と表示
 * - チェックイン処理
 */

// 必要なライブラリのロード
require_once __DIR__ . '/../config.php';

/**
 * ゲスト用のQRコードトークンを生成・保存する
 *
 * @param int $guest_id ゲストID
 * @return string|bool 生成されたトークン、または失敗時はfalse
 */
function generate_qr_token($guest_id) {
    global $pdo;
    
    // 現在のトークンがあるか確認
    $stmt = $pdo->prepare("SELECT qr_code_token FROM guests WHERE id = ?");
    $stmt->execute([$guest_id]);
    $current_token = $stmt->fetchColumn();
    
    // 既存のトークンがあればそれを返す
    if (!empty($current_token)) {
        return $current_token;
    }
    
    // 新しいトークンを生成（32文字のランダム文字列）
    $token = bin2hex(random_bytes(16));
    
    try {
        // トークンと生成日時をデータベースに保存
        $stmt = $pdo->prepare("
            UPDATE guests 
            SET qr_code_token = ?, qr_code_generated = NOW() 
            WHERE id = ?
        ");
        $result = $stmt->execute([$token, $guest_id]);
        
        if ($result) {
            return $token;
        }
        return false;
    } catch (PDOException $e) {
        // エラー処理
        error_log("QRトークン生成エラー: " . $e->getMessage());
        return false;
    }
}

/**
 * QRコードの画像URLを生成する
 * 
 * Google Chart APIを使用してQRコードを生成します
 *
 * @param string $token QRコードに埋め込むトークン
 * @param int $size QRコードのサイズ（ピクセル）
 * @return string QRコード画像のURL
 */
function get_qr_code_url($token, $size = 200) {
    global $site_url;
    
    // QRコードに埋め込むURL（チェックインページへのリンク）
    $checkin_url = $site_url . "admin/checkin.php?token=" . urlencode($token);
    
    // Google Chart APIを使用してQRコードを生成（HTTPSを強制）
    $qr_url = "https://chart.googleapis.com/chart?";
    $qr_url .= "chs={$size}x{$size}";  // サイズ指定
    $qr_url .= "&cht=qr";              // QRコードタイプ
    $qr_url .= "&chl=" . urlencode($checkin_url); // データ
    $qr_url .= "&choe=UTF-8";          // エンコーディング
    
    return $qr_url;
}

/**
 * QRコードのHTMLを生成する
 *
 * @param int $guest_id ゲストID
 * @param array $options オプション設定
 * @return string QRコードのHTML
 */
function get_qr_code_html($guest_id, $options = []) {
    global $pdo;
    
    // デフォルトオプション
    $default_options = [
        'size' => 200,
        'include_instructions' => true,
        'class' => 'qr-code',
        'instruction_text' => '会場受付でこのQRコードをお見せください'
    ];
    
    // オプションをマージ
    $options = array_merge($default_options, $options);
    
    // ゲスト情報を取得
    $stmt = $pdo->prepare("SELECT qr_code_token FROM guests WHERE id = ?");
    $stmt->execute([$guest_id]);
    $token = $stmt->fetchColumn();
    
    // トークンがなければ新規生成
    if (empty($token)) {
        $token = generate_qr_token($guest_id);
    }
    
    // トークンがなければ空文字を返す
    if (empty($token)) {
        return '';
    }
    
    // QRコード画像URLを取得
    $qr_url = get_qr_code_url($token, $options['size']);
    
    // QRコードHTMLを生成
    $html = '<div class="' . htmlspecialchars($options['class']) . '-container">';
    $html .= '<img src="' . htmlspecialchars($qr_url) . '" ';
    $html .= 'alt="チェックインQRコード" ';
    $html .= 'class="' . htmlspecialchars($options['class']) . '" ';
    $html .= 'onerror="this.onerror=null; this.src=\'images/qr-error.png\'; console.error(\'QRコード読み込みエラー: ' . addslashes(htmlspecialchars($qr_url)) . '\');" ';
    $html .= '/>';
    
    // QRコードのURL（デバッグ用）
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $html .= '<div style="display:none;" class="qr-debug-url">' . htmlspecialchars($qr_url) . '</div>';
    }
    
    // 説明テキストを含める場合
    if ($options['include_instructions']) {
        $html .= '<p class="qr-instructions">' . htmlspecialchars($options['instruction_text']) . '</p>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * QRコードトークンからゲスト情報を取得する
 *
 * @param string $token QRコードトークン
 * @return array|bool ゲスト情報の配列、または失敗時はfalse
 */
function get_guest_by_qr_token($token) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM guests 
            WHERE qr_code_token = ?
        ");
        $stmt->execute([$token]);
        $guest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $guest ?: false;
    } catch (PDOException $e) {
        error_log("QRトークンでのゲスト検索エラー: " . $e->getMessage());
        return false;
    }
}

/**
 * ゲストのチェックインを記録する
 *
 * @param int $guest_id ゲストID
 * @param string $checked_by チェックインを記録したスタッフ名
 * @param string $notes 備考
 * @return bool 成功時はtrue、失敗時はfalse
 */
function record_guest_checkin($guest_id, $checked_by = '', $notes = '') {
    global $pdo;
    
    try {
        // デバッグ情報をログに記録
        error_log("チェックイン処理開始: guest_id=$guest_id, checked_by=$checked_by");
        
        // ゲストが存在するか確認
        $check_guest = $pdo->prepare("SELECT id, group_name FROM guests WHERE id = ?");
        $check_guest->execute([$guest_id]);
        $guest_info = $check_guest->fetch(PDO::FETCH_ASSOC);
        
        if (!$guest_info) {
            error_log("チェックイン失敗: ゲストID $guest_id が見つかりません");
            return false;
        }
        
        error_log("ゲスト情報取得成功: " . json_encode($guest_info, JSON_UNESCAPED_UNICODE));
        
        // すでにチェックイン済みか確認
        $check_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM checkins 
            WHERE guest_id = ? AND DATE(checkin_time) = CURDATE()
        ");
        $check_stmt->execute([$guest_id]);
        $already_checked_in = $check_stmt->fetchColumn() > 0;
        
        error_log("チェックイン状況: 既にチェックイン済み=" . ($already_checked_in ? 'はい' : 'いいえ'));
        
        if ($already_checked_in) {
            // 既にチェックイン済みの場合は注記を更新
            $update_stmt = $pdo->prepare("
                UPDATE checkins 
                SET notes = CONCAT(IFNULL(notes, ''), ' / ', ?) 
                WHERE guest_id = ? AND DATE(checkin_time) = CURDATE()
            ");
            $update_notes = "再チェックイン: " . date('Y-m-d H:i:s') . ($notes ? " - $notes" : "");
            $result = $update_stmt->execute([$update_notes, $guest_id]);
            error_log("チェックイン更新結果: " . ($result ? '成功' : '失敗'));
            return $result;
        } else {
            // 新規チェックイン
            $stmt = $pdo->prepare("
                INSERT INTO checkins (guest_id, checked_by, notes, checkin_time) 
                VALUES (?, ?, ?, NOW())
            ");
            $result = $stmt->execute([$guest_id, $checked_by, $notes]);
            
            if ($result) {
                $last_id = $pdo->lastInsertId();
                error_log("新規チェックイン成功: ID=$last_id, guest_id=$guest_id");
            } else {
                error_log("新規チェックイン失敗: " . json_encode($stmt->errorInfo()));
            }
            
            return $result;
        }
    } catch (PDOException $e) {
        error_log("チェックイン記録エラー: " . $e->getMessage());
        error_log("SQL実行エラー詳細: " . json_encode([
            'guest_id' => $guest_id,
            'checked_by' => $checked_by,
            'notes' => $notes,
            'error_code' => $e->getCode(),
            'error_message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE));
        return false;
    }
}

/**
 * QR設定を取得する
 *
 * @param string $key 設定キー
 * @param mixed $default デフォルト値
 * @return mixed 設定値
 */
function get_qr_setting($key, $default = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT setting_value FROM qr_settings 
            WHERE setting_key = ?
        ");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        
        return $value !== false ? $value : $default;
    } catch (PDOException $e) {
        error_log("QR設定取得エラー: " . $e->getMessage());
        return $default;
    }
} 