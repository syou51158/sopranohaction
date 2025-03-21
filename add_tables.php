<?php
// 設定ファイルを読み込み
require_once 'config.php';

// エラーレポート設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo '<h1>テーブルデータ追加</h1>';

try {
    // トランザクション開始
    $pdo->beginTransaction();
    
    // 追加するテーブルデータ
    $tables = [
        ['table_number' => 1, 'table_name' => '家族テーブル1', 'capacity' => 8, 'position' => 'A'],
        ['table_number' => 2, 'table_name' => '家族テーブル2', 'capacity' => 8, 'position' => 'B'],
        ['table_number' => 3, 'table_name' => '友人テーブル1', 'capacity' => 8, 'position' => 'C'],
        ['table_number' => 4, 'table_name' => '友人テーブル2', 'capacity' => 8, 'position' => 'D'],
        ['table_number' => 5, 'table_name' => '職場テーブル', 'capacity' => 8, 'position' => 'F']
    ];
    
    // 既存のデータを削除（オプション）
    $pdo->exec("DELETE FROM seat_assignments");
    $pdo->exec("DELETE FROM seating");
    echo "既存のテーブルデータをクリアしました。<br>";
    
    // テーブルデータを追加
    $stmt = $pdo->prepare("INSERT INTO seating (table_number, table_name, capacity, position) VALUES (?, ?, ?, ?)");
    
    $total_capacity = 0;
    
    foreach ($tables as $table) {
        $stmt->execute([
            $table['table_number'],
            $table['table_name'],
            $table['capacity'],
            $table['position']
        ]);
        $total_capacity += $table['capacity'];
        echo "テーブル {$table['table_number']} ({$table['table_name']}) を追加しました。収容人数: {$table['capacity']}人<br>";
    }
    
    // コミット
    $pdo->commit();
    
    echo "<h2>完了</h2>";
    echo "合計 " . count($tables) . " 個のテーブルを追加しました。総収容人数: {$total_capacity}人";
    echo "<p><a href='admin/seating.php'>席次表管理ページへ</a></p>";
    
} catch (Exception $e) {
    // エラー時はロールバック
    $pdo->rollBack();
    echo "<h2>エラーが発生しました</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
} 