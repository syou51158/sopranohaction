<?php
// 設定ファイルを読み込み
require_once 'config.php';
require_once 'includes/qr_helper.php';

// 出力バッファリングを開始
ob_start();

// デバッグ用ログ関数
function log_debug($message) {
    global $debug_mode;
    if ($debug_mode) {
        try {
            $log_file = __DIR__ . '/logs/form_debug.log';
            $log_dir = dirname($log_file);
            
            // ログディレクトリがなければ作成
            if (!is_dir($log_dir)) {
                if (!mkdir($log_dir, 0777, true)) {
                    error_log("Failed to create log directory: " . $log_dir);
                    return; // ディレクトリ作成に失敗したら処理を中止
                }
            }
            
            // ディレクトリの権限を設定
            if (!chmod($log_dir, 0777)) {
                error_log("Failed to set permissions on log directory: " . $log_dir);
            }
            
            // ログファイルがまだなければ作成
            if (!file_exists($log_file)) {
                touch($log_file);
                chmod($log_file, 0666); // すべてのユーザーが読み書き可能に
            } else if (!is_writable($log_file)) {
                chmod($log_file, 0666); // すべてのユーザーが読み書き可能に
            }
            
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($log_file, "$timestamp $message\n", FILE_APPEND);
        } catch (Exception $e) {
            error_log("Error writing to debug log: " . $e->getMessage());
        }
    }
}

