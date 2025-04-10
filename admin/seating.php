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

// 座席データを取得する関数
function getSeatData($table_id, $seat_number, $assignments) {
    foreach ($assignments as $assignment) {
        if ($assignment['table_id'] == $table_id && $assignment['seat_number'] == $seat_number) {
            // データベースから取得したlayer_textを肩書に修正
            if (isset($assignment['layer_text']) && $assignment['layer_text'] === '層書き') {
                $assignment['layer_text'] = '肩書';
            }
            return $assignment;
        }
    }
    return null;
}

try {
    // テーブル情報を取得
    $stmt = $pdo->query("SELECT * FROM seating_tables ORDER BY table_name");
    $tables = $stmt->fetchAll();
    
    // 出席する回答者と同伴者を一緒に取得（席が割り当てられていない人のみ）
    $stmt = $pdo->prepare("
        -- 回答者のクエリ
        SELECT 
            'respondent' AS person_type,
            r.id AS person_id,
            r.name AS person_name,
            r.title AS person_title,
            g.group_name,
            NULL AS response_id,
            NULL AS age_group,
            NULL AS relationship
        FROM responses r
        LEFT JOIN guests g ON r.guest_id = g.id
        LEFT JOIN seat_assignments sa ON r.id = sa.response_id AND sa.is_companion = 0
        WHERE r.attending = 1
        AND sa.id IS NULL
        
        UNION ALL
        
        -- 同伴者のクエリ
        SELECT 
            'companion' AS person_type,
            c.id AS person_id,
            c.name AS person_name,
            c.title AS person_title,
            g.group_name,
            c.response_id,
            c.age_group,
            r.name AS relationship
        FROM companions c
        JOIN responses r ON c.response_id = r.id
        JOIN guests g ON r.guest_id = g.id
        LEFT JOIN seat_assignments sa ON c.id = sa.companion_id
        WHERE r.attending = 1
        AND sa.id IS NULL
        
        ORDER BY group_name, person_name
    ");
    $stmt->execute();
    $unassigned_people = $stmt->fetchAll();
    
    // デバッグ情報を表示
    if (isset($_GET['debug']) && $_GET['debug'] == 1) {
        echo '<div class="alert alert-info">';
        echo '<h5>デバッグ情報</h5>';
        echo '<p>未割り当てのゲスト数: ' . count($unassigned_people) . '</p>';
        echo '<p>SQL: ' . str_replace("\n", "<br>", $stmt->queryString) . '</p>';
        echo '</div>';
    }
    
    // 既に割り当てられた席を取得
    $stmt = $pdo->query("
        SELECT sa.*, st.table_name, st.table_type, 
               r.name AS guest_name, r.title AS guest_title, g.group_name,
               c.name AS companion_name, c.title AS companion_title
        FROM seat_assignments sa
        LEFT JOIN seating_tables st ON sa.table_id = st.id
        LEFT JOIN responses r ON sa.response_id = r.id
        LEFT JOIN guests g ON r.guest_id = g.id
        LEFT JOIN companions c ON sa.companion_id = c.id
        ORDER BY st.table_name, sa.seat_number
    ");
    $assignments = $stmt->fetchAll();
    
    // 座席割り当てを整理
    $seating_plan = [];
    foreach ($tables as $table) {
        $seating_plan[$table['id']] = [
            'id' => $table['id'],
            'table_name' => $table['table_name'],
            'capacity' => $table['capacity'],
            'table_type' => $table['table_type'],
            'seats' => array_fill(1, $table['capacity'], null)
        ];
    }
    
    foreach ($assignments as $assignment) {
        $table_id = $assignment['table_id'];
        $seat_number = $assignment['seat_number'];
        
        if (isset($seating_plan[$table_id]['seats'][$seat_number])) {
            $guest_name = $assignment['is_companion'] ? $assignment['companion_name'] : $assignment['guest_name'];
            $guest_title = $assignment['is_companion'] ? $assignment['companion_title'] : $assignment['guest_title'];
            $group_name = $assignment['group_name'] ?? '';
            
            $seating_plan[$table_id]['seats'][$seat_number] = [
                'id' => $assignment['id'],
                'response_id' => $assignment['response_id'],
                'companion_id' => $assignment['companion_id'],
                'name' => $guest_name,
                'title' => $guest_title,
                'group' => $group_name,
                'is_companion' => $assignment['is_companion']
            ];
        }
    }
    
    // 着席者の総数をカウント
    $total_seated = count($assignments);
    $total_unassigned = count($unassigned_people);
    
    // POST処理 - 座席割り当て
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['assign_seat'])) {
            $table_id = $_POST['table_id'];
            $seat_number = $_POST['seat_number'];
            $response_id = !empty($_POST['response_id']) ? $_POST['response_id'] : null;
            $companion_id = !empty($_POST['companion_id']) ? $_POST['companion_id'] : null;
            $is_companion = isset($_POST['is_companion']) ? (int)$_POST['is_companion'] : 0;
            
            // 既存の割り当てをチェック
            $stmt = $pdo->prepare("
                SELECT id FROM seat_assignments 
                WHERE table_id = ? AND seat_number = ?
            ");
            $stmt->execute([$table_id, $seat_number]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // 既存の割り当てを削除
                $stmt = $pdo->prepare("DELETE FROM seat_assignments WHERE id = ?");
                $stmt->execute([$existing['id']]);
            }
            
            // 新しい割り当てを追加
            $stmt = $pdo->prepare("
                INSERT INTO seat_assignments (table_id, seat_number, response_id, companion_id, is_companion)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$table_id, $seat_number, $response_id, $companion_id, $is_companion]);
            $assignment_id = $pdo->lastInsertId();
            
            // Ajax リクエストの場合はJSON応答を返す
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => '座席が割り当てられました', 'assignment_id' => $assignment_id]);
                exit;
            }
            
            // 通常のフォーム送信の場合はリダイレクト
            $_SESSION['success_message'] = '座席が正常に割り当てられました。';
            header('Location: seating.php');
            exit;
        }
        
        if (isset($_POST['remove_assignment'])) {
            $assignment_id = $_POST['assignment_id'];
            
            // 割り当てを削除
            $stmt = $pdo->prepare("DELETE FROM seat_assignments WHERE id = ?");
            $stmt->execute([$assignment_id]);
            
            // Ajax リクエストの場合はJSON応答を返す
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => '座席割り当てが解除されました']);
                exit;
            }
            
            // 通常のフォーム送信の場合はリダイレクト
            $_SESSION['success_message'] = '座席割り当てが解除されました。';
            header('Location: seating.php');
            exit;
        }
    }
    
} catch (PDOException $e) {
    die('データベースエラー: ' . ($debug_mode ? $e->getMessage() : '席次表データの取得に失敗しました。'));
}

