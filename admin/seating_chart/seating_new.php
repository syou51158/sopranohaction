<?php
/**
 * 席次表管理システム - 新UI
 * 円卓レイアウトでの席次表管理画面
 */
require_once '../../config.php';
require_once '../inc/functions.php';

// 管理者権限チェック
check_admin();

// データベース接続
$db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// テーブルの数と1テーブルあたりの最大席数を設定
$max_tables = 10; // テーブル数
$seats_per_table = 6; // 1テーブルあたりの最大席数

// 1. 出席するゲスト情報を取得
$guests_query = $db->prepare("
    SELECT 
        g.id, 
        g.name, 
        g.group_name,
        s.table_number,
        s.seat_number
    FROM 
        guests g
    LEFT JOIN 
        seats s ON g.id = s.guest_id
    WHERE 
        g.is_attending = 1
    ORDER BY 
        g.group_name, 
        g.name
");
$guests_query->execute();
$guests = $guests_query->fetchAll(PDO::FETCH_ASSOC);

// 2. 割り当て済みの席情報を取得
$seated_query = $db->prepare("
    SELECT 
        s.table_number,
        s.seat_number,
        g.id as guest_id,
        g.name,
        g.group_name
    FROM 
        seats s
    JOIN 
        guests g ON s.guest_id = g.id
    ORDER BY 
        s.table_number, 
        s.seat_number
");
$seated_query->execute();
$seated_guests = $seated_query->fetchAll(PDO::FETCH_ASSOC);

// 3. 未割り当てのゲストを抽出
$unassigned_guests = [];
foreach ($guests as $guest) {
    if (empty($guest['table_number'])) {
        $unassigned_guests[] = $guest;
    }
}

// 4. 統計情報の集計
$total_guests = count($guests);
$total_assigned = count($seated_guests);
$total_unassigned = $total_guests - $total_assigned;

// 5. グループ情報の集計
$groups = [];
foreach ($guests as $guest) {
    $group_name = $guest['group_name'] ?: '未分類';
    if (!isset($groups[$group_name])) {
        $groups[$group_name] = [
            'total' => 0,
            'assigned' => 0,
            'name' => $group_name
        ];
    }
    $groups[$group_name]['total']++;
    if (!empty($guest['table_number'])) {
        $groups[$group_name]['assigned']++;
    }
}

// ページタイトル
$page_title = "新席次表管理";
include '../inc/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../inc/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">新席次表管理</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="print-seating">
                            <i class="bi bi-printer"></i> 印刷
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="export-csv">
                            <i class="bi bi-file-earmark-excel"></i> CSVエクスポート
                        </button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="reset-all-seats" data-bs-toggle="modal" data-bs-target="#resetAllSeatsModal">
                        <i class="bi bi-trash"></i> 全席リセット
                    </button>
                </div>
            </div>
            
            <!-- 統計情報カード -->
            <div class="row mb-4">
                <div class="col-md-4 col-lg-3 mb-3">
                    <div class="status-card">
                        <div class="status-icon total">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="status-info">
                            <h3>総ゲスト数</h3>
                            <div class="status-count"><?php echo $total_guests; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-3 mb-3">
                    <div class="status-card">
                        <div class="status-icon seated">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div class="status-info">
                            <h3>割り当て済み</h3>
                            <div class="status-count"><?php echo $total_assigned; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-3 mb-3">
                    <div class="status-card">
                        <div class="status-icon unassigned">
                            <i class="bi bi-exclamation-circle-fill"></i>
                        </div>
                        <div class="status-info">
                            <h3>未割り当て</h3>
                            <div class="status-count"><?php echo $total_unassigned; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-lg-3 mb-3">
                    <div class="status-card">
                        <div class="status-icon percentage">
                            <i class="bi bi-pie-chart-fill"></i>
                        </div>
                        <div class="status-info">
                            <h3>完了率</h3>
                            <div class="status-count">
                                <?php 
                                $percentage = $total_guests > 0 ? round(($total_assigned / $total_guests) * 100) : 0;
                                echo $percentage . '%'; 
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- 会場レイアウト -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>会場レイアウト</h5>
                        </div>
                        <div class="card-body">
                            <div class="venue-layout">
                                <div class="venue-header">
                                    <h4>席次表</h4>
                                </div>
                                
                                <!-- 高砂（メインテーブル） -->
                                <div class="high-table-area mb-4">
                                    <div class="high-table">
                                        <div class="high-table-box">
                                            <div class="bride-groom-label">新郎新婦</div>
                                        </div>
                                    </div>
                                    <div class="position-labels">
                                        <div class="kamiza">上座</div>
                                        <div class="shimoza">下座</div>
                                    </div>
                                </div>
                                
                                <!-- グループラベル -->
                                <div class="group-labels mb-3">
                                    <div class="group-label">新郎側</div>
                                    <div class="group-label">新婦側</div>
                                </div>
                                
                                <!-- テーブルエリア -->
                                <div class="tables-area">
                                    <?php for ($table = 1; $table <= $max_tables; $table++): ?>
                                    <div class="round-table-container">
                                        <div class="table-number">テーブル<?php echo $table; ?></div>
                                        <div class="round-table" data-table="<?php echo $table; ?>">
                                            <?php for ($seat = 1; $seat <= $seats_per_table; $seat++): 
                                                // 席に割り当てられているゲストを検索
                                                $assigned_guest = null;
                                                foreach ($seated_guests as $seated) {
                                                    if ($seated['table_number'] == $table && $seated['seat_number'] == $seat) {
                                                        $assigned_guest = $seated;
                                                        break;
                                                    }
                                                }
                                                
                                                $occupied_class = $assigned_guest ? 'occupied' : '';
                                            ?>
                                            <div class="seat <?php echo $occupied_class; ?>" 
                                                 data-table-id="<?php echo $table; ?>" 
                                                 data-seat-number="<?php echo $seat; ?>">
                                                <div class="seat-number"><?php echo $seat; ?></div>
                                                <?php if ($assigned_guest): ?>
                                                <div class="seat-guest">
                                                    <div class="guest-name"><?php echo htmlspecialchars($assigned_guest['name']); ?></div>
                                                    <div class="guest-group"><?php echo htmlspecialchars($assigned_guest['group_name']); ?></div>
                                                </div>
                                                <?php else: ?>
                                                <div class="seat-guest empty">
                                                    <div>空席</div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 未割り当てゲスト -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>未割り当てゲスト</h5>
                            <div class="input-group mt-2">
                                <input type="text" class="form-control" id="guest-search" placeholder="ゲスト検索...">
                                <button class="btn btn-outline-secondary" type="button" id="search-btn">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="unassigned-area">
                                <?php if (empty($unassigned_guests)): ?>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle-fill"></i> 全てのゲストが席に割り当てられています。
                                </div>
                                <?php else: ?>
                                <div class="unassigned-guests">
                                    <?php foreach ($unassigned_guests as $guest): ?>
                                    <div class="guest-card" data-guest-id="<?php echo $guest['id']; ?>">
                                        <div class="guest-card-name"><?php echo htmlspecialchars($guest['name']); ?></div>
                                        <div class="guest-card-group"><?php echo htmlspecialchars($guest['group_name']); ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- グループ一覧 -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5>グループ別状況</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>グループ</th>
                                            <th>割当/合計</th>
                                            <th>進捗</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($groups as $group): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($group['name']); ?></td>
                                            <td><?php echo $group['assigned']; ?>/<?php echo $group['total']; ?></td>
                                            <td>
                                                <?php 
                                                $group_percentage = $group['total'] > 0 ? round(($group['assigned'] / $group['total']) * 100) : 0;
                                                ?>
                                                <div class="progress">
                                                    <div class="progress-bar <?php echo $group_percentage == 100 ? 'bg-success' : 'bg-primary'; ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo $group_percentage; ?>%" 
                                                         aria-valuenow="<?php echo $group_percentage; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        <?php echo $group_percentage; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- 席割り当てモーダル -->
<div class="modal fade" id="assignSeatModal" tabindex="-1" aria-labelledby="assignSeatModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignSeatModalLabel">席の割り当て</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>テーブル <span id="selected-table"></span> の席 <span id="selected-seat"></span> に以下のゲストを割り当てますか？</p>
                <div id="selected-guest-info"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="button" class="btn btn-primary" id="confirm-assign">割り当て</button>
            </div>
        </div>
    </div>
</div>

<!-- 席解除モーダル -->
<div class="modal fade" id="resetSeatModal" tabindex="-1" aria-labelledby="resetSeatModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetSeatModalLabel">席の割り当て解除</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>テーブル <span id="reset-table"></span> の席 <span id="reset-seat"></span> のゲストの割り当てを解除しますか？</p>
                <div id="reset-guest-info"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="button" class="btn btn-danger" id="confirm-reset">割り当て解除</button>
            </div>
        </div>
    </div>
</div>

<!-- 全席リセットモーダル -->
<div class="modal fade" id="resetAllSeatsModal" tabindex="-1" aria-labelledby="resetAllSeatsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetAllSeatsModalLabel">全席リセット確認</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <p><strong>警告:</strong> この操作は全ての席の割り当てを削除します。この操作は元に戻せません。</p>
                    <p>本当に全席の割り当てをリセットしますか？</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="button" class="btn btn-danger" id="confirm-reset-all">全席リセット</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScriptの読み込み -->
<script src="../js/seating_new.js"></script>

<?php include '../inc/footer.php'; ?> 