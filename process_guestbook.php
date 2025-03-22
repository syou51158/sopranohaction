<?php
// 設定ファイルを読み込み
require_once 'config.php';

// POSTデータの確認
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // フォームデータの取得と検証
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $group_name = isset($_POST['group_name']) ? trim($_POST['group_name']) : '';
    $group_id = isset($_POST['group_id']) ? trim($_POST['group_id']) : '';
    
    // 基本的なバリデーション
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'お名前を入力してください。';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '有効なメールアドレスを入力してください。';
    }
    
    if (empty($message)) {
        $errors[] = 'メッセージを入力してください。';
    }
    
    // エラーがなければデータベースに保存
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO guestbook (name, email, message, group_name) VALUES (:name, :email, :message, :group_name)");
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'message' => $message,
                'group_name' => $group_name
            ]);
            
            // 成功メッセージとともにリダイレクト
            $redirect_url = 'guestbook.php?status=success&message=' . urlencode('メッセージを送信しました。管理者の承認後に表示されます。');
            
            // グループIDがあれば追加
            if (!empty($group_id)) {
                $redirect_url .= '&group=' . urlencode($group_id);
            }
            
            header('Location: ' . $redirect_url);
            exit;
        } catch (PDOException $e) {
            if ($debug_mode) {
                $errors[] = "データベースエラー: " . $e->getMessage();
            } else {
                $errors[] = "システムエラーが発生しました。後でもう一度お試しください。";
            }
        }
    }
    
    // エラーがあればエラーメッセージとともにリダイレクト
    if (!empty($errors)) {
        $redirect_url = 'guestbook.php?status=error&message=' . urlencode(implode(' ', $errors));
        
        // グループIDがあれば追加
        if (!empty($group_id)) {
            $redirect_url .= '&group=' . urlencode($group_id);
        }
        
        header('Location: ' . $redirect_url);
        exit;
    }
} else {
    // 不正なアクセスの場合はトップページにリダイレクト
    header('Location: index.php');
    exit;
}
?> 