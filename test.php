<?php
// 基本情報表示
echo "<h1>PHP動作テスト</h1>";
echo "PHP Version: " . phpversion() . "<br>";
echo "現在時刻: " . date('Y-m-d H:i:s') . "<br>";
echo "サーバー名: " . $_SERVER['SERVER_NAME'] . "<br>";
echo "ドキュメントルート: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

// ファイルシステム確認
echo "<h2>ファイル確認</h2>";
echo "カレントディレクトリ: " . __DIR__ . "<br>";
echo "index.phpの存在: " . (file_exists(__DIR__ . '/index.php') ? 'はい' : 'いいえ') . "<br>";
echo "config.phpの存在: " . (file_exists(__DIR__ . '/config.php') ? 'はい' : 'いいえ') . "<br>";
echo ".envファイルの存在: " . (file_exists(__DIR__ . '/.env') ? 'はい' : 'いいえ') . "<br>";
echo ".htaccessファイルの存在: " . (file_exists(__DIR__ . '/.htaccess') ? 'はい' : 'いいえ') . "<br>";

// .envファイルの内容（存在する場合）
if (file_exists(__DIR__ . '/.env')) {
    echo "<h2>.envファイルの内容</h2>";
    echo "<pre>";
    // セキュリティのため、パスワードはマスク
    $env_content = file_get_contents(__DIR__ . '/.env');
    $env_content = preg_replace('/PASSWORD=(.*)/', 'PASSWORD=******', $env_content);
    echo htmlspecialchars($env_content);
    echo "</pre>";
}

// データベース接続テスト
echo "<h2>データベース接続テスト</h2>";
try {
    // まず.envファイルから設定を読み込む
    $env_file = __DIR__ . '/.env';
    $db_host = 'mysql307.phy.lolipop.lan'; // デフォルト値
    $db_name = 'LAA1530328-wedding';
    $db_user = 'LAA1530328';
    $db_pass = 'syou108810';
    
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                if ($name === 'DB_HOST') $db_host = $value;
                if ($name === 'DB_NAME') $db_name = $value;
                if ($name === 'DB_USER') $db_user = $value;
                if ($name === 'DB_PASSWORD') $db_pass = $value;
            }
        }
    }
    
    echo "接続先: $db_host, データベース: $db_name, ユーザー: $db_user<br>";
    
    // PDOで接続
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "データベース接続成功！<br>";
    
    // テーブル一覧を取得
    $stmt = $pdo->query("SHOW TABLES");
    echo "テーブル一覧:<br>";
    echo "<ul>";
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "<li>" . htmlspecialchars($row[0]) . "</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "データベース接続エラー: " . $e->getMessage() . "<br>";
}

// PHPinfo（詳細情報）
echo "<h2>PHP情報</h2>";
echo "<a href='phpinfo.php'>PHPinfo()を表示</a> (別ファイルが必要)";
?> 