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
            <p>以下のQRコードを結婚式当日にご提示ください</p>
            
            <div class="qr-code-container">
                <div id="qrcode" style="margin: 0 auto; padding: 15px; background: #fff; border-radius: 10px; display: inline-block;"></div>
                <p class="qr-instructions">結婚式当日、このQRコードを会場受付でご提示ください</p>
                
                <?php if ($debug_mode): ?>
                <!-- デバッグ情報 -->
                <div style="margin-top: 20px; padding: 10px; background: #f8f8f8; border: 1px solid #ddd; text-align: left; font-size: 12px;">
                    <h4>デバッグ情報:</h4>
                    <p>ゲストID: <?= $guest_info['id'] ?></p>
                    <p>QRトークン: <?= htmlspecialchars($guest_info['qr_code_token']) ?></p>
                    <?php
                    // QRコードに埋め込むURL（チェックインページへのリンク）
                    $checkin_url = $site_url . "admin/checkin.php?token=" . urlencode($guest_info['qr_code_token']);
                    ?>
                    <p>埋め込みURL: <?= htmlspecialchars($checkin_url) ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="note-box">
                <h4>ご注意</h4>
                <p>・QRコードは結婚式当日まで保存してください</p>
                <p>・スクリーンショットの保存や印刷も可能です</p>
                <p>・このコードは招待状に記載されたグループ専用です</p>
            </div>
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
        // QRコードに埋め込むURL（チェックインページへのリンク）
        const checkinUrl = '<?= $site_url . "admin/checkin.php?token=" . urlencode($guest_info['qr_code_token']) ?>';
        
        // QRコードを生成
        new QRCode(document.getElementById("qrcode"), {
            text: checkinUrl,
            width: 256,
            height: 256,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
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