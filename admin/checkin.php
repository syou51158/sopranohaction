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
    
    // チェックイン処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin']) && $guest) {
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        $checked_by = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : '管理者';
        
        $result = record_guest_checkin($guest['id'], $checked_by, $notes);
        
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
            }
        } else {
            $error = 'チェックインの記録に失敗しました。';
        }
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
                    
                    <video id="qr-video" autoplay playsinline></video>
                    
                    <div class="scan-result" id="scan-result">
                        スキャン結果がここに表示されます...
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
    <script src="https://unpkg.com/html5-qrcode/minified/html5-qrcode.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const qrContainer = document.getElementById('qr-video');
        const scanResult = document.getElementById('scan-result');
        
        if (!qrContainer) {
            console.error('QRコードスキャナーコンテナが見つかりません');
            return;
        }
        
        // カメラ入力に関するステータス表示
        scanResult.innerHTML = `
            <div class="info-message">
                <i class="fas fa-camera"></i> カメラへのアクセス許可を確認しています...
            </div>
        `;
        
        // 利用可能なカメラデバイスを取得
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
            scanResult.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> お使いのブラウザはカメラデバイスの検出をサポートしていません。
                </div>
                <p>代わりに下部のトークン入力フォームをご利用ください。</p>
            `;
            return;
        }
        
        // HTML5 QRコードスキャナーを初期化
        const html5QrCode = new Html5Qrcode("qr-video");
        
        // カメラデバイスの一覧を取得
        navigator.mediaDevices.enumerateDevices()
            .then(devices => {
                // カメラデバイスのみをフィルタリング
                const cameras = devices.filter(device => device.kind === 'videoinput');
                
                if (cameras.length === 0) {
                    scanResult.innerHTML = `
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i> カメラデバイスが見つかりません。
                        </div>
                        <p>代わりに下部のトークン入力フォームをご利用ください。</p>
                    `;
                    return;
                }
                
                // カメラの起動（背面カメラを優先）
                let selectedCamera = null;
                // モバイルデバイスの場合、背面カメラを探す
                for (const camera of cameras) {
                    if (camera.label.toLowerCase().includes('back') || 
                        camera.label.toLowerCase().includes('rear') ||
                        camera.label.toLowerCase().includes('環境') || 
                        camera.label.toLowerCase().includes('背面')) {
                        selectedCamera = camera.deviceId;
                        break;
                    }
                }
                
                // 背面カメラが見つからない場合は最初のカメラを使用
                if (!selectedCamera && cameras.length > 0) {
                    selectedCamera = cameras[0].deviceId;
                }
                
                const qrConfig = { 
                    fps: 10, 
                    qrbox: { width: 250, height: 250 },
                    experimentalFeatures: {
                        useBarCodeDetectorIfSupported: true
                    }
                };
                
                let cameraConfig = { deviceId: selectedCamera };
                if (!selectedCamera) {
                    cameraConfig = { facingMode: "environment" };
                }
                
                // カメラの起動
                html5QrCode.start(
                    cameraConfig,
                    qrConfig,
                    onScanSuccess,
                    onScanFailure
                ).catch(err => {
                    scanResult.innerHTML = `
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i> カメラへのアクセスに失敗しました: ${err}
                        </div>
                        <p>カメラへのアクセス許可を確認するか、下部のトークン入力フォームをご利用ください。</p>
                    `;
                });
            })
            .catch(err => {
                scanResult.innerHTML = `
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> カメラデバイスの取得に失敗しました: ${err}
                    </div>
                    <p>代わりに下部のトークン入力フォームをご利用ください。</p>
                `;
            });
        
        // スキャン成功時のコールバック
        function onScanSuccess(decodedText, decodedResult) {
            console.log("QRコード検出: ", decodedText);
            
            // URLからトークンを抽出
            let token = null;
            
            // URLの場合はパラメータから抽出
            if (decodedText.includes('?token=')) {
                try {
                    const url = new URL(decodedText);
                    token = url.searchParams.get('token');
                } catch (e) {
                    // URL解析に失敗した場合は文字列からトークンを探す
                    const matches = decodedText.match(/[?&]token=([^&]+)/);
                    if (matches && matches.length > 1) {
                        token = matches[1];
                    }
                }
            } else {
                // トークンそのものと仮定
                token = decodedText;
            }
            
            if (token) {
                // 成功音を鳴らす (短いビープ音)
                try {
                    const beepSound = new Audio('data:audio/mp3;base64,//uQxAAAAAAAAAAAAAAAAAAAAAAAWGluZwAAAA8AAAAFAAAGUACFhYWFhYWFhYWFhYWFhYWFhYWFvb29vb29vb29vb29vb29vb29vb3p6enp6enp6enp6enp6enp6enp6f////////////////////////////////8AAAA5TEFNRTMuOTlyAm4AAAAALgkAABRGJAN3TQAARgABhZBqUm3YAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA//vAxAAABLQDe7QQAAI8AKz3AiAA0qAXBMQwAAhIAAYRgJAAAoHTDxs1JmboZkS5qzS5m+JnmEJKeMTjtFx45COPEQiERwQiPocEIQgIYcEIjw4IRHBCEQQEJUOCEcEJCEqHBCB8vOV+X5/4QhAQlflQ4/KgfLzlB8uV9wQhCXX7Bf/BA45Xv4ICE/BA45QQOCEIfBAQOCEuEQh4IRCAgY5QfLz//l+dfl5/X5/oQhAQn+gIHHKCBc5XnLzgcrnK/+XnK84fBA4IRCIQEBAQn//wf///wfwQhDggggggccEDghEIhAQEBA5');
                    beepSound.play();
                } catch (e) {
                    console.error("ビープ音の再生に失敗しました: ", e);
                }
                
                // スキャン結果を表示
                scanResult.innerHTML = `
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> QRコードを検出しました！
                    </div>
                    <p>トークン: ${token}</p>
                    <p>リダイレクトしています...</p>
                `;
                
                // 検出したトークンでページをリロード
                setTimeout(() => {
                    window.location.href = `checkin.php?token=${encodeURIComponent(token)}${<?= $scanner_mode ? "'&scanner=1'" : "''"; ?>}`;
                }, 1000);
            } else {
                scanResult.innerHTML = `
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> 無効なQRコードです。トークンが見つかりません。
                    </div>
                    <p>スキャンされた値: ${decodedText}</p>
                `;
            }
        }
        
        // スキャン失敗時のコールバック
        function onScanFailure(error) {
            // エラーは表示しない（連続的にスキャンするため）
            console.log(`QRコードスキャン処理中: ${error}`);
        }
    });
    </script>
</body>
</html> 