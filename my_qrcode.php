<?php
/**
 * マイページ（QRコード表示）
 * 
 * ゲストが自分のQRコードを確認できるページです。
 * 結婚式当日、このQRコードを会場の受付で提示することでスムーズなチェックインが可能です。
 */

// 設定ファイルを読み込み
require_once 'config.php';
require_once 'includes/qr_helper.php';

// URLからグループIDを取得
$group_id = isset($_GET['group']) ? htmlspecialchars($_GET['group']) : null;

// ゲスト情報を初期化
$guest_info = null;
$qr_code_html = '';
$error_message = '';
$attending = false;

// グループIDをチェック
if (!$group_id) {
    $error_message = 'グループIDが指定されていません。招待状のリンクをご確認ください。';
}

// グループIDが存在する場合、データベースからゲスト情報を取得
if ($group_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM guests WHERE group_id = :group_id ORDER BY id ASC LIMIT 1");
        $stmt->execute(['group_id' => $group_id]);
        $guest_data = $stmt->fetch();
        
        if ($guest_data) {
            $guest_info = $guest_data;
            
            // 出席回答を確認
            $stmt = $pdo->prepare("
                SELECT attending FROM responses 
                WHERE guest_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$guest_info['id']]);
            $attending_result = $stmt->fetch(PDO::FETCH_ASSOC);
            $attending = ($attending_result && $attending_result['attending'] == 1);
            
            // 出席回答がある場合のみQRコード生成
            if ($attending) {
                // QRコードトークンを確認または生成
                if (empty($guest_info['qr_code_token'])) {
                    $token = generate_qr_token($guest_info['id']);
                    if ($token) {
                        $guest_info['qr_code_token'] = $token;
                    } else {
                        $error_message = 'QRコードの生成に失敗しました。';
                    }
                }
                
                // QRコードHTMLを生成
                if (!empty($guest_info['qr_code_token'])) {
                    $qr_code_html = get_qr_code_html($guest_info['id'], [
                        'size' => 300,
                        'include_instructions' => true,
                        'instruction_text' => '結婚式当日、このQRコードを会場受付でご提示ください'
                    ]);
                }
            } else {
                $error_message = 'QRコードを表示するには、先に招待状から出席のご回答をお願いいたします。';
            }
        } else {
            $error_message = '指定されたグループIDのゲスト情報が見つかりませんでした。';
        }
    } catch (PDOException $e) {
        $error_message = 'データベースエラーが発生しました。';
        if ($debug_mode) {
            $error_message .= '<br>詳細: ' . $e->getMessage();
        }
    }
}

