<?php
/**
 * 席次表管理システム - 席割り当て削除API
 * ゲストの席の割り当てを削除するAPI
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

if (!isset($post_data['guest_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '必須パラメータが不足しています (guest_id)']);
    exit;
}

$guest_id = $post_data['guest_id'];

try {
    // データベース接続
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ゲストの席割り当て情報を取得（削除前の情報をレスポンスに含めるため）
    $select_query = "SELECT * FROM seating_assignments WHERE guest_id = :guest_id";
    $select_stmt = $db->prepare($select_query);
    $select_stmt->bindParam(':guest_id', $guest_id, PDO::PARAM_INT);
    $select_stmt->execute();
    $assignment = $select_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        // 割り当てが見つからない場合
        echo json_encode([
            'success' => false, 
            'message' => '指定されたゲストの席割り当てが見つかりません'
        ]);
        exit;
    }
    
    // ゲストの席割り当てを削除
    $delete_query = "DELETE FROM seating_assignments WHERE guest_id = :guest_id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':guest_id', $guest_id, PDO::PARAM_INT);
    $delete_stmt->execute();
    
    // 成功レスポンス
    echo json_encode([
        'success' => true, 
        'message' => '席の割り当てが削除されました',
        'data' => $assignment
    ]);
    
} catch (PDOException $e) {
    // エラーレスポンス
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
} 