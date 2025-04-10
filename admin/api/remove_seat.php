<?php
/**
 * 席次表管理システム - 座席割り当て解除API
 * 特定の座席に割り当てられたゲストの割り当てを解除するAPI
 */

// 設定ファイルの読み込み
require_once '../../config.php';

// セッション開始
session_start();

// 管理者権限のチェック
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'アクセス権限がありません']);
    exit;
}

// POSTメソッドのチェック
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POSTメソッドが必要です']);
    exit;
}

// リクエストデータの取得
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// パラメータ検証
if (
    (!isset($data['guest_id']) && (!isset($data['table_number']) || !isset($data['seat_number']))) ||
    (isset($data['guest_id']) && isset($data['table_number']) && isset($data['seat_number']))
) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'guest_idまたは(table_number, seat_number)のいずれかのパラメータセットが必要です'
    ]);
    exit;
}

try {
    // データベース接続
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // トランザクション開始
    $db->beginTransaction();

    if (isset($data['guest_id'])) {
        // ゲストIDによる削除
        $guest_id = intval($data['guest_id']);
        
        // 削除前に座席情報を取得
        $stmt = $db->prepare("
            SELECT table_number, seat_number 
            FROM seats 
            WHERE guest_id = ?
        ");
        $stmt->execute([$guest_id]);
        $seat_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 座席からゲストを削除
        $stmt = $db->prepare("DELETE FROM seats WHERE guest_id = ?");
        $stmt->execute([$guest_id]);
        
        // 成功メッセージとデータを準備
        $message = 'ゲストの座席割り当てを解除しました';
        $result_data = [
            'guest_id' => $guest_id
        ];
        
        // 座席情報があれば追加
        if ($seat_info) {
            $result_data['table_number'] = $seat_info['table_number'];
            $result_data['seat_number'] = $seat_info['seat_number'];
        }
    } else {
        // テーブル番号と座席番号による削除
        $table_number = intval($data['table_number']);
        $seat_number = intval($data['seat_number']);
        
        // 削除前にゲストIDを取得
        $stmt = $db->prepare("
            SELECT guest_id 
            FROM seats 
            WHERE table_number = ? AND seat_number = ?
        ");
        $stmt->execute([$table_number, $seat_number]);
        $seat_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 座席からゲストを削除
        $stmt = $db->prepare("
            DELETE FROM seats 
            WHERE table_number = ? AND seat_number = ?
        ");
        $stmt->execute([$table_number, $seat_number]);
        
        // 成功メッセージとデータを準備
        $message = '座席の割り当てを解除しました';
        $result_data = [
            'table_number' => $table_number,
            'seat_number' => $seat_number
        ];
        
        // ゲスト情報があれば追加
        if ($seat_info) {
            $result_data['guest_id'] = $seat_info['guest_id'];
        }
    }

    // トランザクションをコミット
    $db->commit();
    
    // 成功レスポンスを返す
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $result_data
    ]);
    
} catch (PDOException $e) {
    // エラー発生時はロールバック
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    // エラーレスポンス
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
} 