<?php
// セッションを開始
session_start();

// 設定ファイルを読み込み
require_once '../config.php';

// 認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['error' => '認証エラー']);
    exit;
}

// リクエストパラメータを取得
$table_id = isset($_GET['table_id']) ? intval($_GET['table_id']) : null;
$seat_number = isset($_GET['seat_number']) ? intval($_GET['seat_number']) : null;

if (!$table_id || !$seat_number) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'パラメータ不足']);
    exit;
}

try {
    // 座席データを取得
    $stmt = $pdo->prepare("
        SELECT sa.*, 
               COALESCE(sa.layer_text, r.title, c.title, '肩書') AS display_title,
               CASE 
                   WHEN sa.is_companion = 1 THEN c.name
                   ELSE r.name
               END AS display_name
        FROM seat_assignments sa
        LEFT JOIN responses r ON sa.response_id = r.id
        LEFT JOIN companions c ON sa.companion_id = c.id
        WHERE sa.table_id = ? AND sa.seat_number = ?
    ");
    
    $stmt->execute([$table_id, $seat_number]);
    $seat_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    if ($seat_data) {
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $seat_data['id'],
                'title' => $seat_data['display_title'],
                'name' => $seat_data['display_name'],
                'is_companion' => $seat_data['is_companion'],
                'response_id' => $seat_data['response_id'],
                'companion_id' => $seat_data['companion_id']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '座席データが見つかりません'
        ]);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー',
        'debug' => $debug_mode ? $e->getMessage() : null
    ]);
} 