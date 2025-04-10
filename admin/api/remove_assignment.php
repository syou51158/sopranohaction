<?php
/**
 * 席次表管理システム - 席割り当て解除API
 * ゲストの席割り当てを解除するAPI
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

// パラメータの確認
if (!isset($data['guest_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ゲストIDが必要です']);
    exit;
}

$guest_id = $data['guest_id'];

try {
    // データベース接続
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 席割り当て情報を取得（削除前に記録するため）
    $select_query = "SELECT * FROM seating_assignments WHERE guest_id = ?";
    $select_stmt = $db->prepare($select_query);
    $select_stmt->execute([$guest_id]);
    $assignment = $select_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        // 割り当てが存在しない場合
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'このゲストには席が割り当てられていません']);
        exit;
    }
    
    // 席割り当てを削除
    $delete_query = "DELETE FROM seating_assignments WHERE guest_id = ?";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->execute([$guest_id]);
    
    // 成功レスポンス
    echo json_encode([
        'success' => true, 
        'message' => '席の割り当てを解除しました',
        'data' => [
            'guest_id' => $guest_id,
            'removed_assignment' => $assignment
        ]
    ]);
    
} catch (PDOException $e) {
    // エラーレスポンス
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
} 