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

// グループタイプの追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $type_name = isset($_POST['type_name']) ? trim($_POST['type_name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        
        if (empty($type_name)) {
            $error = "グループタイプ名は必須です。";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO group_types (type_name, description) VALUES (?, ?)");
                $stmt->execute([$type_name, $description]);
                $success = "新しいグループタイプを追加しました。";
            } catch (PDOException $e) {
                $error = "グループタイプの追加に失敗しました。";
                if ($debug_mode) {
                    $error .= " エラー: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        try {
            // このグループタイプを使用しているゲストがいるか確認
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM guests WHERE group_type_id = ?");
            $check_stmt->execute([$id]);
            $count = $check_stmt->fetchColumn();
            
            if ($count > 0) {
                $error = "このグループタイプは {$count} 件のゲストグループで使用されているため削除できません。";
            } else {
                $stmt = $pdo->prepare("DELETE FROM group_types WHERE id = ?");
                $stmt->execute([$id]);
                $success = "グループタイプを削除しました。";
            }
        } catch (PDOException $e) {
            $error = "グループタイプの削除に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    }
}

// グループタイプのリストを取得
$group_types = [];
try {
    $stmt = $pdo->query("SELECT * FROM group_types ORDER BY type_name");
    $group_types = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "グループタイプの取得に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>グループタイプ管理 - <?= $site_name ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-body">
    <div class="admin-dashboard">
        <header class="admin-dashboard-header">
            <div class="admin-logo">
                <h1><i class="fas fa-tags"></i> グループタイプ管理</h1>
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
                    <h2>グループタイプ一覧</h2>
                    <div class="admin-table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>タイプ名</th>
                                    <th>説明</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($group_types)): ?>
                                <tr>
                                    <td colspan="4">グループタイプがありません。</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($group_types as $type): ?>
                                    <tr>
                                        <td><?= $type['id'] ?></td>
                                        <td><?= htmlspecialchars($type['type_name']) ?></td>
                                        <td><?= htmlspecialchars($type['description'] ?? '') ?></td>
                                        <td>
                                            <form method="post" class="inline-form" onsubmit="return confirm('このグループタイプを削除してもよろしいですか？');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $type['id'] ?>">
                                                <button type="submit" class="admin-btn admin-btn-delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                
                <section class="admin-section">
                    <h2>新しいグループタイプを追加</h2>
                    <form class="admin-form" method="post" action="">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="admin-form-group">
                            <label for="type_name">タイプ名 <span class="required">*</span></label>
                            <input type="text" id="type_name" name="type_name" required>
                        </div>
                        
                        <div class="admin-form-group">
                            <label for="description">説明</label>
                            <textarea id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="admin-form-actions">
                            <button type="submit" class="admin-button">
                                <i class="fas fa-plus"></i> グループタイプを追加
                            </button>
                        </div>
                    </form>
                </section>
                
                </div>
                <?php include 'inc/footer.php'; ?>
            </div>
        </div>
    </div>
</body>
</html> 