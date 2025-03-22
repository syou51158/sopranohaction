<?php
require_once "../config.php"; session_start();
if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) { header("Location: index.php"); exit; }
$success = ""; $error = ""; $upload_dir = "../uploads/photos/"; 

// アップロードディレクトリの準備
if (!file_exists($upload_dir)) { 
    mkdir($upload_dir, 0755, true); 
    chmod($upload_dir, 0755); // 確実にパーミッションを設定
} else {
    // すでに存在する場合もパーミッションを確認・設定
    if (!is_writable($upload_dir)) {
        chmod($upload_dir, 0755);
    }
}

// 写真のアップロード処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload' && isset($_FILES['photo'])) {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $is_approved = isset($_POST['is_approved']) ? 1 : 0;
        $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
        $uploaded_by = htmlspecialchars($_SESSION['admin_username']);
        
        if (empty($title)) {
            $error = "タイトルは必須です。";
        } else {
            $file = $_FILES['photo'];
            
            // エラーチェック
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = "ファイルのアップロードに失敗しました。エラーコード: " . $file['error'];
            } else {
                // ファイルタイプのチェック
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $file_type = mime_content_type($file['tmp_name']);
                
                if (!in_array($file_type, $allowed_types)) {
                    $error = "許可されていないファイル形式です。JPEG、PNG、GIF形式のみ許可されています。";
                } else {
                    // 一意なファイル名を生成
                    $filename = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $file['name']);
                    $target_file = $upload_dir . $filename;
                    
                    // ファイルを移動
                    if (move_uploaded_file($file['tmp_name'], $target_file)) {
                        // パーミッション設定を追加（ロリポップサーバー対応）
                        chmod($target_file, 0644);
                        
                        try {
                            $stmt = $pdo->prepare("
                                INSERT INTO photo_gallery 
                                (title, description, filename, original_filename, file_size, file_type, is_approved, display_order, uploaded_by) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$title, $description, $filename, $file['name'], $file['size'], $file_type, $is_approved, $display_order, $uploaded_by]);
                            $success = "写真をアップロードしました。";
                        } catch (PDOException $e) {
                            $error = "データベースへの保存に失敗しました。";
                            if ($debug_mode) {
                                $error .= " エラー: " . $e->getMessage();
                            }
                            // エラーが発生した場合、アップロードしたファイルを削除
                            if (file_exists($target_file)) {
                                unlink($target_file);
                            }
                        }
                    } else {
                        $error = "ファイルの保存に失敗しました。";
                    }
                }
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        try {
            // 写真のファイル名を取得
            $stmt = $pdo->prepare("SELECT filename FROM photo_gallery WHERE id = ?");
            $stmt->execute([$id]);
            $photo = $stmt->fetch();
            
            if ($photo) {
                // データベースから削除
                $delete_stmt = $pdo->prepare("DELETE FROM photo_gallery WHERE id = ?");
                $delete_stmt->execute([$id]);
                
                // ファイルも削除
                $file_path = $upload_dir . $photo['filename'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                $success = "写真を削除しました。";
            } else {
                $error = "写真が見つかりませんでした。";
            }
        } catch (PDOException $e) {
            $error = "写真の削除に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'toggle_approval' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $is_approved = isset($_POST['is_approved']) ? (int)$_POST['is_approved'] : 0;
        $new_approval = $is_approved ? 0 : 1;
        
        try {
            $stmt = $pdo->prepare("UPDATE photo_gallery SET is_approved = ? WHERE id = ?");
            $stmt->execute([$new_approval, $id]);
            $success = "写真の承認状態を変更しました。";
        } catch (PDOException $e) {
            $error = "設定の変更に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $is_approved = isset($_POST['is_approved']) ? 1 : 0;
        $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
        
        if (empty($title)) {
            $error = "タイトルは必須です。";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE photo_gallery 
                    SET title = ?, description = ?, is_approved = ?, display_order = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $is_approved, $display_order, $id]);
                $success = "写真情報を更新しました。";
            } catch (PDOException $e) {
                $error = "情報の更新に失敗しました。";
                if ($debug_mode) {
                    $error .= " エラー: " . $e->getMessage();
                }
            }
        }
    }
}

