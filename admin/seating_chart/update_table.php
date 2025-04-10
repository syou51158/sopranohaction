<?php
require_once '../inc/functions.php';

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// データベース接続
$db = get_db_connection();

// POSTデータがない場合はリダイレクト
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: seating_layout.php');
    exit;
}

try {
    // 削除処理
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        if (!isset($_POST['table_id']) || empty($_POST['table_id'])) {
            throw new Exception('テーブルIDが不正です');
        }
        
        $tableId = (int)$_POST['table_id'];
        
        // トランザクション開始
        $db->beginTransaction();
        
        // 関連する座席割り当てを削除
        $stmt = $db->prepare("DELETE FROM seat_assignments WHERE table_id = ?");
        $stmt->execute([$tableId]);
        
        // テーブルを削除
        $stmt = $db->prepare("DELETE FROM seating_tables WHERE id = ?");
        $stmt->execute([$tableId]);
        
        // コミット
        $db->commit();
        
        $_SESSION['success_message'] = 'テーブルが削除されました';
        header('Location: seating_layout.php');
        exit;
    }
    
    // 必須項目チェック
    if (!isset($_POST['table_name']) || empty($_POST['table_name'])) {
        throw new Exception('テーブル名を入力してください');
    }
    
    if (!isset($_POST['capacity']) || empty($_POST['capacity'])) {
        throw new Exception('席数を入力してください');
    }
    
    if (!isset($_POST['table_type']) || empty($_POST['table_type'])) {
        throw new Exception('テーブルタイプを選択してください');
    }
    
    $tableName = trim($_POST['table_name']);
    $capacity = (int)$_POST['capacity'];
    $tableType = $_POST['table_type'];
    
    // 値の検証
    if ($capacity < 1 || $capacity > 12) {
        throw new Exception('席数は1〜12の範囲で設定してください');
    }
    
    if (!in_array($tableType, ['regular', 'special', 'bridal'])) {
        throw new Exception('無効なテーブルタイプです');
    }
    
    // 新規追加かどうかを判定
    $isNew = !isset($_POST['table_id']) || empty($_POST['table_id']);
    
    if ($isNew) {
        // 新規テーブル追加
        $stmt = $db->prepare("
            INSERT INTO seating_tables (table_name, capacity, table_type)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$tableName, $capacity, $tableType]);
        
        $_SESSION['success_message'] = '新しいテーブルが追加されました';
    } else {
        // テーブル更新
        $tableId = (int)$_POST['table_id'];
        
        // 既存テーブルの情報を取得
        $stmt = $db->prepare("SELECT capacity FROM seating_tables WHERE id = ?");
        $stmt->execute([$tableId]);
        $currentTable = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentTable) {
            throw new Exception('指定されたテーブルが見つかりません');
        }
        
        // テーブル更新
        $stmt = $db->prepare("
            UPDATE seating_tables 
            SET table_name = ?, capacity = ?, table_type = ?
            WHERE id = ?
        ");
        $stmt->execute([$tableName, $capacity, $tableType, $tableId]);
        
        // 席数が減少した場合、超過した席の割り当てを削除
        if ($capacity < $currentTable['capacity']) {
            $stmt = $db->prepare("
                DELETE FROM seat_assignments 
                WHERE table_id = ? AND seat_number > ?
            ");
            $stmt->execute([$tableId, $capacity]);
        }
        
        $_SESSION['success_message'] = 'テーブル情報が更新されました';
    }
    
    // 成功の場合はレイアウト画面にリダイレクト
    header('Location: seating_layout.php');
    exit;
    
} catch (Exception $e) {
    // エラーメッセージをセットしてリダイレクト
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: seating_layout.php');
    exit;
} 