<?php
/**
 * チェックイン案内設定ページ
 * 
 * 管理者がチェックイン後の案内情報を設定するためのページです。
 * - 席次情報の一括登録
 * - 会場マップのアップロード
 * - イベント案内の管理
 * - スケジュールの管理
 */

// セッションを開始
session_start();

// 設定ファイルの読み込み
require_once '../config.php';
require_once '../includes/qr_helper.php';

// 認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// 初期化
$success = '';
$error = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'seating';

// 席次情報の一括登録処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_seating'])) {
    try {
        // 既存の席次情報を削除するかどうか
        $clear_existing = isset($_POST['clear_existing']) && $_POST['clear_existing'] === '1';
        
        if ($clear_existing) {
            $pdo->exec("TRUNCATE TABLE seating_guidance");
            $success = "既存の席次情報を削除しました。";
        }
        
        // CSVアップロード処理
        if (isset($_FILES['seating_csv']) && $_FILES['seating_csv']['error'] === UPLOAD_ERR_OK) {
            $csv_file = $_FILES['seating_csv']['tmp_name'];
            $handle = fopen($csv_file, 'r');
            
            if ($handle !== false) {
                // データベーストランザクション開始
                $pdo->beginTransaction();
                
                // ヘッダー行をスキップ
                $header = fgetcsv($handle);
                
                // CSVデータを処理
                $count = 0;
                $insert_stmt = $pdo->prepare("
                    INSERT INTO seating_guidance 
                    (guest_id, table_id, seat_number, custom_message) 
                    VALUES (?, ?, ?, ?)
                ");
                
                while (($data = fgetcsv($handle)) !== false) {
                    // CSV形式: ゲストID, グループ名, テーブルID, 座席番号, カスタムメッセージ
                    $guest_id = $data[0] ?? null;
                    $table_id = $data[2] ?? null;
                    $seat_number = $data[3] ?? null;
                    $custom_message = $data[4] ?? null;
                    
                    if ($guest_id && $table_id && $seat_number) {
                        $insert_stmt->execute([$guest_id, $table_id, $seat_number, $custom_message]);
                        $count++;
                    }
                }
                
                fclose($handle);
                $pdo->commit();
                
                $success .= " $count 件の席次情報を登録しました。";
            }
        }
        
        // 個別登録処理
        if (isset($_POST['guest_id']) && isset($_POST['table_id']) && isset($_POST['seat_number'])) {
            $guest_id = (int)$_POST['guest_id'];
            $table_id = (int)$_POST['table_id'];
            $seat_number = (int)$_POST['seat_number'];
            $custom_message = $_POST['custom_message'] ?? '';
            
            // 既存のデータがあるか確認
            $check = $pdo->prepare("SELECT id FROM seating_guidance WHERE guest_id = ?");
            $check->execute([$guest_id]);
            
            if ($check->fetch()) {
                // 更新
                $update = $pdo->prepare("
                    UPDATE seating_guidance 
                    SET table_id = ?, seat_number = ?, custom_message = ? 
                    WHERE guest_id = ?
                ");
                $update->execute([$table_id, $seat_number, $custom_message, $guest_id]);
            } else {
                // 新規登録
                $insert = $pdo->prepare("
                    INSERT INTO seating_guidance 
                    (guest_id, table_id, seat_number, custom_message) 
                    VALUES (?, ?, ?, ?)
                ");
                $insert->execute([$guest_id, $table_id, $seat_number, $custom_message]);
            }
            
            $success .= " ゲストID: $guest_id の席次情報を更新しました。";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "席次情報の更新中にエラーが発生しました: " . $e->getMessage();
    }
}

// イベント案内の追加・更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notice'])) {
    try {
        $notice_id = isset($_POST['notice_id']) ? (int)$_POST['notice_id'] : 0;
        $title = $_POST['notice_title'] ?? '';
        $content = $_POST['notice_content'] ?? '';
        $priority = (int)$_POST['notice_priority'] ?? 0;
        $active = isset($_POST['notice_active']) ? 1 : 0;
        
        if ($notice_id > 0) {
            // 更新
            $stmt = $pdo->prepare("
                UPDATE event_notices 
                SET title = ?, content = ?, priority = ?, active = ? 
                WHERE id = ?
            ");
            $stmt->execute([$title, $content, $priority, $active, $notice_id]);
            $success = "お知らせを更新しました。";
        } else {
            // 新規登録
            $stmt = $pdo->prepare("
                INSERT INTO event_notices 
                (title, content, priority, active) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$title, $content, $priority, $active]);
            $success = "新しいお知らせを追加しました。";
        }
    } catch (PDOException $e) {
        $error = "お知らせの更新中にエラーが発生しました: " . $e->getMessage();
    }
}

// イベント案内の削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notice'])) {
    try {
        $notice_id = (int)$_POST['notice_id'];
        $stmt = $pdo->prepare("DELETE FROM event_notices WHERE id = ?");
        $stmt->execute([$notice_id]);
        $success = "お知らせを削除しました。";
    } catch (PDOException $e) {
        $error = "お知らせの削除中にエラーが発生しました: " . $e->getMessage();
    }
}

// 会場マップのアップロード処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_map'])) {
    try {
        $name = $_POST['map_name'] ?? '';
        $description = $_POST['map_description'] ?? '';
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        // 画像アップロード処理
        $image_path = '';
        if (isset($_FILES['map_image']) && $_FILES['map_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/maps/';
            
            // ディレクトリが存在しない場合は作成
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = time() . '_' . basename($_FILES['map_image']['name']);
            $upload_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['map_image']['tmp_name'], $upload_file)) {
                $image_path = 'uploads/maps/' . $filename;
            } else {
                throw new Exception("ファイルのアップロードに失敗しました。");
            }
        }
        
        // デフォルト設定の場合、他のマップのデフォルト設定をオフに
        if ($is_default) {
            $pdo->exec("UPDATE venue_maps SET is_default = 0");
        }
        
        // データベースに登録
        $stmt = $pdo->prepare("
            INSERT INTO venue_maps 
            (name, description, image_path, is_default) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$name, $description, $image_path, $is_default]);
        
        $success = "会場マップを登録しました。";
    } catch (Exception $e) {
        $error = "会場マップの登録中にエラーが発生しました: " . $e->getMessage();
    }
}

