<?php
/**
 * 席次表管理システム - ゲスト情報取得API
 * ゲストIDを指定して情報を取得するAPI
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
if (!isset($_GET['guest_id']) || empty($_GET['guest_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ゲストIDが指定されていません']);
    exit;
}

$guest_id = intval($_GET['guest_id']);

try {
    // データベース接続
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ゲスト情報を取得
    $stmt = $db->prepare("
        SELECT g.id, g.name, g.email, g.attendance, g.group_name
        FROM guests g
        WHERE g.id = :guest_id
    ");
    
    $stmt->bindParam(':guest_id', $guest_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $guest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$guest) {
        // ゲストが見つからない場合
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '指定されたゲストが見つかりません']);
        exit;
    }
    
    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'data' => $guest
    ]);
    
} catch (PDOException $e) {
    // エラーレスポンス
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
} 