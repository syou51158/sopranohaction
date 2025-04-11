<?php
// 設定ファイルを読み込み
require_once '../../config.php';

// セッション開始
session_start();

// 管理者認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => '認証エラー']);
    exit;
}

// レスポンスヘッダーを設定
header('Content-Type: application/json');

// POSTデータがない場合はエラー
if (!isset($_POST['positions']) || empty($_POST['positions'])) {
    echo json_encode(['success' => false, 'message' => 'データが不正です']);
    exit;
}

try {
    // JSONデータをデコード
    $positions = json_decode($_POST['positions'], true);
    
    if (!is_array($positions)) {
        throw new Exception('不正なデータ形式です');
    }
    
    // レイアウト情報を保存するための列を確認・追加
    $columns = ['layout_left', 'layout_top', 'layout_width', 'layout_height'];
    $tableName = 'seating'; // テーブル名は固定
    $allowedColumns = ['layout_left', 'layout_top', 'layout_width', 'layout_height']; // 許可するカラム名リスト

    foreach ($columns as $column) {
        if (!in_array($column, $allowedColumns)) {
            continue; // 不正なカラム名はスキップ
        }

        $stmt = $pdo->prepare("SHOW COLUMNS FROM `" . $tableName . "` LIKE ?");
        $stmt->execute([$column]);

        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `" . $tableName . "` ADD COLUMN `" . $column . "` VARCHAR(50)");
        }
    }
    
    // トランザクション開始
    $pdo->beginTransaction();
    
    // 各テーブルの位置情報を更新
    foreach ($positions as $position) {
        if (!isset($position['table_id']) || empty($position['table_id'])) {
            continue;
        }
        
        $table_id = (int)$position['table_id'];
        $left = isset($position['left']) ? $position['left'] : null;
        $top = isset($position['top']) ? $position['top'] : null;
        $width = isset($position['width']) ? $position['width'] : null;
        $height = isset($position['height']) ? $position['height'] : null;
        
        $stmt = $pdo->prepare("UPDATE seating SET 
                               layout_left = ?, 
                               layout_top = ?, 
                               layout_width = ?, 
                               layout_height = ? 
                               WHERE id = ?");
        $stmt->execute([$left, $top, $width, $height, $table_id]);
    }
    
    // コミット
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'レイアウトを保存しました']);
    
} catch (Exception $e) {
    // エラー発生時はロールバック
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $message = 'テーブル位置の保存に失敗しました';
    if ($debug_mode) {
        $message .= ': ' . $e->getMessage();
    }
    
    echo json_encode(['success' => false, 'message' => $message]);
}   