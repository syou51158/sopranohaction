<?php
// .envファイルの読み込み試行
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // コメント行をスキップ
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // 環境変数の設定
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // 環境変数として設定
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// ホスト環境を自動判定（ローカルかサーバーか）
$app_env = getenv('APP_ENV') ?: 'local'; // 環境変数がなければデフォルトはlocal
$is_local = ($app_env === 'local' || $app_env === 'development');

// 診断情報（デバッグ用）
if (isset($_GET['debug_env']) && $_GET['debug_env'] === '1') {
    echo '<pre>';
    echo "環境変数APP_ENV: " . $app_env . "\n";
    echo "ローカル環境判定: " . ($is_local ? 'true' : 'false') . "\n";
    echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'undefined') . "\n";
    echo '</pre>';
}

// デバッグモード（本番環境ではfalseにする）
$debug_mode = $is_local ? true : false;

// データベース接続情報
if ($is_local) {
    // ローカル環境用設定
    $db_host = getenv('DB_HOST') ?: '127.0.0.1';  // ローカルIPアドレス
    $db_name = getenv('DB_NAME') ?: 'wedding';    // ローカルデータベース名
    $db_user = getenv('DB_USER') ?: 'root';       // XAMPPデフォルトユーザー
    $db_pass = getenv('DB_PASSWORD') ?: '';       // XAMPPデフォルトパスワード（空）
    
    // ローカル環境のURL（IPアドレスベース）
    // 自動的にサーバーのIPアドレスを取得
    $server_ip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
    if (empty($server_ip) || $server_ip == '::1' || $server_ip == '127.0.0.1') {
        // IP取得失敗時や localhost の場合はネットワークIPを使用
        $server_ip = gethostbyname(gethostname());
    }
    
    // IPアドレスを使用したサイトURL
    $site_url = "http://{$server_ip}/wedding/";
    
    $site_email = "info-wedding@sopranohaction.fun";  // 送信元メールアドレス
    $debug_mode = true;      // デバッグモード有効
    define('DEBUG_MODE', true);  // デバッグモード定数
    $mail_debug = false;     // メールデバッグ出力は無効
    define('MAIL_DEBUG', false); // メールデバッグ出力定数
    
    // デバッグ情報をログに出力
    error_log("ローカル環境URL: " . $site_url);
} else {
    // 本番環境用設定（.envから読み込み）
    $db_host = getenv('DB_HOST') ?: 'mysql307.phy.lolipop.lan';
    $db_name = getenv('DB_NAME') ?: 'LAA1530328-wedding';
    $db_user = getenv('DB_USER') ?: 'LAA1530328';
    $db_pass = getenv('DB_PASSWORD') ?: ''; // .env から読み込む、デフォルトは空
    $site_url = "https://sopranohaction.fun/";  // 本番環境のURL（HTTPSに変更）
    $site_email = "info-wedding@sopranohaction.fun";  // 送信元メールアドレス
    $debug_mode = false; // 本番環境ではデバッグモードを必ず無効化
    define('DEBUG_MODE', false);
    $mail_debug = false;     // メールデバッグ出力は無効
    define('MAIL_DEBUG', false); // メールデバッグ出力定数
}

// データベース接続
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

// サイトの基本設定
$site_name = "翔 & あかね - Wedding Invitation";
$base_url = $site_url;    // サイトのベースURL
$smtp_host = getenv('SMTP_HOST') ?: 'smtp.lolipop.jp'; // デフォルト値
$smtp_port = getenv('SMTP_PORT') ?: 465;             // デフォルト値
$smtp_username = getenv('SMTP_USERNAME') ?: 'info-wedding@sopranohaction.fun'; // デフォルト値
$smtp_password = getenv('SMTP_PASSWORD') ?: ''; // .env から読み込む、デフォルトは空
$smtp_secure = getenv('SMTP_SECURE') ?: 'ssl'; // デフォルト値 'ssl' or 'tls' (PHPMailer::ENCRYPTION_SMTPS 相当)
$from_email = getenv('FROM_EMAIL') ?: $site_email; // config.php 内の $site_email をデフォルトに
$from_name = getenv('FROM_NAME') ?: $site_name;   // config.php 内の $site_name をデフォルトに
$mail_debug = filter_var(getenv('MAIL_DEBUG'), FILTER_VALIDATE_BOOLEAN); // .env から読み込む

// タイムゾーンを日本時間に設定
date_default_timezone_set('Asia/Tokyo');

// MySQLのセッションタイムゾーンも日本時間に設定
try {
    $pdo->exec("SET time_zone = '+09:00'");
} catch (PDOException $e) {
    error_log("MySQLタイムゾーン設定エラー: " . $e->getMessage());
}

// Debug出力（テスト用）
if ($debug_mode) {
    $debug_info = "SMTP設定:\n";
    $debug_info .= "host: {$smtp_host}\n";
    $debug_info .= "port: {$smtp_port}\n";
    $debug_info .= "user: {$smtp_username}\n";
    $debug_info .= "pass: " . (empty($smtp_password) ? 'なし' : '設定済み') . "\n";
    $debug_info .= "secure: {$smtp_secure}\n";
    $debug_info .= "mail_debug: " . ($mail_debug ? 'true' : 'false') . "\n";
    $debug_info .= "from_email: {$from_email}\n";
    $debug_info .= "from_name: {$from_name}\n";
    error_log($debug_info);
}

// メールデバッグを強制的に有効化（テスト用）
$mail_debug = false;

// メールヘルパー関数を読み込み
require_once __DIR__ . '/includes/mail_helper.php';

// エラーを表示/非表示
if ($debug_mode) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    error_reporting(0);
}

// ここで終わり     