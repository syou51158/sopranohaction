<?php
/**
 * 席次表管理システム - 座席割り当て解除API
 * 特定の座席またはゲストの割り当てを解除するAPI
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

// POSTメソッドのチェック
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POSTメソッドが必要です']);
    exit;
}

// パラメータ取得（guest_idまたはtable_number+seat_numberのいずれかが必要）
$guest_id = filter_input(INPUT_POST, 'guest_id', FILTER_VALIDATE_INT);
$table_number = filter_input(INPUT_POST, 'table_number', FILTER_VALIDATE_INT);
$seat_number = filter_input(INPUT_POST, 'seat_number', FILTER_VALIDATE_INT);

// パラメータの検証
if (!$guest_id && (!$table_number || !$seat_number)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'guest_id、またはtable_numberとseat_numberの組み合わせが必要です'
    ]);
    exit;
}

try {
    // データベース接続
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // トランザクション開始
    $db->beginTransaction();
    
    // 座席情報を削除するためのクエリを準備
    if ($guest_id) {
        // ゲストIDによる削除
        $stmt = $db->prepare("DELETE FROM seats WHERE guest_id = :guest_id");
        $stmt->bindParam(':guest_id', $guest_id, PDO::PARAM_INT);
        $identifier = "ゲストID: " . $guest_id;
    } else {
        // テーブル番号と座席番号による削除
        $stmt = $db->prepare("DELETE FROM seats WHERE table_number = :table_number AND seat_number = :seat_number");
        $stmt->bindParam(':table_number', $table_number, PDO::PARAM_INT);
        $stmt->bindParam(':seat_number', $seat_number, PDO::PARAM_INT);
        $identifier = "テーブル: " . $table_number . ", 座席: " . $seat_number;
    }
    
    // クエリ実行
    $stmt->execute();
    
    // 削除された行数を取得
    $affected_rows = $stmt->rowCount();
    
    // トランザクションをコミット
    $db->commit();
    
    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'message' => '座席割り当てを解除しました: ' . $identifier,
        'affected_rows' => $affected_rows,
        'data' => [
            'guest_id' => $guest_id,
            'table_number' => $table_number,
            'seat_number' => $seat_number
        ]
    ]);
    
} catch (PDOException $e) {
    // エラー時はロールバック
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    // エラーレスポンス
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
} 