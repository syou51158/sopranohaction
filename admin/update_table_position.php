<?php
// 設定ファイルを読み込み
require_once '../config.php';

// セッション開始
session_start();

// 管理者認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '認証エラー']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['table_id'], $_POST['position'])) {
    $table_id = (int)$_POST['table_id'];
    $position = trim($_POST['position']);
    
    try {
        $stmt = $pdo->prepare("UPDATE seating SET position = ? WHERE id = ?");
        $stmt->execute([$position, $table_id]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'データベースエラー']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'パラメータが不足しています']);
} 