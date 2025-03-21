<?php
// ホスト環境を自動判定（ローカルかサーバーか）
$is_local = true; // ローカル環境では常にtrue

// データベース接続情報
if ($is_local) {
    // ローカル環境用設定
    $db_host = '127.0.0.1';  // ローカルIPアドレス
    $db_name = 'wedding';    // ローカルデータベース名
    $db_user = 'root';       // XAMPPデフォルトユーザー
    $db_pass = '';           // XAMPPデフォルトパスワード（空）
    $site_url = "http://localhost/wedding/";  // ローカル環境のURL
    $site_email = "info-wedding@sopranohaction.fun";  // 送信元メールアドレス
    $debug_mode = true;      // デバッグモード有効
    define('DEBUG_MODE', true);  // デバッグモード定数
    $mail_debug = false;     // メールデバッグ出力は無効
    define('MAIL_DEBUG', false); // メールデバッグ出力定数
} else {
    // 本番環境用設定
    $db_host = 'mysql307.phy.lolipop.lan';  // サーバーのホスト名
    $db_name = 'LAA1530328-wedding';        // データベース名
    $db_user = 'LAA1530328';                // データベースユーザー名
    $db_pass = 'syou108810';                // データベースパスワード
    $site_url = "http://sopranohaction.fun/";  // 本番環境のURL
    $site_email = "info-wedding@sopranohaction.fun";  // 送信元メールアドレス
    $debug_mode = false;     // 本番環境ではデバッグモード無効
    define('DEBUG_MODE', false);  // デバッグモード定数
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