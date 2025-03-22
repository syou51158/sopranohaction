<?php
// データベース接続情報
$db_host = '127.0.0.1';
$db_name = 'wedding';
$db_user = 'root';
$db_pass = '';

// データベース接続テスト
try {
    // 接続
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 成功メッセージ
    echo "<h1>データベース接続テスト</h1>";
    echo "<p style='color:green'>データベース接続に成功しました！</p>";
    
    // テーブル一覧を表示
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "<h2>データベース内のテーブル一覧:</h2>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>データベース内にテーブルはありません。</p>";
    }
    
} catch (PDOException $e) {
    // エラーメッセージ
    echo "<h1>データベース接続テスト</h1>";
    echo "<p style='color:red'>エラー: " . $e->getMessage() . "</p>";
    echo "<p>ホスト: $db_host</p>";
    echo "<p>データベース名: $db_name</p>";
    echo "<p>ユーザー名: $db_user</p>";
}
?> 