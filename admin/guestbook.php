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

// メッセージ承認・削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
    
    if ($message_id > 0) {
        try {
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE guestbook SET approved = 1 WHERE id = :id");
                $stmt->execute(['id' => $message_id]);
                $_SESSION['admin_message'] = "メッセージを承認しました。";
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM guestbook WHERE id = :id");
                $stmt->execute(['id' => $message_id]);
                $_SESSION['admin_message'] = "メッセージを削除しました。";
            }
        } catch (PDOException $e) {
            $_SESSION['admin_error'] = "操作に失敗しました: " . $e->getMessage();
        }
    }
    
    // 同じページにリダイレクト（リロード防止）
    header("Location: guestbook.php");
    exit;
}

// guestbookテーブルの存在を確認し、なければ作成
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS guestbook (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        approved TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    $_SESSION['admin_error'] = "テーブル確認中にエラーが発生しました: " . $e->getMessage();
}

// メッセージを取得
$pending_messages = [];
$approved_messages = [];

try {
    // 未承認メッセージ
    $stmt = $pdo->prepare("SELECT * FROM guestbook WHERE approved = 0 ORDER BY created_at DESC");
    $stmt->execute();
    $pending_messages = $stmt->fetchAll();
    
    // 承認済みメッセージ
    $stmt = $pdo->prepare("SELECT * FROM guestbook WHERE approved = 1 ORDER BY created_at DESC");
    $stmt->execute();
    $approved_messages = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['admin_error'] = "メッセージ取得中にエラーが発生しました: " . $e->getMessage();
}

// 管理メッセージ
$admin_message = isset($_SESSION['admin_message']) ? $_SESSION['admin_message'] : '';
$admin_error = isset($_SESSION['admin_error']) ? $_SESSION['admin_error'] : '';
unset($_SESSION['admin_message'], $_SESSION['admin_error']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ゲストブック管理 - 管理者ページ</title>
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
                <h1><i class="fas fa-book"></i> ゲストブック管理</h1>
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
                
                <?php if ($admin_message): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($admin_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($admin_error): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($admin_error); ?>
                    </div>
                <?php endif; ?>
                
                <section class="admin-section">
                    <h2><i class="fas fa-clock"></i> 承認待ちメッセージ (<?php echo count($pending_messages); ?>)</h2>
                    
                    <?php if (empty($pending_messages)): ?>
                        <p class="no-data">現在、承認待ちのメッセージはありません。</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>名前</th>
                                        <th>メール</th>
                                        <th>メッセージ</th>
                                        <th>投稿日時</th>
                                        <th>アクション</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_messages as $message): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($message['id']); ?></td>
                                            <td><?php echo htmlspecialchars($message['name']); ?></td>
                                            <td><?php echo htmlspecialchars($message['email']); ?></td>
                                            <td class="message-cell"><?php echo nl2br(htmlspecialchars($message['message'])); ?></td>
                                            <td><?php echo date('Y/m/d H:i', strtotime($message['created_at'])); ?></td>
                                            <td class="actions-cell">
                                                <form method="post" class="inline-form">
                                                    <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-approve" title="承認"><i class="fas fa-check"></i></button>
                                                </form>
                                                <form method="post" class="inline-form" onsubmit="return confirm('このメッセージを削除してもよろしいですか？');">
                                                    <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-delete" title="削除"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
                
                <section class="admin-section">
                    <h2><i class="fas fa-check-circle"></i> 承認済みメッセージ (<?php echo count($approved_messages); ?>)</h2>
                    
                    <?php if (empty($approved_messages)): ?>
                        <p class="no-data">現在、承認済みのメッセージはありません。</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>名前</th>
                                        <th>メール</th>
                                        <th>メッセージ</th>
                                        <th>投稿日時</th>
                                        <th>アクション</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approved_messages as $message): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($message['id']); ?></td>
                                            <td><?php echo htmlspecialchars($message['name']); ?></td>
                                            <td><?php echo htmlspecialchars($message['email']); ?></td>
                                            <td class="message-cell"><?php echo nl2br(htmlspecialchars($message['message'])); ?></td>
                                            <td><?php echo date('Y/m/d H:i', strtotime($message['created_at'])); ?></td>
                                            <td class="actions-cell">
                                                <form method="post" class="inline-form" onsubmit="return confirm('このメッセージを削除してもよろしいですか？');">
                                                    <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-delete" title="削除"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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