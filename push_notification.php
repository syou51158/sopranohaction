<?php
/**
 * プッシュ通知処理用エンドポイント
 * 
 * QRコードがスキャンされた際に、ゲストのブラウザに通知を送信するための
 * APIエンドポイントを提供します。
 */

// 設定ファイルの読み込み
require_once 'config.php';

// デバッグ情報をログに出力
error_log("プッシュ通知API呼び出し - メソッド: " . $_SERVER['REQUEST_METHOD'] . ", コンテンツタイプ: " . ($_SERVER['CONTENT_TYPE'] ?? 'なし'));
error_log("POST データ: " . print_r($_POST, true));

// CORSヘッダーを追加（ローカル環境でのテスト用）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Max-Age: 86400'); // 24時間

// オプションリクエストへの対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 200 OK');
    exit;
}

// POSTリクエストのみ受け付ける
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: POST');
    exit;
}

// 必須パラメータのチェック
$token = isset($_POST['token']) ? trim($_POST['token']) : '';
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

// レスポンス初期化
$response = [
    'success' => false,
    'message' => '',
    'debug' => [
        'token' => $token,
        'action' => $action,
        'time' => date('Y-m-d H:i:s'),
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'site_url' => $site_url,
        'server_info' => $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'],
        'post_data' => $_POST,
        'get_data' => $_GET,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? '未設定'
    ]
];

// トークンとアクションが提供されているか確認
if (empty($token) || empty($action)) {
    $response['message'] = '必須パラメータが不足しています。';
    $response['debug']['error'] = 'トークンまたはアクションが空です';
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// 通知アクションの処理
try {
    // トークンからゲスト情報を取得
    $stmt = $pdo->prepare("SELECT * FROM guests WHERE qr_code_token = ?");
    $stmt->execute([$token]);
    $guest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $response['debug']['guest_found'] = ($guest !== false);
    $response['debug']['guest_id'] = $guest ? $guest['id'] : null;
    
    if (!$guest) {
        $response['message'] = '無効なトークンです。';
        $response['debug']['error'] = 'ゲストが見つかりません';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // テーブルが存在するか確認
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'push_notifications'");
    $tableExists = ($tableCheck->rowCount() > 0);
    $response['debug']['table_exists'] = $tableExists;
    
    if (!$tableExists) {
        // push_notificationsテーブルがなければ作成
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
    }
    
    // 既存の未配信通知を確認してキャンセル
    $deleteStmt = $pdo->prepare("
        DELETE FROM push_notifications 
        WHERE token = ? AND is_delivered = 0
    ");
    $deleteStmt->execute([$token]);
    $response['debug']['deleted_pending'] = $deleteStmt->rowCount();
    
    // 通知テーブルに記録
    $insertSql = "
        INSERT INTO push_notifications 
        (guest_id, token, action, created_at) 
        VALUES (?, ?, ?, NOW())
    ";
    $stmt = $pdo->prepare($insertSql);
    $insertResult = $stmt->execute([$guest['id'], $token, $action]);
    
    $response['debug']['insert_result'] = $insertResult;
    $response['debug']['last_insert_id'] = $pdo->lastInsertId();
    
    if ($insertResult) {
        $response['success'] = true;
        $response['message'] = '通知が送信キューに追加されました。';
        
        // ログに成功を記録
        error_log("プッシュ通知成功: guest_id={$guest['id']}, token={$token}, action={$action}");
    } else {
        $response['message'] = '通知の保存に失敗しました。';
        $response['debug']['error'] = implode(', ', $stmt->errorInfo());
        
        // ログにエラーを記録
        error_log("プッシュ通知失敗: " . $response['debug']['error']);
    }
    
} catch (PDOException $e) {
    $response['message'] = 'データベースエラーが発生しました。';
    $response['debug']['error'] = $e->getMessage();
    error_log('Push通知エラー: ' . $e->getMessage());
}

// レスポンスを返す
header('Content-Type: application/json');
echo json_encode($response);
