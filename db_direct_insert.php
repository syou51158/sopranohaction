<?php
// 設定ファイルを読み込み
require_once 'config.php';

try {
    echo "<h2>データベース接続情報</h2>";
    echo "ホスト: $db_host<br>";
    echo "データベース: $db_name<br>";
    echo "ユーザー: $db_user<br>";
    
    // テスト挿入
    $stmt = $pdo->prepare("
        INSERT INTO responses 
        (guest_id, name, email, attending, companions, message, dietary) 
        VALUES 
        (NULL, '直接テスト挿入', 'direct@test.com', 1, 0, 'データベースに直接挿入テスト', '')
    ");
    
    $stmt->execute();
    $id = $pdo->lastInsertId();
    
    echo "<h2>挿入結果</h2>";
    echo "挿入成功！ID: $id<br>";
    
    $stmt = $pdo->prepare("SELECT * FROM responses WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    
    echo "<h2>挿入されたデータ</h2>";
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<h2>エラー</h2>";
    echo $e->getMessage();
}
?>   