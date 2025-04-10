<?php
/**
 * 席次表管理システム - 席割り当て更新API
 * ゲストの席割り当て情報を更新するAPI
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

// JSONデータの取得
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// データ検証
if (!isset($data['guest_id']) || !isset($data['table_number']) || !isset($data['seat_number'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '必須パラメータが不足しています']);
    exit;
}

$guest_id = $data['guest_id'];
$table_number = $data['table_number'];
$seat_number = $data['seat_number'];

try {
    // データベース接続
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // トランザクション開始
    $db->beginTransaction();
    
    // 1. 指定された席に既に割り当てられているゲストがいるか確認
    $check_query = "SELECT guest_id FROM seating_assignments WHERE table_number = :table_number AND seat_number = :seat_number";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':table_number', $table_number, PDO::PARAM_INT);
    $check_stmt->bindParam(':seat_number', $seat_number, PDO::PARAM_INT);
    $check_stmt->execute();
    $existing_assignment = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 既に席が割り当てられている場合、その割り当てを削除
    if ($existing_assignment) {
        $delete_query = "DELETE FROM seating_assignments WHERE table_number = :table_number AND seat_number = :seat_number";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':table_number', $table_number, PDO::PARAM_INT);
        $delete_stmt->bindParam(':seat_number', $seat_number, PDO::PARAM_INT);
        $delete_stmt->execute();
    }
    
    // 2. 対象ゲストの既存の席割り当てを削除
    $remove_query = "DELETE FROM seating_assignments WHERE guest_id = :guest_id";
    $remove_stmt = $db->prepare($remove_query);
    $remove_stmt->bindParam(':guest_id', $guest_id, PDO::PARAM_INT);
    $remove_stmt->execute();
    
    // 3. 新しい席割り当てを挿入
    $insert_query = "INSERT INTO seating_assignments (guest_id, table_number, seat_number) VALUES (:guest_id, :table_number, :seat_number)";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':guest_id', $guest_id, PDO::PARAM_INT);
    $insert_stmt->bindParam(':table_number', $table_number, PDO::PARAM_INT);
    $insert_stmt->bindParam(':seat_number', $seat_number, PDO::PARAM_INT);
    $insert_stmt->execute();
    
    // トランザクションをコミット
    $db->commit();
    
    // 成功レスポンス
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => '席割り当てが更新されました',
        'data' => [
            'guest_id' => $guest_id,
            'table_number' => $table_number, 
            'seat_number' => $seat_number
        ]
    ]);
    
} catch (PDOException $e) {
    // トランザクションをロールバック
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    // エラーレスポンス
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
} 