<?php
/**
 * 席次表管理システム - 席情報取得API
 * テーブル番号と席番号を指定して席情報を取得するAPI
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

// GETパラメータのチェック
if (!isset($_GET['table_number']) || !isset($_GET['seat_number'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'テーブル番号と席番号が必要です']);
    exit;
}

$table_number = intval($_GET['table_number']);
$seat_number = intval($_GET['seat_number']);

try {
    // データベース接続
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 席に割り当てられたゲスト情報を取得
    $stmt = $db->prepare("
        SELECT g.id, g.name, g.email, g.attendance, g.group_name,
               s.table_number, s.seat_number
        FROM seats s
        JOIN guests g ON s.guest_id = g.id
        WHERE s.table_number = :table_number AND s.seat_number = :seat_number
    ");
    
    $stmt->bindParam(':table_number', $table_number, PDO::PARAM_INT);
    $stmt->bindParam(':seat_number', $seat_number, PDO::PARAM_INT);
    $stmt->execute();
    
    $seat_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$seat_info) {
        // 席に割り当てられたゲストがない場合
        echo json_encode([
            'success' => true,
            'is_assigned' => false,
            'data' => [
                'table_number' => $table_number,
                'seat_number' => $seat_number
            ]
        ]);
    } else {
        // 席にゲストが割り当てられている場合
        echo json_encode([
            'success' => true,
            'is_assigned' => true,
            'data' => $seat_info
        ]);
    }
    
} catch (PDOException $e) {
    // エラーレスポンス
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
} 