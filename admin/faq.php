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

// FAQ追加/更新/削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $question = isset($_POST['question']) ? trim($_POST['question']) : '';
        $answer = isset($_POST['answer']) ? trim($_POST['answer']) : '';
        $category = isset($_POST['category']) ? trim($_POST['category']) : '';
        $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;
        
        if (empty($question) || empty($answer)) {
            $error = "質問と回答は必須です。";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO faq 
                    (question, answer, category, display_order, is_visible) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$question, $answer, $category, $display_order, $is_visible]);
                $success = "FAQを追加しました。";
            } catch (PDOException $e) {
                $error = "FAQの追加に失敗しました。";
                if ($debug_mode) {
                    $error .= " エラー: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $question = isset($_POST['question']) ? trim($_POST['question']) : '';
        $answer = isset($_POST['answer']) ? trim($_POST['answer']) : '';
        $category = isset($_POST['category']) ? trim($_POST['category']) : '';
        $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;
        
        if (empty($question) || empty($answer)) {
            $error = "質問と回答は必須です。";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE faq 
                    SET question = ?, answer = ?, category = ?, 
                        display_order = ?, is_visible = ?
                    WHERE id = ?
                ");
                $stmt->execute([$question, $answer, $category, $display_order, $is_visible, $id]);
                $success = "FAQを更新しました。";
            } catch (PDOException $e) {
                $error = "FAQの更新に失敗しました。";
                if ($debug_mode) {
                    $error .= " エラー: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM faq WHERE id = ?");
            $stmt->execute([$id]);
            $success = "FAQを削除しました。";
        } catch (PDOException $e) {
            $error = "FAQの削除に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'toggle_visibility' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $is_visible = isset($_POST['is_visible']) ? (int)$_POST['is_visible'] : 0;
        $new_visibility = $is_visible ? 0 : 1;
        
        try {
            $stmt = $pdo->prepare("UPDATE faq SET is_visible = ? WHERE id = ?");
            $stmt->execute([$new_visibility, $id]);
            $success = "FAQの表示設定を変更しました。";
        } catch (PDOException $e) {
            $error = "設定の変更に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    }
}

// FAQ一覧を取得
$faqs = [];
try {
    $stmt = $pdo->query("SELECT * FROM faq ORDER BY display_order, id");
    $faqs = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "FAQ情報の取得に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// カテゴリの一覧を取得
$categories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM faq WHERE category != '' ORDER BY category");
    $category_results = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $categories = array_filter($category_results); // 空文字列を除外
} catch (PDOException $e) {
    // エラー処理は省略
}

// 編集対象のFAQを取得
$edit_faq = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM faq WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_faq = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "FAQ情報の取得に失敗しました。";
    }
}

// 次の表示順序を取得
$next_display_order = 1;
if (!empty($faqs)) {
    $max_order = max(array_column($faqs, 'display_order'));
    $next_display_order = $max_order + 1;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ管理 - <?= $site_name ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .faq-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-left: 4px solid transparent;
        }
        
        .faq-card.visible {
            border-left-color: var(--primary-color);
        }
        
        .faq-card.hidden {
            border-left-color: #f44336;
            background-color: #fff8f8;
            opacity: 0.7;
        }
        
        .faq-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .faq-title {
            flex-grow: 1;
            margin: 0;
            font-size: 1.1rem;
            line-height: 1.4;
        }
        
        .faq-actions {
            flex-shrink: 0;
            display: flex;
            gap: 5px;
        }
        
        .faq-meta {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 0.85rem;
            color: #666;
        }
        
        .faq-category {
            background-color: #f0f0f0;
            padding: 2px 8px;
            border-radius: 10px;
        }
        
        .faq-order {
            opacity: 0.7;
        }
        
        .faq-answer {
            color: #444;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        
        .category-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .category-tag {
            padding: 5px 12px;
            background-color: #f0f0f0;
            border-radius: 20px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .category-tag:hover, .category-tag.active {
            background-color: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-dashboard">
        <header class="admin-dashboard-header">
            <div class="admin-logo">
                <h1><i class="fas fa-question-circle"></i> FAQ管理</h1>
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
                    <h2><?= $edit_faq ? 'FAQを編集' : '新しいFAQを追加' ?></h2>
                    
                    <form class="admin-form" method="post" action="">
                        <input type="hidden" name="action" value="<?= $edit_faq ? 'edit' : 'add' ?>">
                        <?php if ($edit_faq): ?>
                            <input type="hidden" name="id" value="<?= $edit_faq['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="admin-form-group">
                            <label for="question">質問 <span class="required">*</span></label>
                            <textarea id="question" name="question" rows="2" required><?= $edit_faq ? htmlspecialchars($edit_faq['question']) : '' ?></textarea>
                        </div>
                        
                        <div class="admin-form-group">
                            <label for="answer">回答 <span class="required">*</span></label>
                            <textarea id="answer" name="answer" rows="4" required><?= $edit_faq ? htmlspecialchars($edit_faq['answer']) : '' ?></textarea>
                        </div>
                        
                        <div class="admin-form-row">
                            <div class="admin-form-group">
                                <label for="category">カテゴリ</label>
                                <input type="text" id="category" name="category" list="category-list" value="<?= $edit_faq ? htmlspecialchars($edit_faq['category']) : '' ?>">
                                <datalist id="category-list">
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= htmlspecialchars($category) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <small>既存のカテゴリから選択するか、新しいカテゴリを入力してください</small>
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="display_order">表示順序</label>
                                <input type="number" id="display_order" name="display_order" min="1" value="<?= $edit_faq ? $edit_faq['display_order'] : $next_display_order ?>">
                                <small>数字が小さいほど上に表示されます</small>
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="is_visible" class="checkbox-label">
                                    <input type="checkbox" id="is_visible" name="is_visible" <?= (!$edit_faq || $edit_faq['is_visible']) ? 'checked' : '' ?>>
                                    公開する
                                </label>
                                <small>チェックを外すと非表示になります</small>
                            </div>
                        </div>
                        
                        <div class="admin-form-actions">
                            <?php if ($edit_faq): ?>
                                <a href="faq.php" class="admin-button admin-button-secondary">
                                    <i class="fas fa-times"></i> キャンセル
                                </a>
                            <?php endif; ?>
                            <button type="submit" class="admin-button">
                                <i class="fas fa-<?= $edit_faq ? 'save' : 'plus' ?>"></i> 
                                <?= $edit_faq ? 'FAQを更新' : 'FAQを追加' ?>
                            </button>
                        </div>
                    </form>
                </section>
                
                <section class="admin-section">
                    <h2>FAQ一覧</h2>
                    
                    <?php if (!empty($categories)): ?>
                        <div class="category-filter">
                            <div class="category-tag active" data-category="all">すべて</div>
                            <?php foreach ($categories as $category): ?>
                                <div class="category-tag" data-category="<?= htmlspecialchars($category) ?>">
                                    <?= htmlspecialchars($category) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($faqs)): ?>
                        <p>FAQがありません。上のフォームからFAQを追加してください。</p>
                    <?php else: ?>
                        <div class="faq-list">
                            <?php foreach ($faqs as $faq): ?>
                                <div class="faq-card <?= $faq['is_visible'] ? 'visible' : 'hidden' ?>" data-category="<?= htmlspecialchars($faq['category']) ?>">
                                    <div class="faq-header">
                                        <h3 class="faq-title">
                                            <i class="fas fa-question-circle"></i> 
                                            <?= htmlspecialchars($faq['question']) ?>
                                        </h3>
                                        <div class="faq-actions">
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="action" value="toggle_visibility">
                                                <input type="hidden" name="id" value="<?= $faq['id'] ?>">
                                                <input type="hidden" name="is_visible" value="<?= $faq['is_visible'] ?>">
                                                <button type="submit" class="admin-btn <?= $faq['is_visible'] ? 'admin-btn-success' : 'admin-btn-secondary' ?>" title="<?= $faq['is_visible'] ? '非公開にする' : '公開する' ?>">
                                                    <i class="fas fa-<?= $faq['is_visible'] ? 'eye' : 'eye-slash' ?>"></i>
                                                </button>
                                            </form>
                                            <a href="faq.php?edit=<?= $faq['id'] ?>" class="admin-btn admin-btn-edit" title="編集">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="post" class="inline-form" onsubmit="return confirm('このFAQを削除してもよろしいですか？');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $faq['id'] ?>">
                                                <button type="submit" class="admin-btn admin-btn-delete" title="削除">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <div class="faq-meta">
                                        <?php if (!empty($faq['category'])): ?>
                                            <div class="faq-category">
                                                <i class="fas fa-tag"></i> <?= htmlspecialchars($faq['category']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="faq-order">
                                            <i class="fas fa-sort"></i> 表示順序: <?= $faq['display_order'] ?>
                                        </div>
                                    </div>
                                    
                                    <div class="faq-answer">
                                        <i class="fas fa-comment"></i> <?= nl2br(htmlspecialchars($faq['answer'])) ?>
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
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // カテゴリフィルター機能
        const categoryTags = document.querySelectorAll('.category-tag');
        const faqCards = document.querySelectorAll('.faq-card');
        
        categoryTags.forEach(tag => {
            tag.addEventListener('click', function() {
                const category = this.getAttribute('data-category');
                
                // すべてのタグからactiveクラスを削除
                categoryTags.forEach(t => t.classList.remove('active'));
                
                // クリックされたタグにactiveクラスを追加
                this.classList.add('active');
                
                // FAQカードのフィルタリング
                faqCards.forEach(card => {
                    if (category === 'all' || card.getAttribute('data-category') === category) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    });
    </script>
</body>
</html> 