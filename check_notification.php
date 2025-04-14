<?php
/**
 * 通知チェック用エンドポイント
 * 
 * ゲストのブラウザがポーリングして新しい通知を確認するためのAPIエンドポイントです。
 * 新しい通知があった場合、クライアントサイドでリダイレクトなどのアクションを実行します。
 */

// 設定ファイルの読み込み
require_once 'config.php';

// キャッシュ防止ヘッダーを送信
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// デバッグ情報をログに出力
error_log("通知チェックAPI呼び出し - IP: " . $_SERVER['REMOTE_ADDR'] . ", トークン: " . ($_GET['token'] ?? 'なし'));

// CORSヘッダーを追加（ローカル環境でのテスト用）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Max-Age: 86400'); // 24時間

// オプションリクエストへの対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 200 OK');
    exit;
}

// GETリクエストのみ受け付ける
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: GET');
    exit;
}

// 必須パラメータのチェック
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$timestamp = isset($_GET['t']) ? $_GET['t'] : time(); // キャッシュ回避用タイムスタンプ

// レスポンス初期化
$response = [
    'success' => false,
    'has_notification' => false,
    'action' => '',
    'message' => '',
    'timestamp' => $timestamp,
    'server_time' => date('Y-m-d H:i:s'),
    'debug' => [
        'token' => $token,
        'time' => date('Y-m-d H:i:s'),
        'site_url' => $site_url,
        'server_info' => $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'],
        'remote_addr' => $_SERVER['REMOTE_ADDR'],
        'request_method' => $_SERVER['REQUEST_METHOD']
    ]
];

// トークンが提供されているか確認
if (empty($token)) {
    $response['message'] = '必須パラメータが不足しています。';
    $response['debug']['error'] = 'トークンが空です';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// 通知の確認
try {
    // push_notificationsテーブルが存在するか確認
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'push_notifications'");
    $tableExists = ($tableCheck->rowCount() > 0);
    $response['debug']['table_exists'] = $tableExists;
    
    if (!$tableExists) {
        // テーブルが存在しない場合はテーブルを作成
        $createTableSql = "
            CREATE TABLE IF NOT EXISTS push_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                guest_id INT NOT NULL,
                token VARCHAR(255) NOT NULL,
                action VARCHAR(50) NOT NULL,
                created_at DATETIME NOT NULL,
                is_delivered TINYINT(1) DEFAULT 0,
                delivered_at DATETIME DEFAULT NULL,
                INDEX (token, is_delivered)
            )
        ";
        $pdo->exec($createTableSql);
        $response['debug']['table_created'] = true;
        error_log("通知テーブルを新規作成しました");
    }
    
    // デバッグ用：トークンの存在確認
    $tokenCheck = $pdo->prepare("SELECT id, name FROM guests WHERE qr_code_token = ?");
    $tokenCheck->execute([$token]);
    $guestData = $tokenCheck->fetch(PDO::FETCH_ASSOC);
    $guestExists = ($guestData !== false);
    $response['debug']['token_valid'] = $guestExists;
    $response['debug']['guest_data'] = $guestData;
    
    if (!$guestExists) {
        error_log("無効なトークン: {$token}");
        $response['debug']['token_error'] = "指定されたトークンに一致するゲストが存在しません";
    }
    
    // 未配信の通知を確認
    $stmt = $pdo->prepare("
        SELECT * FROM push_notifications 
        WHERE token = ? AND is_delivered = 0
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $response['debug']['notification_query_executed'] = true;
    $response['debug']['notification_found'] = ($notification !== false);
    
    if ($notification) {
        // 通知を見つけた場合
        error_log("通知を検出: ID={$notification['id']}, トークン={$token}, アクション={$notification['action']}");
        
        $response['success'] = true;
        $response['has_notification'] = true;
        $response['action'] = $notification['action'];
        $response['message'] = '新しい通知があります。';
        $response['debug']['notification_id'] = $notification['id'];
        $response['debug']['notification_action'] = $notification['action'];
        $response['debug']['notification_created_at'] = $notification['created_at'];
        
        // 通知を配信済みにマーク
        $updateStmt = $pdo->prepare("
            UPDATE push_notifications 
            SET is_delivered = 1, delivered_at = NOW() 
            WHERE id = ?
        ");
        $updateResult = $updateStmt->execute([$notification['id']]);
        $response['debug']['marked_as_delivered'] = $updateResult;
        
        error_log("通知を配信済みにマークしました: ID={$notification['id']}");
    } else {
        // 通知がない場合
        $response['success'] = true;
        $response['message'] = '新しい通知はありません。';
        
        // 最近の通知をチェック（デバッグ用）
        $recentCheck = $pdo->prepare("
            SELECT COUNT(*) as total, 
                   MAX(created_at) as last_created,
                   SUM(is_delivered) as delivered_count
            FROM push_notifications 
            WHERE token = ?
        ");
        $recentCheck->execute([$token]);
        $recentStats = $recentCheck->fetch(PDO::FETCH_ASSOC);
        $response['debug']['notification_stats'] = $recentStats;
    }
    
} catch (PDOException $e) {
    $response['message'] = 'データベースエラーが発生しました。';
    $response['debug']['error'] = $e->getMessage();
    error_log('通知チェックエラー: ' . $e->getMessage());
}

// レスポンスを返す
header('Content-Type: application/json');
echo json_encode($response);
