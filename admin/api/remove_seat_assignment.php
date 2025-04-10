<?php
/**
 * 席次表管理システム - 席割り当て解除API
 * 特定の席のゲスト割り当てを解除するAPI
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

// POSTリクエストの確認
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POSTメソッドのみ許可されています']);
    exit;
}

// 必要なパラメータのチェック
$input = json_decode(file_get_contents('php://input'), true);

// テーブルIDと席番号、またはゲストIDのいずれかが必要
if ((!isset($input['table_id']) || !isset($input['seat_number'])) && !isset($input['guest_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '必要なパラメータが不足しています']);
    exit;
}

try {
    // データベース接続
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ゲストIDで削除する場合
    if (isset($input['guest_id'])) {
        $guest_id = intval($input['guest_id']);
        
        $stmt = $db->prepare("DELETE FROM seating_assignments WHERE guest_id = :guest_id");
        $stmt->bindParam(':guest_id', $guest_id, PDO::PARAM_INT);
        $stmt->execute();
    } 
    // テーブルIDと席番号で削除する場合
    else {
        $table_id = intval($input['table_id']);
        $seat_number = intval($input['seat_number']);
        
        $stmt = $db->prepare("DELETE FROM seating_assignments WHERE table_id = :table_id AND seat_number = :seat_number");
        $stmt->bindParam(':table_id', $table_id, PDO::PARAM_INT);
        $stmt->bindParam(':seat_number', $seat_number, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // 削除された行数を確認
    $rowCount = $stmt->rowCount();
    
    if ($rowCount > 0) {
        echo json_encode(['success' => true, 'message' => '席の割り当てが解除されました']);
    } else {
        echo json_encode(['success' => true, 'message' => '該当する席の割り当てはありませんでした']);
    }
    
} catch (PDOException $e) {
    // エラーレスポンス
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
} 