<?php
// 設定ファイルを読み込み
require_once 'config.php';

// 追加したいグループタイプ
$new_group_types = [
    ['新郎側友人', '新郎の友人たち'],
    ['新婦側友人', '新婦の友人たち'],
    ['新郎側親族', '新郎の家族や親族'],
    ['新婦側親族', '新婦の家族や親族']
    // 必要に応じて追加のグループタイプをここに追加
];

// グループタイプを追加
$stmt = $pdo->prepare("INSERT INTO group_types (type_name, description) VALUES (?, ?)");

$success_count = 0;
$error_messages = [];

foreach ($new_group_types as $type) {
    try {
        $stmt->execute($type);
        $success_count++;
    } catch (PDOException $e) {
        // エラーが発生した場合はエラーメッセージを配列に追加
        $error_messages[] = "グループタイプ '{$type[0]}' の追加に失敗しました: " . $e->getMessage();
    }
}

// 結果を表示
echo "<h2>グループタイプ追加結果</h2>";

if ($success_count > 0) {
    echo "<p>{$success_count}件のグループタイプが正常に追加されました。</p>";
}

if (!empty($error_messages)) {
    echo "<h3>エラー</h3>";
    echo "<ul>";
    foreach ($error_messages as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
}

// 現在のグループタイプ一覧を表示
try {
    $stmt = $pdo->query("SELECT * FROM group_types ORDER BY id");
    $group_types = $stmt->fetchAll();
    
    if (!empty($group_types)) {
        echo "<h3>現在のグループタイプ一覧</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>タイプ名</th><th>説明</th></tr>";
        
        foreach ($group_types as $type) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($type['id']) . "</td>";
            echo "<td>" . htmlspecialchars($type['type_name']) . "</td>";
            echo "<td>" . htmlspecialchars($type['description']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>グループタイプが見つかりませんでした。</p>";
    }
} catch (PDOException $e) {
    echo "<p>グループタイプの取得に失敗しました: " . htmlspecialchars($e->getMessage()) . "</p>";
} 