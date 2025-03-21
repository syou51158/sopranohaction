<?php
// 設定ファイルを読み込み
require_once '../config.php';

// セッション開始
session_start();

// 管理者認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// メッセージ初期化
$success = '';
$error = '';

// 情報の追加/更新/削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_transportation') {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $type = 'transportation';
        $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;
        $image_filename = null;
        
        // 画像アップロード処理
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploads_dir = '../uploads/travel/';
            if (!is_dir($uploads_dir)) {
                mkdir($uploads_dir, 0755, true);
            }
            
            $temp_name = $_FILES['image']['tmp_name'];
            $file_name = $_FILES['image']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // 許可する拡張子
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_ext, $allowed_exts)) {
                $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
                if (move_uploaded_file($temp_name, $uploads_dir . $new_filename)) {
                    $image_filename = $new_filename;
                } else {
                    $error = "画像のアップロードに失敗しました。";
                }
            } else {
                $error = "許可されていないファイル形式です。JPG、PNG、GIF形式の画像のみアップロードできます。";
            }
        }
        
        if (empty($title)) {
            $error = "タイトルは必須です。";
        } elseif (empty($error)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO travel_info 
                    (title, description, type, display_order, is_visible, image_filename) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $description, $type, $display_order, $is_visible, $image_filename]);
                $success = "交通情報を追加しました。";
            } catch (PDOException $e) {
                $error = "情報の追加に失敗しました。";
                if ($debug_mode) {
                    $error .= " エラー: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'add_accommodation') {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $type = 'accommodation';
        $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;
        $image_filename = null;
        
        // 画像アップロード処理
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploads_dir = '../uploads/travel/';
            if (!is_dir($uploads_dir)) {
                mkdir($uploads_dir, 0755, true);
            }
            
            $temp_name = $_FILES['image']['tmp_name'];
            $file_name = $_FILES['image']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // 許可する拡張子
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_ext, $allowed_exts)) {
                $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
                if (move_uploaded_file($temp_name, $uploads_dir . $new_filename)) {
                    $image_filename = $new_filename;
                } else {
                    $error = "画像のアップロードに失敗しました。";
                }
            } else {
                $error = "許可されていないファイル形式です。JPG、PNG、GIF形式の画像のみアップロードできます。";
            }
        }
        
        if (empty($title)) {
            $error = "タイトルは必須です。";
        } elseif (empty($error)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO travel_info 
                    (title, description, type, display_order, is_visible, image_filename) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $description, $type, $display_order, $is_visible, $image_filename]);
                $success = "宿泊情報を追加しました。";
            } catch (PDOException $e) {
                $error = "情報の追加に失敗しました。";
                if ($debug_mode) {
                    $error .= " エラー: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;
        
        try {
            // 現在の画像ファイル名を取得
            $stmt = $pdo->prepare("SELECT image_filename FROM travel_info WHERE id = ?");
            $stmt->execute([$id]);
            $current_image = $stmt->fetchColumn();
            
            $image_filename = $current_image;
            
            // 新しい画像がアップロードされた場合
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploads_dir = '../uploads/travel/';
                if (!is_dir($uploads_dir)) {
                    mkdir($uploads_dir, 0755, true);
                }
                
                $temp_name = $_FILES['image']['tmp_name'];
                $file_name = $_FILES['image']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // 許可する拡張子
                $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_ext, $allowed_exts)) {
                    $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
                    if (move_uploaded_file($temp_name, $uploads_dir . $new_filename)) {
                        $image_filename = $new_filename;
                        
                        // 古い画像を削除
                        if ($current_image && file_exists($uploads_dir . $current_image)) {
                            unlink($uploads_dir . $current_image);
                        }
                    } else {
                        $error = "画像のアップロードに失敗しました。";
                    }
                } else {
                    $error = "許可されていないファイル形式です。JPG、PNG、GIF形式の画像のみアップロードできます。";
                }
            }
            
            if (empty($title)) {
                $error = "タイトルは必須です。";
            } elseif (empty($error)) {
                $stmt = $pdo->prepare("
                    UPDATE travel_info 
                    SET title = ?, description = ?, display_order = ?, is_visible = ?, image_filename = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $display_order, $is_visible, $image_filename, $id]);
                $success = "情報を更新しました。";
            }
        } catch (PDOException $e) {
            $error = "情報の更新に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM travel_info WHERE id = ?");
            $stmt->execute([$id]);
            $success = "情報を削除しました。";
        } catch (PDOException $e) {
            $error = "情報の削除に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'toggle_visibility' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $is_visible = isset($_POST['is_visible']) ? (int)$_POST['is_visible'] : 0;
        $new_visibility = $is_visible ? 0 : 1;
        
        try {
            $stmt = $pdo->prepare("UPDATE travel_info SET is_visible = ? WHERE id = ?");
            $stmt->execute([$new_visibility, $id]);
            $success = "表示設定を変更しました。";
        } catch (PDOException $e) {
            $error = "設定の変更に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    }
}

// 情報一覧を取得
$transportation_info = [];
$accommodation_info = [];
try {
    $stmt = $pdo->query("SELECT * FROM travel_info WHERE type = 'transportation' ORDER BY display_order, id");
    $transportation_info = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT * FROM travel_info WHERE type = 'accommodation' ORDER BY display_order, id");
    $accommodation_info = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "情報の取得に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// 編集対象の情報を取得
$edit_info = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM travel_info WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_info = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "情報の取得に失敗しました。";
    }
}

