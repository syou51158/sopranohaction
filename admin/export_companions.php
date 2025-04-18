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

// response_idが指定されているかチェック
if (!isset($_GET['response_id']) || empty($_GET['response_id'])) {
    die('エラー: 出欠回答IDが指定されていません。');
}

$response_id = (int)$_GET['response_id'];

try {
    // 出席者の住所情報を含む全ての回答を取得
    $stmt = $pdo->query("
        SELECT 
            r.id, r.name, r.email, r.postal_code, r.address, r.attending, r.companions, r.message, r.dietary, r.created_at,
            g.group_name, g.group_id
        FROM responses r
        LEFT JOIN guests g ON r.guest_id = g.id
        WHERE r.attending = 1
        ORDER BY r.created_at DESC
    ");
    $responses = $stmt->fetchAll();
    
    // 同伴者情報を取得
    $companions_stmt = $pdo->prepare("
        SELECT * FROM companions 
        WHERE response_id = ? 
        ORDER BY id
    ");
    $companions_stmt->execute([$response_id]);
    $companions = $companions_stmt->fetchAll();
    
    // CSVヘッダー
    $filename = $responses[0]['name'] . '_companions_' . date('Ymd') . '.csv';
    
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
    fputcsv($output, ['出席者ID', 'グループ名', '出席者名', 'メールアドレス', '郵便番号', '住所', '同伴者数', '回答日時', '主要同伴者名', '同伴者年齢区分', '同伴者の食事制限']);
    
    // 同伴者情報を書き込み
    foreach ($responses as $response) {
        if (isset($companions[$response['id']])) {
            foreach ($companions[$response['id']] as $companion) {
                $age_group = '';
                switch ($companion['age_group']) {
                    case 'adult': $age_group = '大人'; break;
                    case 'child': $age_group = '子供'; break;
                    case 'infant': $age_group = '幼児'; break;
                    default: $age_group = '不明';
                }
                
                fputcsv($output, [
                    $response['id'],
                    $response['group_name'] ?? '未指定',
                    $response['name'],
                    $response['email'],
                    $response['postal_code'] ?? '',
                    $response['address'] ?? '',
                    $response['companions'],
                    $response['created_at'],
                    $companion['name'],
                    $age_group,
                    $companion['dietary'] ?? ''
                ]);
            }
        }
    }
    
    // 出力バッファをフラッシュしてCSVを送信
    fclose($output);
    ob_end_flush();
    exit;
    
} catch (PDOException $e) {
    die('データベースエラー: ' . ($debug_mode ? $e->getMessage() : '同伴者情報の取得に失敗しました。'));
}
?> 