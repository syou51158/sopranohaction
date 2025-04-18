<?php
// 設定ファイルを読み込み
require_once 'config.php';
// 通知関連ヘルパーファイルを読み込み
require_once 'includes/notification_helper.php';
require_once 'includes/mail_helper.php';

// POSTデータの確認
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // フォームデータの取得と検証
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $group_name = isset($_POST['group_name']) ? trim($_POST['group_name']) : '';
    $group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
    
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
            $stmt = $pdo->prepare("
                INSERT INTO guestbook (name, email, message, group_name, group_id, is_approved, created_at)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            
            $stmt->execute([$name, $email, $message, $group_name, $group_id]);
            $guestbook_id = $pdo->lastInsertId();
            
            // 成功メッセージをセット
            $_SESSION['success_message'] = "メッセージが送信されました。承認後に表示されます。";
            
            // 管理者へメール通知を送信
            try {
                // 管理者メールアドレスを取得
                $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
                $stmt->execute(['admin_email']);
                $admin_email = $stmt->fetchColumn();
                
                if ($admin_email) {
                    // 結婚式の設定を取得
                    $stmt = $pdo->query("SELECT setting_key, setting_value FROM wedding_settings");
                    $wedding_settings = [];
                    while ($row = $stmt->fetch()) {
                        $wedding_settings[$row['setting_key']] = $row['setting_value'];
                    }
                    
                    // 通知設定を取得
                    $stmt = $pdo->prepare("SELECT * FROM notification_settings WHERE type = ? AND is_enabled = 1");
                    $stmt->execute(['new_guestbook']);
                    $notification = $stmt->fetch();
                    
                    if ($notification) {
                        // プレースホルダーを置換
                        $placeholders = [
                            '{name}' => $name,
                            '{email}' => $email,
                            '{message}' => $message,
                            '{date}' => date('Y-m-d H:i:s'),
                            '{admin_url}' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/wedding/admin/guestbook.php"
                        ];
                        
                        // 管理者への通知 - send_notification関数を使用
                        $admin_result = send_notification('new_guestbook', $placeholders);
                        
                        if ($debug_mode && !$admin_result) {
                            error_log("ゲストブック投稿の管理者通知が送信できませんでした。");
                        }
                        
                        // 投稿者への自動返信
                        // 追加のプレースホルダーを設定（結婚式情報）
                        $guest_placeholders = $placeholders;
                        $guest_placeholders['{bride_name}'] = $wedding_settings['bride_name'] ?? '新婦';
                        $guest_placeholders['{groom_name}'] = $wedding_settings['groom_name'] ?? '新郎';
                        $guest_placeholders['{wedding_date}'] = $wedding_settings['wedding_date'] ?? '未設定';
                        $guest_placeholders['{website_url}'] = $GLOBALS['site_url'];
                        
                        // 自作の確認メールテンプレート
                        $confirmation_template = "{name} 様\n\nこの度はメッセージをお寄せいただき、誠にありがとうございます。\n\n以下の内容で受け付けました：\n\nメッセージ：\n{message}\n\n管理者の承認後にゲストブックに表示されます。\n\n心より感謝申し上げます。\n{bride_name} & {groom_name}\n\n----\nこのメールは自動送信されています。\n{website_url}";
                        
                        // テンプレート内のプレースホルダーを置換
                        $confirmation_subject = "【結婚式】メッセージを受け付けました";
                        $confirmation_body = $confirmation_template;
                        
                        foreach ($guest_placeholders as $placeholder => $value) {
                            $confirmation_body = str_replace($placeholder, $value, $confirmation_body);
                        }
                        
                        // 投稿者へメール送信
                        $result = send_mail(
                            $email,                                    // 宛先
                            $confirmation_subject,                     // 件名
                            $confirmation_body,                        // 本文
                            $site_email,                               // 送信元メールアドレス
                            isset($wedding_settings['bride_name']) && isset($wedding_settings['groom_name']) 
                                ? $wedding_settings['bride_name'] . ' & ' . $wedding_settings['groom_name']
                                : 'Wedding Notification'               // 送信元名
                        );
                        
                        if ($debug_mode && !$result['success']) {
                            error_log("ゲストブック投稿の確認メール送信失敗: " . $result['message']);
                        }
                    }
                }
            } catch (Exception $e) {
                // メール送信エラーはログに記録するが、ユーザーには表示しない
                error_log("Guestbook notification error: " . $e->getMessage());
            }
            
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
            
            $_SESSION['error_messages'] = $errors;
        }
    } else {
        // エラーがあればエラーメッセージとともにリダイレクト
        $_SESSION['error_messages'] = $errors;
        $_SESSION['form_data'] = [
            'name' => $name,
            'email' => $email,
            'message' => $message,
            'group_name' => $group_name,
            'group_id' => $group_id
        ];
        
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