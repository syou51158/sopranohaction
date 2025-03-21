<?php
// 設定ファイルを読み込み
require_once 'config.php';

// SQLファイルを読み込み
$sql = file_get_contents('create_companions_table.sql');

try {
    // テーブル作成のSQLを実行
    $pdo->exec($sql);
    echo "同伴者情報テーブルが正常に作成されました。\n";
} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage() . "\n";
}
?> 