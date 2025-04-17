<?php
// セッションを開始
session_start();

// 設定ファイルを読み込み
require_once '../config.php';

// 認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// 動画アップロードディレクトリの確認と作成
$upload_dir = '../videos/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// サムネイルアップロードディレクトリの確認と作成
$thumbnail_dir = '../images/thumbnails/';
if (!is_dir($thumbnail_dir)) {
    mkdir($thumbnail_dir, 0755, true);
}

// メッセージ変数の初期化
$success_message = '';
$error_message = '';

// 動画アップロード処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload') {
        // アップロードファイルの処理
        if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['video'];
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $is_main_video = isset($_POST['is_main_video']) ? 1 : 0;

            // ファイル情報の取得
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_type = $file['type'];
            
            // ファイルタイプのチェック
            $allowed_types = [
                'video/mp4',       // MP4
                'video/quicktime', // MOV
                'video/x-msvideo', // AVI
                'video/x-ms-wmv',  // WMV
                'video/webm',      // WebM
                'video/ogg',       // OGG/OGV
                'video/mpeg',      // MPEG
                'video/3gpp',      // 3GP
                'video/x-flv'      // FLV
            ];
            if (!in_array($file_type, $allowed_types)) {
                // ファイル拡張子に基づく判定も追加
                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_extensions = ['mp4', 'mov', 'avi', 'wmv', 'webm', 'ogv', 'ogg', 'mpg', 'mpeg', '3gp', 'flv'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    // 拡張子はOKなのでアップロードを許可
                    $valid_file = true;
                } else {
                    $error_message = 'アップロードできるのは一般的な動画形式のみです（MP4、MOV、AVI、WMV、WebM、OGG、MPEGなど）。';
                    $valid_file = false;
                }
            } else {
                $valid_file = true;
            }
            
            if ($valid_file) {
                // ファイル名の作成（一意にするためにタイムスタンプを追加）
                $new_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $file_name);
                
                // サムネイルの処理
                $thumbnail_filename = null;
                if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                    $thumbnail = $_FILES['thumbnail'];
                    $thumbnail_type = $thumbnail['type'];
                    
                    // サムネイルのファイルタイプチェック
                    $allowed_thumbnail_types = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!in_array($thumbnail_type, $allowed_thumbnail_types)) {
                        $error_message = 'サムネイルはJPEG、PNG、GIF形式の画像のみアップロードできます。';
                    } else {
                        // サムネイルファイル名の作成
                        $thumbnail_filename = 'thumb_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $thumbnail['name']);
                        
                        // サムネイルのアップロード
                        if (!move_uploaded_file($thumbnail['tmp_name'], $thumbnail_dir . $thumbnail_filename)) {
                            $error_message = 'サムネイルのアップロードに失敗しました。';
                            $thumbnail_filename = null;
                        }
                    }
                }
                
                if (empty($error_message)) {
                    // 動画ファイルの移動
                    if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
                        try {
                            // もしこれがメイン動画に設定されている場合、他のメイン動画を解除
                            if ($is_main_video) {
                                $stmt = $pdo->prepare("UPDATE video_gallery SET is_main_video = 0 WHERE is_main_video = 1");
                                $stmt->execute();
                            }
                            
                            // データベースに動画情報を保存
                            $stmt = $pdo->prepare("
                                INSERT INTO video_gallery 
                                (title, description, filename, original_filename, file_size, file_type, is_active, is_main_video, thumbnail) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            $stmt->execute([
                                $title,
                                $description,
                                $new_filename,
                                $file_name,
                                $file_size,
                                $file_type,
                                $is_active,
                                $is_main_video,
                                $thumbnail_filename
                            ]);
                            
                            $success_message = '動画をアップロードしました。';
                        } catch (PDOException $e) {
                            $error_message = 'データベースエラー: ' . ($debug_mode ? $e->getMessage() : '動画情報の保存に失敗しました。');
                        }
                    } else {
                        $error_message = 'ファイルのアップロードに失敗しました。';
                    }
                }
            }
        } else {
            $error_message = 'ファイルが選択されていないか、アップロードエラーが発生しました。';
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['video_id'])) {
        // 動画削除処理
        $video_id = (int)$_POST['video_id'];
        
        try {
            // 動画情報を取得
            $stmt = $pdo->prepare("SELECT filename, thumbnail FROM video_gallery WHERE id = ?");
            $stmt->execute([$video_id]);
            $video = $stmt->fetch();
            
            if ($video) {
                // 動画ファイルを削除
                $file_path = $upload_dir . $video['filename'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                // サムネイルがあれば削除
                if ($video['thumbnail']) {
                    $thumbnail_path = $thumbnail_dir . $video['thumbnail'];
                    if (file_exists($thumbnail_path)) {
                        unlink($thumbnail_path);
                    }
                }
                
                // データベースから動画情報を削除
                $stmt = $pdo->prepare("DELETE FROM video_gallery WHERE id = ?");
                $stmt->execute([$video_id]);
                
                $success_message = '動画を削除しました。';
            } else {
                $error_message = '動画が見つかりませんでした。';
            }
        } catch (PDOException $e) {
            $error_message = 'データベースエラー: ' . ($debug_mode ? $e->getMessage() : '動画の削除に失敗しました。');
        }
    } elseif ($_POST['action'] === 'update' && isset($_POST['video_id'])) {
        // 動画情報更新処理
        $video_id = (int)$_POST['video_id'];
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_main_video = isset($_POST['is_main_video']) ? 1 : 0;
        
        try {
            // 現在の動画情報を取得
            $stmt = $pdo->prepare("SELECT is_main_video FROM video_gallery WHERE id = ?");
            $stmt->execute([$video_id]);
            $current_video = $stmt->fetch();
            
            // メイン動画に設定する場合、他のメイン動画を解除
            if ($is_main_video && (!$current_video || !$current_video['is_main_video'])) {
                $stmt = $pdo->prepare("UPDATE video_gallery SET is_main_video = 0 WHERE is_main_video = 1");
                $stmt->execute();
            }
            
            // サムネイルの処理
            $thumbnail_clause = '';
            $params = [$title, $description, $is_active, $is_main_video];
            
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                $thumbnail = $_FILES['thumbnail'];
                $thumbnail_type = $thumbnail['type'];
                
                // サムネイルのファイルタイプチェック
                $allowed_thumbnail_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($thumbnail_type, $allowed_thumbnail_types)) {
                    $error_message = 'サムネイルはJPEG、PNG、GIF形式の画像のみアップロードできます。';
                } else {
                    // 古いサムネイルの情報を取得
                    $stmt = $pdo->prepare("SELECT thumbnail FROM video_gallery WHERE id = ?");
                    $stmt->execute([$video_id]);
                    $old_thumbnail = $stmt->fetch();
                    
                    // 古いサムネイルを削除
                    if ($old_thumbnail && $old_thumbnail['thumbnail']) {
                        $old_thumbnail_path = $thumbnail_dir . $old_thumbnail['thumbnail'];
                        if (file_exists($old_thumbnail_path)) {
                            unlink($old_thumbnail_path);
                        }
                    }
                    
                    // 新しいサムネイルをアップロード
                    $thumbnail_filename = 'thumb_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $thumbnail['name']);
                    if (move_uploaded_file($thumbnail['tmp_name'], $thumbnail_dir . $thumbnail_filename)) {
                        $thumbnail_clause = ', thumbnail = ?';
                        $params[] = $thumbnail_filename;
                    } else {
                        $error_message = 'サムネイルのアップロードに失敗しました。';
                    }
                }
            }
            
            if (empty($error_message)) {
                $params[] = $video_id;
                $stmt = $pdo->prepare("
                    UPDATE video_gallery 
                    SET title = ?, description = ?, is_active = ?, is_main_video = ? $thumbnail_clause
                    WHERE id = ?
                ");
                
                $stmt->execute($params);
                
                $success_message = '動画情報を更新しました。';
            }
        } catch (PDOException $e) {
            $error_message = 'データベースエラー: ' . ($debug_mode ? $e->getMessage() : '動画情報の更新に失敗しました。');
        }
    } elseif ($_POST['action'] === 'set_main' && isset($_POST['video_id'])) {
        // メイン動画設定処理
        $video_id = (int)$_POST['video_id'];
        
        try {
            // 他のメイン動画を解除
            $stmt = $pdo->prepare("UPDATE video_gallery SET is_main_video = 0 WHERE is_main_video = 1");
            $stmt->execute();
            
            // 選択した動画をメイン動画に設定
            $stmt = $pdo->prepare("UPDATE video_gallery SET is_main_video = 1 WHERE id = ?");
            $stmt->execute([$video_id]);
            
            $success_message = 'メイン動画を設定しました。';
        } catch (PDOException $e) {
            $error_message = 'データベースエラー: ' . ($debug_mode ? $e->getMessage() : 'メイン動画の設定に失敗しました。');
        }
    }
}

