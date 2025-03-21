<?php
// 設定ファイルを読み込み
require_once 'config.php';

// URLからステータスメッセージとグループIDを取得
$status = isset($_GET['status']) ? $_GET['status'] : '';
$message = isset($_GET['message']) ? $_GET['message'] : '';
$group_id = isset($_GET['group']) ? $_GET['group'] : '';

// グループ情報を初期化
$group_name = '親愛なるゲスト様';

// グループIDが存在する場合、データベースからゲスト情報を取得
if ($group_id) {
    try {
        $stmt = $pdo->prepare("SELECT group_name FROM guests WHERE group_id = :group_id LIMIT 1");
        $stmt->execute(['group_id' => $group_id]);
        $guest_data = $stmt->fetch();
        
        if ($guest_data) {
            $group_name = $guest_data['group_name'];
        }
    } catch (PDOException $e) {
        if ($debug_mode) {
            echo "データベースエラー: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ゲストブック - 翔 & あかね - Wedding 2025.4.30</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="js/main.js" defer></script>
    <style>
    .field-note {
        display: block;
        font-size: 0.8rem;
        color: #777;
        margin-top: 3px;
        font-style: italic;
    }
    </style>
</head>
<body class="guestbook-page">
    <div class="guestbook-wrapper">
        <header class="guestbook-header">
            <div class="header-inner">
                <h1 class="title">ゲストブック</h1>
                <div class="title-decoration">
                    <span class="decoration-line"></span>
                    <i class="fas fa-leaf"></i>
                    <i class="fas fa-heart"></i>
                    <i class="fas fa-leaf"></i>
                    <span class="decoration-line"></span>
                </div>
                <p class="subtitle">翔 & あかね - Wedding 2025.4.30</p>
                <a href="index.php<?= $group_id ? '?group=' . urlencode($group_id) : '' ?>" class="back-to-invitation"><i class="fas fa-arrow-left"></i> 招待状に戻る</a>
            </div>
        </header>

        <div class="container">
            <!-- ステータスメッセージがあれば表示 -->
            <?php if ($status && $message): ?>
            <div class="message-container <?php echo $status === 'success' ? 'success' : 'error'; ?>">
                <p><?php echo htmlspecialchars(urldecode($message)); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- ゲストブックフォーム -->
            <section class="section guestbook-section">
                <div class="section-inner">
                    <div class="section-description">
                        <p>翔さん＆あかねさんへのメッセージを残してください。<br>公開前に確認させていただきます。</p>
                    </div>
                    
                    <form class="guestbook-form" action="process_guestbook.php" method="post">
                        <div class="form-group">
                            <label for="guestbook-name">お名前<span class="required">*</span></label>
                            <input type="text" id="guestbook-name" name="name" required>
                            <small class="field-note">※ニックネームでも構いません</small>
                        </div>
                        <div class="form-group">
                            <label for="guestbook-group">ご招待者名（非公開）</label>
                            <input type="text" id="guestbook-group" name="group_name" value="<?= htmlspecialchars($group_name) ?>">
                            <small class="field-note">※招待状に記載された名義です。公開されません</small>
                        </div>
                        <div class="form-group">
                            <label for="guestbook-email">メールアドレス（非公開）<span class="required">*</span></label>
                            <input type="email" id="guestbook-email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="guestbook-message">メッセージ<span class="required">*</span></label>
                            <textarea id="guestbook-message" name="message" rows="6" required></textarea>
                        </div>
                        <input type="hidden" name="group_id" value="<?= htmlspecialchars($group_id) ?>">
                        <div class="form-actions">
                            <button type="submit" class="btn primary-btn"><i class="fas fa-paper-plane"></i> メッセージを送信</button>
                        </div>
                    </form>
                </div>
            </section>

            <!-- ゲストブックメッセージ一覧 -->
            <section class="section guestbook-messages-section">
                <h2>みなさまからのメッセージ</h2>
                <div class="guestbook-messages">
                    <?php
                    // ゲストブックメッセージの表示
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM guestbook WHERE approved = 1 ORDER BY created_at DESC");
                        $stmt->execute();
                        $messages = $stmt->fetchAll();
                        
                        if (count($messages) > 0) {
                            foreach ($messages as $message) {
                                echo '<div class="guestbook-message">';
                                echo '<p class="message-content">' . nl2br(htmlspecialchars($message['message'])) . '</p>';
                                echo '<div class="message-meta">';
                                echo '<p class="message-author">- ' . htmlspecialchars($message['name']) . ' 様</p>';
                                echo '<p class="message-date">' . date('Y年m月d日', strtotime($message['created_at'])) . '</p>';
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<p class="no-messages">まだメッセージはありません。最初のメッセージを投稿してみませんか？</p>';
                        }
                    } catch (PDOException $e) {
                        if ($debug_mode) {
                            echo "データベースエラー: " . $e->getMessage();
                        } else {
                            echo '<p class="error-message">メッセージの読み込み中にエラーが発生しました。</p>';
                        }
                    }
                    ?>
                </div>
            </section>
        </div>

        <footer class="guestbook-footer">
            <a href="index.php<?= $group_id ? '?group=' . urlencode($group_id) : '' ?>" class="back-to-invitation"><i class="fas fa-arrow-left"></i> 招待状に戻る</a>
            <div class="footer-decoration">
                <div class="leaf-decoration left"></div>
                <div class="heart-container">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="leaf-decoration right"></div>
            </div>
            <p>&copy; 2023 翔 & あかね - Our Wedding</p>
            <p class="domain">sopranohaction.fun</p>
        </footer>
    </div>
</body>
</html> 