// スケジュールの追加・更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedule'])) {
    try {
        $schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
        $event_name = $_POST['event_name'] ?? '';
        $event_time = $_POST['event_time'] ?? '';
        $description = $_POST['event_description'] ?? '';
        $location = $_POST['event_location'] ?? '';
        
        if ($schedule_id > 0) {
            // 更新
            $stmt = $pdo->prepare("
                UPDATE schedule 
                SET event_name = ?, event_time = ?, event_description = ?, location = ? 
                WHERE id = ?
            ");
            $stmt->execute([$event_name, $event_time, $description, $location, $schedule_id]);
            $success = "スケジュールを更新しました。";
        } else {
            // 新規登録
            $stmt = $pdo->prepare("
                INSERT INTO schedule 
                (event_name, event_time, event_description, location) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$event_name, $event_time, $description, $location]);
            $success = "新しいスケジュールを追加しました。";
        }
    } catch (PDOException $e) {
        $error = "スケジュールの更新中にエラーが発生しました: " . $e->getMessage();
    }
}

// スケジュールの削除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_schedule'])) {
    try {
        $schedule_id = (int)$_POST['schedule_id'];
        $stmt = $pdo->prepare("DELETE FROM schedule WHERE id = ?");
        $stmt->execute([$schedule_id]);
        $success = "スケジュールを削除しました。";
    } catch (PDOException $e) {
        $error = "スケジュールの削除中にエラーが発生しました: " . $e->getMessage();
    }
}

// データ取得：席次テーブル一覧
$seating_tables = [];
try {
    $stmt = $pdo->query("SELECT id, table_name, capacity FROM seating_tables ORDER BY table_name");
    $seating_tables = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "テーブル情報の取得に失敗しました: " . $e->getMessage();
}

// データ取得：ゲスト一覧
$guests = [];
try {
    $stmt = $pdo->query("SELECT id, group_id, group_name FROM guests ORDER BY group_name");
    $guests = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "ゲスト情報の取得に失敗しました: " . $e->getMessage();
}

