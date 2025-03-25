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

// ゲストIDを取得
$guest_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ゲスト情報を取得
$guest_info = null;
if ($guest_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM guests WHERE id = ?");
        $stmt->execute([$guest_id]);
        $guest_info = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "ゲスト情報の取得に失敗しました。";
        if ($debug_mode) {
            $error .= " エラー: " . $e->getMessage();
        }
    }
}

// ゲストが存在しない場合はリダイレクト
if (!$guest_info) {
    header('Location: dashboard.php');
    exit;
}

// 付箋タイプを取得
$fusen_types = [];
try {
    $stmt = $pdo->query("SELECT * FROM fusen_types ORDER BY sort_order, type_name");
    $fusen_types = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "付箋タイプの取得に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// ゲストに割り当てられた付箋を取得
$guest_fusens = [];
try {
    $stmt = $pdo->prepare("
        SELECT gf.*, ft.type_name, ft.type_code, ft.default_message
        FROM guest_fusen gf
        JOIN fusen_types ft ON gf.fusen_type_id = ft.id
        WHERE gf.guest_id = ?
        ORDER BY ft.sort_order, ft.type_name
    ");
    $stmt->execute([$guest_id]);
    $guest_fusens = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "ゲストの付箋情報の取得に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// 付箋の追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_fusen') {
    $fusen_type_id = isset($_POST['fusen_type_id']) ? (int)$_POST['fusen_type_id'] : 0;
    $custom_message = isset($_POST['custom_message']) ? trim($_POST['custom_message']) : '';
    
    if ($fusen_type_id <= 0) {
        $error = "付箋タイプを選択してください。";
    } else {
        try {
            // 同じタイプの付箋が既に割り当てられていないか確認
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM guest_fusen WHERE guest_id = ? AND fusen_type_id = ?");
            $check_stmt->execute([$guest_id, $fusen_type_id]);
            if ($check_stmt->fetchColumn() > 0) {
                $error = "この付箋タイプは既に割り当てられています。";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO guest_fusen (guest_id, fusen_type_id, custom_message)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$guest_id, $fusen_type_id, $custom_message]);
                
                $success = "付箋を追加しました。";
                
                // ページをリロード
                header("Location: guest_fusen.php?id={$guest_id}");
                exit;
            }
        } catch (PDOException $e) {
            $error = "付箋の追加に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    }
}

// 付箋の更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_fusen') {
    $fusen_id = isset($_POST['fusen_id']) ? (int)$_POST['fusen_id'] : 0;
    $custom_message = isset($_POST['custom_message']) ? trim($_POST['custom_message']) : '';
    
    if ($fusen_id <= 0) {
        $error = "無効な付箋IDです。";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE guest_fusen
                SET custom_message = ?
                WHERE id = ? AND guest_id = ?
            ");
            $stmt->execute([$custom_message, $fusen_id, $guest_id]);
            
            $success = "付箋を更新しました。";
            
            // ページをリロード
            header("Location: guest_fusen.php?id={$guest_id}");
            exit;
        } catch (PDOException $e) {
            $error = "付箋の更新に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    }
}

// 付箋の削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_fusen') {
    $fusen_id = isset($_POST['fusen_id']) ? (int)$_POST['fusen_id'] : 0;
    
    if ($fusen_id <= 0) {
        $error = "無効な付箋IDです。";
    } else {
        try {
            $stmt = $pdo->prepare("
                DELETE FROM guest_fusen
                WHERE id = ? AND guest_id = ?
            ");
            $stmt->execute([$fusen_id, $guest_id]);
            
            $success = "付箋を削除しました。";
            
            // ページをリロード
            header("Location: guest_fusen.php?id={$guest_id}");
            exit;
        } catch (PDOException $e) {
            $error = "付箋の削除に失敗しました。";
            if ($debug_mode) {
                $error .= " エラー: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ゲスト付箋管理 - <?= $site_name ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- キャッシュ防止のためのバージョンパラメータを追加 -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
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
        .fusen-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .fusen-card {
            background-color: #fff9c4;
            border: 1px solid #ffeb3b;
            border-radius: 4px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
        }
        .fusen-card h3 {
            margin-top: 0;
            color: #d32f2f;
            font-size: 18px;
            border-bottom: 1px dashed #ffcc80;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .fusen-card p {
            margin: 0 0 15px 0;
            font-size: 14px;
            white-space: pre-wrap;
        }
        .fusen-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .fusen-actions .admin-btn {
            padding: 5px 10px;
        }
        /* モーダルスタイル追加 */
        .admin-modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .admin-modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .admin-modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 20px;
            top: 10px;
        }
        
        .admin-modal-close:hover,
        .admin-modal-close:focus {
            color: black;
            text-decoration: none;
        }
        
        /* Bootstrapモーダルの表示修正 */
        .modal {
            background-color: rgba(0,0,0,0.4);
            max-height: 100%;
            overflow-y: auto;
            z-index: 1100;
        }
        
        .modal-dialog {
            max-width: 600px;
            margin: 30px auto;
        }
        
        .modal-content {
            position: relative;
            background-color: #fff;
            border-radius: 6px;
            box-shadow: 0 3px 9px rgba(0,0,0,.5);
            outline: 0;
        }
        
        .modal-header {
            padding: 15px;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .modal-body {
            position: relative;
            padding: 15px;
        }
        
        .modal-footer {
            padding: 15px;
            text-align: right;
            border-top: 1px solid #e5e5e5;
        }
        
        .modal-header .close {
            margin-top: -2px;
            font-size: 21px;
            font-weight: 700;
            line-height: 1;
            color: #000;
            text-shadow: 0 1px 0 #fff;
            filter: alpha(opacity=20);
            opacity: .2;
            padding: 0;
            cursor: pointer;
            background: transparent;
            border: 0;
            float: right;
        }
        
        .modal-header .close:hover {
            opacity: .5;
        }
        
        .close {
            float: right;
            font-size: 21px;
            font-weight: 700;
            line-height: 1;
            color: #000;
            text-shadow: 0 1px 0 #fff;
            filter: alpha(opacity=20);
            opacity: .2;
            padding: 0;
            cursor: pointer;
            background: transparent;
            border: 0;
        }
        
        .close:hover {
            opacity: .5;
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
                        <div class="admin-section-header">
                            <h2><i class="fas fa-sticky-note"></i> 付箋管理: <?= htmlspecialchars($guest_info['group_name']) ?></h2>
                            <div class="admin-actions">
                                <a href="dashboard.php" class="admin-button admin-button-secondary">
                                    <i class="fas fa-arrow-left"></i> 一覧に戻る
                                </a>
                                <a href="edit_guest.php?id=<?= $guest_id ?>" class="admin-button">
                                    <i class="fas fa-edit"></i> ゲスト情報編集
                                </a>
                            </div>
                        </div>
                        
                        <?php if (isset($success)): ?>
                        <div class="admin-success">
                            <p><?= $success ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error)): ?>
                        <div class="admin-error">
                            <p><?= $error ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="admin-guest-info">
                            <div class="admin-info-card">
                                <h3>招待グループ情報</h3>
                                <p><strong>宛名:</strong> <?= htmlspecialchars($guest_info['group_name']) ?></p>
                                <p><strong>URL識別子:</strong> <?= htmlspecialchars($guest_info['group_id']) ?></p>
                                <p><strong>集合時間:</strong> <?= htmlspecialchars($guest_info['arrival_time']) ?></p>
                                <p><strong>招待URL:</strong> <a href="../index.php?group=<?= urlencode($guest_info['group_id']) ?>" target="_blank"><?= $site_url ?>?group=<?= urlencode($guest_info['group_id']) ?></a></p>
                            </div>
                        </div>
                        
                        <h3>割り当て済み付箋</h3>
                        
                        <?php if (empty($guest_fusens)): ?>
                        <div class="admin-info-box">
                            <p>このゲストに割り当てられた付箋はありません。下のフォームから付箋を追加してください。</p>
                        </div>
                        <?php else: ?>
                        <div class="fusen-list">
                            <?php foreach ($guest_fusens as $fusen): ?>
                            <div class="fusen-card">
                                <h3><?= htmlspecialchars($fusen['type_name']) ?></h3>
                                <p><?= nl2br(htmlspecialchars($fusen['custom_message'] ?: $fusen['default_message'])) ?></p>
                                <div class="fusen-actions">
                                    <button class="admin-btn admin-btn-edit" 
                                        data-fusen-id="<?= $fusen['id'] ?>" 
                                        data-fusen-message="<?= htmlspecialchars($fusen['custom_message'] ?: $fusen['default_message']) ?>" 
                                        data-fusen-type="<?= htmlspecialchars($fusen['type_name']) ?>"
                                        onclick="editFusen(this)">
                                        <i class="fas fa-edit"></i> 編集
                                    </button>
                                    <button class="admin-btn admin-btn-delete" onclick="deleteFusen(<?= $fusen['id'] ?>, '<?= htmlspecialchars($fusen['type_name']) ?>')">
                                        <i class="fas fa-trash"></i> 削除
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <h3>付箋を追加</h3>
                        
                        <?php if (empty($fusen_types)): ?>
                        <div class="admin-info-box">
                            <p>付箋タイプが登録されていません。先に<a href="fusen_settings.php">付箋設定ページ</a>で付箋タイプを登録してください。</p>
                        </div>
                        <?php else: ?>
                        <form class="admin-form" method="post" action="">
                            <input type="hidden" name="action" value="add_fusen">
                            
                            <div class="admin-form-group">
                                <label for="fusen_type_id">付箋タイプ <span class="required">*</span></label>
                                <select id="fusen_type_id" name="fusen_type_id" required>
                                    <option value="">-- 選択してください --</option>
                                    <?php 
                                    // 既に割り当てられていないタイプのみ表示
                                    $assigned_type_ids = array_column($guest_fusens, 'fusen_type_id');
                                    foreach ($fusen_types as $type): 
                                        if (!in_array($type['id'], $assigned_type_ids)):
                                    ?>
                                    <option value="<?= $type['id'] ?>" data-default="<?= htmlspecialchars($type['default_message']) ?>"><?= htmlspecialchars($type['type_name']) ?></option>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </select>
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="custom_message">メッセージ</label>
                                <textarea id="custom_message" name="custom_message" rows="4" placeholder="空白の場合はデフォルトメッセージが使用されます"></textarea>
                            </div>
                            
                            <div class="fusen-preview">
                                <h3 id="preview-title">付箋タイトル</h3>
                                <p id="preview-message">付箋のメッセージ内容がここに表示されます。</p>
                            </div>
                            
                            <div class="admin-form-actions">
                                <button type="submit" class="admin-button">
                                    <i class="fas fa-plus"></i> 付箋を追加
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                        
                        <!-- 編集モーダル -->
                        <div id="edit-modal" class="admin-modal">
                            <div class="admin-modal-content">
                                <span class="admin-modal-close">&times;</span>
                                <h3>付箋を編集: <span id="edit-type-name"></span></h3>
                                
                                <form class="admin-form" method="post" action="">
                                    <input type="hidden" name="action" value="update_fusen">
                                    <input type="hidden" id="edit-fusen-id" name="fusen_id" value="">
                                    
                                    <div class="admin-form-group">
                                        <label for="edit-message">メッセージ</label>
                                        <textarea id="edit-message" name="custom_message" rows="6"></textarea>
                                        <small>空白の場合はデフォルトメッセージが使用されます</small>
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
                                <h3>付箋を削除</h3>
                                
                                <p><span id="delete-type-name"></span>の付箋を削除してもよろしいですか？</p>
                                
                                <form class="admin-form" method="post" action="">
                                    <input type="hidden" name="action" value="delete_fusen">
                                    <input type="hidden" id="delete-fusen-id" name="fusen_id" value="">
                                    
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
                    </section>
                </div>
                
                <?php include 'inc/footer.php'; ?>
            </div>
        </div>
    </div>
    
    <script>
    // ページロード時にモーダルが存在するか確認するデバッグコード
    document.addEventListener('DOMContentLoaded', function() {
        console.log("DOM読み込み完了");
        // モーダル要素の存在を確認
        const editModal = document.getElementById('edit-modal');
        const deleteModal = document.getElementById('delete-modal');
        console.log("編集モーダル存在:", editModal ? "はい" : "いいえ");
        console.log("削除モーダル存在:", deleteModal ? "はい" : "いいえ");
        
        // 付箋タイプ選択時のプレビュー更新
        const fusenTypeSelect = document.getElementById('fusen_type_id');
        const customMessage = document.getElementById('custom_message');
        const previewTitle = document.getElementById('preview-title');
        const previewMessage = document.getElementById('preview-message');
        
        if (fusenTypeSelect) {
            fusenTypeSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    previewTitle.textContent = selectedOption.text;
                    customMessage.value = '';
                    previewMessage.textContent = selectedOption.dataset.default || '付箋のメッセージ内容がここに表示されます。';
                } else {
                    previewTitle.textContent = '付箋タイトル';
                    previewMessage.textContent = '付箋のメッセージ内容がここに表示されます。';
                }
            });
        }
        
        if (customMessage) {
            customMessage.addEventListener('input', function() {
                if (fusenTypeSelect.value) {
                    const selectedOption = fusenTypeSelect.options[fusenTypeSelect.selectedIndex];
                    previewMessage.textContent = this.value || selectedOption.dataset.default || '付箋のメッセージ内容がここに表示されます。';
                } else {
                    previewMessage.textContent = this.value || '付箋のメッセージ内容がここに表示されます。';
                }
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
    function editFusen(button) {
        try {
            const id = button.dataset.fusenId;
            const typeName = button.dataset.fusenType;
            const message = button.dataset.fusenMessage;
            
            console.log("editFusen関数が呼び出されました", id, typeName);
            const modal = document.getElementById('edit-modal');
            if (!modal) {
                console.error("編集モーダルが見つかりません");
                alert("エラー: 編集モーダルが見つかりません。ページを再読み込みしてください。");
                return;
            }
            
            // IDの要素が見つからない場合の例外処理
            const fusenIdInput = document.getElementById('edit-fusen-id');
            const typeNameSpan = document.getElementById('edit-type-name');
            const messageTextarea = document.getElementById('edit-message');
            
            if (!fusenIdInput || !typeNameSpan || !messageTextarea) {
                console.error("必要な要素が見つかりません", {
                    fusenIdInput: !!fusenIdInput, 
                    typeNameSpan: !!typeNameSpan, 
                    messageTextarea: !!messageTextarea
                });
                alert("エラー: モーダル内の要素が見つかりません。ページを再読み込みしてください。");
                return;
            }
            
            fusenIdInput.value = id;
            typeNameSpan.textContent = typeName;
            
            // メッセージを直接設定
            messageTextarea.value = message;
            
            // モーダルを表示
            modal.style.display = 'block';
            console.log("モーダルを表示しました");
        } catch (err) {
            console.error("editFusen関数でエラーが発生しました:", err);
            alert("エラーが発生しました: " + err.message);
        }
    }
    
    // 削除モーダルを開く
    function deleteFusen(id, typeName) {
        try {
            console.log("deleteFusen関数が呼び出されました", id, typeName);
            const modal = document.getElementById('delete-modal');
            if (!modal) {
                console.error("削除モーダルが見つかりません");
                alert("エラー: 削除モーダルが見つかりません。ページを再読み込みしてください。");
                return;
            }
            
            const fusenIdInput = document.getElementById('delete-fusen-id');
            const typeNameSpan = document.getElementById('delete-type-name');
            
            if (!fusenIdInput || !typeNameSpan) {
                console.error("必要な要素が見つかりません");
                alert("エラー: モーダル内の要素が見つかりません。ページを再読み込みしてください。");
                return;
            }
            
            fusenIdInput.value = id;
            typeNameSpan.textContent = typeName;
            modal.style.display = 'block';
            console.log("削除モーダルを表示しました");
        } catch (err) {
            console.error("deleteFusen関数でエラーが発生しました:", err);
            alert("エラーが発生しました: " + err.message);
        }
    }
    </script>
</body>
</html> 