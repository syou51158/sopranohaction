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

// IDが指定されているかチェック
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('エラー: 出欠回答IDが指定されていません。');
}

$id = (int)$_GET['id'];

try {
    // 出欠回答の情報を取得
    $response_stmt = $pdo->prepare("
        SELECT r.*, g.group_name 
        FROM responses r 
        LEFT JOIN guests g ON r.guest_id = g.id 
        WHERE r.id = ?
    ");
    $response_stmt->execute([$id]);
    $response = $response_stmt->fetch();
    
    if (!$response) {
        die('エラー: 指定された出欠回答が見つかりません。');
    }
    
    // 同伴者情報を取得
    $companions_stmt = $pdo->prepare("
        SELECT * FROM companions 
        WHERE response_id = ? 
        ORDER BY id
    ");
    $companions_stmt->execute([$id]);
    $companions = $companions_stmt->fetchAll();
    
    // CSVヘッダー
    $filename = $response['name'] . '_rsvp_' . date('Ymd') . '.csv';
    
    // CSVの出力設定
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // BOMをつけてUTF-8で出力
    echo "\xEF\xBB\xBF";
    
    // 出力バッファを開始
    ob_start();
    
    // CSVファイルハンドラを作成
    $output = fopen('php://output', 'w');
    
    // 回答者情報のセクション
    fputcsv($output, ['■回答者情報']);
    fputcsv($output, ['回答ID', '回答日時', 'グループ名', '名前', 'メールアドレス', '出欠', '同伴者数', 'メッセージ', '食事制限']);
    fputcsv($output, [
        $response['id'],
        $response['created_at'],
        $response['group_name'] ?? '未指定',
        $response['name'],
        $response['email'],
        $response['attending'] ? '出席' : '欠席',
        $response['companions'],
        $response['message'] ?? '',
        $response['dietary'] ?? ''
    ]);
    
    // 空行を挿入
    fputcsv($output, []);
    
    // 同伴者情報のセクション（同伴者がいる場合のみ）
    if (!empty($companions)) {
        fputcsv($output, ['■同伴者情報']);
        fputcsv($output, ['同伴者ID', '名前', '年齢区分', '食事制限・アレルギー']);
        
        foreach ($companions as $companion) {
            $age_group = '';
            switch ($companion['age_group']) {
                case 'adult':
                    $age_group = '大人';
                    break;
                case 'child':
                    $age_group = '子供（小学生以下）';
                    break;
                case 'infant':
                    $age_group = '幼児（3歳以下）';
                    break;
                default:
                    $age_group = '不明';
            }
            
            fputcsv($output, [
                $companion['id'],
                $companion['name'],
                $age_group,
                $companion['dietary'] ?? ''
            ]);
        }
    }
    
    // 出力バッファをフラッシュしてCSVを送信
    fclose($output);
    ob_end_flush();
    exit;
    
} catch (PDOException $e) {
    die('データベースエラー: ' . ($debug_mode ? $e->getMessage() : '出欠回答情報の取得に失敗しました。'));
}
?> 