// データ取得：席次情報
$seating_guidance_list = [];
try {
    $stmt = $pdo->query("
        SELECT sg.*, g.group_name, g.group_id, st.table_name 
        FROM seating_guidance sg
        JOIN guests g ON sg.guest_id = g.id
        JOIN seating_tables st ON sg.table_id = st.id
        ORDER BY g.group_name
    ");
    $seating_guidance_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "席次案内情報の取得に失敗しました: " . $e->getMessage();
}

// データ取得：イベント案内一覧
$event_notices_list = [];
try {
    $stmt = $pdo->query("SELECT * FROM event_notices ORDER BY priority DESC, created_at DESC");
    $event_notices_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "イベント案内情報の取得に失敗しました: " . $e->getMessage();
}

// データ取得：会場マップ一覧
$venue_maps_list = [];
try {
    $stmt = $pdo->query("SELECT * FROM venue_maps ORDER BY is_default DESC, created_at DESC");
    $venue_maps_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "会場マップ情報の取得に失敗しました: " . $e->getMessage();
}

// データ取得：スケジュール一覧
$schedule_list = [];
try {
    $stmt = $pdo->query("SELECT * FROM schedule ORDER BY event_time ASC");
    $schedule_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "スケジュール情報の取得に失敗しました: " . $e->getMessage();
}

// ページタイトル
$page_title = 'チェックイン案内設定';
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
        .tab-container {
            margin-top: 20px;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .tab {
            padding: 10px 15px;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: 4px 4px 0 0;
            margin-right: 5px;
        }
        
        .tab.active {
            background-color: #f5f5f5;
            border-color: #ddd;
            border-bottom-color: transparent;
        }
        
        .tab-content {
            display: none;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-top: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        select, input[type="text"], input[type="number"], input[type="datetime-local"], textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        button {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        button:hover {
            background-color: #45a049;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        table th, table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        table th {
            background-color: #f5f5f5;
        }
        
        .map-preview {
            max-width: 200px;
            max-height: 150px;
            margin-top: 10px;
        }
        
        .notice-card {
            background-color: #f9f9f9;
            border-left: 4px solid #4CAF50;
            padding: 10px;
            margin-bottom: 15px;
        }
        
        .schedule-card {
            background-color: #f9f9f9;
            border-left: 4px solid #2196F3;
            padding: 10px;
            margin-bottom: 15px;
        }
        
        .actions {
            text-align: right;
        }
        
        .text-success {
            color: #4CAF50;
        }
        
        .text-danger {
            color: #F44336;
        }
        
        .active-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            color: white;
            background-color: #4CAF50;
        }
        
        .inactive-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            color: white;
            background-color: #9e9e9e;
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-dashboard">
        <?php include 'inc/header.php'; ?>
        
        <div class="admin-dashboard-content">
            <?php include 'inc/sidebar.php'; ?>
            
            <div class="admin-main">
                <div class="admin-content-wrapper">
                    <h1><i class="fas fa-info-circle"></i> <?= $page_title ?></h1>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <div class="tab-container">
                        <div class="tabs">
                            <div class="tab <?= $active_tab === 'seating' ? 'active' : '' ?>" data-tab="seating">
                                <i class="fas fa-chair"></i> 席次案内
                            </div>
                            <div class="tab <?= $active_tab === 'notices' ? 'active' : '' ?>" data-tab="notices">
                                <i class="fas fa-bullhorn"></i> お知らせ
                            </div>
                            <div class="tab <?= $active_tab === 'maps' ? 'active' : '' ?>" data-tab="maps">
                                <i class="fas fa-map"></i> 会場マップ
                            </div>
                            <div class="tab <?= $active_tab === 'schedule' ? 'active' : '' ?>" data-tab="schedule">
                                <i class="fas fa-calendar-alt"></i> スケジュール
                            </div>
                        </div>
                        
                        <!-- 席次案内タブ -->
                        <div class="tab-content <?= $active_tab === 'seating' ? 'active' : '' ?>" id="seating-tab">
                            <h2>席次案内設定</h2>
                            
                            <h3>個別登録</h3>
                            <form method="post">
                                <div class="form-group">
                                    <label for="guest_id">ゲスト選択:</label>
                                    <select name="guest_id" id="guest_id" required>
                                        <option value="">-- ゲストを選択 --</option>
                                        <?php foreach ($guests as $guest): ?>
                                            <option value="<?= $guest['id'] ?>">
                                                <?= htmlspecialchars($guest['group_name']) ?> (<?= htmlspecialchars($guest['group_id']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="table_id">テーブル選択:</label>
                                    <select name="table_id" id="table_id" required>
                                        <option value="">-- テーブルを選択 --</option>
                                        <?php foreach ($seating_tables as $table): ?>
                                            <option value="<?= $table['id'] ?>">
                                                <?= htmlspecialchars($table['table_name']) ?> (定員: <?= $table['capacity'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="seat_number">座席番号:</label>
                                    <input type="number" name="seat_number" id="seat_number" min="1" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="custom_message">カスタムメッセージ (任意):</label>
                                    <textarea name="custom_message" id="custom_message" rows="3"></textarea>
                                </div>
                                
                                <button type="submit" name="update_seating">登録</button>
                            </form>
                            
                            <h3>CSV一括登録</h3>
                            <form method="post" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="seating_csv">CSVファイル:</label>
                                    <input type="file" name="seating_csv" id="seating_csv" accept=".csv">
                                    <small>CSVフォーマット: ゲストID,グループ名,テーブルID,座席番号,カスタムメッセージ</small>
                                </div>
                                
                                <div class="form-check">
                                    <input type="checkbox" name="clear_existing" id="clear_existing" value="1">
                                    <label for="clear_existing">既存の席次情報をクリアする</label>
                                </div>
                                
                                <button type="submit" name="update_seating">CSVをアップロード</button>
                            </form>
                            
                            <h3>現在の席次情報</h3>
                            <?php if (empty($seating_guidance_list)): ?>
                                <p>席次情報はまだ登録されていません。</p>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>グループ名</th>
                                            <th>グループID</th>
                                            <th>テーブル名</th>
                                            <th>座席番号</th>
                                            <th>カスタムメッセージ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($seating_guidance_list as $guidance): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($guidance['group_name']) ?></td>
                                                <td><?= htmlspecialchars($guidance['group_id']) ?></td>
                                                <td><?= htmlspecialchars($guidance['table_name']) ?></td>
                                                <td><?= $guidance['seat_number'] ?></td>
                                                <td><?= htmlspecialchars($guidance['custom_message'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        
                        <!-- お知らせタブ -->
                        <div class="tab-content <?= $active_tab === 'notices' ? 'active' : '' ?>" id="notices-tab">
                            <h2>イベントお知らせ設定</h2>
                            
                            <h3>新規お知らせ追加</h3>
                            <form method="post">
                                <input type="hidden" name="notice_id" value="0">
                                
                                <div class="form-group">
                                    <label for="notice_title">タイトル:</label>
                                    <input type="text" name="notice_title" id="notice_title" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="notice_content">内容:</label>
                                    <textarea name="notice_content" id="notice_content" rows="4" required></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="notice_priority">優先度:</label>
                                    <input type="number" name="notice_priority" id="notice_priority" value="0" min="0">
                                    <small>数値が大きいほど優先度が高く表示されます。</small>
                                </div>
                                
                                <div class="form-check">
                                    <input type="checkbox" name="notice_active" id="notice_active" checked>
                                    <label for="notice_active">有効にする</label>
                                </div>
                                
                                <button type="submit" name="update_notice">お知らせを追加</button>
                            </form>
                            
                            <h3>現在のお知らせ一覧</h3>
                            <?php if (empty($event_notices_list)): ?>
                                <p>お知らせは登録されていません。</p>
                            <?php else: ?>
                                <?php foreach ($event_notices_list as $notice): ?>
                                    <div class="notice-card">
                                        <h4>
                                            <?= htmlspecialchars($notice['title']) ?>
                                            <?php if ($notice['active']): ?>
                                                <span class="active-badge">有効</span>
                                            <?php else: ?>
                                                <span class="inactive-badge">無効</span>
                                            <?php endif; ?>
                                        </h4>
                                        <p><?= nl2br(htmlspecialchars($notice['content'])) ?></p>
                                        <p><small>優先度: <?= $notice['priority'] ?></small></p>
                                        <div class="actions">
                                            <form method="post" style="display: inline-block;">
                                                <input type="hidden" name="notice_id" value="<?= $notice['id'] ?>">
                                                <button type="submit" name="delete_notice" class="btn-danger" 
                                                        onclick="return confirm('このお知らせを削除しますか？');">
                                                    <i class="fas fa-trash"></i> 削除
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 会場マップタブ -->
                        <div class="tab-content <?= $active_tab === 'maps' ? 'active' : '' ?>" id="maps-tab">
                            <h2>会場マップ設定</h2>
                            
                            <h3>新規マップ追加</h3>
                            <form method="post" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="map_name">マップ名:</label>
                                    <input type="text" name="map_name" id="map_name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="map_description">説明:</label>
                                    <textarea name="map_description" id="map_description" rows="3"></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="map_image">マップ画像:</label>
                                    <input type="file" name="map_image" id="map_image" accept="image/*">
                                </div>
                                
                                <div class="form-check">
                                    <input type="checkbox" name="is_default" id="is_default">
                                    <label for="is_default">デフォルトマップに設定</label>
                                </div>
                                
                                <button type="submit" name="upload_map">マップを登録</button>
                            </form>
                            
                            <h3>登録済みマップ</h3>
                            <?php if (empty($venue_maps_list)): ?>
                                <p>マップは登録されていません。</p>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($venue_maps_list as $map): ?>
                                        <div class="col-md-4">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h5 class="card-title">
                                                        <?= htmlspecialchars($map['name']) ?>
                                                        <?php if ($map['is_default']): ?>
                                                            <span class="badge bg-primary">デフォルト</span>
                                                        <?php endif; ?>
                                                    </h5>
                                                    <?php if (!empty($map['image_path'])): ?>
                                                        <img src="../<?= htmlspecialchars($map['image_path']) ?>" alt="会場マップ" class="map-preview">
                                                    <?php else: ?>
                                                        <p class="text-muted">画像はありません</p>
                                                    <?php endif; ?>
                                                    <p><?= nl2br(htmlspecialchars($map['description'])) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- スケジュールタブ -->
                        <div class="tab-content <?= $active_tab === 'schedule' ? 'active' : '' ?>" id="schedule-tab">
                            <h2>スケジュール設定</h2>
                            
                            <h3>新規スケジュール追加</h3>
                            <form method="post">
                                <input type="hidden" name="schedule_id" value="0">
                                
                                <div class="form-group">
                                    <label for="event_name">イベント名:</label>
                                    <input type="text" name="event_name" id="event_name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="event_time">開始時間:</label>
                                    <input type="datetime-local" name="event_time" id="event_time" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="event_description">説明:</label>
                                    <textarea name="event_description" id="event_description" rows="3"></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="event_location">場所:</label>
                                    <input type="text" name="event_location" id="event_location">
                                </div>
                                
                                <button type="submit" name="update_schedule">スケジュールを追加</button>
                            </form>
                            
                            <h3>現在のスケジュール一覧</h3>
                            <?php if (empty($schedule_list)): ?>
                                <p>スケジュールは登録されていません。</p>
                            <?php else: ?>
                                <?php foreach ($schedule_list as $event): ?>
                                    <div class="schedule-card">
                                        <h4><?= htmlspecialchars($event['event_name']) ?></h4>
                                        <p><strong>時間:</strong> <?= date('Y年m月d日 H:i', strtotime($event['event_time'])) ?></p>
                                        <?php if (!empty($event['event_description'])): ?>
                                            <p><?= nl2br(htmlspecialchars($event['event_description'])) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($event['location'])): ?>
                                            <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($event['location']) ?></p>
                                        <?php endif; ?>
                                        <div class="actions">
                                            <form method="post" style="display: inline-block;">
                                                <input type="hidden" name="schedule_id" value="<?= $event['id'] ?>">
                                                <button type="submit" name="delete_schedule" class="btn-danger" 
                                                        onclick="return confirm('このスケジュールを削除しますか？');">
                                                    <i class="fas fa-trash"></i> 削除
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // タブ切り替え
            $('.tab').click(function() {
                var tab = $(this).data('tab');
                
                $('.tab').removeClass('active');
                $(this).addClass('active');
                
                $('.tab-content').removeClass('active');
                $('#' + tab + '-tab').addClass('active');
                
                // URLのクエリパラメータを更新
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tab);
                window.history.replaceState({}, '', url.toString());
            });
        });
    </script>
</body>
</html> 