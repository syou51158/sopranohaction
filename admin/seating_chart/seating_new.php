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

// ゲストデータを取得（出席するゲストのみ）
$stmt = $db->prepare("
    SELECT g.id, g.name, g.group_name, g.relationship, g.email, g.is_respondent, g.attendance_status,
           COALESCE(s.table_number, 0) AS table_number, COALESCE(s.seat_number, 0) AS seat_number
    FROM guests g
    LEFT JOIN seating s ON g.id = s.guest_id
    WHERE g.attendance_status = 'attending'
    ORDER BY g.group_name, g.is_respondent DESC, g.name
");
$stmt->execute();
$guests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// テーブルごとのゲスト数をカウント
$tableCounts = [];
$maxSeatsPerTable = 6; // 1テーブルあたりの最大席数
$tableCount = 10; // 会場のテーブル数

// 実際に席が割り当てられているテーブルを確認
$assignedTables = [];
$stmt = $db->prepare("SELECT DISTINCT table_number FROM seating WHERE table_number > 0");
$stmt->execute();
$tableResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($tableResults as $table) {
    $assignedTables[$table['table_number']] = true;
}

// テーブルごとの席の割り当て状況を取得
$stmt = $db->prepare("
    SELECT s.table_number, s.seat_number, g.name, g.is_respondent, g.relationship
    FROM seating s
    JOIN guests g ON s.guest_id = g.id
    WHERE s.table_number > 0
    ORDER BY s.table_number, s.seat_number
");
$stmt->execute();
$seatAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// テーブルと席の割り当て状況を整理
$tables = [];
for ($i = 1; $i <= $tableCount; $i++) {
    $tables[$i] = [
        'table_number' => $i,
        'seats' => [],
        'assigned_count' => 0
    ];
    
    // 各席を初期化
    for ($j = 1; $j <= $maxSeatsPerTable; $j++) {
        $tables[$i]['seats'][$j] = [
            'occupied' => false,
            'guest_id' => null,
            'guest_name' => null,
            'is_respondent' => false,
            'relationship' => null
        ];
    }
}

// 割り当て済みの席を設定
foreach ($seatAssignments as $assignment) {
    $tableNum = $assignment['table_number'];
    $seatNum = $assignment['seat_number'];
    
    if (isset($tables[$tableNum]) && isset($tables[$tableNum]['seats'][$seatNum])) {
        $tables[$tableNum]['seats'][$seatNum] = [
            'occupied' => true,
            'guest_name' => $assignment['name'],
            'is_respondent' => $assignment['is_respondent'] == 1,
            'relationship' => $assignment['relationship']
        ];
        $tables[$tableNum]['assigned_count']++;
    }
}

// 席の割り当て済み人数と未割り当て人数をカウント
$assignedCount = 0;
$unassignedCount = 0;

foreach ($guests as $guest) {
    if ($guest['table_number'] > 0 && $guest['seat_number'] > 0) {
        $assignedCount++;
    } else {
        $unassignedCount++;
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">席次表管理</h1>
        <div>
            <a href="seating_layout.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-th"></i> レイアウト設定
            </a>
            <a href="../dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> ダッシュボードに戻る
            </a>
        </div>
    </div>
    
    <!-- ステータスカード -->
    <div class="seating-status">
        <div class="status-card seated">
            <div class="status-number"><?php echo $assignedCount; ?></div>
            <div class="status-label">席割当済</div>
        </div>
        <div class="status-card unassigned">
            <div class="status-number"><?php echo $unassignedCount; ?></div>
            <div class="status-label">未割当</div>
        </div>
    </div>
    
    <!-- 席次表と未割り当てゲスト -->
    <div class="row">
        <!-- 席次表エリア -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">席次表レイアウト</h5>
                </div>
                <div class="card-body">
                    <!-- 凡例 -->
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-color empty"></div>
                            <span>空席</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color occupied"></div>
                            <span>割当済</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color respondent"></div>
                            <span>主回答者</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color companion"></div>
                            <span>同伴者</span>
                        </div>
                    </div>
                    
                    <!-- 会場レイアウト -->
                    <div class="venue-layout mt-4">
                        <div class="venue-header">
                            <h2>結婚披露宴 席次表</h2>
                            <div class="venue-date">2023年12月10日（日）</div>
                            <div class="venue-place">ホテル〇〇〇〇 3F グランドホール</div>
                        </div>
                        
                        <!-- 上座・下座のラベル -->
                        <div class="position-labels">
                            <div class="kamiza">上座</div>
                            <div class="shimoza">下座</div>
                        </div>
                        
                        <!-- グループラベル -->
                        <div class="group-labels">
                            <div class="group-label">会社関係</div>
                            <div class="group-label">友人</div>
                            <div class="group-label">親族</div>
                        </div>
                        
                        <!-- テーブル背景 -->
                        <div class="tables-background">
                            <!-- 高砂 -->
                            <div class="high-table">
                                <div class="high-table-box">高砂（新郎新婦）</div>
                            </div>
                            
                            <!-- テーブルエリア -->
                            <div class="seating-area">
                                <div class="tables-area">
                                    <?php for ($tableNum = 1; $tableNum <= $tableCount; $tableNum++): ?>
                                        <div class="round-table-container" data-table="<?php echo $tableNum; ?>">
                                            <div class="round-table">
                                                <div class="table-number"><?php echo $tableNum; ?></div>
                                                
                                                <?php for ($seatNum = 1; $seatNum <= $maxSeatsPerTable; $seatNum++): 
                                                    $seat = $tables[$tableNum]['seats'][$seatNum];
                                                    $seatClass = 'seat seat-pos-' . $seatNum;
                                                    if ($seat['occupied']) {
                                                        $seatClass .= ' occupied';
                                                        if ($seat['is_respondent']) {
                                                            $seatClass .= ' respondent';
                                                        } else {
                                                            $seatClass .= ' companion';
                                                        }
                                                    }
                                                ?>
                                                <div class="<?php echo $seatClass; ?>" 
                                                     data-table="<?php echo $tableNum; ?>" 
                                                     data-seat="<?php echo $seatNum; ?>"
                                                     data-occupied="<?php echo $seat['occupied'] ? '1' : '0'; ?>">
                                                    <div class="seat-number"><?php echo $seatNum; ?></div>
                                                    <div class="seat-layer">
                                                        <?php 
                                                        // 席の層（上座・下座）を表示
                                                        if ($seatNum == 1 || $seatNum == 2 || $seatNum == 6) {
                                                            echo "上座";
                                                        } else {
                                                            echo "下座";
                                                        }
                                                        ?>
                                                    </div>
                                                    <div class="seat-guest <?php echo $seat['occupied'] ? '' : 'empty'; ?>">
                                                        <?php echo $seat['occupied'] ? htmlspecialchars($seat['guest_name']) : '空席'; ?>
                                                    </div>
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
            </div>
        </div>
        
        <!-- 未割り当てゲスト -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">未割り当てゲスト</h5>
                    <small class="text-muted">ドラッグして席に割り当ててください</small>
                </div>
                <div class="card-body">
                    <div class="unassigned-guests">
                        <?php 
                        $currentGroup = '';
                        foreach ($guests as $guest) {
                            if ($guest['table_number'] == 0 || $guest['seat_number'] == 0) {
                                $guestClass = 'guest-card';
                                if ($guest['is_respondent'] == 1) {
                                    $guestClass .= ' respondent';
                                } else {
                                    $guestClass .= ' companion';
                                }
                                
                                // グループごとに区切りを表示
                                if ($currentGroup != $guest['group_name']) {
                                    $currentGroup = $guest['group_name'];
                                    echo '<div class="group-header mt-3 mb-2">' . htmlspecialchars($currentGroup) . '</div>';
                                }
                        ?>
                        <div class="<?php echo $guestClass; ?>" 
                             draggable="true" 
                             data-guest-id="<?php echo $guest['id']; ?>"
                             data-guest-name="<?php echo htmlspecialchars($guest['name']); ?>"
                             data-is-respondent="<?php echo $guest['is_respondent']; ?>"
                             data-relationship="<?php echo htmlspecialchars($guest['relationship']); ?>">
                            <div class="guest-name"><?php echo htmlspecialchars($guest['name']); ?></div>
                            <div class="guest-group"><?php echo htmlspecialchars($guest['group_name']); ?></div>
                            <?php if (!empty($guest['relationship'])): ?>
                            <div class="guest-relationship"><?php echo htmlspecialchars($guest['relationship']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php 
                            }
                        } 
                        ?>
                        
                        <?php if ($unassignedCount == 0): ?>
                        <div class="alert alert-success">
                            すべてのゲストが席に割り当てられています。
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- 席次表の説明 -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">席次表ガイド</h5>
                </div>
                <div class="card-body">
                    <div class="seating-instructions">
                        <ul>
                            <li><strong>上座（席番号1,2,6）</strong>: テーブルの上座側の席です。目上の方や年配の方を配置します。</li>
                            <li><strong>下座（席番号3,4,5）</strong>: テーブルの下座側の席です。若い方を配置します。</li>
                            <li>ドラッグ＆ドロップで未割り当てのゲストを席に割り当てることができます。</li>
                            <li>席をクリックすると、詳細メニューが表示されます。</li>
                            <li>席の割り当ては自動的に保存されます。</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- 印刷ボタンなど -->
            <div class="mt-3">
                <button id="printSeatingChart" class="btn btn-primary w-100">
                    <i class="fas fa-print"></i> 席次表を印刷する
                </button>
            </div>
        </div>
    </div>
    
    <!-- 席割り当てモーダル -->
    <div class="modal fade" id="assignSeatModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">席の割り当て</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="assignSeatForm">
                        <input type="hidden" id="assignTableNumber" name="table_number">
                        <input type="hidden" id="assignSeatNumber" name="seat_number">
                        
                        <div class="mb-3">
                            <label for="guestSelect" class="form-label">ゲストを選択</label>
                            <select class="form-select" id="guestSelect" name="guest_id" required>
                                <option value="">選択してください</option>
                                <?php 
                                foreach ($guests as $guest) {
                                    if ($guest['table_number'] == 0 || $guest['seat_number'] == 0) {
                                        echo '<option value="' . $guest['id'] . '">' . 
                                             htmlspecialchars($guest['name']) . 
                                             ' (' . htmlspecialchars($guest['group_name']) . ')' .
                                             '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" id="confirmAssignSeat">割り当て</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 席解除モーダル -->
    <div class="modal fade" id="removeSeatModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">席の割り当て解除</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>テーブル <span id="removeTableNumber"></span> の席 <span id="removeSeatNumber"></span> の割り当てを解除しますか？</p>
                    <p>ゲスト: <strong id="removeGuestName"></strong></p>
                    <input type="hidden" id="removeGuestId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-danger" id="confirmRemoveSeat">割り当て解除</button>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="../css/seating.css">

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ドラッグ可能なゲストカード
    const guestCards = document.querySelectorAll('.guest-card');
    const seats = document.querySelectorAll('.seat');
    let draggedGuest = null;
    
    // ドラッグアンドドロップ機能の初期化
    guestCards.forEach(card => {
        card.addEventListener('dragstart', function(e) {
            draggedGuest = this;
            this.classList.add('dragging');
            e.dataTransfer.setData('text/plain', this.dataset.guestId);
        });
        
        card.addEventListener('dragend', function() {
            this.classList.remove('dragging');
        });
    });
    
    // 席へのドロップイベント
    seats.forEach(seat => {
        seat.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drop-hover');
        });
        
        seat.addEventListener('dragleave', function() {
            this.classList.remove('drop-hover');
        });
        
        seat.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drop-hover');
            
            if (draggedGuest) {
                const guestId = draggedGuest.dataset.guestId;
                const guestName = draggedGuest.dataset.guestName;
                const isRespondent = draggedGuest.dataset.isRespondent;
                const tableNumber = this.dataset.table;
                const seatNumber = this.dataset.seat;
                
                // 既に席が埋まっていない場合のみ割り当て
                if (this.dataset.occupied === '0') {
                    assignSeat(guestId, tableNumber, seatNumber, guestName, isRespondent);
                }
            }
        });
        
        // 席のクリックイベント
        seat.addEventListener('click', function() {
            const tableNumber = this.dataset.table;
            const seatNumber = this.dataset.seat;
            const occupied = this.dataset.occupied === '1';
            
            if (occupied) {
                // 席が埋まっている場合は解除モーダルを表示
                const guestName = this.querySelector('.seat-guest').textContent.trim();
                document.getElementById('removeTableNumber').textContent = tableNumber;
                document.getElementById('removeSeatNumber').textContent = seatNumber;
                document.getElementById('removeGuestName').textContent = guestName;
                
                // AJAXでゲストIDを取得
                fetch(`get_seat_guest.php?table=${tableNumber}&seat=${seatNumber}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('removeGuestId').value = data.guest_id;
                            const removeSeatModal = new bootstrap.Modal(document.getElementById('removeSeatModal'));
                            removeSeatModal.show();
                        }
                    });
            } else {
                // 席が空いている場合は割り当てモーダルを表示
                document.getElementById('assignTableNumber').value = tableNumber;
                document.getElementById('assignSeatNumber').value = seatNumber;
                const assignSeatModal = new bootstrap.Modal(document.getElementById('assignSeatModal'));
                assignSeatModal.show();
            }
        });
    });
    
    // 席の割り当てを実行
    function assignSeat(guestId, tableNumber, seatNumber, guestName, isRespondent) {
        const formData = new FormData();
        formData.append('guest_id', guestId);
        formData.append('table_number', tableNumber);
        formData.append('seat_number', seatNumber);
        formData.append('action', 'assign');
        
        fetch('update_seating.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 成功したら画面を更新
                const seat = document.querySelector(`.seat[data-table="${tableNumber}"][data-seat="${seatNumber}"]`);
                seat.classList.add('occupied');
                if (isRespondent === '1') {
                    seat.classList.add('respondent');
                } else {
                    seat.classList.add('companion');
                }
                seat.dataset.occupied = '1';
                
                // 席のゲスト名を更新
                const guestElement = seat.querySelector('.seat-guest');
                guestElement.textContent = guestName;
                guestElement.classList.remove('empty');
                
                // ドラッグしたゲストカードを削除
                if (draggedGuest) {
                    draggedGuest.remove();
                }
                
                // ステータスカードの数値を更新
                updateStatusCards(1);
                
                // ゲスト選択リストから該当ゲストを削除
                const option = document.querySelector(`#guestSelect option[value="${guestId}"]`);
                if (option) {
                    option.remove();
                }
            } else {
                alert('席の割り当てに失敗しました: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('通信エラーが発生しました。');
        });
    }
    
    // モーダルからの席割り当て
    document.getElementById('confirmAssignSeat').addEventListener('click', function() {
        const form = document.getElementById('assignSeatForm');
        const guestId = form.elements['guest_id'].value;
        const tableNumber = form.elements['table_number'].value;
        const seatNumber = form.elements['seat_number'].value;
        
        if (!guestId) {
            alert('ゲストを選択してください。');
            return;
        }
        
        // 選択されたゲストの情報を取得
        const guestOption = document.querySelector(`#guestSelect option[value="${guestId}"]`);
        const guestName = guestOption.textContent.split('(')[0].trim();
        
        // AJAXでゲストが主回答者かどうかを確認
        fetch(`get_guest_info.php?id=${guestId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    assignSeat(guestId, tableNumber, seatNumber, guestName, data.is_respondent);
                    const assignSeatModal = bootstrap.Modal.getInstance(document.getElementById('assignSeatModal'));
                    assignSeatModal.hide();
                }
            });
    });
    
    // 席の割り当て解除を実行
    document.getElementById('confirmRemoveSeat').addEventListener('click', function() {
        const guestId = document.getElementById('removeGuestId').value;
        const tableNumber = document.getElementById('removeTableNumber').textContent;
        const seatNumber = document.getElementById('removeSeatNumber').textContent;
        
        const formData = new FormData();
        formData.append('guest_id', guestId);
        formData.append('table_number', tableNumber);
        formData.append('seat_number', seatNumber);
        formData.append('action', 'remove');
        
        fetch('update_seating.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 成功したら画面を更新
                const seat = document.querySelector(`.seat[data-table="${tableNumber}"][data-seat="${seatNumber}"]`);
                seat.classList.remove('occupied', 'respondent', 'companion');
                seat.dataset.occupied = '0';
                
                // 席のゲスト名をリセット
                const guestElement = seat.querySelector('.seat-guest');
                guestElement.textContent = '空席';
                guestElement.classList.add('empty');
                
                // ステータスカードの数値を更新
                updateStatusCards(-1);
                
                // モーダルを閉じる
                const removeSeatModal = bootstrap.Modal.getInstance(document.getElementById('removeSeatModal'));
                removeSeatModal.hide();
                
                // ゲスト選択リストに該当ゲストを追加
                fetch(`get_guest_info.php?id=${guestId}`)
                    .then(response => response.json())
                    .then(guest => {
                        if (guest.success) {
                            // 未割り当てゲスト一覧に追加
                            addUnassignedGuestCard(guest);
                            
                            // ゲスト選択リストに追加
                            const select = document.getElementById('guestSelect');
                            const option = document.createElement('option');
                            option.value = guest.id;
                            option.textContent = `${guest.name} (${guest.group_name})`;
                            select.appendChild(option);
                        }
                    });
            } else {
                alert('席の割り当て解除に失敗しました: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('通信エラーが発生しました。');
        });
    });
    
    // 未割り当てゲストカードを追加
    function addUnassignedGuestCard(guest) {
        const unassignedGuests = document.querySelector('.unassigned-guests');
        
        // グループヘッダーの存在チェック
        let groupHeader = unassignedGuests.querySelector(`.group-header:contains("${guest.group_name}")`);
        if (!groupHeader) {
            groupHeader = document.createElement('div');
            groupHeader.className = 'group-header mt-3 mb-2';
            groupHeader.textContent = guest.group_name;
            unassignedGuests.appendChild(groupHeader);
        }
        
        // ゲストカードの作成
        const card = document.createElement('div');
        card.className = 'guest-card';
        if (guest.is_respondent == 1) {
            card.classList.add('respondent');
        } else {
            card.classList.add('companion');
        }
        
        card.setAttribute('draggable', 'true');
        card.dataset.guestId = guest.id;
        card.dataset.guestName = guest.name;
        card.dataset.isRespondent = guest.is_respondent;
        card.dataset.relationship = guest.relationship || '';
        
        // カード内容の作成
        const nameDiv = document.createElement('div');
        nameDiv.className = 'guest-name';
        nameDiv.textContent = guest.name;
        
        const groupDiv = document.createElement('div');
        groupDiv.className = 'guest-group';
        groupDiv.textContent = guest.group_name;
        
        card.appendChild(nameDiv);
        card.appendChild(groupDiv);
        
        if (guest.relationship) {
            const relationDiv = document.createElement('div');
            relationDiv.className = 'guest-relationship';
            relationDiv.textContent = guest.relationship;
            card.appendChild(relationDiv);
        }
        
        unassignedGuests.appendChild(card);
        
        // ドラッグ機能の追加
        card.addEventListener('dragstart', function(e) {
            draggedGuest = this;
            this.classList.add('dragging');
            e.dataTransfer.setData('text/plain', this.dataset.guestId);
        });
        
        card.addEventListener('dragend', function() {
            this.classList.remove('dragging');
        });
        
        // 空のメッセージがあれば削除
        const emptyMessage = unassignedGuests.querySelector('.alert-success');
        if (emptyMessage) {
            emptyMessage.remove();
        }
    }
    
    // ステータスカードの数値を更新
    function updateStatusCards(change) {
        const assignedCard = document.querySelector('.status-card.seated .status-number');
        const unassignedCard = document.querySelector('.status-card.unassigned .status-number');
        
        let assignedCount = parseInt(assignedCard.textContent);
        let unassignedCount = parseInt(unassignedCard.textContent);
        
        assignedCount += change;
        unassignedCount -= change;
        
        assignedCard.textContent = assignedCount;
        unassignedCard.textContent = unassignedCount;
        
        // すべてのゲストが割り当てられた場合のメッセージ
        if (unassignedCount === 0) {
            const unassignedGuests = document.querySelector('.unassigned-guests');
            if (!unassignedGuests.querySelector('.alert-success')) {
                const message = document.createElement('div');
                message.className = 'alert alert-success';
                message.textContent = 'すべてのゲストが席に割り当てられています。';
                unassignedGuests.appendChild(message);
            }
        }
    }
    
    // 印刷機能
    document.getElementById('printSeatingChart').addEventListener('click', function() {
        window.print();
    });
    
    // jQuery拡張メソッド（テキスト内容で要素を検索）
    jQuery.expr[':'].contains = function(a, i, m) {
        return jQuery(a).text().toUpperCase().indexOf(m[3].toUpperCase()) >= 0;
    };
});
</script>

<?php require_once '../inc/footer.php'; ?> 