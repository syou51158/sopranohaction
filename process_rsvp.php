<?php
// 設定ファイルを読み込み
require_once 'config.php';

// デバッグ用ログ関数
function log_debug($message) {
    global $debug_mode;
    if ($debug_mode) {
        $log_file = 'logs/form_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "$timestamp $message\n", FILE_APPEND);
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
    
    // デバッグログ - POSTデータ
    log_debug("POST Data: " . print_r($_POST, true));
    
    // デバッグログ - 処理されたデータ
    log_debug("Processed Data: name: $name, email: $email, attending: $attending, companions: $companions, guest_id: $guest_id, group_id: $group_id");
    
    // 基本的なバリデーション
    if (empty($name)) {
        $error = "お名前は必須です。";
        log_debug("Validation Error: Name is empty");
    } elseif (empty($email)) {
        $error = "メールアドレスは必須です。";
        log_debug("Validation Error: Email is empty");
    } else {
        try {
            // 重複チェック - 同じメールアドレスと名前の組み合わせで既に返信がないか確認
            $check_stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM responses 
                WHERE email = :email AND name = :name
            ");
            $check_stmt->execute([
                'email' => $email,
                'name' => $name
            ]);
            $result = $check_stmt->fetch();
            
            if ($result['count'] > 0) {
                $error = "すでに同じ情報で回答が送信されています。";
                log_debug("Duplicate submission detected: $name, $email");
            } else {
                // guest_idが0や空の場合はNULLに設定（外部キー制約のため）
                if (empty($guest_id)) {
                    $guest_id = null;
                    log_debug("Setting guest_id to NULL");
                }
                
                // データベースに保存
                $stmt = $pdo->prepare("
                    INSERT INTO responses 
                    (guest_id, name, email, attending, companions, message, dietary) 
                    VALUES (:guest_id, :name, :email, :attending, :companions, :message, :dietary)
                ");
                
                $params = [
                    'guest_id' => $guest_id,
                    'name' => $name,
                    'email' => $email,
                    'attending' => $attending,
                    'companions' => $companions,
                    'message' => $message,
                    'dietary' => $dietary
                ];
                
                // デバッグログ - SQLパラメータ
                log_debug("SQL Parameters: " . print_r($params, true));
                
                $stmt->execute($params);
                
                // デバッグログ - 挿入成功
                $last_id = $pdo->lastInsertId();
                log_debug("SQL Insert successful. Last Insert ID: " . $last_id);
                
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
                
                // 通知を送信
                try {
                    // 通知ヘルパーを読み込み
                    require_once 'includes/notification_helper.php';
                    
                    // 最新の回答データを取得
                    $response_stmt = $pdo->prepare("SELECT * FROM responses WHERE id = ?");
                    $response_stmt->execute([$last_id]);
                    $response_data = $response_stmt->fetch();
                    
                    // 通知送信
                    send_rsvp_notification($response_data);
                    
                    log_debug("Notification sent for response ID: " . $last_id);
                } catch (Exception $e) {
                    // 通知送信に失敗しても処理を続行
                    log_debug("Failed to send notification: " . $e->getMessage());
                }
                
                // 成功メッセージを設定
                $success = true;
                
                // リダイレクト先を設定
                $redirect_url = $group_id ? "thank_you.php?group=$group_id" : "thank_you.php";
                
                // 3秒後にリダイレクト
                header("Refresh: 3; URL=$redirect_url");
                
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
</head>
<body>
    <div class="response-container">
        <div class="response-card">
            <?php if (isset($success) && $success): ?>
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
                    animation-delay: 1s;
                }
                
                @keyframes fadeOutTransition {
                    0% { opacity: 1; }
                    100% { opacity: 0; }
                }
                
                @keyframes pulseAndFadeOut {
                    0% { transform: scale(1); opacity: 1; }
                    50% { transform: scale(1.05); opacity: 1; }
                    100% { transform: scale(1); opacity: 0; }
                }
                </style>
                <script>
                // JavaScript による確実なリダイレクト処理
                setTimeout(function() {
                    document.body.style.opacity = 0;
                    document.body.style.transition = "opacity 0.5s ease-out";
                    
                    setTimeout(function() {
                        window.location.href = '<?= $redirect_url ?? "thank_you.php" ?>';
                    }, 500);
                }, 2500);
                </script>
            <?php elseif (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle fa-3x"></i>
                    <h2>エラーが発生しました</h2>
                    <p class="error-text"><?= $error ?></p>
                    <a href="javascript:history.back()" class="back-button"><i class="fas fa-arrow-left"></i> 戻る</a>
                </div>
            <?php else: ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle fa-3x"></i>
                    <h2>不正なアクセスです</h2>
                    <p class="error-text">フォームから送信してください。</p>
                    <a href="index.php" class="back-button"><i class="fas fa-home"></i> トップに戻る</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 