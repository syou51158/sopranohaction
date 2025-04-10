<?php
/**
 * API エンドポイント: 座席データ取得
 * 
 * 席次表に必要なデータを取得するAPIエンドポイント
 * 
 * @return JSON - 処理結果と座席データ
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

// GETリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'メソッドが許可されていません']);
    exit;
}

try {
    $db = new PDO(DB_DSN, DB_USER, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 出席するゲスト情報を取得
    $stmt = $db->query("
        SELECT 
            g.id,
            g.name,
            g.name_kana,
            g.email,
            g.status,
            g.group_id,
            gr.name as group_name,
            COALESCE(sa.table_number, 0) as table_number,
            COALESCE(sa.seat_number, 0) as seat_number,
            CASE WHEN sa.guest_id IS NOT NULL THEN 1 ELSE 0 END as is_seated
        FROM 
            guests g
        LEFT JOIN 
            `groups` gr ON g.group_id = gr.id
        LEFT JOIN 
            seat_assignments sa ON g.id = sa.guest_id
        WHERE 
            g.status = 'attending'
        ORDER BY 
            gr.id, g.name_kana
    ");
    
    $guests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // テーブル情報を取得（テーブルごとの席数など）
    $tables = [];
    $stmt = $db->query("
        SELECT 
            table_number,
            COUNT(*) as assigned_seats
        FROM 
            seat_assignments
        GROUP BY 
            table_number
    ");
    
    $tableOccupancy = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // テーブルごとの席数を集計
    foreach ($tableOccupancy as $table) {
        $tables[$table['table_number']] = intval($table['assigned_seats']);
    }
    
    // グループ情報を取得
    $stmt = $db->query("
        SELECT 
            id,
            name,
            description
        FROM 
            `groups`
        ORDER BY 
            id
    ");
    
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 割り当て済みの人数と未割り当ての人数を計算
    $seatedGuests = array_filter($guests, function($guest) {
        return $guest['is_seated'] == 1;
    });
    
    $unseatedGuests = array_filter($guests, function($guest) {
        return $guest['is_seated'] == 0;
    });
    
    // 状態別のゲスト数を集計
    $guestStats = [
        'total' => count($guests),
        'seated' => count($seatedGuests),
        'unseated' => count($unseatedGuests)
    ];
    
    // 最終的な結果を作成
    $result = [
        'success' => true,
        'data' => [
            'guests' => $guests,
            'tables' => $tables,
            'groups' => $groups,
            'stats' => $guestStats
        ]
    ];
    
    // JSON形式で結果を返す
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'データベースエラーが発生しました', 
        'error' => DEBUG_MODE ? $e->getMessage() : null
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '予期せぬエラーが発生しました', 
        'error' => DEBUG_MODE ? $e->getMessage() : null
    ]);
} 