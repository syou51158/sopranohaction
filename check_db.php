<?php
// 設定ファイルを読み込み
require_once 'config.php';

// responsesテーブルの構造を確認
echo "<h2>responsesテーブルの構造</h2>";
$stmt = $pdo->query("DESCRIBE responses");
echo "<pre>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
echo "</pre>";
?> 