// POSTリクエストかどうか確認
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // フォームデータを取得
    $name = isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '';
    $attending = isset($_POST['attending']) ? (int)$_POST['attending'] : 0;
    $companions = isset($_POST['companions']) ? (int)$_POST['companions'] : 0;
    $message = isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '';
    $dietary = isset($_POST['dietary']) ? htmlspecialchars($_POST['dietary']) : '';
    $guest_id = isset($_POST['guest_id']) ? (int)$_POST['guest_id'] : null;
    $group_id = isset($_POST['group_id']) ? htmlspecialchars($_POST['group_id']) : '';
    $email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '';
    $postal_code = isset($_POST['postal_code']) ? htmlspecialchars($_POST['postal_code']) : '';
    $address = isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '';
    $recaptcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
    
    // デバッグログ - POSTデータ
    log_debug("POST Data: " . print_r($_POST, true));
    
    // reCAPTCHA検証
    $recaptcha_valid = false;
    if (!empty($recaptcha_response)) {
        // 開発環境かどうか自動検出（ローカルホストかどうかで判断）
        $is_development = (
            $_SERVER['SERVER_NAME'] == 'localhost' || 
            $_SERVER['SERVER_NAME'] == '127.0.0.1' ||
            strpos($_SERVER['SERVER_NAME'], '.local') !== false
        );
        
        if (!$is_development) {
            // 本番環境: 通常のreCAPTCHA検証を実行
            $recaptcha_secret = '6LfXwg8rAAAAAPIdyZWGj-VGMI_nbdS3aVj0E4nP'; // reCAPTCHA v3 シークレットキー
            $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
            $recaptcha_data = [
                'secret' => $recaptcha_secret,
                'response' => $recaptcha_response,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ];
            
            $recaptcha_options = [
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($recaptcha_data)
                ]
            ];
            
            $recaptcha_context = stream_context_create($recaptcha_options);
            $recaptcha_result = file_get_contents($recaptcha_url, false, $recaptcha_context);
            $recaptcha_json = json_decode($recaptcha_result, true);
            
            // v3では、スコアを評価する（0.0〜1.0の範囲、1.0が最も信頼性が高い）
            if ($recaptcha_json && isset($recaptcha_json['success']) && $recaptcha_json['success']) {
                $score = isset($recaptcha_json['score']) ? $recaptcha_json['score'] : 0;
                // スコアが0.5以上であれば信頼できるとみなす
                $recaptcha_valid = ($score >= 0.5);
                log_debug("reCAPTCHA v3 validation: Score=$score, Valid=" . ($recaptcha_valid ? 'true' : 'false'));
            } else {
                log_debug("reCAPTCHA validation failed: " . print_r($recaptcha_json, true));
            }
        } else {
            // 開発環境: 検証をスキップ
            $recaptcha_valid = true;
            log_debug("reCAPTCHA validation skipped in development environment");
        }
    } else {
        log_debug("No reCAPTCHA response received");
    }
    
    // デバッグログ - 処理されたデータ
    log_debug("Processed Data: name: $name, email: $email, postal_code: $postal_code, address: $address, attending: $attending, companions: $companions, guest_id: $guest_id, group_id: $group_id");
    
    // 基本的なバリデーション
    if (empty($name)) {
        $error = "お名前は必須です。";
        log_debug("Validation Error: Name is empty");
    } elseif (empty($email)) {
        $error = "メールアドレスは必須です。";
        log_debug("Validation Error: Email is empty");
    } elseif (!$recaptcha_valid) {
        $error = "reCAPTCHA認証に失敗しました。ロボットではないことを確認してください。";
        log_debug("Validation Error: reCAPTCHA validation failed");
    } else {
        try {
            // グループIDがある場合、そのグループに対する回答がないか確認
            $group_has_responses = false;
            if ($guest_id && $group_id) {
                $group_check_stmt = $pdo->prepare("
                    SELECT COUNT(*) as count FROM responses 
                    WHERE guest_id = :guest_id
                ");
                $group_check_stmt->execute(['guest_id' => $guest_id]);
                $group_result = $group_check_stmt->fetch();
                $group_has_responses = ($group_result['count'] > 0);
                
                if ($group_has_responses) {
                    log_debug("Group already has responses: group_id=$group_id, guest_id=$guest_id");
                    // グループに既に回答がある場合は、回答済みとしてリダイレクト
                    $redirect_url = 'thank_you.php?group=' . $group_id . '';
                    log_debug("Redirecting to: " . $redirect_url);
                    header('Location: ' . $redirect_url);
                    exit;
                }
            }
            
            // guest_idが0や空の場合はNULLに設定（外部キー制約のため）
            if (empty($guest_id)) {
                $guest_id = null;
                log_debug("Setting guest_id to NULL");
            }
            
            // データベースに保存
            $stmt = $pdo->prepare("
                 INSERT INTO responses 
                 (guest_id, name, email, attending, companions, message, dietary, postal_code, address) 
                 VALUES (:guest_id, :name, :email, :attending, :companions, :message, :dietary, :postal_code, :address)
            ");
            
            $params = [
                'guest_id' => $guest_id,
                'name' => $name,
                'email' => $email,
                'attending' => $attending,
                'companions' => $companions,
                'message' => $message,
                'dietary' => $dietary,
                'postal_code' => $postal_code,
                'address' => $address
            ];
            
            // デバッグログ - SQLパラメータ
            log_debug("SQL Parameters: " . print_r($params, true));
            
            $result = $stmt->execute($params);
            
            // デバッグログ - 挿入成功
            $last_id = $pdo->lastInsertId();
            log_debug("SQL Insert successful. Last Insert ID: " . $last_id);
            
            // QRコードトークンを生成（参加する場合のみ、存在しない場合）
            if ($result && $attending == 1 && $guest_id) {
                $qr_token = generate_qr_token($guest_id);
                log_debug("QRコードトークン生成: " . ($qr_token ? "成功" : "失敗") . " - ゲストID: $guest_id");
            }
            
            // 同伴者情報の保存
            if ($companions > 0 && $attending == 1) {
                // 同伴者の名前配列
                $companion_names = isset($_POST['companion_name']) ? $_POST['companion_name'] : [];
                $companion_ages = isset($_POST['companion_age']) ? $_POST['companion_age'] : [];
                $companion_dietaries = isset($_POST['companion_dietary']) ? $_POST['companion_dietary'] : [];
                
                // デバッグログ - 同伴者データ
                log_debug("Companion Data: " . print_r([
                    'names' => $companion_names,
                    'ages' => $companion_ages,
                    'dietaries' => $companion_dietaries
                ], true));
                
                // 同伴者データを保存
                for ($i = 0; $i < count($companion_names); $i++) {
                    if (!empty($companion_names[$i])) {
                        try {
                            $companion_stmt = $pdo->prepare("
                                INSERT INTO companions 
                                (response_id, name, age_group, dietary) 
                                VALUES (:response_id, :name, :age_group, :dietary)
                            ");
                            
                            $companion_params = [
                                'response_id' => $last_id,
                                'name' => htmlspecialchars($companion_names[$i]),
                                'age_group' => isset($companion_ages[$i]) ? htmlspecialchars($companion_ages[$i]) : 'adult',
                                'dietary' => isset($companion_dietaries[$i]) ? htmlspecialchars($companion_dietaries[$i]) : ''
                            ];
                            
                            $companion_stmt->execute($companion_params);
                            log_debug("Companion inserted: " . $companion_names[$i]);
                        } catch (PDOException $ce) {
                            // 同伴者の保存に失敗しても、メイン回答は保存済みなので続行する
                            log_debug("Error saving companion: " . $ce->getMessage());
                        }
                    }
                }
            }
            
            // QRコード生成（ゲストIDがある場合のみ）
            $qr_code_html = '';
            $qr_code_token = '';
            
            if ($guest_id) {
                // QRコードトークンを生成
                $qr_code_token = generate_qr_token($guest_id);
                log_debug("Generated QR token for guest_id: $guest_id, token: $qr_code_token");
                
                if ($qr_code_token) {
                    // QRコードHTMLを生成
                    $qr_code_html = get_qr_code_html($guest_id, [
                        'size' => 200,
                        'instruction_text' => '会場受付でこのQRコードをお見せください'
                    ]);
                }
            } else {
                // 既存のゲストレコードがない場合は、新しく作成
                try {
                    // まず、同じ名前とメールアドレスでゲストが存在するか確認
                    $check_guest_stmt = $pdo->prepare("
                        SELECT id FROM guests 
                        WHERE name = ? AND email = ?
                    ");
                    $check_guest_stmt->execute([$name, $email]);
                    $existing_guest_id = $check_guest_stmt->fetchColumn();
                    
                    if ($existing_guest_id) {
                        // 既存のゲストIDを使用
                        $guest_id = $existing_guest_id;
                    } else {
                        // 新しいゲストレコードを作成
                        $create_guest_stmt = $pdo->prepare("
                            INSERT INTO guests (name, email, group_name, group_id) 
                            VALUES (?, ?, ?, ?)
                        ");
                        
                        // グループIDがない場合は生成
                        if (empty($group_id)) {
                            $group_id = 'G' . uniqid();
                        }
                        
                        $group_name = $name . 'のグループ';
                        $create_guest_stmt->execute([$name, $email, $group_name, $group_id]);
                        
                        $guest_id = $pdo->lastInsertId();
                        log_debug("Created new guest record: $guest_id");
                    }
                    
                    // QRコードトークンを生成
                    if ($guest_id) {
                        $qr_code_token = generate_qr_token($guest_id);
                        log_debug("Generated QR token for new guest_id: $guest_id, token: $qr_code_token");
                        
                        if ($qr_code_token) {
                            // QRコードHTMLを生成
                            $qr_code_html = get_qr_code_html($guest_id, [
                                'size' => 200,
                                'instruction_text' => '会場受付でこのQRコードをお見せください'
                            ]);
                        }
                        
                        // responsesテーブルのguest_idを更新
                        $update_response_stmt = $pdo->prepare("
                            UPDATE responses SET guest_id = ? WHERE id = ?
                        ");
                        $update_response_stmt->execute([$guest_id, $guest_id]);
                    }
                } catch (PDOException $e) {
                    log_debug("Error creating guest record: " . $e->getMessage());
                    // エラーが発生しても処理を続行
                }
            }
            
            // 通知を送信
            try {
                // 通知ヘルパーを読み込み
                require_once 'includes/notification_helper.php';
                require_once 'includes/mail_helper.php';
                
                // 最新の回答データを取得 - 修正: guest_idではなくレスポンスID(last_id)を使用
                $response_stmt = $pdo->prepare("SELECT * FROM responses WHERE id = ?");
                $response_stmt->execute([$last_id]);
                $response_data = $response_stmt->fetch();
                
                // レスポンスデータが取得できた場合のみ通知を送信
                if ($response_data) {
                    // 通知送信
                    send_rsvp_notification($response_data);
                    log_debug("Notification sent for response ID: " . $last_id);
                } else {
                    log_debug("Failed to get response data for ID: " . $last_id);
                }
                
                // 出席者にはQRコード付きの確認メールを送信
                if ($attending == 1 && !empty($email) && !empty($qr_code_html)) {
                    // 結婚式設定情報を取得
                    $wedding_settings = [];
                    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM wedding_settings");
                    while ($row = $settings_stmt->fetch()) {
                        $wedding_settings[$row['setting_key']] = $row['setting_value'];
                    }
                    
                    // メールのタイトルと本文
                    $subject = "【招待状の受付確認】" . ($wedding_settings['couple_name'] ?? '翔＆あかね') . "の結婚式";
                    
                    // メール本文にQRコードのHTMLを含める
                    $body = "
                        <html>
                        <head>
                            <style>
                                body { font-family: 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { text-align: center; margin-bottom: 20px; }
                                .message { margin-bottom: 30px; }
                                .qr-section { text-align: center; margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9; }
                                .qr-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #4CAF50; }
                                .qr-instructions { margin-top: 15px; font-size: 14px; color: #666; }
                                .footer { margin-top: 30px; font-size: 12px; color: #777; text-align: center; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h2>" . ($wedding_settings['couple_name'] ?? '翔＆あかね') . "の結婚式</h2>
                                </div>
                                
                                <div class='message'>
                                    <p>" . htmlspecialchars($name) . " 様</p>
                                    <p>結婚式の出席登録ありがとうございます。以下の内容で受け付けました。</p>
                                    <ul>
                                        <li>お名前: " . htmlspecialchars($name) . "</li>
                                        <li>ご出欠: 出席</li>
                                        <li>同伴者数: " . $companions . "名</li>
                                        <li>日時: " . ($wedding_settings['wedding_date'] ?? '2024年4月30日') . " " . ($wedding_settings['ceremony_time'] ?? '13:00') . "〜</li>
                                        <li>会場: " . ($wedding_settings['venue_name'] ?? '結婚式場') . "</li>
                                    </ul>
                                </div>
                                
                                <div class='qr-section'>
                                    <div class='qr-title'>📱 スマートチェックイン用QRコード</div>
                                    <p>多くのメールクライアントではセキュリティのため画像が自動的に表示されません。</p>
                                    <div class='qr-button-container'>
                                        <a href='{$site_url}my_qrcode.php?group={$group_id}' class='qr-link-button' style='display:inline-block; padding:12px 20px; background-color:#4285F4; color:white; text-decoration:none; border-radius:5px; font-weight:bold; margin:15px 0;'>
                                            QRコードを表示する（ブラウザで開きます）
                                        </a>
                                    </div>
                                    <p class='qr-instructions' style='margin-top:15px; font-size:14px; color:#555;'>
                                        ※当日の受付をスムーズにするために、リンク先のQRコードを保存しておいてください。<br>
                                        会場の受付でこのQRコードをご提示いただくとスムーズにご案内いたします。
                                    </p>
                                </div>
                                
                                <style>
                                .qr-section {
                                    background-color: #f0f8ff;
                                    border-radius: 10px;
                                    padding: 20px;
                                    margin: 20px 0;
                                    text-align: center;
                                    border: 2px dashed #4285F4;
                                }
                                .qr-title {
                                    font-size: 18px;
                                    font-weight: bold;
                                    color: #4285F4;
                                    margin-bottom: 15px;
                                }
                                .qr-instructions {
                                    margin-top: 15px;
                                    font-size: 14px;
                                    color: #555;
                                }
                                .qr-link-container {
                                    margin-top: 15px;
                                }
                                .qr-link-button {
                                    display: inline-block;
                                    padding: 10px 20px;
                                    background-color: #4285F4;
                                    color: white;
                                    text-decoration: none;
                                    border-radius: 5px;
                                    font-weight: bold;
                                }
                                </style>
                                
                                <p>お会いできることを楽しみにしております。何かご不明な点がありましたら、ご連絡ください。</p>
                                
                                <div class='footer'>
                                    <p>※このメールは自動送信されています。ご返信いただいてもお答えできません。</p>
                                    <p>&copy; " . date('Y') . " " . ($wedding_settings['couple_name'] ?? '翔＆あかね') . " Wedding</p>
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    // メール送信
                    $mail_result = send_mail(
                        $email,                                  // 宛先
                        $subject,                                // 件名
                        $body,                                   // 本文
                        $site_email,                             // 送信元メールアドレス
                        $wedding_settings['couple_name'] ?? '翔＆あかね'  // 送信元名
                    );
                    log_debug("QR code email sent to $email: " . ($mail_result['success'] ? "Success" : "Failed - " . $mail_result['message']));
                }
                
                log_debug("Notification sent for response ID: " . $last_id);
            } catch (Exception $e) {
                // 通知送信に失敗しても処理を続行
                log_debug("Failed to send notification: " . $e->getMessage());
            }
            
            // 成功メッセージを設定
            $success = true;
            
            // 最後にQRコードを生成（出席者のみ）
            if ($attending) {
                // 未定義の関数を呼び出さないように修正
                // 既にQRコードは上のコードで生成されているので、ここでは不要
                // generate_qr_for_guest($response_id, $guest_id, $email);
                
                // QRコードトークンがまだない場合のみ生成を試みる
                if ($guest_id && !$qr_code_token) {
                    $qr_code_token = generate_qr_token($guest_id);
                    log_debug("出席者用QRコードトークン生成（修正後）: " . ($qr_code_token ? "成功" : "失敗") . " - ゲストID: $guest_id");
                }
            }
            
            // グループIDが存在する場合は、グループページにリダイレクト
            if ($group_id) {
                // リダイレクト先を構築
                $redirect_url = 'thank_you.php?group=' . $group_id; // ありがとうページへリダイレクト
                
                // QRコードトークンが存在する場合は、それも追加
                if (isset($qr_token) && !empty($qr_token)) {
                    $redirect_url .= '&token=' . $qr_token;
                }
                
                // 成功メッセージとともにリダイレクト
                log_debug("Redirecting to: " . $redirect_url);
                header('Location: ' . $redirect_url);
                exit;
            } else {
                // グループIDがない場合はトップページにリダイレクト（理論上あまり起きない）
                header('Location: thank_you.php');
                exit;
            }
        } catch (PDOException $e) {
            // エラーメッセージを設定
            $error = "送信に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
                log_debug("PDO Error: " . $e->getMessage());
            }
        }
    }
}

// 成功時のみヘッダーリダイレクトを設定
if (isset($success) && $success) {
    // リダイレクトURLが設定されていない場合のデフォルト値を設定
    if (!isset($redirect_url) || empty($redirect_url)) {
        $redirect_url = 'thank_you.php';
        
        // グループIDがある場合はクエリパラメータとして追加
        if (!empty($group_id)) {
            $redirect_url .= '?group=' . urlencode($group_id);
        }
    }
    
    // ヘッダーリダイレクトの前に何も出力していないことを確認
    // JavaScriptリダイレクトもバックアップとして使用
    header("Location: $redirect_url");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>回答受付 - <?= $site_name ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    /* エレガントな遷移アニメーション */
    body {
        animation: fadeOutTransition 3s forwards;
        animation-delay: 1.5s;
    }
    
    .success-message {
        animation: pulseAndFadeOut 3s forwards;
    }
    
    @keyframes fadeOutTransition {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    
    @keyframes pulseAndFadeOut {
        0% { transform: scale(1); }
        10% { transform: scale(1.05); }
        20% { transform: scale(1); }
        100% { transform: scale(1); }
    }

    /* 同伴者セクションのスタイル改善 */
    #companion-details {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 2px dashed #ddd;
    }
    
    .companion-fieldset {
        background-color: #f9f9f9;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 8px;
        border-left: 3px solid #4CAF50;
    }
    
    .companion-title {
        background-color: #4CAF50;
        color: white;
        padding: 5px 10px;
        display: inline-block;
        border-radius: 4px;
        margin-top: 0;
    }
    
    /* 出席・欠席カードスタイルを追加 */
    .attendance-cards {
        display: flex;
        gap: 15px;
        margin-top: 10px;
        flex-wrap: wrap;
    }
    
    .attendance-card {
        flex: 1;
        min-width: 130px;
        position: relative;
        cursor: pointer;
    }
    
    .attendance-card input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .card-content {
        border: 2px solid #ddd;
        border-radius: 10px;
        padding: 20px 15px;
        text-align: center;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.08);
    }
    
    .card-icon {
        font-size: 28px;
        margin-bottom: 10px;
        color: #888;
    }
    
    .card-text {
        font-weight: 500;
        font-size: 16px;
    }
    
    /* 出席カードのスタイル */
    #attend-yes:checked + .card-content {
        border-color: #4CAF50;
        background-color: rgba(76, 175, 80, 0.1);
    }
    
    #attend-yes:checked + .card-content .card-icon,
    #attend-yes:checked + .card-content .card-text {
        color: #4CAF50;
    }
    
    /* 欠席カードのスタイル */
    #attend-no:checked + .card-content {
        border-color: #F44336;
        background-color: rgba(244, 67, 54, 0.1);
    }
    
    #attend-no:checked + .card-content .card-icon,
    #attend-no:checked + .card-content .card-text {
        color: #F44336;
    }
    
    /* ホバー効果 */
    .card-content:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.12);
    }
    
    /* モバイル最適化 */
    @media (max-width: 480px) {
        .attendance-cards {
            gap: 10px;
        }
        
        .card-content {
            padding: 15px 10px;
        }
        
        .card-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .card-text {
            font-size: 15px;
        }
    }
    </style>
</head>
<body>
    <div class="response-container">
        <div class="response-card">
            <?php if (isset($success) && $success) { ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <h2>ご回答ありがとうございます</h2>
                    <p>ご出欠の回答を受け付けました。</p>
                    <p>感謝のページへご案内します...</p>
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                </div>
                <style>
                /* エレガントな遷移アニメーション */
                body {
                    animation: fadeOutTransition 3s forwards;
                    animation-delay: 1.5s;
                }
                
                .success-message {
                    animation: pulseAndFadeOut 3s forwards;
                }
                
                @keyframes fadeOutTransition {
                    from { opacity: 1; }
                    to { opacity: 0; }
                }
                
                @keyframes pulseAndFadeOut {
                    0% { transform: scale(1); }
                    10% { transform: scale(1.05); }
                    20% { transform: scale(1); }
                    100% { transform: scale(1); }
                }
                </style>
                <script>
                    // 画面遷移を確実にするためのJavaScriptリダイレクト
                    setTimeout(function() {
                        <?php if (isset($redirect_url) && !empty($redirect_url)) { ?>
                        window.location.href = "<?= $redirect_url ?>";
                        <?php } else { ?>
                        window.location.href = "thank_you.php<?= !empty($group_id) ? '?group=' . urlencode($group_id) : '' ?>";
                        <?php } ?>
                    }, 3000); // 3秒後に遷移
                </script>
            <?php } else { ?>
                <div class="response-form">
                    <h2><i class="fas fa-envelope-open-text"></i> ご回答フォーム</h2>
                    
                    <?php if (isset($error)) { ?>
                        <div class="error-message">
                            <?= $error ?>
                        </div>
                    <?php } ?>
                    
                    <form id="rsvp-form" method="post" action="process_rsvp.php">
                        <?php
                        // URLパラメータからグループIDを取得
                        $group_id = isset($_GET['group']) ? htmlspecialchars($_GET['group']) : '';
                        
                        // グループIDからゲスト情報を取得（存在する場合）
                        $guest_info = [
                            'id' => null,
                            'name' => '',
                            'email' => '',
                            'max_companions' => 5
                        ];
                        
                        if (!empty($group_id)) {
                            try {
                                $stmt = $pdo->prepare("SELECT * FROM guests WHERE group_id = :group_id LIMIT 1");
                                $stmt->execute(['group_id' => $group_id]);
                                $row = $stmt->fetch();
                                
                                if ($row) {
                                    $guest_info = [
                                        'id' => $row['id'],
                                        'name' => $row['name'],
                                        'email' => $row['email'],
                                        'max_companions' => $row['max_companions'] ?: 5
                                    ];
                                }
                            } catch (PDOException $e) {
                                // エラー処理（静かに失敗）
                                if ($debug_mode) {
                                    echo "<!-- データベースエラー: " . $e->getMessage() . " -->";
                                }
                            }
                        }
                        ?>
                        
                        <!-- 隠しフィールド -->
                        <input type="hidden" name="guest_id" value="<?= $guest_info['id'] ?>">
                        <input type="hidden" name="group_id" value="<?= $group_id ?>">
                        
                        <div class="form-group">
                            <label for="name">お名前 <span class="required">*</span></label>
                            <input type="text" id="name" name="name" required
                                   value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($guest_info['name']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">メールアドレス <span class="required">*</span></label>
                            <input type="email" id="email" name="email" required
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($guest_info['email']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>ご出欠 <span class="required">*</span></label>
                            <div class="attendance-cards">
                                <label class="attendance-card" for="attend-yes">
                                    <input type="radio" id="attend-yes" name="attending" value="1" 
                                           <?= (isset($_POST['attending']) && $_POST['attending'] == 1) ? 'checked' : '' ?> required>
                                    <div class="card-content">
                                        <div class="card-icon">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="card-text">出席します</div>
                                    </div>
                                </label>
                                <label class="attendance-card" for="attend-no">
                                    <input type="radio" id="attend-no" name="attending" value="0" 
                                           <?= (isset($_POST['attending']) && $_POST['attending'] == 0) ? 'checked' : '' ?> required>
                                    <div class="card-content">
                                        <div class="card-icon">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                        <div class="card-text">欠席します</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group" id="companions-group">
                            <label for="companions">ご同伴者の人数</label>
                            <select id="companions" name="companions">
                                <option value="0">なし</option>
                                <?php for ($i = 1; $i <= $guest_info['max_companions']; $i++): ?>
                                    <option value="<?= $i ?>" <?= (isset($_POST['companions']) && $_POST['companions'] == $i) ? 'selected' : '' ?>><?= $i ?>名</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div id="companion-details" style="display:none;">
                            <h3>ご同伴者の情報</h3>
                            <div id="companion-fields"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="dietary"><strong>ご本人様</strong>の食事に関するご要望（アレルギーなど）</label>
                            <textarea id="dietary" name="dietary" rows="2" placeholder="ご本人様のアレルギーや食事制限をご記入ください"><?= isset($_POST['dietary']) ? htmlspecialchars($_POST['dietary']) : '' ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">メッセージ</label>
                            <textarea id="message" name="message" rows="4"><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
                        </div>
                        
                        <button type="submit" class="submit-button">
                            <i class="fas fa-paper-plane"></i> 送信する
                        </button>
                    </form>
                    
                    <div class="form-footer">
                        <a href="index.php<?= $group_id ? '?group=' . urlencode($group_id) : '' ?>" class="back-link">
                            <i class="fas fa-arrow-left"></i> 招待状に戻る
                        </a>
                    </div>
                </div>
                
                <script>
                // 同伴者フィールドの動的制御
                document.addEventListener('DOMContentLoaded', function() {
                    const attendingRadios = document.querySelectorAll('input[name="attending"]');
                    const companionsGroup = document.getElementById('companions-group');
                    const companionsSelect = document.getElementById('companions');
                    const companionDetails = document.getElementById('companion-details');
                    const companionFields = document.getElementById('companion-fields');
                    
                    // 出欠選択の変更を監視
                    attendingRadios.forEach(radio => {
                        radio.addEventListener('change', function() {
                            if (this.value === '1') { // 出席
                                companionsGroup.style.display = 'block';
                                updateCompanionFields();
                            } else { // 欠席
                                companionsGroup.style.display = 'none';
                                companionDetails.style.display = 'none';
                                companionsSelect.value = '0';
                            }
                        });
                    });
                    
                    // 初期状態の設定
                    const selectedAttending = document.querySelector('input[name="attending"]:checked');
                    if (selectedAttending) {
                        if (selectedAttending.value === '0') {
                            companionsGroup.style.display = 'none';
                        } else {
                            updateCompanionFields();
                        }
                    } else {
                        companionsGroup.style.display = 'none';
                    }
                    
                    // 同伴者数の変更を監視
                    companionsSelect.addEventListener('change', updateCompanionFields);
                    
                    // 同伴者フィールドの更新
                    function updateCompanionFields() {
                        const count = parseInt(companionsSelect.value);
                        companionFields.innerHTML = '';
                        
                        if (count > 0) {
                            companionDetails.style.display = 'block';
                            
                            for (let i = 0; i < count; i++) {
                                const fieldSet = document.createElement('div');
                                fieldSet.className = 'companion-fieldset';
                                fieldSet.innerHTML = `
                                    <h4 class="companion-title">同伴者 ${i + 1}</h4>
                                    <div class="form-group">
                                        <label for="companion_name_${i}">お名前</label>
                                        <input type="text" id="companion_name_${i}" name="companion_name[]" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="companion_age_${i}">年齢区分</label>
                                        <select id="companion_age_${i}" name="companion_age[]">
                                            <option value="adult">大人</option>
                                            <option value="child">子供（小学生〜中学生）</option>
                                            <option value="infant">幼児（未就学児）</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="companion_dietary_${i}"><strong>同伴者 ${i + 1}</strong> の食事に関するご要望（アレルギーなど）</label>
                                        <textarea id="companion_dietary_${i}" name="companion_dietary[]" rows="2" placeholder="この同伴者のアレルギーや食事制限をご記入ください"></textarea>
                                    </div>
                                `;
                                companionFields.appendChild(fieldSet);
                            }
                        } else {
                            companionDetails.style.display = 'none';
                        }
                    }
                    
                    // reCAPTCHA v3トークンの追加
                    const rsvpForm = document.getElementById('rsvp-form');
                    if (rsvpForm) {
                        rsvpForm.addEventListener('submit', function(e) {
                            e.preventDefault();
                            
                            // 送信ボタンを「送信中...」に変更
                            const submitBtn = document.querySelector('button[type="submit"]');
                            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 送信中...';
                            submitBtn.disabled = true;
                            
                            grecaptcha.ready(function() {
                                grecaptcha.execute('6LfXwg8rAAAAAO8tgbD74yqTFHK9ZW6Ns18M8GpF', {action: 'submit'}).then(function(token) {
                                    // トークンを隠しフィールドとして追加
                                    const input = document.createElement('input');
                                    input.type = 'hidden';
                                    input.name = 'g-recaptcha-response';
                                    input.value = token;
                                    rsvpForm.appendChild(input);
                                    
                                    // フォームを送信
                                    rsvpForm.submit();
                                });
                            });
                        });
                    }
                });
                </script>
            <?php } ?>
        </div>
    </div>
</body>
</html>