<?php
// データベース接続とエラーログの取得
require_once('../config.php');
require_once('../includes/db_functions.php');
require_once('../includes/logging_functions.php');

// CORS設定 - 開発環境では適切に調整
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// トークンが提供されているか確認
if (!isset($_GET['token']) || empty($_GET['token'])) {
    echo json_encode([
        'success' => false,
        'message' => 'トークンが指定されていません'
    ]);
    exit;
}

// トークンの取得と基本的なサニタイズ
$token = trim($_GET['token']);

try {
    // データベース接続
    $conn = get_db_connection();
    
    // トークンに対応するグループIDを取得
    $stmt = $conn->prepare("
        SELECT g.id AS group_id
        FROM guests AS gu
        JOIN guest_groups AS g ON gu.group_id = g.id
        WHERE gu.qr_token = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'group_id' => $row['group_id']
        ]);
    } else {
        // トークンに対応するゲストが見つからない場合
        echo json_encode([
            'success' => false,
            'message' => '指定されたトークンに対応するグループが見つかりませんでした'
        ]);
        log_error("API: トークン {$token} に対応するゲストグループが見つかりません");
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    // エラーの場合
    log_error("API エラー: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラーが発生しました',
        'error' => DEBUG ? $e->getMessage() : 'エラーが発生しました'
    ]);
}
?> 