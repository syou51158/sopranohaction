<?php
// 設定ファイルを読み込み
require_once 'config.php';

// URLからグループIDを取得
$group_id = isset($_GET['group']) ? htmlspecialchars($_GET['group']) : null;

// ゲスト情報を初期化
$guest_info = [
    'group_name' => '親愛なるゲスト様',
    'custom_message' => ''
];

// グループIDが存在する場合、データベースからゲスト情報を取得
if ($group_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM guests WHERE group_id = :group_id LIMIT 1");
        $stmt->execute(['group_id' => $group_id]);
        $guest_data = $stmt->fetch();
        
        if ($guest_data) {
            $guest_info = [
                'group_name' => $guest_data['group_name'],
                'custom_message' => $guest_data['custom_message']
            ];
        }
    } catch (PDOException $e) {
        if ($debug_mode) {
            echo "データベースエラー: " . $e->getMessage();
        }
    }
}

// 最新の回答を取得
$response_status = null;
if ($group_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.* FROM responses r
            JOIN guests g ON r.guest_id = g.id
            WHERE g.group_id = :group_id
            ORDER BY r.created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['group_id' => $group_id]);
        $response = $stmt->fetch();
        
        if ($response) {
            $response_status = (int)$response['attending'];
        }
    } catch (PDOException $e) {
        if ($debug_mode) {
            echo "データベースエラー: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ありがとうございます - <?= $site_name ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .thank-you-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            background-color: var(--background-light);
            background-image: linear-gradient(120deg, #e0f7fa 0%, #fff 100%);
            opacity: 0;
            animation: fadeIn 1.2s ease-out forwards;
        }
        
        .thank-you-card {
            background-color: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            text-align: center;
            max-width: 650px;
            width: 100%;
            position: relative;
            overflow: hidden;
            transform: translateY(20px);
            opacity: 0;
            animation: slideUp 1.2s ease-out 0.3s forwards;
            border: 1px solid rgba(230, 230, 230, 0.8);
        }
        
        .thank-you-content {
            position: relative;
            z-index: 1;
        }
        
        .thank-you-icon {
            font-size: 2.5rem;
            color: var(--pink-accent);
            margin-bottom: 1rem;
            animation: heartbeat 1.5s infinite;
            opacity: 0;
            animation: heartbeat 1.5s infinite, fadeIn 1s ease-out 0.8s forwards;
        }
        
        .thank-you-content h1 {
            font-family: 'Noto Serif JP', serif;
            font-size: 2.2rem;
            color: var(--accent-dark);
            margin-bottom: 1.5rem;
            opacity: 0;
            transform: translateY(10px);
            animation: fadeSlideUp 0.8s ease-out 1s forwards;
        }
        
        .thank-you-content p {
            font-size: 1.1rem;
            line-height: 1.8;
            margin-bottom: 1rem;
            color: var(--text-color);
            opacity: 0;
            transform: translateY(10px);
            animation: fadeSlideUp 0.8s ease-out 1.2s forwards;
        }
        
        .thank-you-content p:nth-of-type(2) {
            animation-delay: 1.4s;
        }
        
        .thank-you-content p:nth-of-type(3) {
            animation-delay: 1.6s;
        }
        
        .thank-you-signature {
            font-family: 'Noto Serif JP', serif;
            font-size: 1.5rem;
            color: var(--accent-dark);
            margin: 2rem 0;
            opacity: 0;
            transform: translateY(10px);
            animation: fadeSlideUp 0.8s ease-out 1.8s forwards;
        }
        
        .thank-you-navigation {
            margin-top: 2rem;
            opacity: 0;
            transform: translateY(10px);
            animation: fadeSlideUp 0.8s ease-out 2s forwards;
        }
        
        .return-button {
            display: inline-block;
            padding: 12px 24px;
            background-color: var(--accent-color);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .return-button:hover {
            background-color: var(--accent-dark);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .thank-you-decoration {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
        }
        
        .leaf-decoration {
            position: absolute;
            width: 120px;
            height: 120px;
            background-image: url('images/leaf-decoration.png');
            background-size: contain;
            background-repeat: no-repeat;
            opacity: 0;
            animation: fadeIn 1.5s ease-out 1.5s forwards;
        }
        
        .leaf-decoration.left {
            top: 20px;
            left: 20px;
            transform: rotate(30deg) scale(0.95);
            animation: fadeInRotateLeft 1.5s ease-out 1.5s forwards;
        }
        
        .leaf-decoration.right {
            bottom: 20px;
            right: 20px;
            transform: rotate(-30deg) scale(0.95);
            animation: fadeInRotateRight 1.5s ease-out 1.7s forwards;
        }
        
        /* アニメーションキーフレーム */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes fadeSlideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes heartbeat {
            0% { transform: scale(1); }
            14% { transform: scale(1.2); }
            28% { transform: scale(1); }
            42% { transform: scale(1.2); }
            70% { transform: scale(1); }
        }
        
        @keyframes fadeInRotateLeft {
            from { opacity: 0; transform: rotate(20deg) scale(0.9); }
            to { opacity: 0.2; transform: rotate(30deg) scale(1); }
        }
        
        @keyframes fadeInRotateRight {
            from { opacity: 0; transform: rotate(-20deg) scale(0.9); }
            to { opacity: 0.2; transform: rotate(-30deg) scale(1); }
        }
        
        @media (max-width: 768px) {
            .thank-you-content h1 {
                font-size: 2rem;
            }
            
            .thank-you-content p {
                font-size: 1rem;
            }
            
            .thank-you-signature {
                font-size: 1.3rem;
            }
        }
        
        @media (max-width: 480px) {
            .thank-you-card {
                padding: 30px 20px;
            }
            
            .thank-you-content h1 {
                font-size: 1.8rem;
            }
            
            .leaf-decoration {
                width: 80px;
                height: 80px;
            }
        }

        /* QRコード案内スタイル */
        .qr-info-box {
            margin-top: 25px;
            padding: 20px;
            background-color: #f8f9ff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 4px solid #4285F4;
            text-align: left;
        }

        .qr-info-box h3 {
            color: #4285F4;
            margin-top: 0;
            font-size: 1.3rem;
        }

        .qr-info-box p {
            margin: 10px 0;
            color: #555;
            line-height: 1.6;
        }

        .qr-link {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 24px;
            background-color: #4285F4;
            color: white;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            box-shadow: 0 3px 8px rgba(66, 133, 244, 0.3);
        }

        .qr-link:hover {
            background-color: #3367D6;
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(66, 133, 244, 0.4);
        }
    </style>
</head>
<body>
    <div class="thank-you-container">
        <div class="thank-you-card">
            <div class="thank-you-content">
                <i class="fas fa-heart thank-you-icon"></i>
                <h1>ありがとうございます</h1>
                <p><?= $guest_info['group_name'] ?></p>
                <div class="thank-you-message">
                    <h2>ご回答ありがとうございます</h2>
                    <p>大切なご返信をいただき、心より感謝申し上げます。</p>
                    <?php if (isset($response_status) && $response_status == 1): ?>
                    <p class="special-message">ご出席のご連絡をいただき、とても嬉しく思います。<br>当日お会いできることを楽しみにしております。</p>
                    
                    <!-- QRコードチェックイン案内 -->
                    <div class="qr-info-box">
                        <h3><i class="fas fa-qrcode"></i> QRコードチェックインのご案内</h3>
                        <p>結婚式当日は受付でQRコードをご提示いただくとスムーズにチェックインができます。</p>
                        <p>以下のリンクからQRコードをご確認いただけます。結婚式当日までお手元に保存しておいてください。</p>
                        <a href="<?= $group_id ? "my_qrcode.php?group=" . urlencode($group_id) : "my_qrcode.php" ?>" class="qr-link">
                            <i class="fas fa-arrow-right"></i> マイQRコードを表示する
                        </a>
                    </div>
                    <?php else: ?>
                    <p class="special-message">ご欠席のご連絡をいただき、ありがとうございます。<br>またの機会にお会いできることを楽しみにしております。</p>
                    <?php endif; ?>
                </div>
                
                <div class="thank-you-signature">
                    <p>翔 &amp; あかね</p>
                </div>
                
                <div class="thank-you-navigation">
                    <a href="<?= $group_id ? "index.php?group=$group_id" : 'index.php' ?>" class="return-button">
                        <i class="fas fa-arrow-left"></i> 招待状に戻る
                    </a>
                    <?php if ($group_id): ?>
                    <div style="margin-top: 20px; padding: 15px; background-color: #f5f5f5; border-radius: 8px;">
                        <p style="margin: 0 0 10px 0; font-size: 14px;">以下のURLでいつでもアクセスできます：</p>
                        <a href="<?= $site_url ?>?group=<?= $group_id ?>" style="word-break: break-all; color: #4285F4;">
                            <?= $site_url ?>?group=<?= $group_id ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="thank-you-decoration">
                <div class="leaf-decoration left"></div>
                <div class="leaf-decoration right"></div>
            </div>
        </div>
    </div>
</body>
</html> 