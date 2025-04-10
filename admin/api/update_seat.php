<?php
/**
 * 席次表管理システム - 席更新API
 * 特定の席にゲストを割り当てるAPI
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

if (!isset($input['table_id']) || !isset($input['seat_number']) || !isset($input['guest_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '必要なパラメータが不足しています']);
    exit;
}

$table_id = intval($input['table_id']);
$seat_number = intval($input['seat_number']);
$guest_id = intval($input['guest_id']);

try {
    // データベース接続
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // トランザクション開始
    $db->beginTransaction();
    
    // ゲストが既に別の席に割り当てられているか確認
    $stmt = $db->prepare("SELECT * FROM seating_assignments WHERE guest_id = :guest_id");
    $stmt->bindParam(':guest_id', $guest_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $existing_assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 既存の割り当てがある場合は削除
    if ($existing_assignment) {
        $stmt = $db->prepare("DELETE FROM seating_assignments WHERE guest_id = :guest_id");
        $stmt->bindParam(':guest_id', $guest_id, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // 指定された席に既に別のゲストが割り当てられているか確認
    $stmt = $db->prepare("SELECT * FROM seating_assignments WHERE table_id = :table_id AND seat_number = :seat_number");
    $stmt->bindParam(':table_id', $table_id, PDO::PARAM_INT);
    $stmt->bindParam(':seat_number', $seat_number, PDO::PARAM_INT);
    $stmt->execute();
    
    $existing_seat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 既に席が埋まっている場合は削除
    if ($existing_seat) {
        $stmt = $db->prepare("DELETE FROM seating_assignments WHERE table_id = :table_id AND seat_number = :seat_number");
        $stmt->bindParam(':table_id', $table_id, PDO::PARAM_INT);
        $stmt->bindParam(':seat_number', $seat_number, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    // 新しい席割り当てを挿入
    $stmt = $db->prepare("INSERT INTO seating_assignments (guest_id, table_id, seat_number) VALUES (:guest_id, :table_id, :seat_number)");
    $stmt->bindParam(':guest_id', $guest_id, PDO::PARAM_INT);
    $stmt->bindParam(':table_id', $table_id, PDO::PARAM_INT);
    $stmt->bindParam(':seat_number', $seat_number, PDO::PARAM_INT);
    $stmt->execute();
    
    // トランザクションをコミット
    $db->commit();
    
    // 成功レスポンス
    echo json_encode(['success' => true, 'message' => '席が更新されました']);
    
} catch (PDOException $e) {
    // エラーが発生した場合はロールバック
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    // エラーレスポンス
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
} 