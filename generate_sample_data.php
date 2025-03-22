<?php
// 設定ファイルを読み込み
require_once 'config.php';

// サンプルデータ生成用のスクリプト
echo '<h1>サンプルデータ生成中...</h1>';

// エラーレポート設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 処理開始
try {
    // まず現在のデータをバックアップ（オプション）
    $pdo->beginTransaction();
    
    echo '<h2>サンプルテーブルの生成</h2>';
    
    // サンプルテーブルデータ
    $tables = [
        ['table_number' => 1, 'table_name' => '家族テーブル1', 'capacity' => 8, 'position' => 'A'],
        ['table_number' => 2, 'table_name' => '家族テーブル2', 'capacity' => 8, 'position' => 'B'],
        ['table_number' => 3, 'table_name' => '友人テーブル1', 'capacity' => 8, 'position' => 'C'],
        ['table_number' => 4, 'table_name' => '友人テーブル2', 'capacity' => 8, 'position' => 'D'],
        ['table_number' => 5, 'table_name' => '職場テーブル', 'capacity' => 8, 'position' => 'F']
    ];
    
    // テーブルデータを追加
    $table_stmt = $pdo->prepare("INSERT INTO seating (table_number, table_name, capacity, position) VALUES (?, ?, ?, ?)");
    
    foreach ($tables as $table) {
        try {
            $table_stmt->execute([
                $table['table_number'],
                $table['table_name'],
                $table['capacity'],
                $table['position']
            ]);
            echo "テーブル {$table['table_number']} ({$table['table_name']}) を追加しました。<br>";
        } catch (PDOException $e) {
            // テーブル番号が既存の場合はスキップ
            echo "テーブル {$table['table_number']} は既に存在するためスキップします。<br>";
        }
    }
    
    // テーブルIDの取得
    $table_ids = [];
    $get_tables = $pdo->query("SELECT id, table_number FROM seating");
    while ($row = $get_tables->fetch(PDO::FETCH_ASSOC)) {
        $table_ids[$row['table_number']] = $row['id'];
    }
    
    echo '<h2>グループタイプの確認</h2>';
    
    // グループタイプの設定
    $group_types = [
        ['type_name' => '家族', 'description' => '新郎新婦の家族'],
        ['type_name' => '友人', 'description' => '新郎新婦の友人'],
        ['type_name' => '同僚', 'description' => '新郎新婦の職場関係者']
    ];
    
    $group_type_ids = [];
    
    foreach ($group_types as $type) {
        // グループタイプが存在するか確認
        $check_type = $pdo->prepare("SELECT id FROM group_types WHERE type_name = ?");
        $check_type->execute([$type['type_name']]);
        $existing_type = $check_type->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_type) {
            $group_type_ids[$type['type_name']] = $existing_type['id'];
            echo "グループタイプ '{$type['type_name']}' は既に存在します。 ID: {$existing_type['id']}<br>";
        } else {
            // グループタイプを追加
            $insert_type = $pdo->prepare("INSERT INTO group_types (type_name, description) VALUES (?, ?)");
            $insert_type->execute([$type['type_name'], $type['description']]);
            $type_id = $pdo->lastInsertId();
            $group_type_ids[$type['type_name']] = $type_id;
            echo "グループタイプ '{$type['type_name']}' を追加しました。 ID: {$type_id}<br>";
        }
    }
    
    echo '<h2>サンプルゲストの生成</h2>';
    
    // サンプルゲストデータ (40名分)
    $guest_names = [
        // 新郎側家族 (10名)
        '村岡 太郎', '村岡 花子', '村岡 健太', '村岡 美咲', '村岡 一郎',
        '村岡 直子', '村岡 勇太', '村岡 京子', '村岡 浩二', '村岡 裕子',
        
        // 新婦側家族 (10名)
        '磯野 剛', '磯野 恵子', '磯野 拓也', '磯野 由美', '磯野 雄太',
        '磯野 真理', '磯野 洋介', '磯野 絵美', '磯野 大輔', '磯野 久美子',
        
        // 友人 (15名)
        '佐藤 健', '鈴木 愛', '高橋 誠', '田中 美香', '伊藤 大樹',
        '渡辺 優', '山本 恵', '中村 剛', '小林 瞳', '加藤 隆',
        '吉田 香織', '山田 拓也', '佐々木 裕子', '山口 誠', '松本 さやか',
        
        // 職場関係 (5名)
        '井上 竜也', '木村 洋子', '林 正人', '清水 麻衣', '山崎 隆'
    ];
    
    // まず必要なゲストグループがあるか確認し、なければ作成
    $groups = [
        ['name' => '新郎側家族', 'type' => '家族'],
        ['name' => '新婦側家族', 'type' => '家族'],
        ['name' => '友人', 'type' => '友人'],
        ['name' => '職場', 'type' => '同僚']
    ];
    
    $group_ids = [];
    
    foreach ($groups as $group) {
        $check_group = $pdo->prepare("SELECT id FROM guests WHERE group_name = ?");
        $check_group->execute([$group['name']]);
        $existing_group = $check_group->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_group) {
            $group_ids[$group['name']] = $existing_group['id'];
            echo "ゲストグループ '{$group['name']}' は既に存在します。 ID: {$existing_group['id']}<br>";
        } else {
            $type_id = $group_type_ids[$group['type']];
            $insert_group = $pdo->prepare("INSERT INTO guests (group_name, group_type_id, email, is_group, status) VALUES (?, ?, '', 1, 'active')");
            $insert_group->execute([$group['name'], $type_id]);
            $group_ids[$group['name']] = $pdo->lastInsertId();
            echo "ゲストグループ '{$group['name']}' を追加しました。 ID: {$group_ids[$group['name']]}<br>";
        }
    }
    
    // ゲストデータの作成
    $response_stmt = $pdo->prepare("
        INSERT INTO responses (guest_id, name, email, attending, adults, children, message, dietary_requirements, created_at) 
        VALUES (?, ?, ?, 1, 1, 0, ?, ?, NOW())
    ");
    
    $responses = [];
    $messages = ['楽しみにしています！', 'おめでとうございます！', '素敵な式になりますように', 'お二人の門出を祝福します', ''];
    $dietary = ['特になし', 'ベジタリアン', '乳製品アレルギー', 'グルテンフリー', ''];
    
    for ($i = 0; $i < 40; $i++) {
        $name = $guest_names[$i];
        $email = strtolower(str_replace(' ', '.', transliterator_transliterate('Any-Latin; Latin-ASCII', $name))) . '@example.com';
        
        // グループの割り当て
        if ($i < 10) {
            $group_id = $group_ids['新郎側家族'];
        } elseif ($i < 20) {
            $group_id = $group_ids['新婦側家族'];
        } elseif ($i < 35) {
            $group_id = $group_ids['友人'];
        } else {
            $group_id = $group_ids['職場'];
        }
        
        $message = $messages[array_rand($messages)];
        $dietary_req = $dietary[array_rand($dietary)];
        
        try {
            $response_stmt->execute([$group_id, $name, $email, $message, $dietary_req]);
            $response_id = $pdo->lastInsertId();
            $responses[] = ['id' => $response_id, 'name' => $name];
            echo "ゲスト '{$name}' を追加しました。<br>";
        } catch (PDOException $e) {
            echo "ゲスト '{$name}' の追加に失敗しました: " . $e->getMessage() . "<br>";
        }
    }
    
    echo '<h2>席割り当ての生成</h2>';
    
    // 席割り当て
    $seat_stmt = $pdo->prepare("
        INSERT INTO seat_assignments (response_id, table_id, seat_number) 
        VALUES (?, ?, ?)
    ");
    
    // 各テーブルに8名ずつ割り当て（テーブル1〜5）
    for ($i = 0; $i < count($responses); $i++) {
        $table_number = floor($i / 8) + 1;
        if ($table_number > 5) $table_number = 5; // 残りは全てテーブル5に割り当て
        
        $seat_number = ($i % 8) + 1;
        $response_id = $responses[$i]['id'];
        
        if (isset($table_ids[$table_number])) {
            try {
                $seat_stmt->execute([$response_id, $table_ids[$table_number], $seat_number]);
                echo "ゲスト '{$responses[$i]['name']}' をテーブル {$table_number} の席番号 {$seat_number} に割り当てました。<br>";
            } catch (PDOException $e) {
                echo "席割り当てに失敗しました: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "テーブル {$table_number} が見つからないため、席割り当てをスキップします。<br>";
        }
    }
    
    // コミット
    $pdo->commit();
    
    echo '<h2>完了</h2>';
    echo '40名分のサンプルデータが正常に生成されました。';
    echo '<p><a href="admin/seating.php">席次表管理ページに戻る</a></p>';
    
} catch (Exception $e) {
    // エラー発生時はロールバック
    $pdo->rollBack();
    echo '<h2>エラーが発生しました</h2>';
    echo '<p>' . $e->getMessage() . '</p>';
} 