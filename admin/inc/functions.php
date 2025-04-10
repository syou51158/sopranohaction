<?php
/**
 * 管理画面用共通関数ファイル
 * 
 * 管理画面で使用される共通関数を定義
 */

/**
 * 管理者権限をチェックする関数
 * 
 * @return void
 */
function check_admin() {
    // セッションが開始されていない場合は開始
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // ログインチェック
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: index.php');
        exit;
    }
}

/**
 * データベース接続を行う関数
 * 
 * @return PDO PDOオブジェクト
 */
function db_connect() {
    global $db_host, $db_name, $db_user, $db_pass;
    
    try {
        $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        die("データベース接続エラー: " . $e->getMessage());
    }
}

/**
 * 安全にHTMLをエスケープする関数
 * 
 * @param string $str エスケープする文字列
 * @return string エスケープされた文字列
 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * 管理者かどうかを確認する関数（API用）
 * 
 * @return bool 管理者であればtrue
 */
function isAdmin() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * メッセージを表示する関数
 * 
 * @param string $message 表示するメッセージ
 * @param string $type メッセージの種類 (success, error, info, warning)
 * @return string メッセージのHTML
 */
function show_message($message, $type = 'info') {
    $class = '';
    
    switch ($type) {
        case 'success':
            $class = 'alert-success';
            break;
        case 'error':
            $class = 'alert-danger';
            break;
        case 'warning':
            $class = 'alert-warning';
            break;
        case 'info':
        default:
            $class = 'alert-info';
            break;
    }
    
    return '<div class="alert ' . $class . '">' . $message . '</div>';
} 