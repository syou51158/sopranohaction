<?php
// エラーハンドリングの設定
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// エラーのキャプチャを開始
ob_start();

try {
    // 本来のindex.phpを含める
    require_once 'index.php';
    
} catch (Throwable $e) {
    // 出力バッファをクリア
    ob_end_clean();
    
    // HTMLヘッダ
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>エラーデバッグ</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
            .error { background: #ffebee; border-left: 5px solid #f44336; padding: 10px; margin-bottom: 20px; }
            pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        </style>
    </head>
    <body>
        <h1>エラーが発生しました</h1>';
    
    // エラー情報の表示
    echo '<div class="error">';
    echo '<h2>エラーメッセージ</h2>';
    echo '<p><strong>' . htmlspecialchars($e->getMessage()) . '</strong></p>';
    echo '<h2>エラータイプ</h2>';
    echo '<p>' . get_class($e) . '</p>';
    echo '<h2>ファイル</h2>';
    echo '<p>' . $e->getFile() . ' (行: ' . $e->getLine() . ')</p>';
    echo '</div>';
    
    // スタックトレースの表示
    echo '<h2>スタックトレース</h2>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    
    // 環境変数とサーバー情報
    echo '<h2>環境変数</h2>';
    echo '<pre>';
    
    // APP_ENVの値を表示
    echo 'APP_ENV: ' . (getenv('APP_ENV') ?: 'Not set') . "\n";
    echo 'APP_DEBUG: ' . (getenv('APP_DEBUG') ?: 'Not set') . "\n";
    
    // サーバー情報
    echo '</pre>';
    echo '<h2>サーバー情報</h2>';
    echo '<pre>';
    $server_info = [
        'PHP Version' => phpversion(),
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'Server Name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
        'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'Request URI' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
        'Script Filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'Unknown'
    ];
    
    foreach ($server_info as $key => $value) {
        echo htmlspecialchars("$key: $value") . "\n";
    }
    
    echo '</pre>';
    echo '</body></html>';
}
?> 