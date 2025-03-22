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

// レスポンスヘッダの設定
header('Content-Type: application/json');

try {
    // 必要なテーブルとカラムが存在するか確認
    ensureTablesAndColumnsExist($pdo);
    
    // レイアウト設定の取得
    $layout_settings = null;
    $stmt = $pdo->query("SELECT * FROM layout_settings WHERE id = 1");
    if ($stmt) {
        $layout_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // テーブルの位置情報を取得
    $tables = [];
    $stmt = $pdo->query("
        SELECT id, table_number, table_name, capacity, position, 
               layout_left, layout_top, layout_width, layout_height 
        FROM seating 
        ORDER BY table_number
    ");
    if ($stmt) {
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // レスポンスデータの構築
    $response = [
        'success' => true,
        'layout' => $layout_settings ? [
            'zoom' => (float)$layout_settings['zoom'],
            'translateX' => (float)$layout_settings['translate_x'],
            'translateY' => (float)$layout_settings['translate_y'],
            'width' => (int)$layout_settings['layout_width'],
            'height' => (int)$layout_settings['layout_height']
        ] : null,
        'tables' => $tables
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'データベースエラー',
        'error' => $debug_mode ? $e->getMessage() : null
    ]);
}

/**
 * 必要なテーブルとカラムが存在することを確認し、存在しなければ作成します
 */
function ensureTablesAndColumnsExist($pdo) {
    // seatingテーブルに必要なカラムを確認・追加
    $columns = $pdo->query("SHOW COLUMNS FROM seating");
    $columnNames = [];
    while ($column = $columns->fetch(PDO::FETCH_ASSOC)) {
        $columnNames[] = $column['Field'];
    }
    
    // 必要なカラムを追加
    $requiredColumns = [
        'layout_left' => 'VARCHAR(20)',
        'layout_top' => 'VARCHAR(20)',
        'layout_width' => 'VARCHAR(20)',
        'layout_height' => 'VARCHAR(20)'
    ];
    
    foreach ($requiredColumns as $column => $type) {
        if (!in_array($column, $columnNames)) {
            $pdo->exec("ALTER TABLE seating ADD COLUMN {$column} {$type}");
        }
    }
    
    // layout_settingsテーブルの存在確認
    $tables = $pdo->query("SHOW TABLES LIKE 'layout_settings'");
    if ($tables->rowCount() == 0) {
        // layout_settingsテーブルが存在しない場合は作成
        $pdo->exec("
            CREATE TABLE layout_settings (
                id INT PRIMARY KEY,
                zoom FLOAT DEFAULT 1.0,
                translate_x FLOAT DEFAULT 0,
                translate_y FLOAT DEFAULT 0,
                layout_width INT DEFAULT 1500,
                layout_height INT DEFAULT 1000,
                created_at DATETIME,
                updated_at DATETIME
            )
        ");
    }
}
?> 