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

// エラーと成功メッセージの初期化
$error = '';
$success = '';

// ユーザーアクション処理（有効化/無効化/削除）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['user_id'])) {
    $action = $_POST['action'];
    $user_id = (int)$_POST['user_id'];
    
    // 自分自身に対する操作は禁止
    if ($user_id === (int)$_SESSION['admin_id']) {
        $error = '自分自身のアカウントに対してこの操作はできません。';
    } else {
        try {
            switch ($action) {
                case 'activate':
                    $stmt = $pdo->prepare("UPDATE admin_users SET is_active = 1 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success = 'ユーザーを有効化しました。';
                    break;
                    
                case 'deactivate':
                    $stmt = $pdo->prepare("UPDATE admin_users SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success = 'ユーザーを無効化しました。';
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success = 'ユーザーを削除しました。';
                    break;
                    
                default:
                    $error = '無効なアクションです。';
            }
        } catch (PDOException $e) {
            $error = 'ユーザー操作中にエラーが発生しました。';
            if ($debug_mode) {
                $error .= ' エラー: ' . $e->getMessage();
            }
        }
    }
}

// 管理者ユーザー一覧を取得
$users = [];
try {
    $stmt = $pdo->query("SELECT * FROM admin_users ORDER BY username");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'ユーザー情報の取得に失敗しました。';
    if ($debug_mode) {
        $error .= ' エラー: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー管理 - <?= $site_name ?></title>
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
                        <h2><i class="fas fa-users-cog"></i> ユーザー管理</h2>
                        
                        <?php if ($error): ?>
                        <div class="admin-error">
                            <?= $error ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="admin-success">
                            <?= $success ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="admin-info-box">
                            <p><i class="fas fa-info-circle"></i> <strong>ユーザー管理について</strong></p>
                            <ul>
                                <li>管理者ユーザーの一覧を表示しています</li>
                                <li>ユーザーの有効化/無効化や削除が可能です</li>
                                <li>自分自身のアカウントに対する操作はできません</li>
                            </ul>
                        </div>
                        
                        <div class="admin-table-container">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>ユーザー名</th>
                                        <th>メールアドレス</th>
                                        <th>ステータス</th>
                                        <th>登録日時</th>
                                        <th>最終ログイン</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="7">ユーザーが見つかりません。</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?= $user['id'] ?></td>
                                            <td><?= htmlspecialchars($user['username']) ?></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td>
                                                <?php if ($user['is_active']): ?>
                                                <span class="status-active">有効</span>
                                                <?php else: ?>
                                                <span class="status-inactive">無効</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('Y/m/d H:i', strtotime($user['created_at'])) ?></td>
                                            <td><?= $user['last_login'] ? date('Y/m/d H:i', strtotime($user['last_login'])) : '未ログイン' ?></td>
                                            <td>
                                                <?php if ((int)$user['id'] !== (int)$_SESSION['admin_id']): ?>
                                                    <?php if ($user['is_active']): ?>
                                                    <form method="post" action="" style="display: inline;">
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="admin-btn admin-btn-warning" onclick="return confirm('このユーザーを無効化しますか？');">
                                                            <i class="fas fa-user-slash"></i>
                                                        </button>
                                                    </form>
                                                    <?php else: ?>
                                                    <form method="post" action="" style="display: inline;">
                                                        <input type="hidden" name="action" value="activate">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="admin-btn admin-btn-success">
                                                            <i class="fas fa-user-check"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                    
                                                    <form method="post" action="" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="admin-btn admin-btn-delete" onclick="return confirm('このユーザーを削除しますか？この操作は元に戻せません。');">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="admin-btn-disabled"><i class="fas fa-user-shield"></i> 現在のユーザー</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="admin-actions">
                            <a href="register.php" class="admin-button">
                                <i class="fas fa-user-plus"></i> 新規ユーザー登録
                            </a>
                        </div>
                    </section>
                </div>
                
                <?php include 'inc/footer.php'; ?>
            </div>
        </div>
    </div>
</body>
</html> 