// 動画リストの取得
$videos = [];
try {
    $stmt = $pdo->query("SELECT * FROM video_gallery ORDER BY upload_date DESC");
    $videos = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = 'データベースエラー: ' . ($debug_mode ? $e->getMessage() : '動画情報の取得に失敗しました。');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>動画管理 - <?= $site_name ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .video-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .video-item {
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            background-color: #fff;
            transition: transform 0.2s;
        }
        .video-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .video-thumbnail {
            width: 100%;
            height: 180px;
            position: relative;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .video-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .video-thumbnail .play-icon {
            position: absolute;
            font-size: 3rem;
            color: rgba(255, 255, 255, 0.8);
            background-color: rgba(0, 0, 0, 0.5);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .video-thumbnail:hover .play-icon {
            transform: scale(1.1);
            background-color: rgba(0, 0, 0, 0.7);
        }
        .video-info {
            padding: 15px;
        }
        .video-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .main-video-badge {
            background-color: #ff9800;
            color: white;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: normal;
            margin-left: 5px;
        }
        .video-description {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
            max-height: 60px;
            overflow: hidden;
        }
        .video-meta {
            font-size: 0.8rem;
            color: #888;
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            flex-wrap: wrap;
        }
        .video-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .video-actions form {
            display: inline;
        }
        .video-status {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 0.8rem;
            margin-top: 5px;
        }
        .status-active {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .status-inactive {
            background-color: #f2dede;
            color: #a94442;
        }
        .thumbnail-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: none;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.7);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 70%;
            max-width: 800px;
            border-radius: 5px;
            position: relative;
        }
        .close-button {
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        #editVideoForm .admin-form-group {
            margin-bottom: 15px;
        }
        .video-preview-container {
            width: 100%;
            margin-top: 15px;
        }
        .video-preview-container video {
            width: 100%;
            max-height: 400px;
            background-color: #000;
        }
        .file-label {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #ced4da;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
            transition: background-color 0.3s;
        }
        .file-label:hover {
            background-color: #e9ecef;
        }
        .file-label i {
            margin-right: 5px;
        }
        .file-input {
            display: none;
        }
        .file-name {
            margin-top: 5px;
            font-size: 0.8rem;
            color: #6c757d;
        }
        .drag-drop-area {
            border: 2px dashed #ced4da;
            padding: 30px;
            text-align: center;
            background-color: #f8f9fa;
            margin-bottom: 20px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .drag-drop-area.drag-over {
            background-color: #e9ecef;
            border-color: #6c757d;
        }
        .drag-drop-message {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 15px;
        }
        .drag-drop-icon {
            font-size: 2rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-dashboard">
        <header class="admin-dashboard-header">
            <div class="admin-logo">
                <h1><i class="fas fa-heart"></i> 結婚式管理システム</h1>
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
                    <section class="admin-section">
                        <h2><i class="fas fa-video"></i> 動画管理</h2>
                        
                        <?php if (!empty($success_message)): ?>
                        <div class="admin-success">
                            <?= $success_message ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                        <div class="admin-error">
                            <?= $error_message ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="admin-info-box">
                            <p><i class="fas fa-info-circle"></i> <strong>動画管理について</strong></p>
                            <ul>
                                <li>結婚式ウェブサイトに表示する動画をアップロードできます。</li>
                                <li>アップロードできるのはMP4、MOV、AVI、WMV形式の動画ファイルのみです。</li>
                                <li>サムネイル画像をアップロードすることで、動画のプレビュー表示ができます。</li>
                                <li>「メイン動画」に設定した動画が招待状のトップページに表示されます。</li>
                            </ul>
                        </div>
                        
                        <div class="admin-tabs">
                            <button class="admin-tab active" data-tab="upload">動画アップロード</button>
                            <button class="admin-tab" data-tab="manage">動画一覧・管理</button>
                        </div>
                        
                        <div class="admin-tab-content active" data-tab-content="upload">
                            <h3>新しい動画をアップロード</h3>
                            <form class="admin-form" method="post" enctype="multipart/form-data" id="uploadForm">
                                <input type="hidden" name="action" value="upload">
                                
                                <div class="drag-drop-area" id="dragDropArea">
                                    <div class="drag-drop-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <div class="drag-drop-message">
                                        ここに動画ファイルをドラッグ＆ドロップ
                                    </div>
                                    <div>
                                        <label class="file-label">
                                            <i class="fas fa-video"></i> 動画ファイルを選択
                                            <input type="file" id="video" name="video" required accept="video/mp4,video/quicktime,video/x-msvideo,video/x-ms-wmv,video/webm,video/ogg,video/mpeg,video/3gpp,video/x-flv,.mp4,.mov,.avi,.wmv,.webm,.ogv,.ogg,.mpg,.mpeg,.3gp,.flv" class="file-input">
                                        </label>
                                        <div class="file-name" id="videoFileName">ファイルが選択されていません</div>
                                    </div>
                                </div>
                                
                                <div class="video-preview-container" id="videoPreviewContainer" style="display: none;">
                                    <h4>動画プレビュー</h4>
                                    <video id="videoPreview" controls></video>
                                </div>
                                
                                <div class="admin-form-row">
                                    <div class="admin-form-group">
                                        <label for="title">タイトル <span class="required">*</span></label>
                                        <input type="text" id="title" name="title" required>
                                    </div>
                                </div>
                                
                                <div class="admin-form-group">
                                    <label for="description">説明</label>
                                    <textarea id="description" name="description" rows="3"></textarea>
                                </div>
                                
                                <div class="admin-form-row">
                                    <div class="admin-form-group">
                                        <label for="thumbnail">サムネイル画像</label>
                                        <input type="file" id="thumbnail" name="thumbnail" accept="image/jpeg, image/png, image/gif" onchange="previewThumbnail(this)">
                                        <small>JPEG、PNG、GIF形式の画像ファイル</small>
                                        <img id="thumbnailPreview" src="#" alt="サムネイルプレビュー" class="thumbnail-preview" />
                                    </div>
                                </div>
                                
                                <div class="admin-form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="is_active" checked> 
                                        アクティブ（サイトに表示する）
                                    </label>
                                </div>
                                
                                <div class="admin-form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="is_main_video" id="isMainVideo"> 
                                        メイン動画に設定（招待状トップページに表示）
                                    </label>
                                    <small>メイン動画は1つだけ設定できます。既存のメイン動画は解除されます。</small>
                                </div>
                                
                                <div class="admin-form-actions">
                                    <button type="submit" class="admin-button">
                                        <i class="fas fa-upload"></i> アップロード
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="admin-tab-content" data-tab-content="manage">
                            <h3>動画一覧</h3>
                            
                            <?php if (empty($videos)): ?>
                                <p>動画がまだアップロードされていません。</p>
                            <?php else: ?>
                                <div class="video-grid">
                                    <?php foreach ($videos as $video): ?>
                                    <div class="video-item">
                                        <div class="video-thumbnail">
                                            <?php if ($video['thumbnail']): ?>
                                                <img src="<?= '../images/thumbnails/' . htmlspecialchars($video['thumbnail']) ?>" alt="<?= htmlspecialchars($video['title']) ?>">
                                            <?php else: ?>
                                                <div style="background-color: #000; width: 100%; height: 100%; display: flex; justify-content: center; align-items: center;">
                                                    <i class="fas fa-video" style="font-size: 3rem; color: #666;"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="play-icon" onclick="playVideo('../videos/<?= htmlspecialchars($video['filename']) ?>', '<?= htmlspecialchars($video['title']) ?>')">
                                                <i class="fas fa-play"></i>
                                            </div>
                                        </div>
                                        <div class="video-info">
                                            <div class="video-title">
                                                <?= htmlspecialchars($video['title']) ?>
                                                <?php if ($video['is_main_video']): ?>
                                                    <span class="main-video-badge">メイン</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="video-description"><?= nl2br(htmlspecialchars($video['description'])) ?></div>
                                            <div class="video-meta">
                                                <span>
                                                    <?= date('Y/m/d', strtotime($video['upload_date'])) ?>
                                                </span>
                                                <span>
                                                    <?= round($video['file_size'] / 1048576, 2) ?> MB
                                                </span>
                                            </div>
                                            <div class="video-status <?= $video['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                                <?= $video['is_active'] ? 'アクティブ' : '非アクティブ' ?>
                                            </div>
                                            <div class="video-actions">
                                                <button class="admin-btn admin-btn-edit" onclick="editVideo(<?= $video['id'] ?>, '<?= htmlspecialchars(addslashes($video['title'])) ?>', '<?= htmlspecialchars(addslashes($video['description'])) ?>', <?= $video['is_active'] ?>, <?= $video['is_main_video'] ?>)">
                                                    <i class="fas fa-edit"></i> 編集
                                                </button>
                                                <?php if (!$video['is_main_video']): ?>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="set_main">
                                                    <input type="hidden" name="video_id" value="<?= $video['id'] ?>">
                                                    <button type="submit" class="admin-btn admin-btn-info">
                                                        <i class="fas fa-star"></i> メインに設定
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                <form method="post" onsubmit="return confirm('この動画を削除してもよろしいですか？');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="video_id" value="<?= $video['id'] ?>">
                                                    <button type="submit" class="admin-btn admin-btn-delete">
                                                        <i class="fas fa-trash"></i> 削除
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
                
                <?php include 'inc/footer.php'; ?>
            </div>
        </div>
    </div>
    
    <!-- 動画編集モーダル -->
    <div id="editVideoModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal()">&times;</span>
            <h3>動画情報の編集</h3>
            <form id="editVideoForm" class="admin-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="video_id" id="edit_video_id">
                
                <div class="admin-form-group">
                    <label for="edit_title">タイトル <span class="required">*</span></label>
                    <input type="text" id="edit_title" name="title" required>
                </div>
                
                <div class="admin-form-group">
                    <label for="edit_description">説明</label>
                    <textarea id="edit_description" name="description" rows="3"></textarea>
                </div>
                
                <div class="admin-form-group">
                    <label for="edit_thumbnail">サムネイル画像（変更する場合のみ）</label>
                    <input type="file" id="edit_thumbnail" name="thumbnail" accept="image/jpeg, image/png, image/gif" onchange="previewEditThumbnail(this)">
                    <small>JPEG、PNG、GIF形式の画像ファイル</small>
                    <img id="editThumbnailPreview" src="#" alt="サムネイルプレビュー" class="thumbnail-preview" />
                </div>
                
                <div class="admin-form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="edit_is_active" name="is_active"> 
                        アクティブ（サイトに表示する）
                    </label>
                </div>
                
                <div class="admin-form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="edit_is_main_video" name="is_main_video"> 
                        メイン動画に設定（招待状トップページに表示）
                    </label>
                    <small>メイン動画は1つだけ設定できます。既存のメイン動画は解除されます。</small>
                </div>
                
                <div class="admin-form-actions">
                    <button type="submit" class="admin-button">
                        <i class="fas fa-save"></i> 更新
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 動画再生モーダル -->
    <div id="playVideoModal" class="modal">
        <div class="modal-content" style="width: 80%; max-width: 1000px;">
            <span class="close-button" onclick="closePlayModal()">&times;</span>
            <h3 id="playVideoTitle"></h3>
            <video id="videoPlayer" controls style="width: 100%; max-height: 70vh;"></video>
        </div>
    </div>
    
    <script>
        // タブ切り替え処理
        document.querySelectorAll('.admin-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // タブの切り替え
                document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                
                // コンテンツの切り替え
                const tabContent = tab.getAttribute('data-tab');
                document.querySelectorAll('.admin-tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.querySelector(`.admin-tab-content[data-tab-content="${tabContent}"]`).classList.add('active');
            });
        });
        
        // サムネイルプレビュー処理
        function previewThumbnail(input) {
            const preview = document.getElementById('thumbnailPreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
        
        // 編集用サムネイルプレビュー処理
        function previewEditThumbnail(input) {
            const preview = document.getElementById('editThumbnailPreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
        
        // 動画プレビュー処理
        document.getElementById('video').addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                document.getElementById('videoFileName').textContent = file.name;
                
                // 動画プレビューの表示
                const videoPreview = document.getElementById('videoPreview');
                const videoPreviewContainer = document.getElementById('videoPreviewContainer');
                
                // ファイルURLの作成
                const fileURL = URL.createObjectURL(file);
                videoPreview.src = fileURL;
                videoPreviewContainer.style.display = 'block';
                
                // 動画メタデータの読み込み完了時
                videoPreview.onloadedmetadata = function() {
                    // ここで必要ならば動画の情報を表示できます
                };
            }
        });
        
        // ドラッグ&ドロップ機能
        const dragDropArea = document.getElementById('dragDropArea');
        const videoInput = document.getElementById('video');
        
        // ドラッグオーバー時のイベント
        ['dragenter', 'dragover'].forEach(eventName => {
            dragDropArea.addEventListener(eventName, e => {
                e.preventDefault();
                dragDropArea.classList.add('drag-over');
            }, false);
        });
        
        // ドラッグリーブ・ドロップ終了時のイベント
        ['dragleave', 'drop'].forEach(eventName => {
            dragDropArea.addEventListener(eventName, e => {
                e.preventDefault();
                dragDropArea.classList.remove('drag-over');
            }, false);
        });
        
        // ドロップ時の処理
        dragDropArea.addEventListener('drop', e => {
            e.preventDefault();
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                // 最初のファイルのみ処理
                const file = files[0];
                const fileType = file.type;
                // 対応する動画形式のリスト拡張
                const validTypes = [
                    'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv',
                    'video/webm', 'video/ogg', 'video/mpeg', 'video/3gpp', 'video/x-flv'
                ];
                
                // ファイルタイプで確認
                let isValidFileType = validTypes.includes(fileType);
                
                // ファイルタイプがfalseでも、拡張子で再確認
                if (!isValidFileType) {
                    const fileName = file.name;
                    const extension = fileName.split('.').pop().toLowerCase();
                    const validExtensions = ['mp4', 'mov', 'avi', 'wmv', 'webm', 'ogv', 'ogg', 'mpg', 'mpeg', '3gp', 'flv'];
                    isValidFileType = validExtensions.includes(extension);
                }
                
                if (isValidFileType) {
                    // ファイルインプットに設定
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    videoInput.files = dataTransfer.files;
                    
                    // 表示を更新
                    document.getElementById('videoFileName').textContent = file.name;
                    
                    // 動画プレビューの表示
                    const videoPreview = document.getElementById('videoPreview');
                    const videoPreviewContainer = document.getElementById('videoPreviewContainer');
                    
                    // ファイルURLの作成
                    const fileURL = URL.createObjectURL(file);
                    videoPreview.src = fileURL;
                    videoPreviewContainer.style.display = 'block';
                } else {
                    alert('対応していないファイル形式です。MP4、MOV、AVI、WMV、WebM、OGG、MPEG、3GPなど一般的な動画形式をアップロードしてください。');
                }
            }
        }, false);
        
        // 動画編集モーダル関連
        const editModal = document.getElementById('editVideoModal');
        
        function editVideo(id, title, description, isActive, isMainVideo) {
            document.getElementById('edit_video_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_is_active').checked = isActive === 1;
            document.getElementById('edit_is_main_video').checked = isMainVideo === 1;
            
            // サムネイルプレビューをリセット
            document.getElementById('editThumbnailPreview').style.display = 'none';
            document.getElementById('edit_thumbnail').value = '';
            
            editModal.style.display = 'block';
        }
        
        function closeModal() {
            editModal.style.display = 'none';
        }
        
        // 動画再生モーダル関連
        const playModal = document.getElementById('playVideoModal');
        const videoPlayer = document.getElementById('videoPlayer');
        const playVideoTitle = document.getElementById('playVideoTitle');
        
        function playVideo(videoSrc, videoTitle) {
            console.log('Playing video:', videoSrc);
            videoPlayer.src = videoSrc;
            playVideoTitle.textContent = videoTitle;
            playModal.style.display = 'block';
            
            // 動画を読み込み
            videoPlayer.load();
            // 再生開始
            videoPlayer.play().catch(e => {
                console.error('Video playback error:', e);
                alert('動画の再生中にエラーが発生しました。別の形式で再試行してください。');
            });
        }
        
        function closePlayModal() {
            // 動画を一時停止
            videoPlayer.pause();
            // ソースをリセット
            videoPlayer.src = '';
            playModal.style.display = 'none';
        }
        
        // モーダル外クリックで閉じる
        window.addEventListener('click', function(event) {
            if (event.target === editModal) {
                closeModal();
            }
            if (event.target === playModal) {
                closePlayModal();
            }
        });
    </script>
</body>
</html> 