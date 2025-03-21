<?php
// セッションを開始
session_start();

// 設定ファイルを読み込み
require_once '../config.php';

// 認証チェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// メッセージ用の変数
$messages = [];

// 処理実行
if (isset($_POST['fix_layertext'])) {
    try {
        // 「層書き」を「肩書」に変更
        $stmt = $pdo->prepare("UPDATE seat_assignments SET layer_text = '肩書' WHERE layer_text = '層書き'");
        $stmt->execute();
        
        $affected = $stmt->rowCount();
        $messages[] = [
            'type' => 'success',
            'text' => "{$affected}件の「層書き」を「肩書」に修正しました。"
        ];
    } catch (PDOException $e) {
        $messages[] = [
            'type' => 'danger',
            'text' => "エラーが発生しました: " . ($debug_mode ? $e->getMessage() : "データベース接続エラー")
        ];
    }
}

// 「層書き」が含まれるレコード数を取得
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM seat_assignments WHERE layer_text = '層書き'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $layertext_count = $result['count'];
} catch (PDOException $e) {
    $messages[] = [
        'type' => 'danger',
        'text' => "情報取得エラー: " . ($debug_mode ? $e->getMessage() : "データベース接続エラー")
    ];
    $layertext_count = "取得不可";
}

// HTMLヘッダー
include 'includes/header.php';
?>

<div class="container-fluid admin-container">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="admin-main">
        <div class="admin-content">
            <h1 class="admin-heading">データベース修正ツール</h1>
            
            <?php foreach ($messages as $message): ?>
                <div class="alert alert-<?php echo $message['type']; ?>" role="alert">
                    <?php echo $message['text']; ?>
                </div>
            <?php endforeach; ?>
            
            <div class="card admin-card mb-4">
                <div class="card-header">
                    <h2 class="card-title">「層書き」を「肩書」に修正</h2>
                </div>
                <div class="card-body">
                    <p>現在のデータベースには「層書き」と表記されている肩書きが <strong><?php echo $layertext_count; ?></strong> 件あります。</p>
                    
                    <?php if ($layertext_count > 0): ?>
                        <form method="post" action="">
                            <p>クリックすると、すべての「層書き」を「肩書」に修正します。</p>
                            <button type="submit" name="fix_layertext" class="btn btn-primary">修正を実行</button>
                        </form>
                    <?php else: ?>
                        <p class="text-success">修正が必要なデータはありません。</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card admin-card mb-4">
                <div class="card-header">
                    <h2 class="card-title">その他のデータベース修正</h2>
                </div>
                <div class="card-body">
                    <p>現時点では、他の修正オプションは提供されていません。</p>
                    <a href="seating.php" class="btn btn-secondary">席次表管理に戻る</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// HTMLフッター
include 'includes/footer.php';
?> 