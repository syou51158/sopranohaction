<?php
// セッションを開始
session_start();

// 設定ファイルを読み込み
require_once '../config.php';

// 認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

try {
    // テーブル情報を取得
    $stmt = $pdo->query("SELECT * FROM seating_tables ORDER BY table_name");
    $tables = $stmt->fetchAll();
    
    // 座席割り当て情報を取得
    $stmt = $pdo->query("
        SELECT sa.*, st.table_name, r.name AS guest_name, g.group_name, c.name AS companion_name, c.age_group
        FROM seat_assignments sa
        LEFT JOIN seating_tables st ON sa.table_id = st.id
        LEFT JOIN responses r ON sa.response_id = r.id
        LEFT JOIN guests g ON r.guest_id = g.id
        LEFT JOIN companions c ON sa.companion_id = c.id
        ORDER BY st.table_name, sa.seat_number
    ");
    $assignments = $stmt->fetchAll();
    
    // CSVヘッダー
    $filename = 'seating_plan_' . date('Ymd') . '.csv';
    
    // CSVの出力設定
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // BOMをつけてUTF-8で出力
    echo "\xEF\xBB\xBF";
    
    // 出力バッファを開始
    ob_start();
    
    // CSVファイルハンドラを作成
    $output = fopen('php://output', 'w');
    
    // ヘッダー行を書き込み
    fputcsv($output, ['テーブル', '座席番号', 'ゲスト名', '種別', 'グループ/回答者', '年齢区分']);
    
    // 各テーブルの座席情報を書き込み
    foreach ($tables as $table) {
        $table_id = $table['id'];
        $table_name = $table['table_name'];
        $capacity = $table['capacity'];
        
        for ($seat_number = 1; $seat_number <= $capacity; $seat_number++) {
            $guest_name = '';
            $guest_type = '';
            $group_info = '';
            $age_group = '';
            
            // この座席に割り当てられたゲストを探す
            $assigned = false;
            foreach ($assignments as $assignment) {
                if ($assignment['table_id'] == $table_id && $assignment['seat_number'] == $seat_number) {
                    $assigned = true;
                    if ($assignment['is_companion']) {
                        $guest_name = $assignment['companion_name'];
                        $guest_type = '同伴者';
                        $group_info = $assignment['guest_name'] . 'の同伴者';
                        
                        switch ($assignment['age_group']) {
                            case 'adult':
                                $age_group = '大人';
                                break;
                            case 'child':
                                $age_group = '子供';
                                break;
                            case 'infant':
                                $age_group = '幼児';
                                break;
                            default:
                                $age_group = '';
                        }
                    } else {
                        $guest_name = $assignment['guest_name'];
                        $guest_type = '回答者';
                        $group_info = $assignment['group_name'] ?? '';
                    }
                    break;
                }
            }
            
            // 各座席の情報を書き込み
            fputcsv($output, [
                $table_name,
                $seat_number,
                $guest_name,
                $guest_type,
                $group_info,
                $age_group
            ]);
        }
        
        // テーブル間の区切り
        fputcsv($output, []);
    }
    
    // 出力バッファをフラッシュしてCSVを送信
    fclose($output);
    ob_end_flush();
    exit;
    
} catch (PDOException $e) {
    die('データベースエラー: ' . ($debug_mode ? $e->getMessage() : '席次表データの取得に失敗しました。'));
}
?> 