<?php
// 設定ファイルを読み込み
require_once 'config.php';

// URLパラメータを取得
$group_id = isset($_GET['group']) ? trim($_GET['group']) : '';
$nocache = isset($_GET['nocache']) ? $_GET['nocache'] : time();

// リダイレクト先URL
$redirect_url = $site_url;
if (!empty($group_id)) {
    $redirect_url .= '?group=' . urlencode($group_id);
}

// グループ情報を取得（存在する場合）
$group_name = '親愛なるゲスト様';
if ($group_id) {
    try {
        $stmt = $pdo->prepare("SELECT group_name FROM guests WHERE group_id = :group_id LIMIT 1");
        $stmt->execute(['group_id' => $group_id]);
        $result = $stmt->fetch();
        if ($result) {
            $group_name = $result['group_name'];
        }
    } catch (PDOException $e) {
        // エラー処理（静かに失敗）
    }
}

// OGP画像のタイムスタンプを取得（キャッシュ対策）
$ogp_image_path = 'images/ogp-image.jpg';
$sample_ogp_image_path = 'images/samples/sample-ogp-image.jpg';

// OGP画像が存在しない場合はサンプル画像をコピー
if (!file_exists($ogp_image_path) && file_exists($sample_ogp_image_path)) {
    // サンプル画像をOGP画像としてコピー
    @copy($sample_ogp_image_path, $ogp_image_path);
}

$ogp_timestamp = file_exists($ogp_image_path) ? '?' . filemtime($ogp_image_path) : '';

// カップル名を取得
$couple_names = [
    'bride' => 'あかね',
    'groom' => '翔'
];

try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM wedding_settings WHERE setting_key IN ('bride_name', 'groom_name')");
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        if ($row['setting_key'] == 'bride_name') {
            $couple_names['bride'] = $row['setting_value'];
        } elseif ($row['setting_key'] == 'groom_name') {
            $couple_names['groom'] = $row['setting_value'];
        }
    }
} catch (PDOException $e) {
    // エラー処理（静かに失敗）
}

// 表示用のタイトルとメッセージを作成
$title = $couple_names['groom'] . ' & ' . $couple_names['bride'] . ' - Wedding Invitation';
$description = $group_name . 'さん、結婚式のご招待状です。';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    
    <!-- OGP（Open Graph Protocol）タグ - LINEでの共有表示用に最適化 -->
    <meta property="og:title" content="<?= $title ?>">
    <meta property="og:description" content="<?= $description ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $site_url ?>line_share.php?nocache=<?= $nocache ?><?= !empty($group_id) ? '&group=' . urlencode($group_id) : '' ?>">
    <meta property="og:image" content="<?= $site_url ?>images/ogp-image.jpg<?= $ogp_timestamp ?>">
    <meta property="og:image:secure_url" content="<?= $site_url ?>images/ogp-image.jpg<?= $ogp_timestamp ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:site_name" content="<?= $title ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $title ?>">
    <meta name="twitter:description" content="<?= $description ?>">
    <meta name="twitter:image" content="<?= $site_url ?>images/ogp-image.jpg<?= $ogp_timestamp ?>">
    
    <!-- リダイレクト設定 - 0.5秒後に実際のページへリダイレクト -->
    <meta http-equiv="refresh" content="0.5;url=<?= $redirect_url ?>">
    
    <!-- キャッシュ無効化 -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <style>
        body {
            font-family: 'Noto Sans JP', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f8f7f5;
            color: #333;
            text-align: center;
        }
        
        .loading-container {
            max-width: 90%;
            padding: 20px;
        }
        
        h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #8d6e63;
        }
        
        p {
            margin-bottom: 20px;
        }
        
        .spinner {
            display: inline-block;
            width: 50px;
            height: 50px;
            border: 3px solid rgba(141, 110, 99, 0.3);
            border-radius: 50%;
            border-top-color: #8d6e63;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading-container">
        <h1><?= $title ?></h1>
        <p><?= $description ?></p>
        <div class="spinner"></div>
        <p>ページに移動中です...</p>
    </div>
</body>
</html> 