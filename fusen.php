<?php
// 設定ファイルを読み込み
require_once 'config.php';

// URLからグループIDを取得
$group_id = isset($_GET['group']) ? htmlspecialchars($_GET['group']) : null;

// グループIDが存在しない場合は404エラー
if (!$group_id) {
    http_response_code(404);
    echo 'グループIDが指定されていません。';
    exit;
}

// ゲスト情報を取得
$guest_info = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM guests WHERE group_id = :group_id LIMIT 1");
    $stmt->execute(['group_id' => $group_id]);
    $guest_info = $stmt->fetch();
    
    if (!$guest_info) {
        http_response_code(404);
        echo '指定されたグループIDは存在しません。';
        exit;
    }
} catch (PDOException $e) {
    if ($debug_mode) {
        echo "データベースエラー: " . $e->getMessage();
    } else {
        http_response_code(500);
        echo '内部サーバーエラーが発生しました。';
    }
    exit;
}

// ゲストの付箋情報を取得
$fusens = [];
try {
    $stmt = $pdo->prepare("
        SELECT gf.*, ft.type_name, ft.type_code, ft.default_message
        FROM guest_fusen gf
        JOIN fusen_types ft ON gf.fusen_type_id = ft.id
        WHERE gf.guest_id = ?
        ORDER BY ft.sort_order, ft.type_name
    ");
    $stmt->execute([$guest_info['id']]);
    $fusens = $stmt->fetchAll();
} catch (PDOException $e) {
    // エラーが発生した場合は空の配列のままにする
    if ($debug_mode) {
        echo "付箋データの取得エラー: " . $e->getMessage();
    }
}

// 付箋がない場合は404エラー
if (empty($fusens)) {
    http_response_code(404);
    echo 'このグループには付箋がありません。';
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>付箋 - <?= $site_name ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f6f2;
            color: #333;
            font-family: 'Noto Sans JP', sans-serif;
            margin: 0;
            padding: 0;
        }
        .fusen-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .fusen-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #ccc;
        }
        .fusen-header h1 {
            color: #d35400;
            font-family: 'M PLUS Rounded 1c', sans-serif;
            margin-bottom: 5px;
        }
        .fusen-header p {
            color: #666;
            margin-top: 0;
        }
        .fusen-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .fusen-item {
            background-color: #fff9c4;
            border: 1px solid #ffeb3b;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        .fusen-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(45deg, #ffcc80, #ffb74d);
        }
        .fusen-item h2 {
            color: #d32f2f;
            font-size: 1.5rem;
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 1px dashed #ffcc80;
            padding-bottom: 10px;
            font-family: 'M PLUS Rounded 1c', sans-serif;
        }
        .fusen-item p {
            margin: 0;
            font-size: 1.1rem;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        .fusen-footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px dashed #ccc;
        }
        .fusen-back-link {
            display: inline-block;
            background-color: #e67e22;
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .fusen-back-link:hover {
            background-color: #d35400;
        }
        .fusen-back-link i {
            margin-right: 5px;
        }
        .fusen-decoration {
            text-align: center;
            margin: 30px 0;
        }
        .decoration-icon {
            color: #e67e22;
            font-size: 1.5rem;
            margin: 0 10px;
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 600px) {
            .fusen-container {
                padding: 15px;
            }
            .fusen-item h2 {
                font-size: 1.3rem;
            }
            .fusen-item p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="fusen-container">
        <div class="fusen-header">
            <h1>付箋のご案内</h1>
            <p><?= htmlspecialchars($guest_info['group_name']) ?>へのお願いとご案内</p>
        </div>
        
        <div class="fusen-decoration">
            <i class="fas fa-heart decoration-icon"></i>
            <i class="fas fa-leaf decoration-icon"></i>
            <i class="fas fa-heart decoration-icon"></i>
        </div>
        
        <div class="fusen-list">
            <?php foreach ($fusens as $fusen): ?>
            <div class="fusen-item">
                <h2><?= htmlspecialchars($fusen['type_name']) ?></h2>
                <p><?= nl2br(htmlspecialchars($fusen['custom_message'] ?: $fusen['default_message'])) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="fusen-footer">
            <a href="index.php?group=<?= urlencode($group_id) ?>" class="fusen-back-link">
                <i class="fas fa-arrow-left"></i> 招待状に戻る
            </a>
        </div>
    </div>
</body>
</html> 