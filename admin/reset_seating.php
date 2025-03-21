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
    // 座席割り当て情報をすべて削除
    $stmt = $pdo->prepare("TRUNCATE TABLE seat_assignments");
    $stmt->execute();
    
    // 成功メッセージをセッションに保存
    $_SESSION['success_message'] = "席次表の割り当て情報をリセットしました。";
    
    // 席次表管理ページにリダイレクト
    header('Location: seating.php');
    exit;
} catch (PDOException $e) {
    // エラーメッセージをセッションに保存
    $_SESSION['error_message'] = "席次表のリセットに失敗しました。";
    if ($debug_mode) {
        $_SESSION['error_message'] .= " エラー: " . $e->getMessage();
    }
    
    // 席次表管理ページにリダイレクト
    header('Location: seating.php');
    exit;
}
?> 