<?php
/**
 * QRコードチェックインページ
 * 
 * 結婚式会場の受付でスタッフがゲストのQRコードをスキャンして
 * チェックインを記録するためのページです。
 */

// 設定の読み込み
require_once '../config.php';
require_once '../includes/qr_helper.php';

// セッション開始
session_start();

// 管理者認証チェック
if (!isset($_SESSION['admin_id'])) {
    // クエリパラメータを保持してログインページにリダイレクト
    $redirect = 'login.php';
    if (isset($_GET['token'])) {
        $redirect .= '?redirect=checkin.php&token=' . urlencode($_GET['token']);
    }
    header("Location: $redirect");
    exit;
}

// 初期化
$error = '';
$success = '';
$guest = null;

// QRコードトークンが提供された場合
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // トークンからゲスト情報を取得
    $guest = get_guest_by_qr_token($token);
    
    // 自動チェックイン（scan_actionパラメータがある場合）
    if (isset($_GET['scan_action']) && $_GET['scan_action'] === 'auto_checkin' && $guest) {
        // 自動チェックイン処理
        $notes = "QRコードスキャン自動チェックイン";
        $checked_by = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : '管理者';
        
        // リダイレクト機能を無効化（常に false を渡す）
        $result = record_guest_checkin($guest['id'], $checked_by, $notes, false);
        
        if ($result) {
            $success = htmlspecialchars($guest['group_name']) . ' のチェックインを自動記録しました！';
            
            // 同伴者情報を取得（該当する場合）
            try {
                $stmt = $pdo->prepare("
                    SELECT * FROM guests 
                    WHERE group_id = ? AND id != ?
                ");
                $stmt->execute([$guest['group_id'], $guest['id']]);
                $companions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $companions = [];
                error_log("同伴者情報取得エラー: " . $e->getMessage());
            }
            
            // ゲストのスマホに通知を送信
            $push_url = $site_url . 'push_notification.php';
            $push_data = [
                'token' => $token,
                'action' => 'redirect_to_guidance'
            ];
            
            // cURLを使用してPOSTリクエストを送信
            $ch = curl_init($push_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $push_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $push_response = curl_exec($ch);
            curl_close($ch);
            
            // 通知送信結果をログに記録
            error_log("プッシュ通知送信結果: " . $push_response);
        } else {
            $error = 'チェックインの自動記録に失敗しました。';
            error_log("自動チェックイン失敗: token=$token, guest_id=" . ($guest ? $guest['id'] : 'なし'));
        }
    }
    // 通常のPOSTによるチェックイン処理
    else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin']) && $guest) {
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        $checked_by = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : '管理者';
        
        // リダイレクト機能を無効化（常に false を渡す）
        $result = record_guest_checkin($guest['id'], $checked_by, $notes, false);
        
        if ($result) {
            $success = htmlspecialchars($guest['group_name']) . ' のチェックインを記録しました！';
            
            // 同伴者情報を取得（該当する場合）
            try {
                $stmt = $pdo->prepare("
                    SELECT * FROM guests 
                    WHERE group_id = ? AND id != ?
                ");
                $stmt->execute([$guest['group_id'], $guest['id']]);
                $companions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $companions = [];
                error_log("同伴者情報取得エラー: " . $e->getMessage());
            }
            
            // ゲストのスマホに通知を送信
            $push_url = $site_url . 'push_notification.php';
            $push_data = [
                'token' => $token,
                'action' => 'redirect_to_guidance'
            ];
            
            // cURLを使用してPOSTリクエストを送信
            $ch = curl_init($push_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $push_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $push_response = curl_exec($ch);
            curl_close($ch);
            
            // 通知送信結果をログに記録
            error_log("プッシュ通知送信結果: " . $push_response);
        } else {
            $error = 'チェックインの記録に失敗しました。';
            error_log("チェックイン失敗: token=$token, guest_id=" . ($guest ? $guest['id'] : 'なし'));
        }
    } else if (!$guest) {
        $error = '無効なQRコードです。このトークンに対応するゲスト情報が見つかりません。';
        error_log("無効なQRコード: token=$token");
    }
}

// QRコードスキャナーモードかどうか
$scanner_mode = isset($_GET['scanner']) && $_GET['scanner'] === '1';

// 管理者名を取得
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : '管理者';

// ページタイトル
$page_title = 'QRコードチェックイン';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - 管理画面</title>
    
    <!-- QRコードスキャナーライブラリをローカルホストにコピーして使用 -->
    <script src="js/html5-qrcode.min.js"></script>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .checkin-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .scanner-container {
            margin-bottom: 30px;
            text-align: center;
        }
        
        #qr-video {
            max-width: 100%;
            border: 2px solid #4CAF50;
            border-radius: 5px;
        }
        
        .scan-result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        
        .guest-info {
            margin-top: 20px;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #ddd;
            background-color: #fff;
        }
        
        .guest-info h3 {
            margin-top: 0;
            color: #4CAF50;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .checkin-form {
            margin-top: 20px;
        }
        
        .checkin-form textarea {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .success-message {
            background-color: #dff0d8;
            color: #3c763d;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .error-message {
            background-color: #f2dede;
            color: #a94442;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .info-message {
            background-color: #d9edf7;
            color: #31708f;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .companions-list {
            margin-top: 20px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 4px;
        }
        
        .companions-list h4 {
            margin-top: 0;
            color: #555;
        }
        
        .qr-manual-input {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px dashed #ccc;
        }
        
        .scan-instructions {
            margin: 15px 0;
            color: #666;
        }
        
        .scan-toggle {
            margin: 20px 0;
        }
        
        /* スキャナーモード用スタイル */
        .scanner-mode {
            background-color: #000;
            color: #fff;
        }
        
        .scanner-mode .admin-header, 
        .scanner-mode .admin-footer,
        .scanner-mode .admin-nav-toggle {
            display: none;
        }
        
        .scanner-mode .admin-container {
            margin: 0;
            padding: 0;
            max-width: 100%;
        }
        
        .scanner-mode .admin-content-wrapper {
            margin: 0;
            padding: 0;
        }
        
        .scanner-mode #qr-video {
            width: 100vw;
            height: 80vh;
            object-fit: cover;
            border: none;
        }
        
        .scanner-mode .scan-result {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: rgba(0, 0, 0, 0.8);
            color: #fff;
            padding: 20px;
            min-height: 20vh;
        }
        
        .scanner-mode .scan-controls {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 100;
        }
        
        .scanner-mode .admin-button {
            background-color: rgba(255, 255, 255, 0.2);
        }
    </style>
    <!-- スキャナーモードの場合、bodyにクラスを追加 -->
    <?php if ($scanner_mode): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('scanner-mode');
        });
    </script>
    <?php endif; ?>
