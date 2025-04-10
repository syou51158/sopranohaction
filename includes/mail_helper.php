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
        $mail->Host       = 'smtp.lolipop.jp';                // SMTPサーバー
        $mail->SMTPAuth   = true;                             // SMTP認証を有効化
        $mail->Username   = 'info-wedding@sopranohaction.fun'; // SMTPユーザー名
        $mail->Password   = 'Syou108810--'; // ロリポップのメールパスワードを設定してください
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;      // 暗号化方式（SSL）
        $mail->Port       = 465;                              // ポート
        
        // デバッグ情報（開発環境のみ有効にする）
        if (defined('MAIL_DEBUG') && MAIL_DEBUG) {
            // メールデバッグが有効な場合はログファイルに出力
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) {
                $log_file = __DIR__ . '/../logs/mail_debug.log';
                $timestamp = date('Y-m-d H:i:s');
                file_put_contents($log_file, "$timestamp DEBUG: $str\n", FILE_APPEND);
            };
        } else if (defined('DEBUG_MODE') && DEBUG_MODE) {
            // デバッグモードが有効だがメールデバッグは無効の場合はログファイルに出力
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) {
                $log_file = __DIR__ . '/../logs/mail_debug.log';
                $timestamp = date('Y-m-d H:i:s');
                file_put_contents($log_file, "$timestamp DEBUG: $str\n", FILE_APPEND);
            };
        } else {
            // どちらも無効の場合はデバッグ出力しない
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
        }
        
        // 文字セット
        $mail->CharSet = 'UTF-8';
        
        // 送信元
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