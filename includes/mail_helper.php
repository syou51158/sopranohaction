<?php
// Composerのオートロードファイルを読み込み
require_once __DIR__ . '/../vendor/autoload.php';

// PHPMailerクラスを使用
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * SMTPを使用してメールを送信する関数
 * 
 * @param string $to 宛先メールアドレス
 * @param string $subject 件名
 * @param string $message 本文
 * @param string $from_email 送信元メールアドレス
 * @param string $from_name 送信元名
 * @param array $reply_to 返信先の配列 ['email' => 'example@example.com', 'name' => '名前']
 * @return array ['success' => 成功/失敗, 'message' => エラーメッセージ]
 */
function send_mail($to, $subject, $message, $from_email, $from_name = '', $reply_to = null) {
    // 結果配列の初期化
    $result = [
        'success' => false,
        'message' => ''
    ];
    
    // PHPMailerのインスタンスを作成
    $mail = new PHPMailer(true);
    
    try {
        // サーバー設定
        $mail->isSMTP();                                      // SMTPを使用
        global $smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_secure, $mail_debug;

        $mail->Host       = $smtp_host;                // SMTPサーバー
        $mail->SMTPAuth   = !empty($smtp_password);    // SMTP認証を有効化 (パスワードがあればtrue)
        $mail->Username   = $smtp_username;            // SMTPユーザー名
        $mail->Password   = $smtp_password;            // SMTPパスワード
        if (strtolower($smtp_secure) === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else { // デフォルトは 'ssl' (SMTPS)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        $mail->Port       = $smtp_port;                 // ポート
        
        if ($mail_debug) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;            // デバッグ出力を有効化
            $mail->Debugoutput = function($str, $level) {
                $log_file = __DIR__ . '/../logs/mail_debug.log'; // ログディレクトリが存在するか確認が必要
                $log_dir = dirname($log_file);
                
                try {
                    // ログディレクトリが存在するか確認
                    if (!is_dir($log_dir)) {
                        if (!mkdir($log_dir, 0777, true)) {
                            error_log("Failed to create mail log directory: " . $log_dir);
                            return;
                        }
                    }
                    
                    // ディレクトリの権限を設定（エラー抑制）
                    @chmod($log_dir, 0777);
                    
                    // ログファイルがまだなければ作成
                    if (!file_exists($log_file)) {
                        touch($log_file);
                        @chmod($log_file, 0666); // すべてのユーザーが読み書き可能に（エラー抑制）
                    } else if (!is_writable($log_file)) {
                        @chmod($log_file, 0666); // すべてのユーザーが読み書き可能に（エラー抑制）
                    }
                    
                    // ファイルに書き込めるか確認してから書き込み
                    if (is_writable($log_file)) {
                        $timestamp = date('Y-m-d H:i:s');
                        file_put_contents($log_file, "$timestamp DEBUG [$level]: $str\n", FILE_APPEND);
                    } else {
                        error_log("Mail debug log file is not writable: " . $log_file);
                    }
                } catch (Exception $e) {
                    error_log("Error writing to mail debug log: " . $e->getMessage());
                }
            };
        } else {
            $mail->SMTPDebug = SMTP::DEBUG_OFF;               // デバッグ出力を無効化
        }
        
        // 文字セット
        $mail->CharSet = 'UTF-8';
        
        global $from_email, $from_name; // config.php から読み込んだ変数をグローバルスコープから取得
        $mail->setFrom($from_email, $from_name);
        
        // 返信先が指定されている場合は設定
        if ($reply_to) {
            $mail->addReplyTo($reply_to['email'], $reply_to['name'] ?? '');
        } else {
            $mail->addReplyTo($from_email, $from_name);
        }
        
        // 宛先
        $mail->addAddress($to);
        
        // 内容
        $mail->isHTML(true);  // HTMLメールとして送信
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message); // HTML非対応クライアント用プレーンテキスト
        
        // 送信
        $mail->send();
        
        $result['success'] = true;
        $result['message'] = 'メールが正常に送信されました。';
        
    } catch (Exception $e) {
        $result['message'] = "メール送信に失敗しました: {$mail->ErrorInfo}";
    }
    
    return $result;
}

/**
 * システムからのメール送信をラップする便利関数
 * 
 * @param string $to 宛先メールアドレス
 * @param string $subject 件名
 * @param string $message 本文
 * @return array ['success' => 成功/失敗, 'message' => エラーメッセージ]
 */
function send_system_mail($to, $subject, $message) {
    global $site_email;
    
    return send_mail(
        $to,
        $subject,
        $message,
        $site_email,
        '結婚式管理システム'
    );
}   