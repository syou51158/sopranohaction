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

// ゲストグループを取得
$guests = [];
try {
    $stmt = $pdo->query("
        SELECT g.*, gt.type_name 
        FROM guests g
        LEFT JOIN group_types gt ON g.group_type_id = gt.id
        ORDER BY g.group_name
    ");
    $guests = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "ゲスト情報の取得に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// 出欠回答を取得
$responses = [];
try {
    $stmt = $pdo->query("
        SELECT r.*, g.group_name 
        FROM responses r 
        LEFT JOIN guests g ON r.guest_id = g.id 
        ORDER BY r.created_at DESC
    ");
    $responses = $stmt->fetchAll();
    
    // 同伴者情報を取得
    $companions = [];
    $stmt_companions = $pdo->query("
        SELECT c.*, r.name as primary_guest
        FROM companions c
        JOIN responses r ON c.response_id = r.id
        ORDER BY c.response_id, c.id
    ");
    $all_companions = $stmt_companions->fetchAll();
    
    // 回答IDごとに同伴者をグループ化
    foreach ($all_companions as $companion) {
        $companions[$companion['response_id']][] = $companion;
    }
} catch (PDOException $e) {
    $error = "回答情報の取得に失敗しました。";
    if ($debug_mode) {
        $error .= " エラー: " . $e->getMessage();
    }
}

// 参加者・欠席者カウント
$attending_count = 0;
$not_attending_count = 0;

foreach ($responses as $response) {
    if ($response['attending']) {
        $attending_count++;
        // 同伴者も加算
        $attending_count += $response['companions'];
    } else {
        $not_attending_count++;
    }
}

// 新しいゲストグループの追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_guest') {
    $group_name = isset($_POST['group_name']) ? trim($_POST['group_name']) : '';
    $group_id = isset($_POST['group_id']) ? trim($_POST['group_id']) : '';
    $arrival_time = isset($_POST['arrival_time']) ? trim($_POST['arrival_time']) : '';
    $custom_message = isset($_POST['custom_message']) ? trim($_POST['custom_message']) : '';
    $max_companions = isset($_POST['max_companions']) ? (int)$_POST['max_companions'] : 0;
    $group_type_id = isset($_POST['group_type_id']) && !empty($_POST['group_type_id']) ? (int)$_POST['group_type_id'] : null;
    
    if (empty($group_name) || empty($group_id) || empty($arrival_time)) {
        $add_error = "グループ名、グループID、到着時間は必須です。";
    } else {
        try {
            // グループIDが既に存在するか確認
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM guests WHERE group_id = ?");
            $check_stmt->execute([$group_id]);
            if ($check_stmt->fetchColumn() > 0) {
                $add_error = "このグループIDは既に使用されています。別のIDを指定してください。";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO guests 
                    (group_id, group_name, arrival_time, custom_message, max_companions, group_type_id) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $group_id,
                    $group_name,
                    $arrival_time,
                    $custom_message,
                    $max_companions,
                    $group_type_id
                ]);
                
                // 成功メッセージを設定
                $success = "新しいゲストグループを追加しました。";
                
                // ページをリロード
                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            $add_error = "ゲストの追加に失敗しました。";
            if ($debug_mode) {
                $add_error .= " エラー: " . $e->getMessage();
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
    <title>管理ダッシュボード - <?= $site_name ?></title>
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
                        <h2>出欠状況概要</h2>
                        <div class="admin-stats">
                            <div class="admin-stat-card">
                                <div class="admin-stat-icon">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="admin-stat-info">
                                    <h3>参加予定</h3>
                                    <p class="admin-stat-count"><?= $attending_count ?> 人</p>
                                </div>
                            </div>
                            <div class="admin-stat-card">
                                <div class="admin-stat-icon">
                                    <i class="fas fa-times"></i>
                                </div>
                                <div class="admin-stat-info">
                                    <h3>欠席予定</h3>
                                    <p class="admin-stat-count"><?= $not_attending_count ?> 人</p>
                                </div>
                            </div>
                            <div class="admin-stat-card">
                                <div class="admin-stat-icon">
                                    <i class="fas fa-user-friends"></i>
                                </div>
                                <div class="admin-stat-info">
                                    <h3>ゲストグループ</h3>
                                    <p class="admin-stat-count"><?= count($guests) ?> グループ</p>
                                </div>
                            </div>
                            <div class="admin-stat-card">
                                <div class="admin-stat-icon">
                                    <i class="fas fa-envelope-open-text"></i>
                                </div>
                                <div class="admin-stat-info">
                                    <h3>回答数</h3>
                                    <p class="admin-stat-count"><?= count($responses) ?> 件</p>
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <section id="guests" class="admin-section">
                        <h2>招待グループ一覧</h2>
                        <div class="admin-table-container">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>招待状の宛名</th>
                                        <th>URL識別子</th>
                                        <th>集合時間</th>
                                        <th>同伴者上限</th>
                                        <th>グループタイプ</th>
                                        <th>招待URL</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($guests)): ?>
                                    <tr>
                                        <td colspan="8">ゲストグループがありません。</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($guests as $guest): ?>
                                        <tr>
                                            <td><?= $guest['id'] ?></td>
                                            <td><?= htmlspecialchars($guest['group_name']) ?></td>
                                            <td><?= htmlspecialchars($guest['group_id']) ?></td>
                                            <td><?= htmlspecialchars($guest['arrival_time']) ?></td>
                                            <td><?= $guest['max_companions'] ?> 名</td>
                                            <td><?= htmlspecialchars($guest['type_name'] ?? '未設定') ?></td>
                                            <td>
                                                <a href="../index.php?group=<?= urlencode($guest['group_id']) ?>" target="_blank">
                                                    <?= $site_url ?>?group=<?= urlencode($guest['group_id']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <a href="edit_guest.php?id=<?= $guest['id'] ?>" class="admin-btn admin-btn-edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="guest_fusen.php?id=<?= $guest['id'] ?>" class="admin-btn admin-btn-info">
                                                    <i class="fas fa-sticky-note"></i>
                                                </a>
                                                <a href="delete_guest.php?id=<?= $guest['id'] ?>" class="admin-btn admin-btn-delete" onclick="return confirm('本当に削除しますか？');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    
                    <section id="responses" class="admin-section">
                        <h2>回答一覧</h2>
                        <div class="admin-table-container">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>グループ</th>
                                        <th>名前</th>
                                        <th>出欠</th>
                                        <th>同伴者</th>
                                        <th>メッセージ</th>
                                        <th>食事制限</th>
                                        <th>回答日時</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($responses)): ?>
                                    <tr>
                                        <td colspan="9">まだ回答がありません。</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($responses as $response): ?>
                                        <tr>
                                            <td><?= $response['id'] ?></td>
                                            <td><?= htmlspecialchars($response['group_name'] ?? '未指定') ?></td>
                                            <td><?= htmlspecialchars($response['name']) ?></td>
                                            <td><?= $response['attending'] ? '<span class="attending">出席</span>' : '<span class="not-attending">欠席</span>' ?></td>
                                            <td>
                                                <?= $response['companions'] ?> 名
                                                <?php if ($response['companions'] > 0 && $response['attending'] && isset($companions[$response['id']])): ?>
                                                <button type="button" class="admin-btn admin-btn-info view-companions" data-response-id="<?= $response['id'] ?>">
                                                    <i class="fas fa-users"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                            <td class="message-cell"><?= nl2br(htmlspecialchars($response['message'] ?? '')) ?></td>
                                            <td><?= nl2br(htmlspecialchars($response['dietary'] ?? '')) ?></td>
                                            <td><?= date('Y/m/d H:i', strtotime($response['created_at'])) ?></td>
                                            <td>
                                                <a href="export_response.php?id=<?= $response['id'] ?>" class="admin-btn admin-btn-export" title="CSV出力">
                                                    <i class="fas fa-file-csv"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    
                    <section id="add-guest" class="admin-section">
                        <h2>招待グループを追加</h2>
                        
                        <?php if (isset($add_error)): ?>
                        <div class="admin-error">
                            <?= $add_error ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($success)): ?>
                        <div class="admin-success">
                            <?= $success ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="admin-info-box">
                            <p><i class="fas fa-info-circle"></i> <strong>招待グループとは</strong></p>
                            <ul>
                                <li>「招待状の宛名」は招待状に表示される宛先名です（例：山田様、田中家）。敬称（様など）を含めて入力してください。</li>
                                <li>「招待URL識別子」は招待状の個別URLを生成するために使用されます</li>
                                <li>一つの招待グループから複数の方が個別に出欠回答できます</li>
                            </ul>
                        </div>
                        
                        <form class="admin-form" method="post" action="" id="add-guest-form">
                            <input type="hidden" name="action" value="add_guest">
                            
                            <div class="admin-form-row">
                                <div class="admin-form-group">
                                    <label for="group_name">招待状の宛名 <span class="required">*</span></label>
                                    <input type="text" id="group_name" name="group_name" required value="<?= isset($_POST['group_name']) ? htmlspecialchars($_POST['group_name']) : '' ?>">
                                    <small>敬称（様など）を含めて入力。例：山田様、田中家、会社の皆様</small>
                                </div>
                                
                                <div class="admin-form-group">
                                    <label for="group_id">招待URL識別子 <span class="required">*</span></label>
                                    <input type="text" id="group_id" name="group_id" required pattern="[a-zA-Z0-9_-]+" title="英数字、ハイフン、アンダースコアのみ使用可能です" value="<?= isset($_POST['group_id']) ? htmlspecialchars($_POST['group_id']) : '' ?>">
                                    <small>URLに使用される識別子。英数字、ハイフン、アンダースコアのみ（例: yamada-family, company-a）</small>
                                </div>
                            </div>
                            
                            <div class="admin-form-row">
                                <div class="admin-form-group">
                                    <label for="arrival_time">集合時間 <span class="required">*</span></label>
                                    <input type="text" id="arrival_time" name="arrival_time" required pattern="([0-1][0-9]|2[0-3]):[0-5][0-9]" title="HH:MM形式で入力してください" placeholder="例: 12:30" value="<?= isset($_POST['arrival_time']) ? htmlspecialchars($_POST['arrival_time']) : '' ?>">
                                    <small>時間:分の形式で入力（例: 12:30, 14:00）</small>
                                </div>
                                
                                <div class="admin-form-group">
                                    <label for="max_companions">同伴者上限</label>
                                    <input type="number" id="max_companions" name="max_companions" min="0" value="<?= isset($_POST['max_companions']) ? (int)$_POST['max_companions'] : '0' ?>">
                                </div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="group_type_id">グループタイプ</label>
                                <select id="group_type_id" name="group_type_id">
                                    <option value="">-- 選択してください --</option>
                                    <?php
                                    try {
                                        $type_stmt = $pdo->query("SELECT * FROM group_types ORDER BY type_name");
                                        $group_types = $type_stmt->fetchAll();
                                        foreach ($group_types as $type) {
                                            $selected = (isset($_POST['group_type_id']) && $_POST['group_type_id'] == $type['id']) ? 'selected' : '';
                                            echo '<option value="' . $type['id'] . '" ' . $selected . '>' . htmlspecialchars($type['type_name']) . '</option>';
                                        }
                                    } catch (PDOException $e) {
                                        // グループタイプの取得に失敗した場合は何も表示しない
                                    }
                                    ?>
                                </select>
                                <small>ゲストの分類カテゴリ</small>
                            </div>
                            
                            <div class="admin-form-group">
                                <label for="custom_message">カスタムメッセージ</label>
                                <textarea id="custom_message" name="custom_message" rows="4"><?= isset($_POST['custom_message']) ? htmlspecialchars($_POST['custom_message']) : '' ?></textarea>
                                <small>このグループに表示する特別なメッセージ</small>
                            </div>
                            
                            <div class="admin-form-actions">
                                <button type="submit" class="admin-button">
                                    <i class="fas fa-plus"></i> 招待グループを追加
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
                
                <?php include 'inc/footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- 同伴者情報モーダル -->
    <div id="companions-modal" class="admin-modal">
        <div class="admin-modal-content">
            <span class="admin-modal-close">&times;</span>
            <h2>同伴者情報</h2>
            <div id="companions-details"></div>
        </div>
    </div>
    
    <style>
    /* モーダル関連のスタイル */
    .admin-modal {
        display: none;
        position: fixed;
        z-index: 1000;
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
        border: 1px solid #ddd;
        width: 70%;
        max-width: 800px;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        position: relative;
    }
    
    .admin-modal-close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        position: absolute;
        right: 15px;
        top: 10px;
    }
    
    .admin-modal-close:hover {
        color: black;
    }
    
    .companions-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    
    .companions-table th, .companions-table td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: left;
    }
    
    .companions-table th {
        background-color: #f2f2f2;
    }
    
    .companions-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    
    .companions-table tr:hover {
        background-color: #f5f5f5;
    }
    
    .companion-info {
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px dashed #ddd;
    }
    
    .admin-btn-export {
        background-color: #28a745;
    }
    
    .admin-btn-export:hover {
        background-color: #218838;
    }
    
    .view-companions {
        margin-left: 5px;
        font-size: 0.8rem;
    }
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 同伴者情報モーダル
        const modal = document.getElementById('companions-modal');
        const detailsContainer = document.getElementById('companions-details');
        const closeBtn = document.querySelector('.admin-modal-close');
        
        // 同伴者詳細ボタンのイベントリスナー
        const viewButtons = document.querySelectorAll('.view-companions');
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const responseId = this.getAttribute('data-response-id');
                showCompanionDetails(responseId);
            });
        });
        
        // モーダルを閉じるボタン
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        // モーダルの外側をクリックしても閉じる
        window.addEventListener('click', function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });
        
        // 同伴者情報を表示する関数
        function showCompanionDetails(responseId) {
            // PHPから同伴者データを取得
            const companions = <?= json_encode($companions) ?>;
            const responseCompanions = companions[responseId] || [];
            
            if (responseCompanions.length === 0) {
                detailsContainer.innerHTML = '<p>同伴者情報がありません。</p>';
            } else {
                // ページ内の回答者名を取得
                const primaryGuest = responseCompanions[0].primary_guest;
                
                let html = `<p><strong>${primaryGuest}</strong>さんの同伴者情報</p>`;
                html += '<table class="companions-table">';
                html += '<thead><tr><th>名前</th><th>年齢区分</th><th>食事制限・アレルギー</th></tr></thead>';
                html += '<tbody>';
                
                responseCompanions.forEach(companion => {
                    const ageGroup = companion.age_group === 'adult' ? '大人' :
                                    companion.age_group === 'child' ? '子供（小学生以下）' :
                                    companion.age_group === 'infant' ? '幼児（3歳以下）' : '不明';
                    
                    html += '<tr>';
                    html += `<td>${companion.name}</td>`;
                    html += `<td>${ageGroup}</td>`;
                    html += `<td>${companion.dietary || '特になし'}</td>`;
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                
                // CSVエクスポートへのリンク
                html += `<p style="margin-top: 15px; text-align: center;">
                    <a href="export_companions.php?response_id=${responseId}" class="admin-button">
                        <i class="fas fa-file-csv"></i> 同伴者情報をCSV出力
                    </a>
                </p>`;
                
                detailsContainer.innerHTML = html;
            }
            
            // モーダルを表示
            modal.style.display = 'block';
        }
    });
    </script>
</body>
</html> 