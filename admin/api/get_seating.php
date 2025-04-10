<?php
/**
 * 席次表管理システム - 座席情報取得API
 * 現在の座席割り当て状況を取得するAPI
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

// GETメソッドのチェック
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'GETメソッドが必要です']);
    exit;
}

try {
    // データベース接続
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 席情報を取得（ゲスト情報も結合）
    $stmt = $db->prepare("
        SELECT s.guest_id, s.table_number, s.seat_number, 
               g.name, g.email, g.group_id, g.attendance_status, g.plus_one
        FROM seats s
        JOIN guests g ON s.guest_id = g.id
        ORDER BY s.table_number, s.seat_number
    ");
    $stmt->execute();
    $seats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 未割り当てのゲスト情報を取得（参加するゲストのみ）
    $stmt = $db->prepare("
        SELECT g.id as guest_id, g.name, g.email, g.group_id, g.attendance_status, g.plus_one
        FROM guests g
        LEFT JOIN seats s ON g.id = s.guest_id
        WHERE s.guest_id IS NULL 
        AND g.attendance_status = 'attending'
        ORDER BY g.name
    ");
    $stmt->execute();
    $unassigned_guests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // グループ情報を取得
    $stmt = $db->prepare("
        SELECT id, name 
        FROM guest_groups
        ORDER BY id
    ");
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // グループIDをキーとしたマッピングを作成
    $group_map = [];
    foreach ($groups as $group) {
        $group_map[$group['id']] = $group['name'];
    }
    
    // 席ごとのゲストデータを整形
    $seating_data = [];
    foreach ($seats as $seat) {
        // グループ名を追加
        $seat['group_name'] = isset($group_map[$seat['group_id']]) ? $group_map[$seat['group_id']] : '';
        
        // テーブルと席の組み合わせをキーにしてデータを整理
        $key = $seat['table_number'] . '-' . $seat['seat_number'];
        $seating_data[$key] = $seat;
    }
    
    // 未割り当てゲストにもグループ名を追加
    foreach ($unassigned_guests as &$guest) {
        $guest['group_name'] = isset($group_map[$guest['group_id']]) ? $group_map[$guest['group_id']] : '';
    }
    
    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'seating_data' => $seating_data,
        'unassigned_guests' => $unassigned_guests
    ]);
    
} catch (PDOException $e) {
    // エラーレスポンス
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
} 