// 同伴者情報を取得
$companions = [];
if ($guest_info && isset($guest_info['group_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM guests 
            WHERE group_id = :group_id AND id != :id
            ORDER BY id ASC
        ");
        $stmt->execute([
            'group_id' => $guest_info['group_id'],
            'id' => $guest_info['id']
        ]);
        $companions = $stmt->fetchAll();
    } catch (PDOException $e) {
        // エラー処理（静かに失敗）
        if ($debug_mode) {
            error_log("同伴者情報取得エラー: " . $e->getMessage());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>マイQRコード - 結婚式チェックイン</title>
    
    <!-- スタイルシートの読み込み -->
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- QRコード生成ライブラリ -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <style>
        .mypage-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px 15px;
        }
        
        .mypage-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .mypage-header h1 {
            font-family: 'Noto Serif JP', serif;
            font-weight: 400;
            color: #4a4a4a;
            margin-bottom: 10px;
        }
        
        .mypage-header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .error-container {
            background-color: #fff5f5;
            border-left: 4px solid #ff5252;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .qr-section {
            background-color: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .guest-info-section {
            margin-top: 20px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
        }
        
        .guest-info-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #e0e0e0;
        }
        
        .guest-info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .guest-info-label {
            font-weight: 500;
            color: #555;
            margin-bottom: 5px;
            display: block;
        }
        
        .guest-info-value {
            font-size: 1.1rem;
        }
        
        .companions-section {
            margin-top: 30px;
        }
        
        .companions-title {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: #555;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
        }
        
        .companion-item {
            background-color: #f0f9ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .back-to-invite {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background-color: #6b9cae;
            color: white;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .back-to-invite:hover {
            background-color: #5a8a9c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .qr-code-container img {
            max-width: 100%;
            height: auto;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 15px;
            background-color: #fff;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
        }
        
        .qr-instructions {
            margin-top: 15px;
            font-size: 1.1rem;
            color: #555;
            line-height: 1.5;
            padding: 10px 20px;
            background-color: #f7f7f7;
            border-radius: 8px;
            display: inline-block;
        }
        
        .note-box {
            margin-top: 30px;
            padding: 15px;
            background-color: #fffde7;
            border-left: 4px solid #ffd600;
            border-radius: 5px;
        }
        
        .note-box h4 {
            color: #5d4037;
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .note-box p {
            margin: 5px 0;
            color: #5d4037;
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .mypage-container {
                padding: 20px 10px;
            }
            
            .qr-section {
                padding: 20px 15px;
            }
            
            .mypage-header h1 {
                font-size: 1.5rem;
            }
            
            .guest-info-value {
                font-size: 1rem;
            }
        }
        
        /* チェックインダイアログのスタイル */
        .checkin-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .checkin-dialog {
            background-color: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 80%;
            transform: translateY(20px);
            transition: transform 0.4s ease;
        }
        
        .checkin-success-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #4CAF50;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px;
        }
        
        .checkin-success-icon svg {
            width: 50px;
            height: 50px;
            stroke-dasharray: 80;
            stroke-dashoffset: 80;
            animation: check-draw 1s forwards;
        }
        
        @keyframes check-draw {
            to {
                stroke-dashoffset: 0;
            }
        }
        
        .checkin-message {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .checkin-submessage {
            font-size: 1rem;
            color: #666;
            margin-bottom: 25px;
        }
        
        .checkin-dialog-button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
        }
        
        .checkin-dialog-button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="mypage-container">
        <div class="mypage-header">
            <h1>結婚式チェックイン - マイQRコード</h1>
            <p>大切な日のスムーズな受付のために</p>
        </div>
        
        <?php if (!empty($error_message)): ?>
        <div class="error-container">
            <p><?= $error_message ?></p>
        </div>
        <?php endif; ?>
        
        <?php if ($guest_info && !empty($qr_code_html)): ?>
        <div class="qr-section">
            <h2><?= htmlspecialchars($guest_info['group_name']) ?></h2>
            <p>会場受付で提示するQRコード</p>
            
            <div class="qr-code-container">
                <div id="qrcode" style="margin: 0 auto; padding: 15px; background: #fff; border-radius: 10px; display: inline-block;"></div>
                <p class="qr-instructions">結婚式当日、会場受付でこのQRコードを提示してください</p>
                
                <?php if (isset($debug_mode) && $debug_mode === true): ?>
                <!-- デバッグ情報 -->
                <div style="margin-top: 20px; padding: 10px; background: #f8f8f8; border: 1px solid #ddd; text-align: left; font-size: 12px;">
                    <h4>デバッグ情報:</h4>
                    <p>ゲストID: <?= $guest_info['id'] ?></p>
                    <p>QRトークン: <?= htmlspecialchars($guest_info['qr_code_token']) ?></p>
                    <?php
                    // QRコードに埋め込むURL（ゲスト用案内ページへのリンク）
                    $guidance_url = $site_url . "guidance.php?token=" . urlencode($guest_info['qr_code_token']);
                    ?>
                    <p>埋め込みURL: <?= htmlspecialchars($guidance_url) ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="note-box">
                <h4>ご注意</h4>
                <p>・このQRコードは結婚式当日まで保存してください</p>
                <p>・会場の受付スタッフがスキャンしますので画面を提示するだけで大丈夫です</p>
                <p>・スクリーンショットの保存や印刷も可能です</p>
                <p>・このコードは招待状に記載されたグループ専用です</p>
                <p>・受付でスキャンすると席次案内などの情報が自動的に表示されます</p>
            </div>
            
            <!-- チェックイン説明 -->
            <div class="checkin-explanation" style="margin-top: 30px; padding: 20px; background-color: #f1f8e9; border-radius: 10px; border-left: 4px solid #8bc34a;">
                <h4 style="color: #33691e; margin-top: 0;"><i class="fas fa-info-circle"></i> チェックイン時の流れ</h4>
                <ol style="padding-left: 20px; color: #33691e;">
                    <li>会場受付でこのQRコードをスタッフに見せる</li>
                    <li>スタッフがQRコードをスキャン</li>
                    <li>スキャン完了後、自動的にチェックイン完了画面が表示される</li>
                    <li>席次や会場案内など、当日必要な情報が自動的にこのスマートフォンに表示される</li>
                </ol>
                <p style="margin-top: 15px; font-size: 0.9rem; color: #558b2f;">※チェックイン時はスマートフォンの電源を入れた状態でお待ちください</p>
            </div>
            
            <?php if (isset($debug_mode) && $debug_mode === true): ?>
            <!-- デバッグ用：リダイレクト機能テスト -->
            <div style="margin-top: 20px; padding: 10px; background: #f0f0f0; border: 1px solid #ddd; text-align: center;">
                <h4>デバッグ用：自動リダイレクト機能テスト</h4>
                <button id="test-notification" style="padding: 8px 16px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    通知をテスト送信
                </button>
                <div id="test-result" style="margin-top: 10px; font-size: 12px;"></div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="guest-info-section">
            <div class="guest-info-item">
                <span class="guest-info-label">グループ名</span>
                <div class="guest-info-value"><?= htmlspecialchars($guest_info['group_name']) ?></div>
            </div>
            <div class="guest-info-item">
                <span class="guest-info-label">ご到着予定時間</span>
                <div class="guest-info-value"><?= htmlspecialchars($guest_info['arrival_time']) ?></div>
            </div>
            <?php if (!empty($guest_info['custom_message'])): ?>
            <div class="guest-info-item">
                <span class="guest-info-label">メッセージ</span>
                <div class="guest-info-value"><?= nl2br(htmlspecialchars($guest_info['custom_message'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($companions)): ?>
        <div class="companions-section">
            <h3 class="companions-title">同伴者情報</h3>
            <?php foreach ($companions as $companion): ?>
            <div class="companion-item">
                <div class="guest-info-value"><?= htmlspecialchars($companion['name']) ?> 様</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="<?= $group_id ? "index.php?group=" . urlencode($group_id) : "index.php" ?>" class="back-to-invite">
                <i class="fas fa-arrow-left"></i> 招待状に戻る
            </a>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // QRコードを生成
        <?php if ($guest_info && !empty($guest_info['qr_code_token'])): ?>
        // QRコードに埋め込むURL（招待状ページへのリンク）
        const guidanceUrl = '<?= $site_url . "index.php?group=" . urlencode($guest_info['group_id']) . "&token=" . urlencode($guest_info['qr_code_token']) . "&auto_checkin=1" ?>';
        
        // QRコードを生成
        new QRCode(document.getElementById("qrcode"), {
            text: guidanceUrl,
            width: 256,
            height: 256,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
        
        // QRコードの下に埋め込みURLを表示（デバッグ用）
        <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
        const debugInfo = document.createElement('div');
        debugInfo.className = 'debug-info';
        debugInfo.style.fontSize = '10px';
        debugInfo.style.wordBreak = 'break-all';
        debugInfo.style.color = '#999';
        debugInfo.style.marginTop = '10px';
        debugInfo.style.padding = '5px';
        debugInfo.innerHTML = `<strong>デバッグ情報</strong><br>URL: ${guidanceUrl}`;
        document.getElementById('qrcode').parentNode.appendChild(debugInfo);
        <?php endif; ?>
        
        // チェックイン完了ダイアログを表示する関数
        function showCheckinDialog() {
            const dialog = document.createElement('div');
            dialog.className = 'checkin-dialog';
            
            const dialogContent = document.createElement('div');
            dialogContent.className = 'checkin-dialog-content';
            
            const icon = document.createElement('div');
            icon.className = 'success-icon';
            icon.innerHTML = '<i class="fas fa-check-circle"></i>';
            
            const message = document.createElement('div');
            message.className = 'success-message';
            message.innerHTML = '<h2>チェックイン完了</h2><p>受付が完了しました。会場案内ページへ移動します。</p>';
            
            const progressBar = document.createElement('div');
            progressBar.className = 'progress-bar';
            const progress = document.createElement('div');
            progress.className = 'progress';
            progressBar.appendChild(progress);
            
            dialogContent.appendChild(icon);
            dialogContent.appendChild(message);
            dialogContent.appendChild(progressBar);
            dialog.appendChild(dialogContent);
            
            document.body.appendChild(dialog);
            
            // プログレスバーのアニメーション
            setTimeout(() => {
                progress.style.width = '100%';
            }, 100);
            
            // 3秒後にリダイレクト
            setTimeout(() => {
                const groupId = '<?= urlencode($guest_info['group_id']) ?>';
                // group IDパラメータを追加してリダイレクト
                window.location.href = '<?= $site_url ?>index.php?group=' + groupId + '&checkin_complete=1';
            }, 3000);
        }
        
        // 通知をポーリングするための関数
        function checkForNotifications() {
            const token = '<?= urlencode($guest_info['qr_code_token']) ?>';
            const checkUrl = '<?= $site_url ?>check_notification.php?token=' + token + '&t=' + Date.now(); // キャッシュ防止
            
            console.log('通知チェック中...', checkUrl);
            const testResultElem = document.getElementById('test-result');
            if (testResultElem) {
                testResultElem.innerHTML = '最終チェック: ' + new Date().toLocaleTimeString();
            }
            
            fetch(checkUrl)
                .then(response => {
                    console.log('サーバーからの応答があります:', response.status);
                    if (!response.ok) {
                        throw new Error('サーバーエラー: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('通知チェック結果:', data);
                    // 通知がある場合の処理
                    if (data.success && data.has_notification) {
                        console.log('通知を受信しました - リダイレクト開始:', data);
                        
                        // アクションに応じた処理
                        if (data.action === 'redirect_to_index') {
                            // ポーリングを停止
                            clearInterval(pollingInterval);
                            
                            // おしゃれなダイアログを表示
                            showCheckinDialog();
                        }
                    }
                })
                .catch(error => {
                    console.error('通知チェックエラー:', error);
                    if (testResultElem) {
                        testResultElem.innerHTML = 'エラー: ' + error.message;
                    }
                    // エラーがあっても継続（次回のポーリングで回復する可能性）
                });
        }
        
        // 初回チェック（ページ読み込み時）
        checkForNotifications();
        
        // 1秒ごとにポーリング（より頻繁にチェック）
        const pollingInterval = setInterval(checkForNotifications, 1000);
        
        // ページがアンロードされる際にインターバルをクリア
        window.addEventListener('beforeunload', function() {
            clearInterval(pollingInterval);
        });
        
        // デバッグ用：直接遷移テスト
        function testDirectRedirect() {
            showCheckinDialog();
        }
        
        // ページが30秒以上アクティブな場合、明示的なテスト用ボタンを追加
        setTimeout(() => {
            // もしデバッグモードではなく、通常遷移も発生していない場合
            if (!document.querySelector('.checkin-overlay')) {
                const debugButton = document.createElement('div');
                debugButton.style.position = 'fixed';
                debugButton.style.bottom = '10px';
                debugButton.style.right = '10px';
                debugButton.style.backgroundColor = 'rgba(0,0,0,0.1)';
                debugButton.style.padding = '8px';
                debugButton.style.borderRadius = '5px';
                debugButton.style.fontSize = '10px';
                debugButton.style.cursor = 'pointer';
                debugButton.innerHTML = '接続テスト';
                debugButton.onclick = function() {
                    // サーバー接続をテスト
                    fetch('<?= $site_url ?>check_notification.php?test=1&t=' + Date.now())
                        .then(response => response.text())
                        .then(() => {
                            alert('サーバー接続は正常です');
                        })
                        .catch(() => {
                            alert('サーバー接続に問題があります');
                        });
                };
                document.body.appendChild(debugButton);
            }
        }, 30000);
        
        <?php if (isset($debug_mode) && $debug_mode === true): ?>
        // デバッグ用：通知テスト機能
        const testButton = document.getElementById('test-notification');
        const testResult = document.getElementById('test-result');
        
        if (testButton && testResult) {
            testButton.addEventListener('click', function() {
                testResult.innerHTML = '通知を送信中...';
                testResult.style.color = '#666';
                
                // 自分自身に通知を送信
                const pushUrl = '<?= $site_url ?>push_notification.php';
                testResult.innerHTML += '<br>送信先URL: ' + pushUrl;
                
                const pushData = new FormData();
                pushData.append('token', '<?= urlencode($guest_info['qr_code_token']) ?>');
                pushData.append('action', 'redirect_to_index');
                
                fetch(pushUrl, {
                    method: 'POST',
                    body: pushData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('サーバーエラー: ' + response.status + ' ' + response.statusText);
                    }
                    testResult.innerHTML += '<br>サーバーからの応答を受信しました';
                    return response.json();
                })
                .then(data => {
                    console.log('通知送信結果:', data);
                    testResult.innerHTML += '<br>応答データ: ' + JSON.stringify(data);
                    
                    if (data.success) {
                        testResult.innerHTML = '通知を送信しました！ダイアログが表示されます。';
                        testResult.style.color = '#4CAF50';
                        
                        // テスト用に直接ダイアログを表示
                        showCheckinDialog();
                    } else {
                        testResult.innerHTML = '通知送信失敗: ' + data.message;
                        if (data.debug) {
                            testResult.innerHTML += '<br>デバッグ情報: ' + JSON.stringify(data.debug);
                        }
                        testResult.style.color = '#f44336';
                    }
                })
                .catch(error => {
                    console.error('通知送信エラー:', error);
                    testResult.innerHTML = 'エラー: ' + error.message;
                    testResult.style.color = '#f44336';
                });
            });
        }
        <?php endif; ?>
        <?php endif; ?>
        
        // QRコードを長押しで保存できるようにするヒント表示
        const qrCodeContainer = document.getElementById('qrcode');
        if (qrCodeContainer) {
            // QRコード内の画像が生成された後に実行
            setTimeout(function() {
                const qrImage = qrCodeContainer.querySelector('img');
                if (qrImage) {
                    qrImage.addEventListener('contextmenu', function(e) {
                        // 右クリックメニューを表示（デフォルト動作を維持）
                    });
                    
                    // モバイルデバイス向けロングタップイベント
                    let timer;
                    qrImage.addEventListener('touchstart', function() {
                        timer = setTimeout(function() {
                            alert('QRコードを長押しすると保存できます');
                        }, 800);
                    });
                    
                    qrImage.addEventListener('touchend', function() {
                        clearTimeout(timer);
                    });
                }
            }, 500); // 少し遅延を入れて、QRコード画像が生成された後に実行
        }
    });
    </script>
</body>
</html> 