$page_title = "席次表管理";
include 'inc/header.php';
?>

<!-- 外部CSSファイルを読み込み -->
<link rel="stylesheet" href="css/seating.css">

<!-- jQuery UI CSSを読み込み - ドラッグアンドドロップに必要 -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo $page_title; ?></h1>
        <div>
            <a href="print_seating.php" class="btn btn-secondary mr-2" target="_blank">
                <i class="fas fa-print"></i> 印刷プレビュー
            </a>
            <a href="export_seating.php" class="btn btn-success mr-2">
                <i class="fas fa-file-csv"></i> CSVエクスポート
            </a>
            <a href="reset_seating.php" class="btn btn-danger" onclick="return confirm('すべての座席割り当てをリセットしますか？この操作は元に戻せません。');">
                <i class="fas fa-trash"></i> 座席割り当てをリセット
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">座席状況概要</h5>
                </div>
                <div class="card-body">
                    <div class="seating-status">
                        <div class="status-card seated">
                            <div class="status-number"><?php echo $total_seated; ?></div>
                            <div class="status-label">割り当て済み</div>
                        </div>
                        <div class="status-card unassigned">
                            <div class="status-number"><?php echo $total_unassigned; ?></div>
                            <div class="status-label">未割り当て</div>
                        </div>
                    </div>
                    
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-color empty"></div>
                            <span>空席</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color occupied"></div>
                            <span>割り当て済み</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color respondent"></div>
                            <span>回答者</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color companion"></div>
                            <span>同伴者</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- 左側：未割り当てゲスト一覧 -->
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">未割り当てゲスト (<?php echo count($unassigned_people); ?>名)</h5>
                </div>
                <div class="card-body unassigned-guests">
                    <?php if (count($unassigned_people) > 0): ?>
                        <?php foreach ($unassigned_people as $person): ?>
                            <div class="guest-card <?php echo $person['person_type']; ?>" 
                                 draggable="true"
                                 data-person-id="<?php echo $person['person_id']; ?>"
                                 data-person-type="<?php echo $person['person_type']; ?>">
                                <div class="guest-name"><?php echo htmlspecialchars($person['person_name']); ?></div>
                                <div class="guest-group"><?php echo htmlspecialchars($person['group_name'] ?? ''); ?></div>
                                <?php if ($person['person_type'] === 'companion'): ?>
                                    <div class="guest-relationship">
                                        <?php echo htmlspecialchars($person['relationship'] ?? ''); ?>の同伴者
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center">未割り当てのゲストはいません</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 席次表説明 -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">席次表のご案内</h5>
                </div>
                <div class="card-body">
                    <div class="seating-instructions">
                        <ul>
                            <li>○の中の数字が上座のテーブルの順番です。</li>
                            <li>上座より職場→友人→親族の順に配置しましょう。</li>
                            <li>1テーブル4～6名掛けです。①②③④⑤⑥の順で上座の席の順番です。</li>
                            <li>ゲストの名様に『様』をおつけください。</li>
                            <li>小さなお子様には「くん、ちゃん」をつけてください。</li>
                            <li>近い親戚の方ほどより上座、遠い親戚の方ほどより下座です。</li>
                            <li>ご同席者同士の会話が弾むように組みましょう！</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 右側：席次表レイアウト -->
        <div class="col-md-9">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">席次表レイアウト</h5>
                </div>
                <div class="card-body">
                    <div class="venue-layout">
                        <div class="venue-header">
                            <h2>結婚披露宴席次表</h2>
                            <?php
                            // 結婚式情報を取得（エラー処理を追加）
                            try {
                                // テーブルの存在確認
                                $check_table = $pdo->query("SHOW TABLES LIKE 'wedding_details'");
                                if ($check_table->rowCount() > 0) {
                                    $wedding_info = $pdo->query("SELECT wedding_date, venue_name FROM wedding_details LIMIT 1")->fetch();
                                    $wedding_date = isset($wedding_info['wedding_date']) ? date('Y年n月j日', strtotime($wedding_info['wedding_date'])) : '2023年12月10日';
                                    $venue_name = isset($wedding_info['venue_name']) ? $wedding_info['venue_name'] : 'ホテルグランドパレス';
                                } else {
                                    // テーブルが存在しない場合はデフォルト値を使用
                                    $wedding_date = '2023年12月10日';
                                    $venue_name = 'ホテルグランドパレス';
                                }
                            } catch (PDOException $e) {
                                // エラー発生時もデフォルト値を使用
                                $wedding_date = '2023年12月10日';
                                $venue_name = 'ホテルグランドパレス';
                            }
                            ?>
                            <p class="venue-date"><?php echo $wedding_date; ?></p>
                            <p class="venue-place"><?php echo htmlspecialchars($venue_name); ?>に於いて</p>
                        </div>
                        
                        <div class="seating-area">
                            <!-- 高砂（新郎新婦席） -->
                            <div class="high-table">
                                <div class="high-table-box">
                                    <span>新郎・新婦</span>
                                </div>
                            </div>
                            
                            <!-- 上座の表示 -->
                            <div class="position-labels">
                                <div class="kamiza">上座</div>
                            </div>
                            
                            <!-- カテゴリラベル -->
                            <div class="group-labels">
                                <div class="group-label">職場</div>
                                <div class="group-label">友人</div>
                                <div class="group-label">親族</div>
                            </div>
                            
                            <!-- 背景エリア -->
                            <div class="tables-background"></div>
                            
                            <!-- 丸テーブルの配置 -->
                            <div class="tables-area">
                                <?php
                                // 1〜10までのテーブル用の配列を作成
                                $table_positions = [];
                                for ($i = 1; $i <= 10; $i++) {
                                    $table_positions[$i] = null;
                                }
                                
                                // 既存のテーブルをテーブル番号別に整理
                                foreach ($seating_plan as $table_id => $table) {
                                    $table_num = intval($table['table_name']);
                                    if ($table_num >= 1 && $table_num <= 10) {
                                        $table_positions[$table_num] = [
                                            'id' => $table_id,
                                            'data' => $table
                                        ];
                                    }
                                }
                                
                                // テーブル位置の指定（左上から右下へ、列ごとに）
                                $position_map = [
                                    2, 1, 1, 2,    // 1行目: テーブル2,1,1,2
                                    4, 3, 3, 4,    // 2行目: テーブル4,3,3,4
                                    6, 5, 5, 6,    // 3行目: テーブル6,5,5,6
                                    8, 7, 7, 8,    // 4行目: テーブル8,7,7,8
                                    10, 9, 9, 10   // 5行目: テーブル10,9,9,10
                                ];
                                
                                // 表示するテーブル数の調整（実際のゲスト数に応じて）
                                $max_tables = 10; // 最大で10テーブル
                                
                                // グリッドセルを生成
                                $cell_count = 0;
                                foreach ($position_map as $table_num) {
                                    $cell_count++;
                                    
                                    // テーブル数を超えたら空白セルを表示
                                    if ($table_num > $max_tables) {
                                        echo '<div class="empty-cell"></div>';
                                        continue;
                                    }
                                    
                                    // テーブルデータの取得
                                    $table_data = $table_positions[$table_num] ?? null;
                                    
                                    // テーブルが存在しない場合、ダミーテーブルを作成
                                    if ($table_data === null) {
                                        echo '<div class="round-table-container">';
                                        echo '<div class="round-table">';
                                        echo '<div class="table-number">' . $table_num . '</div>';
                                        
                                        // テーブル10の場合、親の席を表示
                                        if ($table_num == 10) {
                                            echo '<div class="parent-note father">(父)</div>';
                                            echo '<div class="parent-note mother">(母)</div>';
                                        }
                                        
                                        // 6つの席を表示
                                        for ($seat = 1; $seat <= 6; $seat++) {
                                            echo '<div class="seat seat-pos-' . $seat . '" data-table-num="' . $table_num . '" data-seat-number="' . $seat . '">';
                                            echo '<div class="seat-number">' . $seat . '</div>';
                                            echo '<div class="seat-layer">肩書</div>';
                                            echo '<div class="seat-guest empty">お名前</div>';
                                            echo '</div>';
                                        }
                                        
                                        echo '</div>'; // round-table
                                        echo '</div>'; // round-table-container
                                        continue;
                                    }
                                    
                                    // 実際のテーブルを表示
                                    $table_id = $table_data['id'];
                                    $table = $table_data['data'];
                                    
                                    echo '<div class="round-table-container">';
                                    echo '<div class="round-table" data-table-id="' . $table_id . '">';
                                    echo '<div class="table-number">' . $table['table_name'] . '</div>';
                                    
                                    // テーブル10の場合、親の席を表示
                                    if ($table_num == 10) {
                                        echo '<div class="parent-note father">(父)</div>';
                                        echo '<div class="parent-note mother">(母)</div>';
                                    }
                                    
                                    // 座席を表示
                                    $max_seats = min(6, $table['capacity']); // 最大6席まで
                                    for ($seat = 1; $seat <= $max_seats; $seat++) {
                                        $seat_data = $table['seats'][$seat] ?? null;
                                        $is_occupied = !empty($seat_data);
                                        $seat_class = 'seat seat-pos-' . $seat;
                                        
                                        if ($is_occupied) {
                                            $seat_class .= ' occupied';
                                            if (isset($seat_data['is_companion']) && $seat_data['is_companion']) {
                                                $seat_class .= ' companion';
                                            } else {
                                                $seat_class .= ' respondent';
                                            }
                                        }
                                        
                                        echo '<div class="' . $seat_class . '" ' . 
                                             'data-table-id="' . $table_id . '" ' .
                                             'data-seat-number="' . $seat . '" ' .
                                             'data-assignment-id="' . ($seat_data['id'] ?? '') . '">';
                                        
                                        echo '<div class="seat-number">' . $seat . '</div>';
                                        
                                        if ($is_occupied) {
                                            echo '<div class="seat-layer">' . htmlspecialchars($seat_data['title'] ?? '肩書') . '</div>';
                                            echo '<div class="seat-guest">' . htmlspecialchars($seat_data['name']) . ' 様</div>';
                                        } else {
                                            echo '<div class="seat-layer">肩書</div>';
                                            echo '<div class="seat-guest empty">お名前</div>';
                                        }
                                        
                                        echo '</div>'; // seat
                                    }
                                    
                                    echo '</div>'; // round-table
                                    echo '</div>'; // round-table-container
                                }
                                ?>
                            </div>
                            
                            <!-- 下座の表示 -->
                            <div class="position-labels" style="margin-top: 20px;">
                                <div class="shimoza">下座</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- モーダル - 座席割り当て -->
