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

// ギフト追加/更新/削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $guest_id = isset($_POST['guest_id']) ? (int)$_POST['guest_id'] : null;
        $gift_type = isset($_POST['gift_type']) ? trim($_POST['gift_type']) : '';
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $received_date = isset($_POST['received_date']) ? trim($_POST['received_date']) : date('Y-m-d');
        $thank_you_sent = isset($_POST['thank_you_sent']) ? 1 : 0;
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        
        if (empty($gift_type)) {
            $error = "ギフトの種類を選択してください。";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO gifts 
                    (guest_id, gift_type, amount, description, received_date, thank_you_sent, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$guest_id, $gift_type, $amount, $description, $received_date, $thank_you_sent, $notes]);
                $success = "ギフト情報を追加しました。";
            } catch (PDOException $e) {
                $error = "ギフト情報の追加に失敗しました。";
                if ($debug_mode) {
                    $error .= " エラー: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $guest_id = isset($_POST['guest_id']) ? (int)$_POST['guest_id'] : null;
        $gift_type = isset($_POST['gift_type']) ? trim($_POST['gift_type']) : '';
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $received_date = isset($_POST['received_date']) ? trim($_POST['received_date']) : date('Y-m-d');
        $thank_you_sent = isset($_POST['thank_you_sent']) ? 1 : 0;
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        
        if (empty($gift_type)) {
            $error = "ギフトの種類を選択してください。";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE gifts 
                    SET guest_id = ?, gift_type = ?, amount = ?, description = ?, 
                        received_date = ?, thank_you_sent = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $guest_id, $gift_type, $amount, $description, 
                    $received_date, $thank_you_sent, $notes, $id
                ]);
                $success = "ギフト情報を更新しました。";
            } catch (PDOException $e) {
                $error = "ギフト情報の更新に失敗しました。";
                if ($debug_mode) {
                    $error .= " エラー: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM gifts WHERE id = ?");
            $stmt->execute([$id]);
            $success = "ギフト情報を削除しました。";
        } catch (PDOException $e) {
            $error = "ギフト情報の削除に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'update_thank_you' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $thank_you_sent = isset($_POST['thank_you_sent']) ? 1 : 0;
        
        try {
            $stmt = $pdo->prepare("UPDATE gifts SET thank_you_sent = ? WHERE id = ?");
            $stmt->execute([$thank_you_sent, $id]);
            $success = "お礼状送信状況を更新しました。";
        } catch (PDOException $e) {
            $error = "更新に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    }
}

// ゲストグループ一覧を取得
$guests = [];
try {
    $stmt = $pdo->query("SELECT id, group_name FROM guests ORDER BY group_name");
    $guests = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "ゲスト情報の取得に失敗しました。";
}

// ギフト情報一覧を取得
$gifts = [];
try {
    $stmt = $pdo->query("
        SELECT g.*, gu.group_name
        FROM gifts g
        LEFT JOIN guests gu ON g.guest_id = gu.id
        ORDER BY g.received_date DESC
    ");
    $gifts = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "ギフト情報の取得に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// 編集対象のギフト情報を取得
$edit_gift = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM gifts WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_gift = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "ギフト情報の取得に失敗しました。";
    }
}

// 統計情報
$stats = [
    'total_amount' => 0,
    'cash_count' => 0,
    'present_count' => 0,
    'other_count' => 0,
    'thank_you_sent' => 0,
    'thank_you_pending' => 0,
];

foreach ($gifts as $gift) {
    if ($gift['gift_type'] === '現金') {
        $stats['total_amount'] += $gift['amount'];
        $stats['cash_count']++;
    } elseif ($gift['gift_type'] === 'プレゼント') {
        $stats['present_count']++;
    } else {
        $stats['other_count']++;
    }
    
    if ($gift['thank_you_sent']) {
        $stats['thank_you_sent']++;
    } else {
        $stats['thank_you_pending']++;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ギフト管理 - <?= $site_name ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background-color: #fff;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            margin-top: 0;
            font-size: 1rem;
            color: #666;
        }
        .stat-card p {
            font-size: 1.5rem;
            font-weight: bold;
            margin: 10px 0 0;
            color: var(--primary-color);
        }
        .thank-you-toggle {
            cursor: pointer;
        }
        .gift-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-right: 5px;
        }
        .gift-tag.cash {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        .gift-tag.present {
            background-color: #f8bbd0;
            color: #c2185b;
        }
        .gift-tag.other {
            background-color: #e0e0e0;
            color: #616161;
        }
        #amountField {
            display: none;
        }
        .amount-field.show {
            display: block !important;
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-dashboard">
        <header class="admin-dashboard-header">
            <div class="admin-logo">
                <h1><i class="fas fa-gift"></i> ギフト管理</h1>
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
                    <h2>ギフト統計</h2>
                    
                    <div class="stats-cards">
                        <div class="stat-card">
                            <h3>総ご祝儀金額</h3>
                            <p><?= number_format($stats['total_amount']) ?> 円</p>
                        </div>
                        <div class="stat-card">
                            <h3>現金ギフト</h3>
                            <p><?= $stats['cash_count'] ?> 件</p>
                        </div>
                        <div class="stat-card">
                            <h3>プレゼント</h3>
                            <p><?= $stats['present_count'] ?> 件</p>
                        </div>
                        <div class="stat-card">
                            <h3>その他ギフト</h3>
                            <p><?= $stats['other_count'] ?> 件</p>
                        </div>
                        <div class="stat-card">
                            <h3>お礼状送信済み</h3>
                            <p><?= $stats['thank_you_sent'] ?> / <?= count($gifts) ?></p>
                        </div>
                    </div>
                </section>
                
                <section class="admin-section">
                    <h2><?= $edit_gift ? 'ギフト情報を編集' : '新しいギフト情報を追加' ?></h2>
                    
                    <form class="admin-form" method="post" action="">
                        <input type="hidden" name="action" value="<?= $edit_gift ? 'edit' : 'add' ?>">
                        <?php if ($edit_gift): ?>
                            <input type="hidden" name="id" value="<?= $edit_gift['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="admin-form-row">
                            <div class="admin-form-group">
                                <label for="guest_id">贈り主</label>
                                <select id="guest_id" name="guest_id">
                                    <option value="">-- 選択してください --</option>
                                    <?php foreach ($guests as $guest): ?>
                                        <option value="<?= $guest['id'] ?>" <?= $edit_gift && $edit_gift['guest_id'] == $guest['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($guest['group_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="gift_type">ギフトの種類 <span class="required">*</span></label>
                                <select id="gift_type" name="gift_type" required onchange="toggleAmountField()">
                                    <option value="">-- 選択してください --</option>
                                    <option value="現金" <?= $edit_gift && $edit_gift['gift_type'] === '現金' ? 'selected' : '' ?>>現金 (ご祝儀)</option>
                                    <option value="プレゼント" <?= $edit_gift && $edit_gift['gift_type'] === 'プレゼント' ? 'selected' : '' ?>>プレゼント</option>
                                    <option value="その他" <?= $edit_gift && $edit_gift['gift_type'] === 'その他' ? 'selected' : '' ?>>その他</option>
                                </select>
                            </div>
                            
                            <div id="amountField" class="admin-form-group amount-field <?= $edit_gift && $edit_gift['gift_type'] === '現金' ? 'show' : '' ?>">
                                <label for="amount">金額</label>
                                <input type="number" id="amount" name="amount" min="0" step="1000" value="<?= $edit_gift ? $edit_gift['amount'] : '0' ?>">
                                <small>円</small>
                            </div>
                        </div>
                        
                        <div class="admin-form-row">
                            <div class="admin-form-group">
                                <label for="received_date">受け取り日</label>
                                <input type="date" id="received_date" name="received_date" value="<?= $edit_gift ? $edit_gift['received_date'] : date('Y-m-d') ?>">
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="thank_you_sent" class="checkbox-label">
                                    <input type="checkbox" id="thank_you_sent" name="thank_you_sent" <?= $edit_gift && $edit_gift['thank_you_sent'] ? 'checked' : '' ?>>
                                    お礼状送信済
                                </label>
                            </div>
                        </div>
                        
                        <div class="admin-form-group">
                            <label for="description">ギフトの説明</label>
                            <textarea id="description" name="description" rows="2"><?= $edit_gift ? htmlspecialchars($edit_gift['description']) : '' ?></textarea>
                        </div>
                        
                        <div class="admin-form-group">
                            <label for="notes">備考</label>
                            <textarea id="notes" name="notes" rows="2"><?= $edit_gift ? htmlspecialchars($edit_gift['notes']) : '' ?></textarea>
                        </div>
                        
                        <div class="admin-form-actions">
                            <?php if ($edit_gift): ?>
                                <a href="gifts.php" class="admin-button admin-button-secondary">
                                    <i class="fas fa-times"></i> キャンセル
                                </a>
                            <?php endif; ?>
                            <button type="submit" class="admin-button">
                                <i class="fas fa-<?= $edit_gift ? 'save' : 'plus' ?>"></i> 
                                <?= $edit_gift ? 'ギフト情報を更新' : 'ギフト情報を追加' ?>
                            </button>
                        </div>
                    </form>
                </section>
                
                <section class="admin-section">
                    <h2>ギフト一覧</h2>
                    
                    <?php if (empty($gifts)): ?>
                        <p>ギフト情報がありません。上のフォームからギフト情報を追加してください。</p>
                    <?php else: ?>
                        <div class="admin-table-container">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>贈り主</th>
                                        <th>種類</th>
                                        <th>内容</th>
                                        <th>金額</th>
                                        <th>受け取り日</th>
                                        <th>お礼状</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gifts as $gift): ?>
                                        <tr>
                                            <td><?= $gift['id'] ?></td>
                                            <td><?= htmlspecialchars($gift['group_name'] ?? '未指定') ?></td>
                                            <td>
                                                <?php if ($gift['gift_type'] === '現金'): ?>
                                                    <span class="gift-tag cash">現金</span>
                                                <?php elseif ($gift['gift_type'] === 'プレゼント'): ?>
                                                    <span class="gift-tag present">プレゼント</span>
                                                <?php else: ?>
                                                    <span class="gift-tag other">その他</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($gift['description'] ?? '') ?></td>
                                            <td>
                                                <?php if ($gift['gift_type'] === '現金'): ?>
                                                    <?= number_format($gift['amount']) ?> 円
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('Y/m/d', strtotime($gift['received_date'])) ?></td>
                                            <td>
                                                <form method="post" class="inline-form">
                                                    <input type="hidden" name="action" value="update_thank_you">
                                                    <input type="hidden" name="id" value="<?= $gift['id'] ?>">
                                                    <input type="hidden" name="thank_you_sent" value="<?= $gift['thank_you_sent'] ? '0' : '1' ?>">
                                                    <button type="submit" class="thank-you-toggle admin-btn <?= $gift['thank_you_sent'] ? 'admin-btn-success' : 'admin-btn-secondary' ?>">
                                                        <i class="fas fa-<?= $gift['thank_you_sent'] ? 'check' : 'times' ?>"></i>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <a href="gifts.php?edit=<?= $gift['id'] ?>" class="admin-btn admin-btn-edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="post" class="inline-form" onsubmit="return confirm('このギフト情報を削除してもよろしいですか？');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $gift['id'] ?>">
                                                    <button type="submit" class="admin-btn admin-btn-delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
    
    <script>
    function toggleAmountField() {
        const giftType = document.getElementById('gift_type').value;
        const amountField = document.getElementById('amountField');
        
        if (giftType === '現金') {
            amountField.classList.add('show');
        } else {
            amountField.classList.remove('show');
        }
    }
    
    // ページ読み込み時に実行
    document.addEventListener('DOMContentLoaded', function() {
        toggleAmountField();
    });
    </script>
</body>
</html> 