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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@300;400;500;600&family=Cormorant+Garamond:wght@300;400;500;600&family=Great+Vibes&family=Tangerine:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f9f7f5;
            background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI1MCIgaGVpZ2h0PSI1MCIgdmlld0JveD0iMCAwIDUwIDUwIj48cGF0aCBmaWxsPSIjZjJlZWU5IiBkPSJNMjUsMC4zYzEzLjYsMCwyNC43LDExLjEsMjQuNywyNC43cy0xMS4xLDI0LjctMjQuNywyNC43UzAuMywzOC42LDAuMywyNVMxMS40LDAuMywyNSwwLjN6Ii8+PC9zdmc+');
            background-size: 100px 100px;
            color: #4a4a4a;
            font-family: 'Noto Serif JP', serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .fusen-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .fusen-content {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 3rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(217, 185, 155, 0.3);
        }
        .fusen-header {
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }
        .fusen-header::after {
            content: '';
            display: block;
            width: 100px;
            height: 2px;
            background: linear-gradient(90deg, rgba(217,185,155,0), rgba(217,185,155,1), rgba(217,185,155,0));
            margin: 1.5rem auto 0;
        }
        .fusen-header h1 {
            font-family: 'Great Vibes', cursive, 'Noto Serif JP', serif;
            color: #b18b5f;
            font-size: 2.8rem;
            font-weight: 400;
            margin: 0 0 0.5rem;
            letter-spacing: 1px;
        }
        .fusen-header p {
            font-size: 1.1rem;
            color: #8a7466;
            margin: 0.5rem 0 0;
            font-weight: 300;
            letter-spacing: 1px;
        }
        .fusen-list {
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
        }
        .fusen-item {
            background-color: #fff;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.03);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(217, 185, 155, 0.2);
        }
        .fusen-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        }
        .fusen-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #d3b590, #e7d7c1);
        }
        .fusen-item h2 {
            color: #b18b5f;
            font-size: 1.5rem;
            margin: 0 0 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(217, 185, 155, 0.2);
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .fusen-item p {
            margin: 0;
            font-size: 1.05rem;
            line-height: 1.8;
            white-space: pre-wrap;
            color: #5a5a5a;
        }
        .fusen-footer {
            text-align: center;
            margin-top: 3rem;
        }
        .fusen-back-link {
            display: inline-block;
            background: linear-gradient(135deg, #d3b590, #c4a47b);
            color: white;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.9rem;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(181, 139, 95, 0.2);
            font-weight: 400;
        }
        .fusen-back-link:hover {
            background: linear-gradient(135deg, #c4a47b, #b5935f);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(181, 139, 95, 0.3);
        }
        .fusen-back-link i {
            margin-right: 8px;
        }
        .decoration {
            text-align: center;
            margin: 2rem 0;
            position: relative;
        }
        .decoration::before,
        .decoration::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 35%;
            height: 1px;
            background: linear-gradient(90deg, rgba(217,185,155,0), rgba(217,185,155,0.5));
        }
        .decoration::before {
            left: 0;
        }
        .decoration::after {
            right: 0;
            background: linear-gradient(90deg, rgba(217,185,155,0.5), rgba(217,185,155,0));
        }
        .decoration-icon {
            color: #d3b590;
            font-size: 1.2rem;
            margin: 0 0.5rem;
            position: relative;
            z-index: 1;
        }
        .page-decoration {
            position: absolute;
            width: 150px;
            height: 150px;
            opacity: 0.04;
            pointer-events: none;
        }
        .decoration-top-left {
            top: -50px;
            left: -50px;
            background: radial-gradient(circle, #d3b590 10%, transparent 70%);
        }
        .decoration-bottom-right {
            bottom: -50px;
            right: -50px;
            background: radial-gradient(circle, #d3b590 10%, transparent 70%);
        }
        .envelope-decoration {
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 2.5rem;
            color: rgba(217, 185, 155, 0.07);
            transform: rotate(-15deg);
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .fusen-content {
                padding: 2rem;
            }
            .fusen-header h1 {
                font-size: 2.2rem;
            }
            .fusen-item {
                padding: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .fusen-container {
                margin: 1rem auto;
                padding: 0 1rem;
            }
            .fusen-content {
                padding: 1.5rem;
            }
            .fusen-header h1 {
                font-size: 1.8rem;
            }
            .fusen-header p {
                font-size: 0.95rem;
            }
            .fusen-item h2 {
                font-size: 1.3rem;
            }
            .fusen-item p {
                font-size: 0.95rem;
                line-height: 1.7;
            }
            .decoration::before,
            .decoration::after {
                width: 30%;
            }
        }
    </style>
</head>
<body>
    <div class="fusen-container">
        <div class="fusen-content">
            <div class="page-decoration decoration-top-left"></div>
            <div class="page-decoration decoration-bottom-right"></div>
            <i class="fas fa-envelope envelope-decoration"></i>
            
            <div class="fusen-header">
                <h1>ご案内</h1>
                <p><?= htmlspecialchars($guest_info['group_name']) ?>へのメッセージ</p>
            </div>
            
            <div class="decoration">
                <i class="fas fa-leaf decoration-icon"></i>
                <i class="fas fa-heart decoration-icon"></i>
                <i class="fas fa-leaf decoration-icon"></i>
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
    </div>
</body>
</html> 