// 写真一覧を取得
$photos = [];
try {
    $stmt = $pdo->query("SELECT * FROM photo_gallery ORDER BY display_order ASC, upload_date DESC");
    $photos = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "写真情報の取得に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// 編集対象の写真を取得
$edit_photo = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM photo_gallery WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_photo = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "写真情報の取得に失敗しました。";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>写真管理 - <?= $site_name ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .photo-card {
            border-radius: 8px;
            overflow: hidden;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .photo-card.not-approved {
            opacity: 0.7;
        }
        
        .photo-card.not-approved::before {
            content: '未承認';
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #f44336;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            z-index: 1;
        }
        
        .photo-image {
            height: 200px;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        .photo-content {
            padding: 15px;
        }
        
        .photo-title {
            margin: 0 0 5px;
            font-size: 1.1rem;
        }
        
        .photo-meta {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 10px;
        }
        
        .photo-description {
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .photo-actions {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-dashboard">
        <header class="admin-dashboard-header">
            <div class="admin-logo">
                <h1><i class="fas fa-images"></i> 写真管理</h1>
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
                
                <section class="admin-section">
                    <h2><?= $edit_photo ? '写真情報を編集' : '新しい写真をアップロード' ?></h2>
                    
                    <form class="admin-form" method="post" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?= $edit_photo ? 'edit' : 'upload' ?>">
                        <?php if ($edit_photo): ?>
                            <input type="hidden" name="id" value="<?= $edit_photo['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="admin-form-row">
                            <div class="admin-form-group">
                                <label for="title">タイトル <span class="required">*</span></label>
                                <input type="text" id="title" name="title" required value="<?= $edit_photo ? htmlspecialchars($edit_photo['title']) : '' ?>">
                            </div>
                            
                            <?php if (!$edit_photo): ?>
                            <div class="admin-form-group">
                                <label for="photo">写真ファイル <span class="required">*</span></label>
                                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/gif" required>
                                <small>JPEG、PNG、GIF形式の画像ファイルのみ許可されています。</small>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="admin-form-group">
                            <label for="description">説明</label>
                            <textarea id="description" name="description" rows="3"><?= $edit_photo ? htmlspecialchars($edit_photo['description']) : '' ?></textarea>
                        </div>
                        
                        <div class="admin-form-group">
                            <label for="is_approved" class="checkbox-label">
                                <input type="checkbox" id="is_approved" name="is_approved" <?= (!$edit_photo || $edit_photo['is_approved']) ? 'checked' : '' ?>>
                                承認済み（サイトに表示する）
                            </label>
                            <small>チェックを外すと写真はサイトに表示されません</small>
                        </div>
                        
                        <div class="admin-form-group">
                            <label for="display_order">表示順序</label>
                            <input type="number" id="display_order" name="display_order" min="0" value="<?= $edit_photo ? htmlspecialchars($edit_photo['display_order']) : '0' ?>">
                        </div>
                        
                        <div class="admin-form-actions">
                            <?php if ($edit_photo): ?>
                                <a href="photos.php" class="admin-button admin-button-secondary">
                                    <i class="fas fa-times"></i> キャンセル
                                </a>
                            <?php endif; ?>
                            <button type="submit" class="admin-button">
                                <i class="fas fa-<?= $edit_photo ? 'save' : 'upload' ?>"></i> 
                                <?= $edit_photo ? '情報を更新' : '写真をアップロード' ?>
                            </button>
                        </div>
                    </form>
                </section>
                
                <section class="admin-section">
                    <h2>写真ギャラリー</h2>
                    
                    <div class="photo-filter">
                        <button class="photo-filter-btn active" data-filter="all">すべて</button>
                        <button class="photo-filter-btn" data-filter="approved">承認済み</button>
                        <button class="photo-filter-btn" data-filter="pending">未承認</button>
                    </div>
                    
                    <?php if (empty($photos)): ?>
                        <p>写真がまだアップロードされていません。上のフォームから写真をアップロードしてください。</p>
                    <?php else: ?>
                        <div class="photo-grid">
                            <?php foreach ($photos as $photo): ?>
                                <div class="photo-card <?= $photo['is_approved'] ? 'approved' : 'not-approved' ?>" data-status="<?= $photo['is_approved'] ? 'approved' : 'pending' ?>">
                                    <div class="photo-image" style="background-image: url('../uploads/photos/<?= htmlspecialchars($photo['filename']) ?>');" data-id="<?= $photo['id'] ?>"></div>
                                    <div class="photo-content">
                                        <h3 class="photo-title"><?= htmlspecialchars($photo['title']) ?></h3>
                                        <div class="photo-meta">
                                            <div>アップロード: <?= date('Y/m/d H:i', strtotime($photo['upload_date'])) ?></div>
                                            <div>アップロード者: <?= htmlspecialchars($photo['uploaded_by']) ?></div>
                                        </div>
                                        <?php if (!empty($photo['description'])): ?>
                                            <div class="photo-description"><?= nl2br(htmlspecialchars($photo['description'])) ?></div>
                                        <?php endif; ?>
                                        <div class="photo-actions">
                                            <div>
                                                <form method="post" class="inline-form">
                                                    <input type="hidden" name="action" value="toggle_approval">
                                                    <input type="hidden" name="id" value="<?= $photo['id'] ?>">
                                                    <input type="hidden" name="is_approved" value="<?= $photo['is_approved'] ?>">
                                                    <button type="submit" class="admin-btn <?= $photo['is_approved'] ? 'admin-btn-success' : 'admin-btn-secondary' ?>" title="<?= $photo['is_approved'] ? '非承認にする' : '承認する' ?>">
                                                        <i class="fas fa-<?= $photo['is_approved'] ? 'check-circle' : 'times-circle' ?>"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <div>
                                                <a href="photos.php?edit=<?= $photo['id'] ?>" class="admin-btn admin-btn-edit" title="編集">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="post" class="inline-form" onsubmit="return confirm('この写真を削除してもよろしいですか？');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $photo['id'] ?>">
                                                    <button type="submit" class="admin-btn admin-btn-delete" title="削除">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                
                </div>
                <?php include 'inc/footer.php'; ?>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 写真フィルター機能
        const filterButtons = document.querySelectorAll('.photo-filter-btn');
        const photoCards = document.querySelectorAll('.photo-card');
        
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');
                
                // すべてのボタンからactiveクラスを削除
                filterButtons.forEach(btn => btn.classList.remove('active'));
                
                // クリックされたボタンにactiveクラスを追加
                this.classList.add('active');
                
                // 写真カードのフィルタリング
                photoCards.forEach(card => {
                    if (filter === 'all' || card.getAttribute('data-status') === filter) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    });
    </script>
</body>
</html>
