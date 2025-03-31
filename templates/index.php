<?php
// 設定ファイルを読み込み
require_once '../config.php';

// URLからグループIDを取得
$group_id = isset($_GET['group']) ? htmlspecialchars($_GET['group']) : null;

// 利用可能なテンプレート
$templates = [
    [
        'id' => 'v3_natural_green',
        'name' => 'ナチュラルグリーン',
        'description' => 'ナチュラルな緑を基調としたシンプルで上品なデザイン',
        'thumbnail' => '../images/templates/v3_natural_green.jpg',
        'path' => 'v3_natural_green/index.php' . ($group_id ? '?group=' . urlencode($group_id) : '')
    ],
    // 他のテンプレートを追加できます
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>結婚式Web招待状 - テンプレート選択</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b9d61;
            --primary-light: #8bc34a;
            --primary-dark: #5d8b4f;
            --secondary-color: #f8f4e6;
            --text-color: #333;
            --border-color: #e0d5c1;
            --shadow-color: rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Sans JP', sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--secondary-color);
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            text-align: center;
            margin-bottom: 50px;
            padding: 20px;
        }
        
        h1 {
            font-family: 'Noto Serif JP', serif;
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: var(--primary-dark);
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .template-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px var(--shadow-color);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .template-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .template-thumbnail {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-bottom: 1px solid #eee;
        }
        
        .template-thumbnail-placeholder {
            width: 100%;
            height: 200px;
            background-color: #f8f8f8;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            font-size: 1.5rem;
        }
        
        .template-info {
            padding: 20px;
        }
        
        .template-name {
            font-weight: 500;
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: var(--primary-dark);
        }
        
        .template-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        .template-link {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }
        
        .template-link:hover {
            background-color: var(--primary-dark);
        }
        
        .back-link {
            display: inline-block;
            margin-top: 30px;
            color: var(--primary-dark);
            text-decoration: none;
        }
        
        .back-link i {
            margin-right: 5px;
        }
        
        footer {
            text-align: center;
            margin-top: 50px;
            padding: 20px;
            color: #888;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .templates-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .templates-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>結婚式Web招待状テンプレート</h1>
            <p class="subtitle">お好みのデザインテンプレートをお選びください</p>
        </header>
        
        <div class="templates-grid">
            <?php foreach ($templates as $template): ?>
            <div class="template-card">
                <?php if (file_exists($template['thumbnail'])): ?>
                <img src="<?= $template['thumbnail'] ?>" alt="<?= $template['name'] ?>" class="template-thumbnail">
                <?php else: ?>
                <div class="template-thumbnail-placeholder">
                    <i class="fas fa-image"></i>
                </div>
                <?php endif; ?>
                
                <div class="template-info">
                    <h2 class="template-name"><?= $template['name'] ?></h2>
                    <p class="template-description"><?= $template['description'] ?></p>
                    <a href="<?= $template['path'] ?>" class="template-link">テンプレートを表示</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center;">
            <a href="<?= $group_id ? '../index.php?group=' . urlencode($group_id) : '../index.php' ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> メインページに戻る
            </a>
        </div>
        
        <footer>
            <p>&copy; <?= date('Y') ?> 結婚式Web招待状サービス</p>
        </footer>
    </div>
</body>
</html> 