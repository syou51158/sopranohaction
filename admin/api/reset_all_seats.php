<?php
/**
 * 席次表管理システム - 全座席割り当てリセットAPI
 * すべての座席割り当てを一括で解除するAPI
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

// 確認パラメータのチェック
$confirm = filter_input(INPUT_POST, 'confirm', FILTER_VALIDATE_BOOLEAN);
if (!$confirm) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '確認パラメータが必要です']);
    exit;
}

try {
    // データベース接続
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // トランザクション開始
    $db->beginTransaction();
    
    // 現在の割り当て数を取得
    $stmt = $db->query("SELECT COUNT(*) FROM seats");
    $before_count = $stmt->fetchColumn();
    
    // 全ての座席割り当てを削除
    $stmt = $db->prepare("TRUNCATE TABLE seats");
    $stmt->execute();
    
    // トランザクションをコミット
    $db->commit();
    
    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'message' => 'すべての座席割り当てをリセットしました',
        'data' => [
            'removed_assignments' => $before_count
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