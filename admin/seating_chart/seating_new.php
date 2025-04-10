<?php
/**
 * 新席次表管理システム - メイン画面
 * 円卓式テーブルレイアウトと座席割り当て管理
 */

// 設定ファイルの読み込み
require_once '../../config.php';

// 関数ファイルの読み込み
require_once '../functions.php';

// 管理者かどうかのチェック
check_admin_auth();

// データベース接続
$db = db_connect();

// 参加するゲスト情報を取得
$stmt = $db->prepare("
    SELECT 
        g.id, g.name, g.name_kana, g.email, g.group_id, 
        gr.name as group_name,
        CASE WHEN s.guest_id IS NOT NULL THEN 1 ELSE 0 END AS is_seated
    FROM 
        guests g
    LEFT JOIN 
        groups gr ON g.group_id = gr.id
    LEFT JOIN 
        seats s ON g.id = s.guest_id
    WHERE 
        g.is_attending = 1
    ORDER BY 
        gr.id, g.name_kana
");
$stmt->execute();
$all_guests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 席情報を取得
$stmt = $db->prepare("
    SELECT 
        s.guest_id, s.table_number, s.seat_number, 
        g.name, g.name_kana, g.group_id,
        gr.name as group_name
    FROM 
        seats s
    JOIN 
        guests g ON s.guest_id = g.id
    LEFT JOIN 
        groups gr ON g.group_id = gr.id
    ORDER BY 
        s.table_number, s.seat_number
");
$stmt->execute();
$seated_guests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// グループ情報を取得
$stmt = $db->prepare("
    SELECT id, name, color
    FROM groups
    ORDER BY id
");
$stmt->execute();
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// テーブル情報を取得（今回は固定で8テーブル）
$table_count = 8;
$seats_per_table = 6; // 各テーブルの席数

// 座席ごとのゲスト情報をマッピング
$seating_map = [];
foreach ($seated_guests as $guest) {
    $table_number = $guest['table_number'];
    $seat_number = $guest['seat_number'];
    $key = $table_number . '_' . $seat_number;
    $seating_map[$key] = $guest;
}

// 未着席ゲストを取得
$unseated_guests = array_filter($all_guests, function($guest) {
    return $guest['is_seated'] == 0;
});

// グループIDでグループ分け
$guests_by_group = [];
foreach ($unseated_guests as $guest) {
    $group_id = $guest['group_id'];
    if (!isset($guests_by_group[$group_id])) {
        $guests_by_group[$group_id] = [];
    }
    $guests_by_group[$group_id][] = $guest;
}

// 着席済みゲスト数とテーブルごとの統計
$seated_count = count($seated_guests);
$total_guests = count($all_guests);
$tables_stats = [];

for ($i = 1; $i <= $table_count; $i++) {
    $tables_stats[$i] = 0;
}

foreach ($seated_guests as $guest) {
    $table_number = $guest['table_number'];
    if (isset($tables_stats[$table_number])) {
        $tables_stats[$table_number]++;
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新席次表管理 - 管理画面</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/seating.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                width: 100%;
                height: 100%;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <?php include '../inc/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include '../inc/sidebar.php'; ?>
            
            <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">新席次表管理</h1>
                    <div class="btn-toolbar mb-2 mb-md-0 no-print">
                        <div class="btn-group mr-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="print-seating">
                                <i class="fas fa-print"></i> 印刷
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="export-csv">
                                <i class="fas fa-file-csv"></i> CSVエクスポート
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="reset-all-seats">
                            <i class="fas fa-trash-alt"></i> 全席リセット
                        </button>
                    </div>
                </div>

                <div class="alert alert-info no-print">
                    <p><strong>席次表の使い方:</strong></p>
                    <ul>
                        <li>未割り当てゲストを席にドラッグして割り当てができます</li>
                        <li>割り当て済みの席をクリックすると、割り当て解除ができます</li>
                        <li>グループごとにゲストが色分けされています</li>
                    </ul>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">席割り当て状況</h5>
                            </div>
                            <div class="card-body">
                                <div class="progress mb-3">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo ($seated_count / $total_guests) * 100; ?>%;" 
                                         aria-valuenow="<?php echo $seated_count; ?>" aria-valuemin="0" aria-valuemax="<?php echo $total_guests; ?>">
                                        <?php echo $seated_count; ?> / <?php echo $total_guests; ?>
                                    </div>
                                </div>
                                <p class="card-text">配席済み: <?php echo $seated_count; ?> 名 / 全参加者: <?php echo $total_guests; ?> 名</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">テーブル別状況</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php for ($i = 1; $i <= $table_count; $i++): ?>
                                    <div class="col-3 mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="table-status table-<?php echo $i; ?> mr-2">
                                                <?php echo $i; ?>
                                            </div>
                                            <div>
                                                <?php echo $tables_stats[$i]; ?>/<?php echo $seats_per_table; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 会場レイアウト -->
                <div class="venue-layout">
                    <div class="venue-header no-print">
                        <h3>会場レイアウト</h3>
                    </div>
                    
                    <!-- 高砂（メインテーブル） -->
                    <div class="high-table">
                        <div class="high-table-box">高砂</div>
                    </div>
                    
                    <!-- テーブルエリア -->
                    <div class="tables-area">
                        <?php for ($table_num = 1; $table_num <= $table_count; $table_num++): ?>
                        <div class="table-container">
                            <div class="table-number"><?php echo $table_num; ?>番テーブル</div>
                            <div class="round-table">
                                <?php for ($seat_num = 1; $seat_num <= $seats_per_table; $seat_num++): 
                                    $key = $table_num . '_' . $seat_num;
                                    $is_occupied = isset($seating_map[$key]);
                                    $guest_data = $is_occupied ? $seating_map[$key] : null;
                                    $seat_position = get_seat_position($seat_num, $seats_per_table);
                                    $guest_group = $is_occupied ? $guest_data['group_id'] : 0;
                                    $group_color = $is_occupied ? get_group_color($groups, $guest_group) : '';
                                ?>
                                <div class="seat seat-<?php echo $seat_num; ?> <?php echo $is_occupied ? 'occupied' : ''; ?>" 
                                     style="<?php echo $is_occupied ? "background-color: $group_color;" : ''; ?>"
                                     data-table-id="<?php echo $table_num; ?>" 
                                     data-seat-number="<?php echo $seat_num; ?>">
                                    <?php if ($is_occupied): ?>
                                    <div class="guest-name"><?php echo $guest_data['name']; ?></div>
                                    <?php else: ?>
                                    <div class="seat-number"><?php echo $seat_num; ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- 未割り当てゲスト一覧 -->
                <div class="mt-5 no-print">
                    <h3>未割り当てゲスト</h3>
                    
                    <div class="unassigned-guests">
                        <?php foreach ($groups as $group): 
                            if (!isset($guests_by_group[$group['id']])) continue;
                            $group_guests = $guests_by_group[$group['id']];
                            if (empty($group_guests)) continue;
                        ?>
                        <div class="group-container">
                            <div class="group-header" style="background-color: <?php echo $group['color']; ?>">
                                <?php echo $group['name']; ?> (<?php echo count($group_guests); ?>名)
                            </div>
                            <div class="group-guests">
                                <?php foreach ($group_guests as $guest): ?>
                                <div class="guest-item" draggable="true" 
                                     data-guest-id="<?php echo $guest['id']; ?>"
                                     data-guest-name="<?php echo htmlspecialchars($guest['name']); ?>"
                                     data-group-id="<?php echo $guest['group_id']; ?>"
                                     style="border-left-color: <?php echo $group['color']; ?>">
                                    <?php echo htmlspecialchars($guest['name']); ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- 席割り当てモーダル -->
    <div class="modal fade" id="assignSeatModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">席の割り当て</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>以下のゲストを割り当てますか？</p>
                    <div id="assign-guest-info"></div>
                    <p>テーブル: <span id="assign-table-id"></span>, 席番号: <span id="assign-seat-number"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" id="confirm-assign">割り当て</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 席割り当て解除モーダル -->
    <div class="modal fade" id="resetSeatModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">席の割り当て解除</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>以下のゲストの席割り当てを解除しますか？</p>
                    <div id="reset-guest-info"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-danger" id="confirm-reset">割り当て解除</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 全席リセット確認モーダル -->
    <div class="modal fade" id="resetAllSeatsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">全席リセット確認</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <p><strong>警告:</strong> すべての席割り当てがリセットされます。この操作は元に戻せません。</p>
                        <p>現在、<?php echo $seated_count; ?>名のゲストに席が割り当てられています。</p>
                    </div>
                    <p>本当にすべての席割り当てをリセットしますか？</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-danger" id="confirm-reset-all">リセット実行</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="../js/seating_new.js"></script>

    <?php
    /**
     * 席の位置を計算する関数
     * 円形テーブルで6席の場合の座標を返す
     */
    function get_seat_position($seat_number, $total_seats) {
        $angle = ($seat_number - 1) * (360 / $total_seats);
        return $angle;
    }

    /**
     * グループの色を取得する関数
     */
    function get_group_color($groups, $group_id) {
        foreach ($groups as $group) {
            if ($group['id'] == $group_id) {
                return $group['color'];
            }
        }
        return '#cccccc'; // デフォルト色
    }
    ?>
</body>
</html> 