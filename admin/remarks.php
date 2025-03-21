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

// 備考・お願い追加/更新/削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
        
        if (empty($content)) {
            $error = "内容は必須です。";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO remarks (content, display_order) VALUES (?, ?)");
                $stmt->execute([$content, $display_order]);
                $success = "新しい備考・お願いを追加しました。";
            } catch (PDOException $e) {
                $error = "備考・お願いの追加に失敗しました。";
                if ($debug_mode) {
                    $error .= " エラー: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
        
        if (empty($content)) {
            $error = "内容は必須です。";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE remarks SET content = ?, display_order = ? WHERE id = ?");
                $stmt->execute([$content, $display_order, $id]);
                $success = "備考・お願いを更新しました。";
            } catch (PDOException $e) {
                $error = "備考・お願いの更新に失敗しました。";
                if ($debug_mode) {
                    $error .= " エラー: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM remarks WHERE id = ?");
            $stmt->execute([$id]);
            $success = "備考・お願いを削除しました。";
        } catch (PDOException $e) {
            $error = "備考・お願いの削除に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    }
}

// 備考・お願い一覧を取得
$remarks = [];
try {
    $stmt = $pdo->query("SELECT * FROM remarks ORDER BY display_order ASC");
    $remarks = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "備考・お願い情報の取得に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// 編集対象の備考・お願い情報を取得
$edit_remark = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM remarks WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_remark = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "備考・お願い情報の取得に失敗しました。";
    }
}

// 次の表示順を取得
$next_order = 1;
try {
    $stmt = $pdo->query("SELECT MAX(display_order) AS max_order FROM remarks");
    $result = $stmt->fetch();
    if ($result && $result['max_order']) {
        $next_order = (int)$result['max_order'] + 1;
    }
} catch (PDOException $e) {
    // エラー時は1をデフォルト値として使用
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>備考・お願い管理 - <?= $site_name ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .remark-item {
            position: relative;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            padding: 20px;
            margin-bottom: 20px;
            border-left: 3px solid #8BC34A;
            transition: all 0.3s ease;
        }
        
        .remark-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .remark-content {
            margin: 0 0 15px 0;
            color: #444;
            line-height: 1.6;
            font-size: 1.1rem;
        }
        
        .remark-order {
            display: inline-block;
            background-color: #f5f9f0;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            color: #566045;
            margin-right: 10px;
        }
        
        .remark-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #eee;
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .remark-info {
            font-size: 0.9rem;
            color: #666;
        }
        
        .remark-actions {
            display: flex;
            gap: 8px;
        }
        
        @media (max-width: 768px) {
            .remark-item {
                padding: 15px;
            }
            
            .remark-content {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-dashboard">
        <header class="admin-dashboard-header">
            <div class="admin-logo">
                <h1><i class="fas fa-sticky-note"></i> 備考・お願い管理</h1>
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
                    <h2><?= $edit_remark ? '備考・お願いを編集' : '新しい備考・お願いを追加' ?></h2>
                    
                    <form class="admin-form" method="post" action="">
                        <input type="hidden" name="action" value="<?= $edit_remark ? 'edit' : 'add' ?>">
                        <?php if ($edit_remark): ?>
                            <input type="hidden" name="id" value="<?= $edit_remark['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="admin-form-row">
                            <div class="admin-form-group" style="flex: 4;">
                                <label for="content">内容 <span class="required">*</span></label>
                                <textarea id="content" name="content" rows="4" required><?= $edit_remark ? htmlspecialchars($edit_remark['content']) : '' ?></textarea>
                                <small>ゲストに表示したい備考事項やお願いを入力してください</small>
                                <small>グループ情報を表示するには以下のプレースホルダーが使えます：</small>
                                <ul class="placeholder-list" style="font-size: 0.85rem; color: #666; margin-top: 5px; padding-left: 20px;">
                                    <li><code>{group_name}</code> - グループ名</li>
                                    <li><code>{group_name_case}</code> - グループ名 + 「の場合は」</li>
                                    <li><code>{arrival_time}</code> - グループの集合時間</li>
                                    <li><code>{arrival_time_minus10}</code> - グループの集合時間の10分前</li>
                                </ul>
                            </div>
                            
                            <div class="admin-form-group" style="flex: 1;">
                                <label for="display_order">表示順</label>
                                <input type="number" id="display_order" name="display_order" min="1" value="<?= $edit_remark ? $edit_remark['display_order'] : $next_order ?>">
                                <small>小さい順に表示されます</small>
                            </div>
                        </div>
                        
                        <div class="admin-form-actions">
                            <?php if ($edit_remark): ?>
                                <a href="remarks.php" class="admin-button admin-button-secondary">
                                    <i class="fas fa-times"></i> キャンセル
                                </a>
                            <?php endif; ?>
                            <button type="submit" class="admin-button">
                                <i class="fas fa-<?= $edit_remark ? 'save' : 'plus' ?>"></i> 
                                <?= $edit_remark ? '備考・お願いを更新' : '備考・お願いを追加' ?>
                            </button>
                        </div>
                    </form>
                </section>
                
                <section class="admin-section">
                    <h2>備考・お願い一覧</h2>
                    
                    <?php if (empty($remarks)): ?>
                        <p>備考・お願いが設定されていません。上のフォームから内容を追加してください。</p>
                    <?php else: ?>
                        <div class="remarks-list">
                            <?php foreach ($remarks as $remark): ?>
                                <div class="remark-item">
                                    <p class="remark-content"><?= nl2br(htmlspecialchars($remark['content'])) ?></p>
                                    
                                    <div class="remark-footer">
                                        <div class="remark-info">
                                            <span class="remark-order">表示順: <?= $remark['display_order'] ?></span>
                                            <span class="remark-date">更新: <?= date('Y/m/d H:i', strtotime($remark['updated_at'])) ?></span>
                                        </div>
                                        
                                        <div class="remark-actions">
                                            <a href="remarks.php?edit=<?= $remark['id'] ?>" class="admin-btn admin-btn-edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="post" class="inline-form" onsubmit="return confirm('この備考・お願いを削除してもよろしいですか？');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $remark['id'] ?>">
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
                
                <section class="admin-section">
                    <h2>プレビュー表示</h2>
                    
                    <div class="preview-container">
                        <div class="notes-section" style="max-width: 800px; margin: 0 auto; background-color: rgba(255, 255, 255, 0.9); border-radius: 12px; padding: 30px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);">
                            <div class="section-title" style="text-align: center; margin-bottom: 30px;">
                                <h2 style="font-family: 'M PLUS Rounded 1c', sans-serif; color: #566045; margin-bottom: 10px; font-size: 1.8rem;">備考・お願い</h2>
                                <div class="title-underline" style="width: 100px; height: 3px; background: linear-gradient(to right, #8BC34A, #b8c1a2); margin: 0 auto;"></div>
                            </div>
                            <div class="notes-container">
                                <?php if (!empty($remarks)): ?>
                                    <div style="background-color: #fff3cd; border: 1px solid #ffeeba; padding: 10px; margin-bottom: 20px; border-radius: 5px;">
                                        <p style="margin: 0; color: #856404;"><strong>プレビュー注意：</strong> プレースホルダーはここでは置換されません。実際のサイト表示時には招待グループの情報が反映されます。</p>
                                    </div>
                                    <ul class="notes-list" style="list-style: none; padding: 0; margin: 0 0 30px 0;">
                                        <?php foreach ($remarks as $remark): ?>
                                            <li class="note-item" style="display: flex; align-items: flex-start; margin-bottom: 20px; padding: 15px; background-color: #f9f9f9; border-radius: 10px; border-left: 3px solid #8BC34A; box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);">
                                                <i class="fas fa-leaf note-icon" style="color: #8BC34A; font-size: 1.2rem; margin-right: 15px; margin-top: 3px;"></i>
                                                <div class="note-content" style="flex: 1; line-height: 1.6; color: #555;"><?= nl2br(htmlspecialchars($remark['content'])) ?></div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    
                                    <div style="background-color: #e8f5e9; border: 1px solid #c8e6c9; padding: 10px; margin-bottom: 20px; border-radius: 5px;">
                                        <p style="margin: 0; color: #2e7d32;"><strong>置換例：</strong></p>
                                        <ul style="margin: 5px 0 0 0; padding-left: 20px; color: #2e7d32;">
                                            <li>「{group_name}の集合時間は{arrival_time}です。」 → 「田嶋の集合時間は10:00です。」</li>
                                            <li>「{group_name_case}受付を{arrival_time}に行います。」 → 「田嶋の場合は受付を10:00に行います。」</li>
                                            <li>「{group_name_case}{arrival_time_minus10}にお越しください。」 → 「翔家族の場合は11:01にお越しください。」</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="couple-info" style="text-align: right; margin-top: 30px; font-style: italic; color: #666; line-height: 1.6;">
                                        <p style="margin: 5px 0;">新郎: 村岡 翔</p>
                                        <p style="margin: 5px 0;">新婦: 磯野 あかね</p>
                                    </div>
                                <?php else: ?>
                                    <p style="text-align: center; color: #888; font-style: italic;">備考・お願いが設定されていません。</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
                </div>
                <?php include 'inc/footer.php'; ?>
            </div>
        </div>
    </div>
</body>
</html> 