<?php
// 設定ファイルを読み込み
require_once 'config.php';

// SQLファイルを読み込み
$sql = file_get_contents('create_seating_tables.sql');

try {
    // 複数のSQL文を実行するため、セミコロンで分割
    $queries = explode(';', $sql);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;
        
        // 各クエリを実行
        $pdo->exec($query);
    }
    
    echo "席次表管理用のテーブルが正常に作成されました。\n";
} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage() . "\n";
}
?> 