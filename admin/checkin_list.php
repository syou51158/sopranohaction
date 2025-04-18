<?php
/**
 * チェックイン一覧管理
 * 
 * ゲストのチェックイン状況を一覧表示し、統計情報を提供するページです。
 */

// 設定の読み込み
require_once '../config.php';
require_once '../includes/qr_helper.php';

// セッション開始
session_start();

// 管理者認証チェック
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php?redirect=checkin_list.php");
    exit;
}

// 初期化
$error = '';
$success = '';

// フィルタリング設定
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$group_filter = isset($_GET['group']) ? $_GET['group'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// チェックイン削除処理
if (isset($_POST['action']) && $_POST['action'] === 'delete_checkin' && isset($_POST['guest_id'])) {
    // ゲストIDの検証
    $guest_id = filter_var($_POST['guest_id'], FILTER_VALIDATE_INT);
    
    if ($guest_id !== false) {
        try {
            // トランザクション開始
            $pdo->beginTransaction();
            
            // ゲスト情報を取得（ログ記録用）
            $stmt = $pdo->prepare("SELECT id, group_name, group_id FROM guests WHERE id = :guest_id");
            $stmt->bindParam(':guest_id', $guest_id);
            $stmt->execute();
            $guest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($guest) {
                // チェックイン記録を削除
                $stmt = $pdo->prepare("DELETE FROM checkins WHERE guest_id = :guest_id");
                $stmt->bindParam(':guest_id', $guest_id);
                $result = $stmt->execute();
                
                if ($result) {
                    $pdo->commit();
                    $success = "「{$guest['group_name']}」のチェックイン記録を削除しました。";
                    
                    // localStorageクリア用にセッション変数に保存
                    if (!isset($_SESSION['deleted_all_group_ids'])) {
                        $_SESSION['deleted_all_group_ids'] = [];
                    }
                    $_SESSION['deleted_all_group_ids'][] = $guest['group_id'];
                    
                    // logAction('チェックイン削除', "「{$guest['group_name']}」（ID: {$guest_id}）のチェックイン記録を削除しました");
                } else {
                    $pdo->rollBack();
                    $error = "チェックイン記録の削除に失敗しました。";
                    // logAction('エラー', "チェックイン削除に失敗: ゲストID " . $guest_id);
                }
            } else {
                $pdo->rollBack();
                $error = "ゲスト情報が見つかりませんでした。";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "データベースエラー: " . $e->getMessage();
            // logAction('エラー', "チェックイン削除中にデータベースエラー: " . $e->getMessage());
        }
    } else {
        $error = "無効なゲストIDです。";
    }
}

// 全てのチェックイン削除処理
if (isset($_POST['delete_all_checkins'])) {
    // 削除前にゲストIDを取得して後でlocalStorageクリア用に利用
    try {
        // まずは削除前にゲストIDとグループ名の対応を取得しておく
        $stmt = $pdo->prepare("
            SELECT DISTINCT c.guest_id, g.group_id 
            FROM checkins c
            JOIN guests g ON c.guest_id = g.id
        ");
        $stmt->execute();
        $guest_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // グループIDのみの配列を作成
        $group_ids = array_column($guest_data, 'group_id');
        
        // 削除処理
        $stmt = $pdo->prepare("DELETE FROM checkins");
        
        if ($stmt->execute()) {
            // ローカルストレージ削除用にセッション変数に保存
            if (!empty($group_ids)) {
                $_SESSION['deleted_all_group_ids'] = $group_ids;
            }
            
            $_SESSION['success_message'] = '全てのチェックイン履歴を正常に削除しました。';
        } else {
            $_SESSION['error_message'] = '全てのチェックイン履歴の削除に失敗しました。';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'データベースエラー: ' . $e->getMessage();
        // logAction('エラー', '全チェックイン履歴削除でのエラー: ' . $e->getMessage());
    }
    
    header("Location: checkin_list.php");
    exit;
}

// 検索クエリを構築
$query_parts = [];
$params = [];

// 基本クエリ
$base_query = "
    SELECT c.*, g.group_name as guest_name, g.group_name, g.group_id
    FROM checkins c
    JOIN guests g ON c.guest_id = g.id
";

// 日付フィルター
if ($date_filter) {
    $query_parts[] = "DATE(c.checkin_time) = ?";
    $params[] = $date_filter;
}

// グループフィルター
if ($group_filter) {
    $query_parts[] = "(g.group_id LIKE ? OR g.group_name LIKE ?)";
    $params[] = "%$group_filter%";
    $params[] = "%$group_filter%";
}

// 状態フィルター
switch ($status_filter) {
    case 'today':
        $query_parts[] = "DATE(c.checkin_time) = CURDATE()";
        break;
    case 'yesterday':
        $query_parts[] = "DATE(c.checkin_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'this_week':
        $query_parts[] = "YEARWEEK(c.checkin_time) = YEARWEEK(NOW())";
        break;
}

// クエリを組み立て
$where_clause = !empty($query_parts) ? "WHERE " . implode(" AND ", $query_parts) : "";
$full_query = $base_query . $where_clause . " ORDER BY c.checkin_time DESC";

// チェックインデータを取得
try {
    $stmt = $pdo->prepare($full_query);
    $stmt->execute($params);
    $checkins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "データベースエラー: " . $e->getMessage();
    $checkins = [];
}

// 統計データを取得
function getCheckinStats($pdo, $date_filter) {
    $stats = [
        'total_count' => 0,
        'hourly_data' => [],
        'group_data' => []
    ];
    
    try {
        // 総チェックイン数
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM checkins c
            JOIN guests g ON c.guest_id = g.id
            WHERE DATE(c.checkin_time) = ?
        ");
        $stmt->execute([$date_filter]);
        $stats['total_count'] = $stmt->fetchColumn();
        
        // 時間別チェックイン数
        $stmt = $pdo->prepare("
            SELECT HOUR(c.checkin_time) as hour, COUNT(*) as count
            FROM checkins c
            WHERE DATE(c.checkin_time) = ?
            GROUP BY HOUR(c.checkin_time)
            ORDER BY hour
        ");
        $stmt->execute([$date_filter]);
        $stats['hourly_data'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // グループ別チェックイン数
        $stmt = $pdo->prepare("
            SELECT g.group_name, COUNT(*) as count
            FROM checkins c
            JOIN guests g ON c.guest_id = g.id
            WHERE DATE(c.checkin_time) = ?
            GROUP BY g.group_name
            ORDER BY count DESC
        ");
        $stmt->execute([$date_filter]);
        $stats['group_data'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
    } catch (PDOException $e) {
        // エラー処理
    }
    
    return $stats;
}

$stats = getCheckinStats($pdo, $date_filter);

// 利用可能な日付リストを取得
try {
    $stmt = $pdo->query("
        SELECT DISTINCT DATE(checkin_time) as date 
        FROM checkins 
        ORDER BY date DESC
    ");
    $available_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $available_dates = [];
}

// 利用可能なグループリストを取得
try {
    $stmt = $pdo->query("
        SELECT DISTINCT group_name, group_id
        FROM guests
        WHERE group_name IS NOT NULL AND group_name != ''
        ORDER BY group_name
    ");
    $available_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $available_groups = [];
}

// ページタイトル
$page_title = 'チェックイン一覧・統計';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - 管理画面</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .checkin-list-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .filters-container {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .stat-card h3 {
            margin-top: 0;
            color: #4CAF50;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .big-number {
            font-size: 3rem;
            font-weight: bold;
            color: #333;
            text-align: center;
            margin: 20px 0;
        }
        
        .checkin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .checkin-table th, .checkin-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        
        .checkin-table th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
        }
        
        .checkin-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .checkin-table tr:hover {
            background-color: #f0f0f0;
        }
        
        .table-container {
            max-height: 500px;
            overflow-y: auto;
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .admin-actions {
            display: flex;
            gap: 5px;
        }
        
        .no-results {
            padding: 30px;
            text-align: center;
            color: #666;
        }
        
        .chart-container {
            height: 300px;
            margin-top: 10px;
        }
        
        .export-container {
            margin-top: 20px;
            text-align: right;
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'inc/header.php'; ?>
        
        <div class="admin-content-wrapper">
            <div class="checkin-list-container">
                <h2><i class="fas fa-clipboard-check"></i> <?= $page_title ?></h2>
                
                <!-- エラーメッセージ -->
                <?php if ($error): ?>
                    <div class="admin-error">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <!-- 成功メッセージ -->
                <?php if ($success): ?>
                    <div class="admin-success">
                        <i class="fas fa-check-circle"></i> <?= $success ?>
                    </div>
                <?php endif; ?>
                
                <!-- フィルター -->
                <div class="filters-container">
                    <h3><i class="fas fa-filter"></i> フィルター</h3>
                    <form class="filter-form" method="get" action="checkin_list.php">
                        <div class="filter-group">
                            <label for="date">日付:</label>
                            <select name="date" id="date" class="form-control">
                                <option value="">すべての日付</option>
                                <?php foreach ($available_dates as $date): ?>
                                    <option value="<?= $date ?>" <?= $date_filter == $date ? 'selected' : '' ?>>
                                        <?= date('Y年m月d日', strtotime($date)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="group">グループ:</label>
                            <select name="group" id="group" class="form-control">
                                <option value="">すべてのグループ</option>
                                <?php foreach ($available_groups as $group): ?>
                                    <option value="<?= htmlspecialchars($group['group_id']) ?>" 
                                            <?= $group_filter == $group['group_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($group['group_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">状態:</label>
                            <select name="status" id="status" class="form-control">
                                <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>すべて</option>
                                <option value="today" <?= $status_filter == 'today' ? 'selected' : '' ?>>今日</option>
                                <option value="yesterday" <?= $status_filter == 'yesterday' ? 'selected' : '' ?>>昨日</option>
                                <option value="this_week" <?= $status_filter == 'this_week' ? 'selected' : '' ?>>今週</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="admin-button">
                                <i class="fas fa-search"></i> 絞り込む
                            </button>
                            <a href="checkin_list.php" class="admin-button admin-button-secondary">
                                <i class="fas fa-redo"></i> リセット
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- 統計情報 -->
                <?php if ($date_filter): ?>
                <div class="stats-container">
                    <div class="stat-card">
                        <h3><i class="fas fa-users"></i> チェックイン総数</h3>
                        <div class="big-number"><?= $stats['total_count'] ?></div>
                        <p>日付: <?= date('Y年m月d日', strtotime($date_filter)) ?></p>
                    </div>
                    
                    <div class="stat-card">
                        <h3><i class="fas fa-chart-bar"></i> 時間別チェックイン数</h3>
                        <div class="chart-container">
                            <canvas id="hourlyChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><i class="fas fa-chart-pie"></i> グループ別チェックイン</h3>
                        <div class="chart-container">
                            <canvas id="groupChart"></canvas>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- エクスポートボタン -->
                <div class="export-container">
                    <a href="checkin.php" class="admin-button">
                        <i class="fas fa-qrcode"></i> QRコードチェックイン
                    </a>
                    <a href="export_checkins.php<?= $date_filter ? "?date=$date_filter" : '' ?>" class="admin-button">
                        <i class="fas fa-file-export"></i> CSVエクスポート
                    </a>
                    
                    <form method="post" action="checkin_list.php" style="display: inline-block; margin-left: 10px;" onsubmit="return confirm('全てのチェックイン履歴を削除してもよろしいですか？この操作は取り消せません。');">
                        <button type="submit" name="delete_all_checkins" class="admin-button admin-button-danger">
                            <i class="fas fa-trash-alt"></i> 全履歴削除
                        </button>
                    </form>
                </div>
                
                <!-- チェックイン一覧 -->
                <div class="table-container">
                    <table class="checkin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ゲスト名</th>
                                <th>グループ</th>
                                <th>チェックイン時間</th>
                                <th>記録者</th>
                                <th>備考</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($checkins)): ?>
                                <tr>
                                    <td colspan="7" class="no-results">
                                        <i class="fas fa-info-circle"></i> チェックインデータがありません
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($checkins as $checkin): ?>
                                    <tr>
                                        <td><?= $checkin['id'] ?></td>
                                        <td><?= htmlspecialchars($checkin['guest_name']) ?></td>
                                        <td><?= htmlspecialchars($checkin['group_name']) ?></td>
                                        <td><?= date('Y/m/d H:i:s', strtotime($checkin['checkin_time'])) ?></td>
                                        <td><?= htmlspecialchars($checkin['checked_by'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($checkin['notes'] ?: '-') ?></td>
                                        <td class="admin-actions">
                                            <form method="post" class="delete-form" onsubmit="return confirm('このチェックイン記録を削除しますか？\n\n※この操作は元に戻せません。');">
                                                <input type="hidden" name="action" value="delete_checkin">
                                                <input type="hidden" name="guest_id" value="<?php echo $checkin['guest_id']; ?>">
                                                <button type="submit" class="admin-button admin-button-small admin-button-danger">
                                                    <i class="fas fa-trash"></i> 削除
                                                </button>
                                            </form>
                                            <a href="checkin.php?token=<?= htmlspecialchars(generate_qr_token($checkin['guest_id'])) ?>" class="admin-button admin-button-small">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php include 'inc/footer.php'; ?>
    </div>
    
    <?php if ($date_filter): ?>
    <!-- グラフ用のJavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 時間別チェックインチャート
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        const hourLabels = Array.from({length: 24}, (_, i) => `${i}時`);
        const hourData = hourLabels.map(hour => {
            const hourNum = parseInt(hour);
            return <?= json_encode($stats['hourly_data']) ?>[hourNum] || 0;
        });
        
        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: hourLabels,
                datasets: [{
                    label: 'チェックイン数',
                    data: hourData,
                    backgroundColor: 'rgba(76, 175, 80, 0.6)',
                    borderColor: 'rgba(76, 175, 80, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        
        // グループ別チェックインチャート
        const groupData = <?= json_encode($stats['group_data']) ?>;
        const groupLabels = Object.keys(groupData);
        const groupValues = Object.values(groupData);
        
        // 色の生成
        const generateColors = (count) => {
            const colors = [];
            for (let i = 0; i < count; i++) {
                const hue = (i * 137) % 360; // 黄金角を使用して色相を分散
                colors.push(`hsla(${hue}, 70%, 60%, 0.7)`);
            }
            return colors;
        };
        
        const groupCtx = document.getElementById('groupChart').getContext('2d');
        new Chart(groupCtx, {
            type: 'pie',
            data: {
                labels: groupLabels,
                datasets: [{
                    data: groupValues,
                    backgroundColor: generateColors(groupLabels.length),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 15,
                            font: {
                                size: 10
                            },
                            generateLabels: function(chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map(function(label, i) {
                                        const meta = chart.getDatasetMeta(0);
                                        const value = data.datasets[0].data[i];
                                        
                                        // ラベルを省略
                                        const shortenLabel = label.length > 10 ? 
                                            label.substr(0, 10) + '...' : label;
                                        
                                        return {
                                            text: `${shortenLabel} (${value})`,
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            index: i
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                return `${label}: ${value}人`;
                            }
                        }
                    }
                }
            }
        });
    });
    </script>
    <?php endif; ?>
    
    <!-- ローカルストレージクリアのためのJavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 全削除の場合
        <?php if (isset($_SESSION['deleted_all_group_ids']) && is_array($_SESSION['deleted_all_group_ids'])): ?>
            const groupIds = <?php echo json_encode($_SESSION['deleted_all_group_ids']); ?>;
            console.log('削除された全グループID:', groupIds);
            
            // 各グループIDに対してローカルストレージをクリア
            groupIds.forEach(groupId => {
                clearLocalStorageForGroup(groupId);
            });
            
            // セッション変数をクリア
            <?php unset($_SESSION['deleted_all_group_ids']); ?>
        <?php endif; ?>
        
        // 削除ボタンのイベントリスナー
        document.querySelectorAll('.delete-checkin').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('このチェックイン履歴を削除しますか？関連するQRコードスキャン履歴も削除されます。')) {
                    e.preventDefault();
                }
            });
        });
        
        // 全削除ボタンのイベントリスナー
        document.querySelector('#delete-all-checkins').addEventListener('click', function(e) {
            if (!confirm('全てのチェックイン履歴を削除しますか？全てのQRコードスキャン履歴も削除されます。')) {
                e.preventDefault();
            }
        });
    });
    </script>
</body>
</html> 