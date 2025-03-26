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

// グループタイプを取得
$group_types = [];
try {
    $stmt = $pdo->query("SELECT * FROM group_types ORDER BY type_name");
    $group_types = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "グループタイプの取得に失敗しました。";
}

// イベント追加/更新/削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $event_time = isset($_POST['event_time']) ? trim($_POST['event_time']) : '';
        $event_name = isset($_POST['event_name']) ? trim($_POST['event_name']) : '';
        $event_description = isset($_POST['event_description']) ? trim($_POST['event_description']) : '';
        $for_group_type_id = isset($_POST['for_group_type_id']) && !empty($_POST['for_group_type_id']) ? (int)$_POST['for_group_type_id'] : null;
        
        if (empty($event_time) || empty($event_name)) {
            $error = "イベント時間とイベント名は必須です。";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO schedule (event_time, event_name, event_description, for_group_type_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$event_time, $event_name, $event_description, $for_group_type_id]);
                $success = "新しいイベントを追加しました。";
            } catch (PDOException $e) {
                $error = "イベントの追加に失敗しました。";
                if ($debug_mode) {
                    $error .= " エラー: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $event_time = isset($_POST['event_time']) ? trim($_POST['event_time']) : '';
        $event_name = isset($_POST['event_name']) ? trim($_POST['event_name']) : '';
        $event_description = isset($_POST['event_description']) ? trim($_POST['event_description']) : '';
        $for_group_type_id = isset($_POST['for_group_type_id']) && !empty($_POST['for_group_type_id']) ? (int)$_POST['for_group_type_id'] : null;
        
        if (empty($event_time) || empty($event_name)) {
            $error = "イベント時間とイベント名は必須です。";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE schedule SET event_time = ?, event_name = ?, event_description = ?, for_group_type_id = ? WHERE id = ?");
                $stmt->execute([$event_time, $event_name, $event_description, $for_group_type_id, $id]);
                $success = "イベントを更新しました。";
            } catch (PDOException $e) {
                $error = "イベントの更新に失敗しました。";
                if ($debug_mode) {
                    $error .= " エラー: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM schedule WHERE id = ?");
            $stmt->execute([$id]);
            $success = "イベントを削除しました。";
        } catch (PDOException $e) {
            $error = "イベントの削除に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    }
}

// イベント一覧を取得
$events = [];
try {
    $stmt = $pdo->query("
        SELECT s.*, gt.type_name 
        FROM schedule s
        LEFT JOIN group_types gt ON s.for_group_type_id = gt.id
        ORDER BY s.event_time
    ");
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "イベント情報の取得に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// 編集対象のイベント情報を取得
$edit_event = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM schedule WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_event = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "イベント情報の取得に失敗しました。";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>タイムスケジュール管理 - <?= $site_name ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* タイムラインの改善スタイル */
        .timeline {
            position: relative;
            margin: 40px 0;
            padding-left: 50px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 15px;
            width: 3px;
            background: linear-gradient(to bottom, var(--primary-color), #8BC34A);
            border-radius: 3px;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            padding: 20px;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .timeline-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            top: 20px;
            left: -44px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: var(--primary-color);
            border: 4px solid #fff;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.3);
            z-index: 1;
        }
        
        /* 時間が近いイベント同士を線で接続 */
        .timeline-item::after {
            content: '';
            position: absolute;
            top: 30px;
            left: -34px;
            width: 20px;
            height: 2px;
            background-color: #8BC34A;
            z-index: 0;
        }
        
        .timeline-time {
            display: inline-block;
            font-weight: 500;
            color: #043c04;
            background-color: #a5d6a7;
            padding: 5px 12px;
            border-radius: 20px;
            margin-bottom: 0;
            font-size: 1.1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .timeline-time-wrapper {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .timeline-time-label {
            font-size: 0.8rem;
            color: #555;
            background-color: #f0f0f0;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 10px;
        }
        
        .timeline-time-separator {
            position: relative;
            display: inline-block;
            padding: 0 2px;
            opacity: 0.9;
            color: #043c04;
        }
        
        .timeline-title {
            margin: 0 0 12px;
            font-size: 1.3rem;
            color: #333;
            line-height: 1.4;
        }
        
        .timeline-description {
            color: #555;
            margin-bottom: 15px;
            line-height: 1.5;
            padding: 12px 15px;
            background-color: #f9f9f9;
            border-radius: 6px;
            border-left: 3px solid #e0e0e0;
        }
        
        .timeline-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #eee;
            padding-top: 12px;
            margin-top: 12px;
        }
        
        .timeline-group {
            font-size: 0.9rem;
            color: #555;
            padding: 3px 10px;
            background-color: #f0f0f0;
            border-radius: 15px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .timeline-group i {
            color: #666;
        }
        
        .timeline-group.all-guests {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .timeline-group.all-guests i {
            color: #388e3c;
        }
        
        .timeline-group.specific-group {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .timeline-group.specific-group i {
            color: #1976d2;
        }
        
        .timeline-actions {
            display: flex;
            gap: 8px;
        }
        
        .admin-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background-color: #f5f5f5;
            color: #555;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .admin-btn:hover {
            background-color: #e0e0e0;
        }
        
        .admin-btn i {
            font-size: 1rem;
        }
        
        .admin-btn-edit {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .admin-btn-edit:hover {
            background-color: #bbdefb;
        }
        
        .admin-btn-delete {
            background-color: #ffebee;
            color: #e53935;
        }
        
        .admin-btn-delete:hover {
            background-color: #ffcdd2;
        }
        
        /* フォーム改善 */
        .admin-form {
            background-color: #fff;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .admin-form-group {
            margin-bottom: 20px;
        }
        
        .admin-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .admin-form input[type="time"],
        .admin-form input[type="text"],
        .admin-form select,
        .admin-form textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .admin-form input[type="time"]:focus,
        .admin-form input[type="text"]:focus,
        .admin-form select:focus,
        .admin-form textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
            outline: none;
        }
        
        .admin-form textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.5;
        }
        
        .admin-form small {
            display: block;
            margin-top: 5px;
            font-size: 0.85rem;
            color: #666;
        }
        
        .admin-form .required {
            color: #e53935;
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .admin-form-row {
                flex-direction: column;
            }
            
            .timeline {
                padding-left: 40px;
            }
            
            .timeline-item::before {
                left: -34px;
            }
            
            .timeline-item::after {
                left: -24px;
            }
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-dashboard">
        <header class="admin-dashboard-header">
            <div class="admin-logo">
                <h1><i class="fas fa-calendar-day"></i> タイムスケジュール管理</h1>
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
                    <h2><?= $edit_event ? 'イベントを編集' : '新しいイベントを追加' ?></h2>
                    
                    <form class="admin-form" method="post" action="">
                        <input type="hidden" name="action" value="<?= $edit_event ? 'edit' : 'add' ?>">
                        <?php if ($edit_event): ?>
                            <input type="hidden" name="id" value="<?= $edit_event['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="admin-form-row">
                            <div class="admin-form-group">
                                <label for="event_time">時間 <span class="required">*</span></label>
                                <input type="time" id="event_time" name="event_time" required value="<?= $edit_event ? $edit_event['event_time'] : '' ?>">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="event_name">イベント名 <span class="required">*</span></label>
                                <input type="text" id="event_name" name="event_name" required value="<?= $edit_event ? htmlspecialchars($edit_event['event_name']) : '' ?>">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="for_group_type_id">対象グループ</label>
                                <select id="for_group_type_id" name="for_group_type_id">
                                    <option value="">すべて</option>
                                    <?php foreach ($group_types as $type): ?>
                                        <option value="<?= $type['id'] ?>" <?= $edit_event && $edit_event['for_group_type_id'] == $type['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type['type_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small>特定のグループのみが見るイベント</small>
                            </div>
                        </div>
                        
                        <div class="admin-form-group">
                            <label for="event_description">説明</label>
                            <textarea id="event_description" name="event_description" rows="3"><?= $edit_event ? htmlspecialchars($edit_event['event_description']) : '' ?></textarea>
                        </div>
                        
                        <div class="admin-form-actions">
                            <?php if ($edit_event): ?>
                                <a href="schedule.php" class="admin-button admin-button-secondary">
                                    <i class="fas fa-times"></i> キャンセル
                                </a>
                            <?php endif; ?>
                            <button type="submit" class="admin-button">
                                <i class="fas fa-<?= $edit_event ? 'save' : 'plus' ?>"></i> 
                                <?= $edit_event ? 'イベントを更新' : 'イベントを追加' ?>
                            </button>
                        </div>
                    </form>
                </section>
                
                <section class="admin-section">
                    <h2>タイムスケジュール</h2>
                    
                    <?php if (empty($events)): ?>
                        <p>イベントが設定されていません。上のフォームからイベントを追加してください。</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($events as $event): ?>
                                <div class="timeline-item">
                                    <div class="timeline-time-wrapper">
                                        <div class="timeline-time">
                                            <?php 
                                            $time = strtotime($event['event_time']);
                                            $hour = date('H', $time);
                                            $minute = date('i', $time);
                                            $ampm = ($hour < 12) ? '午前' : '午後';
                                            $hour12 = ($hour > 12) ? $hour - 12 : $hour;
                                            $hour12 = ($hour12 == 0) ? 12 : $hour12; // 0時は12時として表示
                                            echo $hour . '<span class="timeline-time-separator">:</span>' . $minute;
                                            ?>
                                        </div>
                                        <div class="timeline-time-label"><?= $ampm ?></div>
                                    </div>
                                    <h3 class="timeline-title"><?= htmlspecialchars($event['event_name']) ?></h3>
                                    
                                    <?php if (!empty($event['event_description'])): ?>
                                        <div class="timeline-description">
                                            <?= nl2br(htmlspecialchars($event['event_description'])) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="timeline-footer">
                                        <?php if (!empty($event['for_group_type_id'])): ?>
                                            <div class="timeline-group specific-group">
                                                <i class="fas fa-users"></i> <?= htmlspecialchars($event['type_name'] ?? $event['for_group_type_id']) ?>のみ
                                            </div>
                                        <?php else: ?>
                                            <div class="timeline-group all-guests">
                                                <i class="fas fa-globe"></i> すべてのゲスト
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="timeline-actions">
                                            <a href="schedule.php?edit=<?= $event['id'] ?>" class="admin-btn admin-btn-edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="post" class="inline-form" onsubmit="return confirm('このイベントを削除してもよろしいですか？');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $event['id'] ?>">
                                                <button type="submit" class="admin-btn admin-btn-delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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
</body>
</html> 