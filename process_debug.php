<?php
// 設定ファイルを読み込み
require_once 'config.php';

// セキュリティのため、ローカル環境でのみ実行可能にする
if (!$is_local) {
    die("このスクリプトはローカル環境でのみ実行できます。");
}

// ヘッダーの設定
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>デバッグ情報</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.5; }
        h1, h2, h3 { color: #333; }
        .section { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; }
        .error { color: red; }
        .success { color: green; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        pre { background-color: #f5f5f5; padding: 10px; overflow: auto; }
        form { margin-top: 15px; }
        input, button { padding: 5px 10px; }
    </style>
</head>
<body>
    <h1>ウェディングサイト デバッグ情報</h1>
    
    <div class="section">
        <h2>設定情報</h2>
        <p>データベースホスト: <?php echo $db_host; ?></p>
        <p>データベース名: <?php echo $db_name; ?></p>
        <p>データベースユーザー: <?php echo $db_user; ?></p>
        <p>サイトURL: <?php echo $site_url; ?></p>
        <p>デバッグモード: <?php echo $debug_mode ? 'オン' : 'オフ'; ?></p>
    </div>
    
    <div class="section">
        <h2>データベース接続テスト</h2>
        <?php
        if (isset($pdo)) {
            echo '<p class="success">データベース接続成功</p>';
            
            // データベースのバージョン情報を表示
            try {
                $stmt = $pdo->query("SELECT VERSION() as version");
                $version = $stmt->fetch();
                echo '<p>MySQL バージョン: ' . $version['version'] . '</p>';
            } catch (PDOException $e) {
                echo '<p class="error">バージョン情報の取得に失敗: ' . $e->getMessage() . '</p>';
            }
        } else {
            echo '<p class="error">データベース接続情報が見つかりません</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>テーブル情報</h2>
        <?php
        try {
            // テーブル一覧を取得
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($tables) > 0) {
                echo '<p>データベース内のテーブル数: ' . count($tables) . '</p>';
                echo '<ul>';
                foreach ($tables as $table) {
                    echo '<li>' . $table . '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p class="error">テーブルが見つかりません</p>';
            }
        } catch (PDOException $e) {
            echo '<p class="error">テーブル情報の取得に失敗: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>guests テーブル</h2>
        <?php
        try {
            $stmt = $pdo->query("SELECT * FROM guests LIMIT 10");
            $guests = $stmt->fetchAll();
            
            if (count($guests) > 0) {
                echo '<table>';
                echo '<tr>';
                foreach (array_keys($guests[0]) as $column) {
                    echo '<th>' . htmlspecialchars($column) . '</th>';
                }
                echo '</tr>';
                
                foreach ($guests as $guest) {
                    echo '<tr>';
                    foreach ($guest as $value) {
                        echo '<td>' . htmlspecialchars($value ?? 'NULL') . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>データがありません</p>';
            }
        } catch (PDOException $e) {
            echo '<p class="error">guests テーブルの取得に失敗: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>responses テーブル</h2>
        <?php
        try {
            $stmt = $pdo->query("SELECT * FROM responses ORDER BY id DESC LIMIT 10");
            $responses = $stmt->fetchAll();
            
            if (count($responses) > 0) {
                echo '<table>';
                echo '<tr>';
                foreach (array_keys($responses[0]) as $column) {
                    echo '<th>' . htmlspecialchars($column) . '</th>';
                }
                echo '</tr>';
                
                foreach ($responses as $response) {
                    echo '<tr>';
                    foreach ($response as $value) {
                        echo '<td>' . htmlspecialchars($value ?? 'NULL') . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>データがありません</p>';
            }
        } catch (PDOException $e) {
            echo '<p class="error">responses テーブルの取得に失敗: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>デバッグログ</h2>
        <?php
        $log_file = 'logs/form_debug.log';
        if (file_exists($log_file)) {
            $log_content = file_get_contents($log_file);
            if (!empty($log_content)) {
                echo '<pre>' . htmlspecialchars($log_content) . '</pre>';
            } else {
                echo '<p>ログファイルは空です</p>';
            }
        } else {
            echo '<p class="error">ログファイルが見つかりません: ' . $log_file . '</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>テスト送信フォーム</h2>
        <p>以下のフォームでテスト送信ができます：</p>
        <form action="process_rsvp.php" method="post">
            <div>
                <label for="test-name">お名前:</label>
                <input type="text" id="test-name" name="name" value="テストユーザー" required>
            </div>
            <div>
                <label for="test-email">メールアドレス:</label>
                <input type="email" id="test-email" name="email" value="test@example.com" required>
            </div>
            <div>
                <label>出席:</label>
                <input type="radio" id="test-attend-yes" name="attending" value="1" checked>
                <label for="test-attend-yes">出席</label>
                <input type="radio" id="test-attend-no" name="attending" value="0">
                <label for="test-attend-no">欠席</label>
            </div>
            <div>
                <label for="test-companions">同伴者:</label>
                <select id="test-companions" name="companions">
                    <option value="0">0名</option>
                    <option value="1">1名</option>
                    <option value="2">2名</option>
                </select>
            </div>
            <div>
                <label for="test-message">メッセージ:</label>
                <textarea id="test-message" name="message">テストメッセージです。</textarea>
            </div>
            <div>
                <label for="test-dietary">食事制限:</label>
                <textarea id="test-dietary" name="dietary">なし</textarea>
            </div>
            <button type="submit">テスト送信</button>
        </form>
    </div>
</body>
</html> 