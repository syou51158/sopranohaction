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

// メッセージ変数
$message = null;
$message_type = null;

// 新規付箋タイプの追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_fusen_type') {
    $type_code = isset($_POST['type_code']) ? trim($_POST['type_code']) : '';
    $type_name = isset($_POST['type_name']) ? trim($_POST['type_name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $default_message = isset($_POST['default_message']) ? trim($_POST['default_message']) : '';
    $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
    
    if (empty($type_code) || empty($type_name)) {
        $message = '識別子と名前は必須です。';
        $message_type = 'error';
    } else {
        try {
            // 既に同じtype_codeが存在するかチェック
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM fusen_types WHERE type_code = ?");
            $check_stmt->execute([$type_code]);
            if ($check_stmt->fetchColumn() > 0) {
                $message = 'この識別子は既に使用されています。';
                $message_type = 'error';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO fusen_types (type_code, type_name, description, default_message, sort_order)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$type_code, $type_name, $description, $default_message, $sort_order]);
                
                $message = '新しい付箋タイプを追加しました。';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = '付箋タイプの追加に失敗しました。';
            if ($debug_mode) {
                $message .= ' エラー: ' . $e->getMessage();
            }
            $message_type = 'error';
        }
    }
}

// 付箋タイプの更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_fusen_type') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $type_name = isset($_POST['type_name']) ? trim($_POST['type_name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $default_message = isset($_POST['default_message']) ? trim($_POST['default_message']) : '';
    $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
    
    if ($id <= 0 || empty($type_name)) {
        $message = 'IDと名前は必須です。';
        $message_type = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE fusen_types 
                SET type_name = ?, description = ?, default_message = ?, sort_order = ?
                WHERE id = ?
            ");
            $stmt->execute([$type_name, $description, $default_message, $sort_order, $id]);
            
            $message = '付箋タイプを更新しました。';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = '付箋タイプの更新に失敗しました。';
            if ($debug_mode) {
                $message .= ' エラー: ' . $e->getMessage();
            }
            $message_type = 'error';
        }
    }
}

// 付箋タイプの削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_fusen_type') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        $message = '無効なIDです。';
        $message_type = 'error';
    } else {
        try {
            // 削除前に使用状況を確認
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM guest_fusen WHERE fusen_type_id = ?");
            $check_stmt->execute([$id]);
            if ($check_stmt->fetchColumn() > 0) {
                $message = 'この付箋タイプは現在使用中のため削除できません。';
                $message_type = 'error';
            } else {
                $stmt = $pdo->prepare("DELETE FROM fusen_types WHERE id = ?");
                $stmt->execute([$id]);
                
                $message = '付箋タイプを削除しました。';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = '付箋タイプの削除に失敗しました。';
            if ($debug_mode) {
                $message .= ' エラー: ' . $e->getMessage();
            }
            $message_type = 'error';
        }
    }
}