<div class="modal fade" id="assignSeatModal" tabindex="-1" role="dialog" aria-labelledby="assignSeatModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignSeatModalLabel">座席割り当て</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="assignSeatForm">
                    <input type="hidden" id="assign_table_id" name="table_id" value="">
                    <input type="hidden" id="assign_seat_number" name="seat_number" value="">
                    
                    <div class="form-group">
                        <label for="person_select">ゲスト選択:</label>
                        <select id="person_select" class="form-control">
                            <option value="">--- 選択してください ---</option>
                            <optgroup label="回答者">
                                <?php foreach ($unassigned_people as $person): ?>
                                    <?php if ($person['person_type'] === 'respondent'): ?>
                                        <option value="<?php echo $person['person_type']; ?>_<?php echo $person['person_id']; ?>">
                                            <?php echo htmlspecialchars($person['person_name']); ?> 
                                            (<?php echo htmlspecialchars($person['group_name'] ?? ''); ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="同伴者">
                                <?php foreach ($unassigned_people as $person): ?>
                                    <?php if ($person['person_type'] === 'companion'): ?>
                                        <option value="<?php echo $person['person_type']; ?>_<?php echo $person['person_id']; ?>">
                                            <?php echo htmlspecialchars($person['person_name']); ?> 
                                            (<?php echo htmlspecialchars($person['relationship'] ?? ''); ?>の同伴者)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    
                    <input type="hidden" id="response_id" name="response_id" value="">
                    <input type="hidden" id="companion_id" name="companion_id" value="">
                    <input type="hidden" id="is_companion" name="is_companion" value="0">
                    <input type="hidden" name="assign_seat" value="1">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">キャンセル</button>
                <button type="button" class="btn btn-primary" id="submitAssignment">割り当て</button>
            </div>
        </div>
    </div>
</div>

<!-- モーダル - 座席解除確認 -->
<div class="modal fade" id="removeSeatModal" tabindex="-1" role="dialog" aria-labelledby="removeSeatModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="removeSeatModalLabel">座席割り当て解除</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>この座席の割り当てを解除しますか？</p>
                <form id="removeSeatForm">
                    <input type="hidden" id="remove_assignment_id" name="assignment_id" value="">
                    <input type="hidden" name="remove_assignment" value="1">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">キャンセル</button>
                <button type="button" class="btn btn-danger" id="submitRemoval">解除</button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery UI JSを読み込み - ドラッグアンドドロップに必要 -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
$(document).ready(function() {
    // モーダルの初期化 - 最上部に表示されるように設定
    $('#assignSeatModal, #removeSeatModal').each(function(){
        $(this).appendTo('body');
    });
    
    // 空席クリック時のイベント - 割り当てモーダル表示
    $('.seat:not(.occupied)').on('click', function(e) {
        e.stopPropagation(); // イベントの伝播を止める
        var tableId = $(this).data('table-id');
        var seatNumber = $(this).data('seat-number');
        
        $('#assign_table_id').val(tableId);
        $('#assign_seat_number').val(seatNumber);
        $('#assignSeatModal').modal('show');
    });
    
    // 既に割り当てられた座席クリック時 - 解除モーダル表示
    $('.seat.occupied').on('click', function(e) {
        e.stopPropagation(); // イベントの伝播を止める
        var assignmentId = $(this).data('assignment-id');
        $('#remove_assignment_id').val(assignmentId);
        $('#removeSeatModal').modal('show');
    });
    
    // ゲスト選択時の処理
    $('#person_select').on('change', function() {
        var selected = $(this).val();
        
        if (selected) {
            var parts = selected.split('_');
            var personType = parts[0];
            var personId = parts[1];
            
            // フォームフィールドをリセット
            $('#response_id').val('');
            $('#companion_id').val('');
            $('#is_companion').val('0');
            
            // 選択したゲストタイプに応じてフィールドを設定
            if (personType === 'respondent') {
                $('#response_id').val(personId);
                $('#is_companion').val('0');
            } else if (personType === 'companion') {
                $('#companion_id').val(personId);
                $('#is_companion').val('1');
            }
        }
    });
    
    // 割り当てフォーム送信
    $('#submitAssignment').on('click', function() {
        var selected = $('#person_select').val();
        
        if (!selected) {
            alert('ゲストを選択してください');
            return;
        }
        
        $.ajax({
            url: 'seating.php',
            type: 'POST',
            data: $('#assignSeatForm').serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload(); // ページを再読み込みして最新状態を表示
                } else {
                    alert('エラー: ' + response.message);
                }
            },
            error: function() {
                alert('通信エラーが発生しました');
            }
        });
    });
    
    // 解除フォーム送信
    $('#submitRemoval').on('click', function() {
        $.ajax({
            url: 'seating.php',
            type: 'POST',
            data: $('#removeSeatForm').serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload(); // ページを再読み込みして最新状態を表示
                } else {
                    alert('エラー: ' + response.message);
                }
            },
            error: function() {
                alert('通信エラーが発生しました');
            }
        });
    });
    
    // ドラッグ＆ドロップの実装
    $('.guest-card').draggable({
        helper: 'clone',
        opacity: 0.7,
        zIndex: 100,
        start: function(event, ui) {
            $(this).addClass('dragging');
        },
        stop: function(event, ui) {
            $(this).removeClass('dragging');
        }
    });
    
    $('.seat:not(.occupied)').droppable({
        accept: '.guest-card',
        hoverClass: 'drop-hover',
        drop: function(event, ui) {
            var tableId = $(this).data('table-id');
            var seatNumber = $(this).data('seat-number');
            var personType = $(ui.draggable).data('person-type');
            var personId = $(ui.draggable).data('person-id');
            
            // Ajaxリクエストでドロップされたゲストを座席に割り当て
            var formData = {
                'assign_seat': 1,
                'table_id': tableId,
                'seat_number': seatNumber
            };
            
            if (personType === 'respondent') {
                formData.response_id = personId;
                formData.is_companion = 0;
            } else if (personType === 'companion') {
                formData.companion_id = personId;
                formData.is_companion = 1;
            }
            
            $.ajax({
                url: 'seating.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload(); // ページを再読み込みして最新状態を表示
                    } else {
                        alert('エラー: ' + response.message);
                    }
                },
                error: function() {
                    alert('通信エラーが発生しました');
                }
            });
        }
    });
});
</script>

<?php include 'inc/footer.php'; ?> 