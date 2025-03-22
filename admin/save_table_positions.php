<?php
// 設定ファイルを読み込み
require_once '../config.php';

// セッション開始
session_start();

// デバッグモードの場合はエラー表示を有効にする
if (isset($debug_mode) && $debug_mode) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// 受信データのログ記録（デバッグ用）
$raw_input = file_get_contents('php://input');
error_log('save_table_positions.php - 受信データ: ' . $raw_input);

// 管理者認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '認証エラー']);
    exit;
}

// JSONデータを受け取る
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!$data) {
    $json_error = json_last_error_msg();
    error_log('JSONデコードエラー: ' . $json_error);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'データが無効です: ' . $json_error]);
    exit;
}

try {
    // 受信データの検証
    if (!isset($data['tables']) || !is_array($data['tables'])) {
        throw new Exception('テーブルデータが見つからないか無効です');
    }
    
    error_log('受信したテーブル数: ' . count($data['tables']));
    
    if (!isset($data['layout'])) {
        throw new Exception('レイアウトデータが見つかりません');
    }
    
    // テーブル構造の確認と必要に応じた作成
    ensureTablesAndColumnsExist($pdo);
    
    // トランザクション開始
    $pdo->beginTransaction();
    
    // テーブル位置情報を保存
    if (isset($data['tables']) && is_array($data['tables'])) {
        $success_count = 0;
        $error_count = 0;
        
        error_log("テーブル位置情報の保存開始: " . count($data['tables']) . "個のテーブル");
        
        foreach ($data['tables'] as $index => $table) {
            if (!isset($table['id'])) {
                error_log("テーブル[$index]: IDが設定されていません");
                continue;
            }
            
            error_log("テーブル[$index]: ID {$table['id']} の処理開始 - 位置: left={$table['left']}, top={$table['top']}");
            
            try {
                // テーブルIDが存在するか確認
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM seating WHERE id = ?");
                $check_stmt->execute([$table['id']]);
                $exists = $check_stmt->fetchColumn() > 0;
                
                if (!$exists) {
                    error_log("テーブル[$index]: ID {$table['id']} はデータベースに存在しません");
                    $error_count++;
                    continue;
                }
                
                // テーブル位置情報を更新
                $stmt = $pdo->prepare("
                    UPDATE seating 
                    SET layout_left = ?, layout_top = ?, layout_width = ?, layout_height = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $table['left'],
                    $table['top'],
                    $table['width'],
                    $table['height'],
                    $table['id']
                ]);
                
                error_log("テーブル[$index]: ID {$table['id']} を正常に更新しました - 位置: left={$table['left']}, top={$table['top']}, width={$table['width']}, height={$table['height']}");
                $success_count++;
            } catch (Exception $e) {
                error_log("テーブル[$index]: ID {$table['id']} の更新中にエラー: " . $e->getMessage());
                $error_count++;
            }
        }
        
        error_log("テーブル更新: 成功 $success_count 件, 失敗 $error_count 件");
    }
    
    // レイアウト全体の情報を保存
    if (isset($data['layout'])) {
        // レイアウト設定があるか確認
        $stmt = $pdo->query("SELECT COUNT(*) FROM layout_settings WHERE id = 1");
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            // 既存のレイアウト設定を更新
            $stmt = $pdo->prepare("
                UPDATE layout_settings 
                SET zoom = ?, translate_x = ?, translate_y = ?, layout_width = ?, layout_height = ?, updated_at = NOW()
                WHERE id = 1
            ");
        } else {
            // 新しいレイアウト設定を挿入
            $stmt = $pdo->prepare("
                INSERT INTO layout_settings 
                (id, zoom, translate_x, translate_y, layout_width, layout_height, created_at, updated_at) 
                VALUES (1, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
        }
        
        $stmt->execute([
            $data['layout']['zoom'],
            $data['layout']['translateX'],
            $data['layout']['translateY'],
            $data['layout']['width'],
            $data['layout']['height']
        ]);
        
        error_log("レイアウト全体の情報を更新しました");
    }
    
    // トランザクションをコミット
    $pdo->commit();
    
    error_log('レイアウト情報を正常に保存しました');
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // エラー時はロールバック
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('save_table_positions.php - エラー: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'エラーが発生しました: ' . $e->getMessage(),
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
            error_log("seatingテーブルに {$column} カラムを追加しました");
        }
    }
    
    // テーブルの一覧をログに出力
    $result = $pdo->query("SELECT id, table_number, table_name FROM seating");
    $tables = $result->fetchAll(PDO::FETCH_ASSOC);
    error_log("データベース内のテーブル数: " . count($tables));
    foreach ($tables as $table) {
        error_log("DB内テーブル: ID {$table['id']}, 番号 {$table['table_number']}, 名前 {$table['table_name']}");
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
        error_log("layout_settingsテーブルを作成しました");
    }
}
?> 