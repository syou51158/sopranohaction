<?php
/**
 * 席次表管理システム - メイン画面
 */
require_once '../config.php';
require_once 'inc/functions.php';

// 管理者権限チェック
check_admin();

// データベース接続
$db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. 出席するゲスト情報の取得 (responsesテーブルから出席者を取得)
$guests_query = $db->prepare("
    SELECT 
        r.id, 
        r.name, 
        r.email, 
        g.group_name,
        sa.table_number,
        sa.seat_number
    FROM 
        responses r
    LEFT JOIN
        guests g ON r.guest_id = g.id
    LEFT JOIN 
        seating_assignments sa ON r.id = sa.guest_id
    WHERE 
        r.attending = 1
    ORDER BY 
        g.group_name, 
        r.name
");
$guests_query->execute();
$guests = $guests_query->fetchAll(PDO::FETCH_ASSOC);

// 2. 未割り当てのゲストを抽出
$unassigned_guests = [];
foreach ($guests as $guest) {
    if (empty($guest['table_number'])) {
        $unassigned_guests[] = $guest;
    }
}

// 3. 割り当て済みの席情報を取得
$seating_query = $db->prepare("
    SELECT 
        sa.table_number,
        sa.seat_number,
        r.id as guest_id,
        r.name as guest_name,
        g.group_name
    FROM 
        seating_assignments sa
    JOIN 
        responses r ON sa.guest_id = r.id
    LEFT JOIN
        guests g ON r.guest_id = g.id
    ORDER BY 
        sa.table_number, 
        sa.seat_number
");
$seating_query->execute();
$seated_guests = $seating_query->fetchAll(PDO::FETCH_ASSOC);

// 4. 統計情報の計算
$total_guests = count($guests);
$total_assigned = count($seated_guests);
$total_unassigned = $total_guests - $total_assigned;

// 5. テーブル情報（現在は固定で6テーブル、各テーブル6席を想定）
$tables = [];
$max_tables = 6;  // テーブル数
$seats_per_table = 6;  // 1テーブルあたりの席数

// ページタイトル
$page_title = "席次表管理";
include 'inc/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'inc/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">席次表管理</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="print-seating">
                            <i class="bi bi-printer"></i> 印刷
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="export-csv">
                            <i class="bi bi-file-earmark-excel"></i> CSVエクスポート
                        </button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="reset-all-seats">
                        <i class="bi bi-trash"></i> 全席リセット
                    </button>
                </div>
            </div>
            
            <!-- 統計情報 -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">出席者数</h5>
                            <p class="card-text display-4"><?php echo $total_guests; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">割り当て済み</h5>
                            <p class="card-text display-4"><?php echo $total_assigned; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">未割り当て</h5>
                            <p class="card-text display-4"><?php echo $total_unassigned; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- 席次配置エリア -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5>席次配置</h5>
                        </div>
                        <div class="card-body">
                            <div class="venue-layout">
                                <div class="venue-header">
                                    <h4>会場レイアウト</h4>
                                </div>
                                
                                <!-- 高砂（メインテーブル） -->
                                <div class="high-table-area mb-4">
                                    <div class="high-table">
                                        <div class="high-table-label">高砂</div>
                                    </div>
                                </div>
                                
                                <!-- テーブルエリア -->
                                <div class="tables-area">
                                    <?php for ($table = 1; $table <= $max_tables; $table++): ?>
                                    <div class="table-container" data-table="<?php echo $table; ?>">
                                        <div class="table-number">テーブル <?php echo $table; ?></div>
                                        <div class="round-table">
                                            <?php for ($seat = 1; $seat <= $seats_per_table; $seat++): 
                                                // 席に割り当てられているゲストを検索
                                                $assigned_guest = null;
                                                foreach ($seated_guests as $seated) {
                                                    if ($seated['table_number'] == $table && $seated['seat_number'] == $seat) {
                                                        $assigned_guest = $seated;
                                                        break;
                                                    }
                                                }
                                                
                                                $seat_class = $assigned_guest ? 'occupied' : 'empty';
                                                $guest_name = $assigned_guest ? htmlspecialchars($assigned_guest['guest_name']) : '';
                                                $guest_group = $assigned_guest ? htmlspecialchars($assigned_guest['group_name']) : '';
                                            ?>
                                            <div class="seat <?php echo $seat_class; ?>" 
                                                 data-table-id="<?php echo $table; ?>" 
                                                 data-seat-number="<?php echo $seat; ?>">
                                                <div class="seat-number"><?php echo $seat; ?></div>
                                                <?php if ($assigned_guest): ?>
                                                <div class="guest-info">
                                                    <div class="guest-name"><?php echo $guest_name; ?></div>
                                                    <div class="guest-group"><?php echo $guest_group; ?></div>
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
                
                <!-- 未割り当てゲストリスト -->
                <div class="col-md-4">
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
                        <div class="card-body unassigned-guests">
                            <?php if (empty($unassigned_guests)): ?>
                                <div class="alert alert-success">
                                    全てのゲストが席に割り当てられています。
                                </div>
                            <?php else: ?>
                                <?php foreach ($unassigned_guests as $guest): ?>
                                <div class="guest-card" data-guest-id="<?php echo $guest['id']; ?>">
                                    <div class="guest-card-name"><?php echo htmlspecialchars($guest['name']); ?></div>
                                    <div class="guest-card-group"><?php echo htmlspecialchars($guest['group_name']); ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
                <p>以下のゲストの席の割り当てを解除しますか？</p>
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
<script src="js/seating_new.js"></script>

<?php include 'inc/footer.php'; ?>