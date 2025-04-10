<?php
require_once '../inc/functions.php';
require_once '../inc/header.php';

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// データベース接続
$db = get_db_connection();

// テーブル情報を取得
$stmt = $db->prepare("
    SELECT * FROM seating_tables 
    ORDER BY table_type DESC, table_name
");
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 席の割り当て状況を取得
$stmt = $db->prepare("
    SELECT sa.*, r.name as guest_name, r.guest_id, g.group_name, c.name as companion_name
    FROM seat_assignments sa
    LEFT JOIN responses r ON sa.response_id = r.id
    LEFT JOIN guests g ON r.guest_id = g.id
    LEFT JOIN companions c ON sa.companion_id = c.id
    ORDER BY sa.table_id, sa.seat_number
");
$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 割り当て情報をテーブルごとに整理
$tableAssignments = [];
foreach ($assignments as $assignment) {
    $tableId = $assignment['table_id'];
    if (!isset($tableAssignments[$tableId])) {
        $tableAssignments[$tableId] = [];
    }
    $tableAssignments[$tableId][$assignment['seat_number']] = $assignment;
}

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_table_positions'])) {
        // テーブル位置の保存処理
        foreach ($_POST['table_positions'] as $tableId => $position) {
            $stmt = $db->prepare("
                UPDATE seating_tables 
                SET position_x = ?, position_y = ? 
                WHERE id = ?
            ");
            $stmt->execute([$position['x'], $position['y'], $tableId]);
        }
        
        // 成功メッセージをセット
        $_SESSION['success_message'] = 'テーブルレイアウトが保存されました。';
        header('Location: seating_layout.php');
        exit;
    }
}

// 席の数をカウント
$totalSeats = 0;
$occupiedSeats = 0;
foreach ($tables as $table) {
    $totalSeats += $table['capacity'];
    if (isset($tableAssignments[$table['id']])) {
        $occupiedSeats += count($tableAssignments[$table['id']]);
    }
}

?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">席次表レイアウト設定</h1>
        <div>
            <a href="seating_new.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-users"></i> 席割り当て画面
            </a>
            <a href="../dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> ダッシュボードに戻る
            </a>
        </div>
    </div>
    
    <!-- ステータスカード -->
    <div class="seating-status mb-4">
        <div class="status-card seated">
            <div class="status-number"><?php echo $occupiedSeats; ?></div>
            <div class="status-label">席割当済</div>
        </div>
        <div class="status-card total">
            <div class="status-number"><?php echo $totalSeats; ?></div>
            <div class="status-label">総座席数</div>
        </div>
        <div class="status-card percentage">
            <div class="status-number"><?php echo $totalSeats > 0 ? round(($occupiedSeats / $totalSeats) * 100) : 0; ?>%</div>
            <div class="status-label">席使用率</div>
        </div>
    </div>
    
    <div class="row">
        <!-- レイアウト設定エリア -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">会場レイアウト設定</h5>
                    <div>
                        <button id="resetPositions" class="btn btn-sm btn-warning me-2">
                            <i class="fas fa-undo"></i> 位置をリセット
                        </button>
                        <button id="savePositions" class="btn btn-sm btn-success">
                            <i class="fas fa-save"></i> レイアウトを保存
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted">テーブルをドラッグして配置してください。レイアウトは保存ボタンを押すまで反映されません。</p>
                    
                    <form id="positionForm" method="post">
                        <input type="hidden" name="save_table_positions" value="1">
                        <div id="tablePositions"></div>
                    </form>
                    
                    <div class="venue-container">
                        <div class="venue-header">
                            <div class="venue-title">結婚披露宴 会場レイアウト</div>
                            <div class="kamiza-label">上座</div>
                        </div>
                        
                        <!-- 高砂（主賓卓） -->
                        <div class="high-table">
                            <div class="high-table-inner">高砂（新郎新婦）</div>
                        </div>
                        
                        <!-- テーブル配置エリア -->
                        <div class="tables-area" id="tablesArea">
                            <?php foreach ($tables as $table): ?>
                                <?php
                                    $tableClass = 'venue-table';
                                    if ($table['table_type'] === 'special') {
                                        $tableClass .= ' special-table';
                                    } elseif ($table['table_type'] === 'bridal') {
                                        $tableClass .= ' bridal-table';
                                    }
                                    
                                    $tableStyle = '';
                                    if ($table['position_x'] !== null && $table['position_y'] !== null) {
                                        $tableStyle = "left: {$table['position_x']}px; top: {$table['position_y']}px;";
                                    }
                                    
                                    // テーブルの席使用率を計算
                                    $assignedCount = isset($tableAssignments[$table['id']]) ? count($tableAssignments[$table['id']]) : 0;
                                    $usagePercentage = $table['capacity'] > 0 ? round(($assignedCount / $table['capacity']) * 100) : 0;
                                    
                                    // 使用率に応じたクラスを追加
                                    if ($usagePercentage >= 90) {
                                        $tableClass .= ' table-full';
                                    } elseif ($usagePercentage >= 50) {
                                        $tableClass .= ' table-half';
                                    } elseif ($usagePercentage > 0) {
                                        $tableClass .= ' table-some';
                                    } else {
                                        $tableClass .= ' table-empty';
                                    }
                                ?>
                                <div class="<?php echo $tableClass; ?>" 
                                     id="table-<?php echo $table['id']; ?>" 
                                     data-table-id="<?php echo $table['id']; ?>"
                                     data-table-name="<?php echo htmlspecialchars($table['table_name']); ?>"
                                     data-table-capacity="<?php echo $table['capacity']; ?>"
                                     style="<?php echo $tableStyle; ?>">
                                    <div class="table-name"><?php echo htmlspecialchars($table['table_name']); ?></div>
                                    <div class="table-usage">
                                        <span class="usage-count"><?php echo $assignedCount; ?>/<?php echo $table['capacity']; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="shimoza-label">下座</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- テーブル設定エリア -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">テーブル設定</h5>
                </div>
                <div class="card-body">
                    <div class="table-list">
                        <?php foreach ($tables as $table): ?>
                            <?php
                                $assignedCount = isset($tableAssignments[$table['id']]) ? count($tableAssignments[$table['id']]) : 0;
                                $badgeClass = 'bg-success';
                                if ($assignedCount >= $table['capacity']) {
                                    $badgeClass = 'bg-danger';
                                } elseif ($assignedCount >= $table['capacity'] * 0.7) {
                                    $badgeClass = 'bg-warning text-dark';
                                }
                            ?>
                            <div class="table-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="table-name"><?php echo htmlspecialchars($table['table_name']); ?></span>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo $assignedCount; ?>/<?php echo $table['capacity']; ?></span>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-outline-primary edit-table" 
                                                data-table-id="<?php echo $table['id']; ?>"
                                                data-table-name="<?php echo htmlspecialchars($table['table_name']); ?>"
                                                data-table-capacity="<?php echo $table['capacity']; ?>"
                                                data-table-type="<?php echo $table['table_type']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3">
                        <button id="addTableBtn" class="btn btn-success w-100">
                            <i class="fas fa-plus"></i> 新しいテーブルを追加
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- レイアウト保存ボタン（モバイル用） -->
            <div class="d-lg-none mb-4">
                <button id="savePositionsMobile" class="btn btn-primary w-100">
                    <i class="fas fa-save"></i> レイアウトを保存
                </button>
            </div>
            
            <!-- 凡例 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">使用率の色分け</h5>
                </div>
                <div class="card-body">
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-color table-empty"></div>
                            <span>未使用 (0%)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color table-some"></div>
                            <span>少し使用 (1-49%)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color table-half"></div>
                            <span>半分使用 (50-89%)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color table-full"></div>
                            <span>ほぼ満席 (90-100%)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- テーブル編集モーダル -->
<div class="modal fade" id="editTableModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTableTitle">テーブル編集</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editTableForm" method="post" action="update_table.php">
                    <input type="hidden" id="editTableId" name="table_id">
                    
                    <div class="mb-3">
                        <label for="editTableName" class="form-label">テーブル名</label>
                        <input type="text" class="form-control" id="editTableName" name="table_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editTableCapacity" class="form-label">席数</label>
                        <input type="number" class="form-control" id="editTableCapacity" name="capacity" min="1" max="12" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editTableType" class="form-label">テーブルタイプ</label>
                        <select class="form-select" id="editTableType" name="table_type">
                            <option value="regular">通常テーブル</option>
                            <option value="special">特別テーブル（主賓など）</option>
                            <option value="bridal">新郎新婦テーブル</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="button" class="btn btn-danger" id="deleteTableBtn">削除</button>
                <button type="button" class="btn btn-primary" id="saveTableBtn">保存</button>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="../css/seating.css">
<script>
document.addEventListener('DOMContentLoaded', function() {
    // テーブルのドラッグ＆ドロップを初期化
    const tables = document.querySelectorAll('.venue-table');
    const tablesArea = document.getElementById('tablesArea');
    const positionForm = document.getElementById('positionForm');
    const tablePositions = document.getElementById('tablePositions');
    
    // 各テーブルをドラッグ可能にする
    tables.forEach(table => {
        makeTableDraggable(table);
    });
    
    // テーブルをドラッグ可能にする関数
    function makeTableDraggable(table) {
        let isDragging = false;
        let offsetX, offsetY;
        
        table.addEventListener('mousedown', startDrag);
        table.addEventListener('touchstart', startDrag, { passive: false });
        
        function startDrag(e) {
            if (e.type === 'touchstart') {
                e.preventDefault();
                const touch = e.touches[0];
                offsetX = touch.clientX - table.getBoundingClientRect().left;
                offsetY = touch.clientY - table.getBoundingClientRect().top;
            } else {
                offsetX = e.clientX - table.getBoundingClientRect().left;
                offsetY = e.clientY - table.getBoundingClientRect().top;
            }
            
            isDragging = true;
            table.classList.add('dragging');
            
            document.addEventListener('mousemove', drag);
            document.addEventListener('touchmove', drag, { passive: false });
            document.addEventListener('mouseup', stopDrag);
            document.addEventListener('touchend', stopDrag);
        }
        
        function drag(e) {
            if (!isDragging) return;
            
            let clientX, clientY;
            if (e.type === 'touchmove') {
                e.preventDefault();
                const touch = e.touches[0];
                clientX = touch.clientX;
                clientY = touch.clientY;
            } else {
                clientX = e.clientX;
                clientY = e.clientY;
            }
            
            const areaRect = tablesArea.getBoundingClientRect();
            const tableRect = table.getBoundingClientRect();
            
            let left = clientX - areaRect.left - offsetX;
            let top = clientY - areaRect.top - offsetY;
            
            // テーブルが会場エリアの外に出ないようにする
            left = Math.max(0, Math.min(left, areaRect.width - tableRect.width));
            top = Math.max(0, Math.min(top, areaRect.height - tableRect.height));
            
            table.style.left = left + 'px';
            table.style.top = top + 'px';
            
            // 位置情報を更新
            updateTablePosition(table.dataset.tableId, left, top);
        }
        
        function stopDrag() {
            if (!isDragging) return;
            
            isDragging = false;
            table.classList.remove('dragging');
            
            document.removeEventListener('mousemove', drag);
            document.removeEventListener('touchmove', drag);
            document.removeEventListener('mouseup', stopDrag);
            document.removeEventListener('touchend', stopDrag);
        }
    }
    
    // テーブル位置を更新する関数
    function updateTablePosition(tableId, left, top) {
        // hidden inputを更新または作成
        let input = document.getElementById(`position_${tableId}`);
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.id = `position_${tableId}`;
            input.name = `table_positions[${tableId}][x]`;
            tablePositions.appendChild(input);
            
            const inputY = document.createElement('input');
            inputY.type = 'hidden';
            inputY.id = `position_${tableId}_y`;
            inputY.name = `table_positions[${tableId}][y]`;
            tablePositions.appendChild(inputY);
        }
        
        document.getElementById(`position_${tableId}`).value = left;
        document.getElementById(`position_${tableId}_y`).value = top;
    }
    
    // 保存ボタンのイベント
    document.getElementById('savePositions').addEventListener('click', function() {
        positionForm.submit();
    });
    
    // モバイル用保存ボタン
    const savePositionsMobile = document.getElementById('savePositionsMobile');
    if (savePositionsMobile) {
        savePositionsMobile.addEventListener('click', function() {
            positionForm.submit();
        });
    }
    
    // 位置リセットボタン
    document.getElementById('resetPositions').addEventListener('click', function() {
        if (confirm('テーブルの位置をリセットしますか？')) {
            tables.forEach(table => {
                table.style.left = '';
                table.style.top = '';
                updateTablePosition(table.dataset.tableId, 0, 0);
            });
        }
    });
    
    // テーブル編集ボタンのイベント
    const editButtons = document.querySelectorAll('.edit-table');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tableId = this.dataset.tableId;
            const tableName = this.dataset.tableName;
            const tableCapacity = this.dataset.tableCapacity;
            const tableType = this.dataset.tableType;
            
            document.getElementById('editTableId').value = tableId;
            document.getElementById('editTableName').value = tableName;
            document.getElementById('editTableCapacity').value = tableCapacity;
            document.getElementById('editTableType').value = tableType;
            
            // モーダルのタイトルを設定
            document.getElementById('editTableTitle').textContent = `「${tableName}」テーブルの編集`;
            
            // モーダルを表示
            const modal = new bootstrap.Modal(document.getElementById('editTableModal'));
            modal.show();
        });
    });
    
    // テーブル保存ボタンのイベント
    document.getElementById('saveTableBtn').addEventListener('click', function() {
        document.getElementById('editTableForm').submit();
    });
    
    // テーブル削除ボタンのイベント
    document.getElementById('deleteTableBtn').addEventListener('click', function() {
        const tableId = document.getElementById('editTableId').value;
        const tableName = document.getElementById('editTableName').value;
        
        if (confirm(`「${tableName}」テーブルを削除してもよろしいですか？このテーブルに割り当てられた席も全て削除されます。`)) {
            const form = document.getElementById('editTableForm');
            const deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'action';
            deleteInput.value = 'delete';
            form.appendChild(deleteInput);
            form.submit();
        }
    });
    
    // 新しいテーブル追加ボタン
    document.getElementById('addTableBtn').addEventListener('click', function() {
        // フォームをリセット
        document.getElementById('editTableId').value = '';
        document.getElementById('editTableName').value = '';
        document.getElementById('editTableCapacity').value = '8';
        document.getElementById('editTableType').value = 'regular';
        
        // モーダルのタイトルを設定
        document.getElementById('editTableTitle').textContent = '新しいテーブルを追加';
        
        // 削除ボタンを非表示に
        document.getElementById('deleteTableBtn').style.display = 'none';
        
        // モーダルを表示
        const modal = new bootstrap.Modal(document.getElementById('editTableModal'));
        modal.show();
    });
});
</script>

<?php require_once '../inc/footer.php'; ?> 