</head>
<body>
    <div class="admin-container">
        <?php if (!$scanner_mode): ?>
            <?php include 'inc/header.php'; ?>
        <?php endif; ?>
        
        <div class="admin-content-wrapper">
            <div class="checkin-container">
                <?php if (!$scanner_mode): ?>
                    <h2><i class="fas fa-qrcode"></i> <?= $page_title ?></h2>
                <?php endif; ?>
                
                <!-- スキャナーモード切り替えボタン -->
                <div class="scan-toggle">
                    <?php if ($scanner_mode): ?>
                        <a href="checkin.php" class="admin-button"><i class="fas fa-arrow-left"></i> 通常モードに戻る</a>
                    <?php else: ?>
                        <a href="checkin.php?scanner=1" class="admin-button"><i class="fas fa-camera"></i> スキャナーモードに切り替え</a>
                    <?php endif; ?>
                </div>
                
                <!-- エラーメッセージ -->
                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <!-- 成功メッセージ -->
                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> <?= $success ?>
                        
                        <?php if (!empty($companions)): ?>
                            <div class="companions-list">
                                <h4>同じグループの他のゲスト:</h4>
                                <ul>
                                    <?php foreach ($companions as $companion): ?>
                                        <li>
                                            <?= htmlspecialchars($companion['name']) ?>
                                            <form method="post" action="checkin.php?token=<?= htmlspecialchars(generate_qr_token($companion['id'])) ?>" style="display: inline;">
                                                <input type="hidden" name="notes" value="グループチェックイン">
                                                <button type="submit" name="checkin" class="admin-button admin-button-small">
                                                    <i class="fas fa-user-check"></i> チェックイン
                                                </button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- QRコードスキャナー -->
                <div class="scanner-container">
                    <h3><i class="fas fa-camera"></i> QRコードをスキャン</h3>
                    <p class="scan-instructions">
                        ゲストのQRコードをカメラにかざしてスキャンしてください。
                    </p>
                    
                    <div id="qr-reader" style="width:100%"></div>
                    
                    <div class="scan-result" id="scan-result">
                        <div class="info-message">
                            <i class="fas fa-camera"></i> カメラへのアクセス許可を確認しています...
                        </div>
                    </div>
                </div>
                
                <!-- ゲスト情報表示 -->
                <?php if ($guest): ?>
                <div class="guest-info">
                    <h3><?= htmlspecialchars($guest['group_name']) ?></h3>
                    <p><strong>名前:</strong> <?= isset($guest['name']) ? htmlspecialchars($guest['name']) : '未設定' ?></p>
                    <p><strong>グループID:</strong> <?= htmlspecialchars($guest['group_id']) ?></p>
                    <p><strong>メールアドレス:</strong> <?= isset($guest['email']) ? htmlspecialchars($guest['email']) : '未設定' ?></p>
                    
                    <!-- チェックインフォーム -->
                    <form class="checkin-form" method="post" action="checkin.php?token=<?= htmlspecialchars($token) ?>">
                        <div class="form-group">
                            <label for="notes">備考:</label>
                            <textarea name="notes" id="notes" rows="3" placeholder="必要に応じてメモを入力"></textarea>
                        </div>
                        
                        <button type="submit" name="checkin" class="admin-button">
                            <i class="fas fa-user-check"></i> チェックイン記録
                        </button>
                    </form>
                    
                    <!-- チェックイン履歴 -->
                    <?php
                    try {
                        $stmt = $pdo->prepare("
                            SELECT * FROM checkins 
                            WHERE guest_id = ? 
                            ORDER BY checkin_time DESC
                        ");
                        $stmt->execute([$guest['id']]);
                        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        $history = [];
                    }
                    
                    if (!empty($history)):
                    ?>
                    <div class="checkin-history">
                        <h4>チェックイン履歴</h4>
                        <ul>
                            <?php foreach ($history as $entry): ?>
                            <li>
                                <?= date('Y年m月d日 H:i:s', strtotime($entry['checkin_time'])) ?>
                                <?php if (!empty($entry['checked_by'])): ?>
                                    (記録者: <?= htmlspecialchars($entry['checked_by']) ?>)
                                <?php endif; ?>
                                <?php if (!empty($entry['notes'])): ?>
                                    - <?= htmlspecialchars($entry['notes']) ?>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- 手動トークン入力フォーム -->
                <div class="qr-manual-input">
                    <h3><i class="fas fa-keyboard"></i> トークンを手動入力</h3>
                    <form method="get" action="checkin.php">
                        <div class="form-group">
                            <label for="token">QRコードトークン:</label>
                            <input type="text" name="token" id="token" placeholder="トークンを入力" 
                                  value="<?= isset($_GET['token']) ? htmlspecialchars($_GET['token']) : '' ?>">
                        </div>
                        <button type="submit" class="admin-button">
                            <i class="fas fa-search"></i> 検索
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <?php if (!$scanner_mode): ?>
            <?php include 'inc/footer.php'; ?>
        <?php endif; ?>
    </div>
    
    <!-- QRコードスキャナーのJavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 要素の取得
        const qrContainer = document.getElementById('qr-reader');
        const scanResult = document.getElementById('scan-result');
        
        if (!qrContainer || !scanResult) {
            console.error('QRコードスキャナー要素が見つかりません');
            return;
        }
        
        // HTML5QRコードライブラリが読み込まれているか確認
        if (typeof Html5Qrcode === 'undefined') {
            scanResult.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> QRコードスキャナーライブラリが読み込めませんでした。
                </div>
                <p>ブラウザを更新するか、下部のトークン入力フォームをご利用ください。</p>
            `;
            return;
        }
        
        // ブラウザがカメラをサポートしているか確認
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            scanResult.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> お使いのブラウザはカメラ機能をサポートしていません。
                </div>
                <p>他のブラウザをお試しいただくか、下部のトークン入力フォームをご利用ください。</p>
            `;
            return;
        }
        
        // シンプルなhtml5-qrcode設定
        const html5QrCode = new Html5Qrcode("qr-reader");
        const config = { 
            fps: 10, 
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0
        };
        
        // カメラ権限リクエスト
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(function(stream) {
                // ストリームを停止
                stream.getTracks().forEach(track => track.stop());
                
                // カメラデバイスの列挙
                navigator.mediaDevices.enumerateDevices()
                    .then(function(devices) {
                        // カメラをフィルタリング
                        const cameras = devices.filter(device => device.kind === 'videoinput');
                        
                        if (cameras.length === 0) {
                            scanResult.innerHTML = `
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i> カメラデバイスが見つかりません。
                                </div>
                                <p>デバイスにカメラが接続されているか確認してください。</p>
                            `;
                            return;
                        }
                        
                        // カメラの起動
                        scanResult.innerHTML = `
                            <div class="info-message">
                                <i class="fas fa-spinner fa-spin"></i> カメラを起動中...
                            </div>
                        `;
                        
                        // デフォルトでは環境カメラ（背面カメラ）を使用
                        const cameraId = { facingMode: "environment" };
                        
                        // QRコードスキャナーの開始
                        html5QrCode.start(
                            cameraId, 
                            config,
                            onScanSuccess,
                            onScanFailure
                        )
                        .then(() => {
                            scanResult.innerHTML = `
                                <div class="info-message">
                                    <i class="fas fa-camera"></i> QRコードをカメラにかざしてください
                                </div>
                            `;
                        })
                        .catch((err) => {
                            console.error("スキャナーの起動に失敗:", err);
                            scanResult.innerHTML = `
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i> カメラの起動に失敗しました。
                                </div>
                                <p>ブラウザの設定でカメラへのアクセスを許可しているか確認してください。</p>
                                <p><small>エラー: ${err}</small></p>
                            `;
                        });
                    })
                    .catch(function(err) {
                        console.error("デバイス一覧の取得に失敗:", err);
                        scanResult.innerHTML = `
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i> カメラデバイスの取得に失敗しました。
                            </div>
                            <p>ブラウザの設定を確認するか、下部のトークン入力フォームをご利用ください。</p>
                        `;
                    });
            })
            .catch(function(err) {
                console.error("カメラアクセスの許可が得られませんでした:", err);
                scanResult.innerHTML = `
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> カメラへのアクセス許可が得られませんでした。
                    </div>
                    <p>ブラウザの設定でカメラへのアクセスを許可してください。</p>
                    <p><small>エラー: ${err.name} - ${err.message}</small></p>
                `;
            });
        
        // スキャン成功時のコールバック
        function onScanSuccess(decodedText, decodedResult) {
            console.log(`QRコード検出: ${decodedText}`);
            
            // トークンを取得
            let token = null;
            
            // URLからトークンを抽出するか、テキストをそのまま使用
            if (decodedText.includes('?token=')) {
                try {
                    const url = new URL(decodedText);
                    token = url.searchParams.get('token');
                } catch (e) {
                    const matches = decodedText.match(/[?&]token=([^&]+)/);
                    if (matches && matches.length > 1) {
                        token = matches[1];
                    }
                }
            } else {
                token = decodedText;
            }
            
            if (token) {
                // 成功表示
                scanResult.innerHTML = `
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> QRコードを検出しました！
                    </div>
                    <p>リダイレクトしています...</p>
                `;
                
                // カメラを停止
                html5QrCode.stop()
                    .then(() => console.log("QRスキャナーを停止しました"))
                    .catch(err => console.error("カメラ停止エラー:", err));
                
                // ページ遷移
                setTimeout(() => {
                    window.location.href = `checkin.php?token=${encodeURIComponent(token)}${<?= $scanner_mode ? "'&scanner=1'" : "''"; ?>}&scan_action=auto_checkin`;
                }, 1000);
            } else {
                scanResult.innerHTML = `
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> 無効なQRコードです。
                    </div>
                    <p>スキャンされた値: ${decodedText}</p>
                `;
            }
        }
        
        // スキャン失敗時のコールバック
        function onScanFailure(error) {
            // 連続スキャン中のエラーは表示しない（ログのみ）
            console.log(`QRコードスキャン処理中...`);
        }
    });
    </script>
</body>
</html> 