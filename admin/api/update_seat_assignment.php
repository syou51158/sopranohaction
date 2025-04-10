<?php
/**
 * 席次表管理システム - 席割り当て更新API
 * ゲストの席の割り当てを更新するAPI
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
$post_data = json_decode(file_get_contents('php://input'), true);

if (!isset($post_data['guest_id']) || !isset($post_data['table_id']) || !isset($post_data['seat_number'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '必須パラメータが不足しています (guest_id, table_id, seat_number)']);
    exit;
}

$guest_id = $post_data['guest_id'];
$table_id = $post_data['table_id'];
$seat_number = $post_data['seat_number'];

try {
    // データベース接続
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // トランザクション開始
    $db->beginTransaction();
    
    // 指定された席が既に割り当てられているか確認
    $check_query = "SELECT * FROM seating_assignments WHERE table_id = :table_id AND seat_number = :seat_number";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':table_id', $table_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':seat_number', $seat_number, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($existing_assignment = $check_stmt->fetch(PDO::FETCH_ASSOC)) {
        // 既存の割り当てがある場合は削除
        $delete_query = "DELETE FROM seating_assignments WHERE table_id = :table_id AND seat_number = :seat_number";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':table_id', $table_id, PDO::PARAM_INT);
        $delete_stmt->bindParam(':seat_number', $seat_number, PDO::PARAM_INT);
        $delete_stmt->execute();
    }
    
    // ゲストの既存の席割り当てを削除
    $delete_guest_query = "DELETE FROM seating_assignments WHERE guest_id = :guest_id";
    $delete_guest_stmt = $db->prepare($delete_guest_query);
    $delete_guest_stmt->bindParam(':guest_id', $guest_id, PDO::PARAM_INT);
    $delete_guest_stmt->execute();
    
    // 新しい席割り当てを挿入
    $insert_query = "INSERT INTO seating_assignments (guest_id, table_id, seat_number) VALUES (:guest_id, :table_id, :seat_number)";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':guest_id', $guest_id, PDO::PARAM_INT);
    $insert_stmt->bindParam(':table_id', $table_id, PDO::PARAM_INT);
    $insert_stmt->bindParam(':seat_number', $seat_number, PDO::PARAM_INT);
    $insert_stmt->execute();
    
    // トランザクション確定
    $db->commit();
    
    // 成功レスポンス
    echo json_encode([
        'success' => true, 
        'message' => '席の割り当てが更新されました',
        'data' => [
            'guest_id' => $guest_id,
            'table_id' => $table_id,
            'seat_number' => $seat_number
        ]
    ]);
    
} catch (PDOException $e) {
    // トランザクションロールバック
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    // エラーレスポンス
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
} 