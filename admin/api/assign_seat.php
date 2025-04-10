<?php
/**
 * API エンドポイント: 席の割り当て
 * 
 * ゲストをテーブルと席に割り当てるためのAPIエンドポイント
 * 
 * @param int guest_id - 割り当てるゲストのID
 * @param int table_number - テーブル番号
 * @param int seat_number - 席番号
 * @return JSON - 処理結果
 */

// 必要なファイルのインクルード
require_once '../../config.php';
require_once BASEPATH . '/lib/functions.php';
require_once BASEPATH . '/lib/auth.php';

// セッション開始
session_start();

// 管理者権限チェック
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '権限がありません']);
    exit;
}

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'メソッドが許可されていません']);
    exit;
}

// パラメータの取得と検証
$guestId = filter_input(INPUT_POST, 'guest_id', FILTER_VALIDATE_INT);
$tableNumber = filter_input(INPUT_POST, 'table_number', FILTER_VALIDATE_INT);
$seatNumber = filter_input(INPUT_POST, 'seat_number', FILTER_VALIDATE_INT);

// 必須パラメータの検証
if (!$guestId || !$tableNumber || !$seatNumber) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'パラメータが不足しています', 
        'data' => [
            'guest_id' => $guestId,
            'table_number' => $tableNumber,
            'seat_number' => $seatNumber
        ]
    ]);
    exit;
}

try {
    $db = new PDO(DB_DSN, DB_USER, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // トランザクション開始
    $db->beginTransaction();
    
    // ゲストが存在するか確認
    $stmt = $db->prepare("SELECT id, name FROM guests WHERE id = :guest_id");
    $stmt->bindParam(':guest_id', $guestId);
    $stmt->execute();
    $guest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$guest) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '指定されたゲストが見つかりません']);
        exit;
    }
    
    // 席が既に割り当てられているか確認
    $stmt = $db->prepare("SELECT g.id, g.name FROM seat_assignments sa 
                         JOIN guests g ON sa.guest_id = g.id 
                         WHERE sa.table_number = :table_number AND sa.seat_number = :seat_number");
    $stmt->bindParam(':table_number', $tableNumber);
    $stmt->bindParam(':seat_number', $seatNumber);
    $stmt->execute();
    $existingAssignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingAssignment) {
        // 席が既に別のゲストに割り当てられている場合は古い割り当てを削除
        $stmt = $db->prepare("DELETE FROM seat_assignments 
                             WHERE table_number = :table_number AND seat_number = :seat_number");
        $stmt->bindParam(':table_number', $tableNumber);
        $stmt->bindParam(':seat_number', $seatNumber);
        $stmt->execute();
    }
    
    // ゲストの既存の席の割り当てを削除
    $stmt = $db->prepare("DELETE FROM seat_assignments WHERE guest_id = :guest_id");
    $stmt->bindParam(':guest_id', $guestId);
    $stmt->execute();
    
    // 新しい席の割り当てを挿入
    $stmt = $db->prepare("INSERT INTO seat_assignments (guest_id, table_number, seat_number) 
                         VALUES (:guest_id, :table_number, :seat_number)");
    $stmt->bindParam(':guest_id', $guestId);
    $stmt->bindParam(':table_number', $tableNumber);
    $stmt->bindParam(':seat_number', $seatNumber);
    $stmt->execute();
    
    // トランザクションのコミット
    $db->commit();
    
    // 成功レスポンスの返却
    echo json_encode([
        'success' => true, 
        'message' => '席の割り当てが完了しました',
        'data' => [
            'guest' => $guest,
            'table_number' => $tableNumber,
            'seat_number' => $seatNumber,
            'previous_assignment' => $existingAssignment ? [
                'guest_id' => $existingAssignment['id'],
                'guest_name' => $existingAssignment['name']
            ] : null
        ]
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'データベースエラーが発生しました', 
        'error' => DEBUG_MODE ? $e->getMessage() : null
    ]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '予期せぬエラーが発生しました', 
        'error' => DEBUG_MODE ? $e->getMessage() : null
    ]);
} 