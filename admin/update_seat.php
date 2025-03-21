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

// POSTリクエストのみ処理
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => '不正なリクエスト']);
    exit;
}

// 処理オプション
$action = isset($_POST['action']) ? $_POST['action'] : '';

// 層書きを肩書に一括修正
if ($action === 'fix_layertext') {
    try {
        // 層書きを肩書に修正
        $stmt = $pdo->prepare("UPDATE seat_assignments SET layer_text = '肩書' WHERE layer_text = '層書き'");
        $stmt->execute();
        
        $affected = $stmt->rowCount();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "{$affected}件の「層書き」を「肩書」に修正しました。",
            'affected' => $affected
        ]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'データベースエラー',
            'debug' => $debug_mode ? $e->getMessage() : null
        ]);
    }
    exit;
}

// 個別の座席データ更新
if ($action === 'update_seat') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $layer_text = isset($_POST['layer_text']) ? $_POST['layer_text'] : '肩書';
    
    // 層書きを肩書に自動修正
    if ($layer_text === '層書き') {
        $layer_text = '肩書';
    }
    
    if (!$id) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'パラメータ不足']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE seat_assignments SET layer_text = ? WHERE id = ?");
        $stmt->execute([$layer_text, $id]);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "座席情報を更新しました。",
            'id' => $id,
            'layer_text' => $layer_text
        ]);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'データベースエラー',
            'debug' => $debug_mode ? $e->getMessage() : null
        ]);
    }
    exit;
}

// アクションが指定されていない場合
header('Content-Type: application/json');
echo json_encode(['error' => 'アクションが指定されていません']);
exit; 