// 次の表示順序を取得
$next_transportation_order = 1;
$next_accommodation_order = 1;

if (!empty($transportation_info)) {
    $max_order = max(array_column($transportation_info, 'display_order'));
    $next_transportation_order = $max_order + 1;
}

if (!empty($accommodation_info)) {
    $max_order = max(array_column($accommodation_info, 'display_order'));
    $next_accommodation_order = $max_order + 1;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>交通・宿泊情報管理 - <?= $site_name ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .travel-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }
        
        .travel-card.visible {
            border-left-color: var(--primary-color);
        }
        
        .travel-card.hidden {
            border-left-color: #f44336;
            background-color: #fff8f8;
            opacity: 0.85;
        }
        
        .travel-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-3px);
        }
        
        .travel-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .travel-title {
            flex-grow: 1;
            margin: 0;
            font-size: 1.2rem;
            line-height: 1.4;
            color: #333;
            font-weight: 500;
        }
        
        .travel-actions {
            flex-shrink: 0;
            display: flex;
            gap: 8px;
        }
        
        .travel-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 0.85rem;
            color: #666;
            align-items: center;
        }
        
        .travel-order {
            opacity: 0.7;
        }
        
        .travel-status {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        .travel-status.visible {
            background-color: rgba(76, 175, 80, 0.1);
            color: #388e3c;
        }
        
        .travel-status.hidden {
            background-color: rgba(244, 67, 54, 0.1);
            color: #d32f2f;
        }
        
        .travel-description {
            color: #444;
            margin-top: 10px;
            padding: 15px;
            border-radius: 5px;
            background-color: #f9f9f9;
            position: relative;
            max-height: 200px;
            overflow-y: auto;
            line-height: 1.6;
            border-left: 3px solid #eee;
        }
        
        .travel-description::-webkit-scrollbar {
            width: 6px;
            background-color: #f5f5f5;
        }
        
        .travel-description::-webkit-scrollbar-thumb {
            background-color: #ddd;
            border-radius: 3px;
        }
        
        .travel-description::-webkit-scrollbar-thumb:hover {
            background-color: #ccc;
        }
        
        .tab-container {
            margin-bottom: 30px;
        }
        
        .tab-nav {
            display: flex;
            gap: 2px;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .tab-button {
            padding: 12px 25px;
            background-color: #f5f5f5;
            border: none;
            border-radius: 5px 5px 0 0;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab-button:hover {
            background-color: #e9e9e9;
        }
        
        .tab-button.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .travel-image {
            margin: 15px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            background-color: #f5f5f5;
            height: 250px;
        }
        
        .travel-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .travel-image:hover img {
            transform: scale(1.05);
        }
        
        .current-image {
            margin: 15px 0;
            padding: 15px;
            border: 1px dashed #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        
        .current-image p {
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: #555;
        }
        
        .current-image img {
            max-width: 100%;
            max-height: 250px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        /* 画像プレビュー機能 */
        .image-preview {
            display: none;
            margin: 15px 0;
            padding: 15px;
            border: 1px dashed #4CAF50;
            border-radius: 8px;
            background-color: #f1f8e9;
        }
        
        .image-preview.active {
            display: block;
        }
        
        .image-preview-title {
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: #388e3c;
        }
        
        .image-preview-container {
            text-align: center;
        }
        
        .image-preview-container img {
            max-width: 100%;
            max-height: 250px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        /* テキストエリア改善 */
        .admin-form textarea {
            min-height: 150px;
            line-height: 1.6;
            padding: 12px;
            font-size: 0.95rem;
            resize: vertical;
        }
        
        .admin-form textarea:focus {
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.3);
            border-color: var(--primary-color);
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .travel-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .travel-image {
                height: 200px;
            }
            
            .tab-button {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-dashboard">
        <header class="admin-dashboard-header">
            <div class="admin-logo">
                <h1><i class="fas fa-map-marked-alt"></i> 交通・宿泊情報管理</h1>
            </div>
            <div class="admin-user">
                <span>ようこそ、<?= htmlspecialchars($_SESSION['admin_username']) ?> さん</span>
                <a href="logout.php" class="admin-logout"><i class="fas fa-sign-out-alt"></i> ログアウト</a>
            </div>
        </header>
        
        <div class="admin-dashboard-content">
            <?php include 'inc/sidebar.php'; ?>
            
            <div class="admin-main">
                <div class="admin-content-wrapper">
                
                <?php if (!empty($success)): ?>
                <div class="admin-success">
                    <?= $success ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                <div class="admin-error">
                    <?= $error ?>
                </div>
                <?php endif; ?>
                
                <div class="tab-container">
                    <div class="tab-nav">
                        <button class="tab-button <?= !$edit_info || $edit_info['type'] === 'transportation' ? 'active' : '' ?>" data-tab="transportation">
                            <i class="fas fa-car"></i> 交通情報
                        </button>
                        <button class="tab-button <?= $edit_info && $edit_info['type'] === 'accommodation' ? 'active' : '' ?>" data-tab="accommodation">
                            <i class="fas fa-hotel"></i> 宿泊情報
                        </button>
                    </div>
                    
                    <div class="tab-content <?= !$edit_info || $edit_info['type'] === 'transportation' ? 'active' : '' ?>" id="transportation-tab">
                        <section class="admin-section">
                            <h2><?= $edit_info && $edit_info['type'] === 'transportation' ? '交通情報を編集' : '新しい交通情報を追加' ?></h2>
                            
                            <form class="admin-form" method="post" action="" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="<?= $edit_info && $edit_info['type'] === 'transportation' ? 'edit' : 'add_transportation' ?>">
                                <?php if ($edit_info && $edit_info['type'] === 'transportation'): ?>
                                    <input type="hidden" name="id" value="<?= $edit_info['id'] ?>">
                                <?php endif; ?>
                                
                                <div class="admin-form-group">
                                    <label for="transportation-title">タイトル <span class="required">*</span></label>
                                    <input type="text" id="transportation-title" name="title" required value="<?= $edit_info && $edit_info['type'] === 'transportation' ? htmlspecialchars($edit_info['title']) : '' ?>">
                                </div>
                                
                                <div class="admin-form-group">
                                    <label for="transportation-description">説明文</label>
                                    <textarea id="transportation-description" name="description" rows="4"><?= $edit_info && $edit_info['type'] === 'transportation' ? htmlspecialchars($edit_info['description']) : '' ?></textarea>
                                </div>
                                
                                <div class="admin-form-row">
                                    <div class="admin-form-group">
                                        <label for="transportation-display-order">表示順序</label>
                                        <input type="number" id="transportation-display-order" name="display_order" min="1" value="<?= $edit_info && $edit_info['type'] === 'transportation' ? $edit_info['display_order'] : $next_transportation_order ?>">
                                        <small>数字が小さいほど上に表示されます</small>
                                    </div>
                                    
                                    <div class="admin-form-group">
                                        <label for="transportation-is-visible" class="checkbox-label">
                                            <input type="checkbox" id="transportation-is-visible" name="is_visible" <?= (!$edit_info || ($edit_info['type'] === 'transportation' && $edit_info['is_visible'])) ? 'checked' : '' ?>>
                                            公開する
                                        </label>
                                        <small>チェックを外すと非表示になります</small>
                                    </div>
                                </div>
                                
                                <div class="admin-form-group">
                                    <label for="transportation-image">画像アップロード</label>
                                    <input type="file" id="transportation-image" name="image" class="image-upload" data-preview="transportation-preview">
                                    
                                    <div id="transportation-preview" class="image-preview">
                                        <p class="image-preview-title">プレビュー:</p>
                                        <div class="image-preview-container"></div>
                                    </div>
                                    
                                    <?php if ($edit_info && $edit_info['type'] === 'transportation' && !empty($edit_info['image_filename'])): ?>
                                    <div class="current-image">
                                        <p>現在の画像:</p>
                                        <img src="../uploads/travel/<?= htmlspecialchars($edit_info['image_filename']) ?>" alt="<?= htmlspecialchars($edit_info['title']) ?>">
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="admin-form-actions">
                                    <?php if ($edit_info && $edit_info['type'] === 'transportation'): ?>
                                        <a href="travel.php" class="admin-button admin-button-secondary">
                                            <i class="fas fa-times"></i> キャンセル
                                        </a>
                                    <?php endif; ?>
                                    <button type="submit" class="admin-button">
                                        <i class="fas fa-<?= $edit_info && $edit_info['type'] === 'transportation' ? 'save' : 'plus' ?>"></i> 
                                        <?= $edit_info && $edit_info['type'] === 'transportation' ? '情報を更新' : '情報を追加' ?>
                                    </button>
                                </div>
                            </form>
                        </section>
                        
                        <section class="admin-section">
                            <h2>交通情報一覧</h2>
                            
                            <?php if (empty($transportation_info)): ?>
                                <p>交通情報がありません。上のフォームから情報を追加してください。</p>
                            <?php else: ?>
                                <div class="travel-list">
                                    <?php foreach ($transportation_info as $info): ?>
                                        <div class="travel-card <?= $info['is_visible'] ? 'visible' : 'hidden' ?>">
                                            <div class="travel-header">
                                                <h3 class="travel-title"><?= htmlspecialchars($info['title']) ?></h3>
                                                <div class="travel-actions">
                                                    <a href="travel.php?edit=<?= $info['id'] ?>" class="action-link edit-link">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="post" style="display: inline;" onsubmit="return confirm('この情報を削除してもよろしいですか？');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= $info['id'] ?>">
                                                        <button type="submit" class="action-link delete-link">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                    <form method="post" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_visibility">
                                                        <input type="hidden" name="id" value="<?= $info['id'] ?>">
                                                        <input type="hidden" name="is_visible" value="<?= $info['is_visible'] ?>">
                                                        <button type="submit" class="action-link visibility-link">
                                                            <i class="fas fa-<?= $info['is_visible'] ? 'eye-slash' : 'eye' ?>"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            <div class="travel-meta">
                                                <span class="travel-order">表示順: <?= $info['display_order'] ?></span>
                                                <span class="travel-status <?= $info['is_visible'] ? 'visible' : 'hidden' ?>">
                                                    <?= $info['is_visible'] ? '表示中' : '非表示' ?>
                                                </span>
                                            </div>
                                            
                                            <?php if (!empty($info['image_filename'])): ?>
                                            <div class="travel-image">
                                                <img src="../uploads/travel/<?= htmlspecialchars($info['image_filename']) ?>" alt="<?= htmlspecialchars($info['title']) ?>">
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="travel-description">
                                                <?= nl2br(htmlspecialchars($info['description'])) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>
                    
                    <div class="tab-content <?= $edit_info && $edit_info['type'] === 'accommodation' ? 'active' : '' ?>" id="accommodation-tab">
                        <section class="admin-section">
                            <h2><?= $edit_info && $edit_info['type'] === 'accommodation' ? '宿泊情報を編集' : '新しい宿泊情報を追加' ?></h2>
                            
                            <form class="admin-form" method="post" action="" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="<?= $edit_info && $edit_info['type'] === 'accommodation' ? 'edit' : 'add_accommodation' ?>">
                                <?php if ($edit_info && $edit_info['type'] === 'accommodation'): ?>
                                    <input type="hidden" name="id" value="<?= $edit_info['id'] ?>">
                                <?php endif; ?>
                                
                                <div class="admin-form-group">
                                    <label for="accommodation-title">タイトル <span class="required">*</span></label>
                                    <input type="text" id="accommodation-title" name="title" required value="<?= $edit_info && $edit_info['type'] === 'accommodation' ? htmlspecialchars($edit_info['title']) : '' ?>">
                                </div>
                                
                                <div class="admin-form-group">
                                    <label for="accommodation-description">説明文</label>
                                    <textarea id="accommodation-description" name="description" rows="4"><?= $edit_info && $edit_info['type'] === 'accommodation' ? htmlspecialchars($edit_info['description']) : '' ?></textarea>
                                </div>
                                
                                <div class="admin-form-row">
                                    <div class="admin-form-group">
                                        <label for="accommodation-display-order">表示順序</label>
                                        <input type="number" id="accommodation-display-order" name="display_order" min="1" value="<?= $edit_info && $edit_info['type'] === 'accommodation' ? $edit_info['display_order'] : $next_accommodation_order ?>">
                                        <small>数字が小さいほど上に表示されます</small>
                                    </div>
                                    
                                    <div class="admin-form-group">
                                        <label for="accommodation-is-visible" class="checkbox-label">
                                            <input type="checkbox" id="accommodation-is-visible" name="is_visible" <?= (!$edit_info || ($edit_info['type'] === 'accommodation' && $edit_info['is_visible'])) ? 'checked' : '' ?>>
                                            公開する
                                        </label>
                                        <small>チェックを外すと非表示になります</small>
                                    </div>
                                </div>
                                
                                <div class="admin-form-group">
                                    <label for="accommodation-image">画像アップロード</label>
                                    <input type="file" id="accommodation-image" name="image" class="image-upload" data-preview="accommodation-preview">
                                    
                                    <div id="accommodation-preview" class="image-preview">
                                        <p class="image-preview-title">プレビュー:</p>
                                        <div class="image-preview-container"></div>
                                    </div>
                                    
                                    <?php if ($edit_info && $edit_info['type'] === 'accommodation' && !empty($edit_info['image_filename'])): ?>
                                    <div class="current-image">
                                        <p>現在の画像:</p>
                                        <img src="../uploads/travel/<?= htmlspecialchars($edit_info['image_filename']) ?>" alt="<?= htmlspecialchars($edit_info['title']) ?>">
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="admin-form-actions">
                                    <?php if ($edit_info && $edit_info['type'] === 'accommodation'): ?>
                                        <a href="travel.php" class="admin-button admin-button-secondary">
                                            <i class="fas fa-times"></i> キャンセル
                                        </a>
                                    <?php endif; ?>
                                    <button type="submit" class="admin-button">
                                        <i class="fas fa-<?= $edit_info && $edit_info['type'] === 'accommodation' ? 'save' : 'plus' ?>"></i> 
                                        <?= $edit_info && $edit_info['type'] === 'accommodation' ? '情報を更新' : '情報を追加' ?>
                                    </button>
                                </div>
                            </form>
                        </section>
                        
                        <section class="admin-section">
                            <h2>宿泊情報一覧</h2>
                            
                            <?php if (empty($accommodation_info)): ?>
                                <p>宿泊情報がありません。上のフォームから情報を追加してください。</p>
                            <?php else: ?>
                                <div class="travel-list">
                                    <?php foreach ($accommodation_info as $info): ?>
                                        <div class="travel-card <?= $info['is_visible'] ? 'visible' : 'hidden' ?>">
                                            <div class="travel-header">
                                                <h3 class="travel-title"><?= htmlspecialchars($info['title']) ?></h3>
                                                <div class="travel-actions">
                                                    <a href="travel.php?edit=<?= $info['id'] ?>" class="action-link edit-link">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="post" style="display: inline;" onsubmit="return confirm('この情報を削除してもよろしいですか？');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= $info['id'] ?>">
                                                        <button type="submit" class="action-link delete-link">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                    <form method="post" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_visibility">
                                                        <input type="hidden" name="id" value="<?= $info['id'] ?>">
                                                        <input type="hidden" name="is_visible" value="<?= $info['is_visible'] ?>">
                                                        <button type="submit" class="action-link visibility-link">
                                                            <i class="fas fa-<?= $info['is_visible'] ? 'eye-slash' : 'eye' ?>"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            <div class="travel-meta">
                                                <span class="travel-order">表示順: <?= $info['display_order'] ?></span>
                                                <span class="travel-status <?= $info['is_visible'] ? 'visible' : 'hidden' ?>">
                                                    <?= $info['is_visible'] ? '表示中' : '非表示' ?>
                                                </span>
                                            </div>
                                            
                                            <?php if (!empty($info['image_filename'])): ?>
                                            <div class="travel-image">
                                                <img src="../uploads/travel/<?= htmlspecialchars($info['image_filename']) ?>" alt="<?= htmlspecialchars($info['title']) ?>">
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="travel-description">
                                                <?= nl2br(htmlspecialchars($info['description'])) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>
                </div>
                </div>
                <?php include 'inc/footer.php'; ?>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // タブ機能
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                const tab = this.getAttribute('data-tab');
                
                // すべてのタブボタンからactiveクラスを削除
                tabButtons.forEach(btn => btn.classList.remove('active'));
                
                // すべてのタブコンテンツからactiveクラスを削除
                tabContents.forEach(content => content.classList.remove('active'));
                
                // クリックされたタブボタンとそのコンテンツにactiveクラスを追加
                this.classList.add('active');
                document.getElementById(tab + '-tab').classList.add('active');
            });
        });
        
        // 画像プレビュー機能
        const imageInputs = document.querySelectorAll('.image-upload');
        
        imageInputs.forEach(input => {
            input.addEventListener('change', function() {
                const previewId = this.getAttribute('data-preview');
                const previewDiv = document.getElementById(previewId);
                const previewContainer = previewDiv.querySelector('.image-preview-container');
                
                // プレビューをクリア
                previewContainer.innerHTML = '';
                
                // ファイルが選択されている場合
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    
                    // 画像ファイルかどうかを確認
                    if (file.type.match('image.*')) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            // プレビュー画像を作成
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = 'プレビュー';
                            
                            // プレビューコンテナに画像を追加
                            previewContainer.appendChild(img);
                            
                            // プレビューを表示
                            previewDiv.classList.add('active');
                        }
                        
                        // ファイルを読み込む
                        reader.readAsDataURL(file);
                    }
                } else {
                    // ファイルが選択されていない場合はプレビューを非表示
                    previewDiv.classList.remove('active');
                }
            });
        });
    });
    </script>
</body>
</html> 