// 付箋タイプの一覧を取得
$fusen_types = [];
try {
    $stmt = $pdo->query("SELECT * FROM fusen_types ORDER BY sort_order, type_name");
    $fusen_types = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = '付箋タイプの取得に失敗しました。';
    if ($debug_mode) {
        $message .= ' エラー: ' . $e->getMessage();
    }
    $message_type = 'error';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>付箋設定 - <?= $site_name ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .fusen-preview {
            background-color: #fff9c4;
            border: 1px solid #ffeb3b;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            max-width: 400px;
        }
        .fusen-preview h3 {
            margin-top: 0;
            color: #d32f2f;
            font-size: 18px;
            border-bottom: 1px dashed #ffcc80;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .fusen-preview p {
            margin: 0;
            font-size: 14px;
            white-space: pre-wrap;
        }
    </style>
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
                        <h2><i class="fas fa-sticky-note"></i> 付箋設定</h2>
                        
                        <?php if ($message): ?>
                        <div class="admin-<?= $message_type ?>">
                            <p><?= $message ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="admin-tabs">
                            <ul class="admin-tabs-nav">
                                <li class="active"><a href="#fusen-types">付箋の種類</a></li>
                                <li><a href="#add-fusen-type">新規付箋タイプ追加</a></li>
                            </ul>
                            
                            <div class="admin-tabs-content">
                                <div id="fusen-types" class="admin-tab-pane active">
                                    <h3>付箋タイプ一覧</h3>
                                    
                                    <?php if (empty($fusen_types)): ?>
                                    <p>付箋タイプが登録されていません。新規付箋タイプを追加してください。</p>
                                    <?php else: ?>
                                    <div class="admin-table-container">
                                        <table class="admin-table">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>識別子</th>
                                                    <th>名前</th>
                                                    <th>説明</th>
                                                    <th>表示順</th>
                                                    <th>操作</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($fusen_types as $type): ?>
                                                <tr>
                                                    <td><?= $type['id'] ?></td>
                                                    <td><?= htmlspecialchars($type['type_code']) ?></td>
                                                    <td><?= htmlspecialchars($type['type_name']) ?></td>
                                                    <td><?= htmlspecialchars(mb_substr($type['description'], 0, 30)) ?><?= mb_strlen($type['description']) > 30 ? '...' : '' ?></td>
                                                    <td><?= $type['sort_order'] ?></td>
                                                    <td>
                                                        <button class="admin-btn admin-btn-edit" onclick="editFusenType(<?= $type['id'] ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="admin-btn admin-btn-delete" onclick="deleteFusenType(<?= $type['id'] ?>, '<?= htmlspecialchars($type['type_name']) ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- 編集モーダル -->
                                    <div id="edit-modal" class="admin-modal">
                                        <div class="admin-modal-content">
                                            <span class="admin-modal-close">&times;</span>
                                            <h3>付箋タイプを編集</h3>
                                            
                                            <form class="admin-form" method="post" action="">
                                                <input type="hidden" name="action" value="update_fusen_type">
                                                <input type="hidden" id="edit-id" name="id" value="">
                                                
                                                <div class="admin-form-row">
                                                    <div class="admin-form-group">
                                                        <label for="edit-type-code">識別子</label>
                                                        <input type="text" id="edit-type-code" readonly>
                                                        <small>識別子は変更できません</small>
                                                    </div>
                                                    
                                                    <div class="admin-form-group">
                                                        <label for="edit-type-name">名前 <span class="required">*</span></label>
                                                        <input type="text" id="edit-type-name" name="type_name" required>
                                                    </div>
                                                </div>
                                                
                                                <div class="admin-form-group">
                                                    <label for="edit-description">説明</label>
                                                    <textarea id="edit-description" name="description" rows="2"></textarea>
                                                </div>
                                                
                                                <div class="admin-form-group">
                                                    <label for="edit-default-message">デフォルトメッセージ</label>
                                                    <textarea id="edit-default-message" name="default_message" rows="4"></textarea>
                                                </div>
                                                
                                                <div class="admin-form-group">
                                                    <label for="edit-sort-order">表示順</label>
                                                    <input type="number" id="edit-sort-order" name="sort_order" min="0">
                                                </div>
                                                
                                                <div class="fusen-preview">
                                                    <h3 id="preview-title">付箋タイトル</h3>
                                                    <p id="preview-message">付箋のメッセージ内容がここに表示されます。</p>
                                                </div>
                                                
                                                <div class="admin-form-actions">
                                                    <button type="submit" class="admin-button">
                                                        <i class="fas fa-save"></i> 変更を保存
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <!-- 削除確認モーダル -->
                                    <div id="delete-modal" class="admin-modal">
                                        <div class="admin-modal-content">
                                            <span class="admin-modal-close">&times;</span>
                                            <h3>付箋タイプを削除</h3>
                                            
                                            <p>「<span id="delete-name"></span>」を削除してもよろしいですか？</p>
                                            <p class="admin-warning">この操作は元に戻せません。</p>
                                            
                                            <form class="admin-form" method="post" action="">
                                                <input type="hidden" name="action" value="delete_fusen_type">
                                                <input type="hidden" id="delete-id" name="id" value="">
                                                
                                                <div class="admin-form-actions">
                                                    <button type="button" class="admin-button admin-button-secondary admin-modal-close">
                                                        <i class="fas fa-times"></i> キャンセル
                                                    </button>
                                                    <button type="submit" class="admin-button admin-button-danger">
                                                        <i class="fas fa-trash"></i> 削除する
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="add-fusen-type" class="admin-tab-pane">
                                    <h3>新規付箋タイプ追加</h3>
                                    
                                    <form class="admin-form" method="post" action="">
                                        <input type="hidden" name="action" value="add_fusen_type">
                                        
                                        <div class="admin-form-row">
                                            <div class="admin-form-group">
                                                <label for="type-code">識別子 <span class="required">*</span></label>
                                                <input type="text" id="type-code" name="type_code" required pattern="[a-z0-9_]+" title="半角英数字とアンダースコアのみ使用可能です">
                                                <small>英数字(小文字)と_(アンダースコア)のみ使用可能です</small>
                                            </div>
                                            
                                            <div class="admin-form-group">
                                                <label for="type-name">名前 <span class="required">*</span></label>
                                                <input type="text" id="type-name" name="type_name" required>
                                                <small>例: 挙式付箋、乾杯付箋など</small>
                                            </div>
                                        </div>
                                        
                                        <div class="admin-form-group">
                                            <label for="description">説明</label>
                                            <textarea id="description" name="description" rows="2"></textarea>
                                            <small>この付箋の用途や目的について説明してください</small>
                                        </div>
                                        
                                        <div class="admin-form-group">
                                            <label for="default-message">デフォルトメッセージ</label>
                                            <textarea id="default-message" name="default_message" rows="4"></textarea>
                                            <small>付箋に表示するデフォルトメッセージを入力してください</small>
                                        </div>
                                        
                                        <div class="admin-form-group">
                                            <label for="sort-order">表示順</label>
                                            <input type="number" id="sort-order" name="sort_order" min="0" value="0">
                                            <small>数字が小さいほど先に表示されます</small>
                                        </div>
                                        
                                        <div class="fusen-preview">
                                            <h3 id="new-preview-title">付箋タイトル</h3>
                                            <p id="new-preview-message">付箋のメッセージ内容がここに表示されます。</p>
                                        </div>
                                        
                                        <div class="admin-form-actions">
                                            <button type="submit" class="admin-button">
                                                <i class="fas fa-plus"></i> 付箋タイプを追加
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
                
                <?php include 'inc/footer.php'; ?>
            </div>
        </div>
    </div>
    
    <script>
    // タブ切り替え
    document.addEventListener('DOMContentLoaded', function() {
        const tabLinks = document.querySelectorAll('.admin-tabs-nav a');
        
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // タブナビゲーションの切り替え
                document.querySelectorAll('.admin-tabs-nav li').forEach(item => {
                    item.classList.remove('active');
                });
                this.parentElement.classList.add('active');
                
                // タブコンテンツの切り替え
                const targetId = this.getAttribute('href').substring(1);
                document.querySelectorAll('.admin-tab-pane').forEach(pane => {
                    pane.classList.remove('active');
                });
                document.getElementById(targetId).classList.add('active');
            });
        });
        
        // 新規作成フォームのプレビュー更新
        const typeName = document.getElementById('type-name');
        const defaultMessage = document.getElementById('default-message');
        const previewTitle = document.getElementById('new-preview-title');
        const previewMessage = document.getElementById('new-preview-message');
        
        typeName.addEventListener('input', function() {
            previewTitle.textContent = this.value || '付箋タイトル';
        });
        
        defaultMessage.addEventListener('input', function() {
            previewMessage.textContent = this.value || '付箋のメッセージ内容がここに表示されます。';
        });
        
        // 編集フォームのプレビュー更新
        const editTypeName = document.getElementById('edit-type-name');
        const editDefaultMessage = document.getElementById('edit-default-message');
        const editPreviewTitle = document.getElementById('preview-title');
        const editPreviewMessage = document.getElementById('preview-message');
        
        if (editTypeName) {
            editTypeName.addEventListener('input', function() {
                editPreviewTitle.textContent = this.value || '付箋タイトル';
            });
        }
        
        if (editDefaultMessage) {
            editDefaultMessage.addEventListener('input', function() {
                editPreviewMessage.textContent = this.value || '付箋のメッセージ内容がここに表示されます。';
            });
        }
        
        // モーダルの閉じるボタン
        document.querySelectorAll('.admin-modal-close').forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.admin-modal').style.display = 'none';
            });
        });
        
        // モーダル外クリックで閉じる
        window.addEventListener('click', function(e) {
            document.querySelectorAll('.admin-modal').forEach(modal => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    });
    
    // 編集モーダルを開く
    function editFusenType(id) {
        // データを取得
        const types = <?= json_encode($fusen_types) ?>;
        const type = types.find(t => t.id == id);
        
        if (type) {
            // フォームに値をセット
            document.getElementById('edit-id').value = type.id;
            document.getElementById('edit-type-code').value = type.type_code;
            document.getElementById('edit-type-name').value = type.type_name;
            document.getElementById('edit-description').value = type.description;
            document.getElementById('edit-default-message').value = type.default_message;
            document.getElementById('edit-sort-order').value = type.sort_order;
            
            // プレビューを更新
            document.getElementById('preview-title').textContent = type.type_name;
            document.getElementById('preview-message').textContent = type.default_message || '付箋のメッセージ内容がここに表示されます。';
            
            // モーダルを表示
            document.getElementById('edit-modal').style.display = 'block';
        }
    }
    
    // 削除モーダルを開く
    function deleteFusenType(id, name) {
        document.getElementById('delete-id').value = id;
        document.getElementById('delete-name').textContent = name;
        document.getElementById('delete-modal').style.display = 'block';
    }
    </script>
</body>
</html> 