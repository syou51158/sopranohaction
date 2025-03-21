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
    
    <?php if (isset($_GET['debug']) && $_GET['debug'] == 1): ?>
    <div class="alert alert-info mb-4">
        <h4>デバッグ情報</h4>
        <p>以下はデータ構造のダンプです：</p>
        <pre><?php 
            // 新郎テーブルの構造を確認
            $groom_table = array_filter($seating_plan, function($t) { 
                return $t['table_name'] === '新郎'; 
            });
            if (!empty($groom_table)) {
                echo "新郎テーブル:\n";
                print_r(current($groom_table));
            }
            
            // 割り当てられた座席の一部を表示
            echo "\n割り当て済み座席の例（先頭3件）:\n";
            $count = 0;
            foreach ($assignments as $assignment) {
                if ($count++ < 3) {
                    print_r($assignment);
                }
            }
        ?></pre>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">テーブル配置</h5>
                </div>
                <div class="card-body">
                    <div class="venue-layout">
                        <div class="venue-header">
                            <h2>村岡家・健野家 結婚披露宴席次表</h2>
                            <p class="venue-date">2025年4月30日</p>
                            <p class="venue-place">スイートテラスに於いて</p>
                        </div>
                        
                        <div class="bridal-table-area">
                            <div class="bridal-table-container">
                                <div class="bridal-table groom">
                                    <div class="table-name">新郎</div>
                                    <div class="table-seats">
                                        <?php 
                                        // 新郎テーブルの座席を表示
                                        $groom_table = array_filter($seating_plan, function($t) { 
                                            return $t['table_name'] === '新郎'; 
                                        });
                                        
                                        if ($groom_table) {
                                            $table_id = key($groom_table);
                                            $table = current($groom_table);
                                            for ($i = 1; $i <= $table['capacity']; $i++): 
                                                // 座席データを取得
                                                $seat_data = getSeatData($table_id, $i, $assignments);
                                                
                                                // 肩書と名前を取得
                                                $seat_title = isset($seat_data['layer_text']) ? htmlspecialchars($seat_data['layer_text']) : '肩書';
                                                
                                                // 同伴者かどうかでデータの取得元を変える
                                                $seat_name = '';
                                                if (!empty($seat_data)) {
                                                    if ($seat_data['is_companion'] == 1 && isset($companion_data[$seat_data['companion_id']])) {
                                                        $seat_name = htmlspecialchars($companion_data[$seat_data['companion_id']]['name']);
                                                    } elseif (isset($response_data[$seat_data['response_id']])) {
                                                        $seat_name = htmlspecialchars($response_data[$seat_data['response_id']]['name']);
                                                    }
                                                }
                                                
                                                if (empty($seat_name)) {
                                                    $seat_name = 'お名前';
                                                }
                                                
                                                $is_occupied = !empty($seat_data);
                                                $is_empty_class = !$is_occupied ? 'empty' : '';
                                        ?>
                                        <div class="seat <?php echo $is_occupied ? 'occupied' : ''; ?>" 
                                             data-seat-number="<?php echo $i; ?>"
                                             data-table-id="<?php echo $table_id; ?>"
                                             data-assignment-id="<?php echo $seat_data['id'] ?? ''; ?>">
                                            <div class="seat-layer">
                                                <?php echo $seat_title; ?>
                                            </div>
                                            <div class="seat-guest <?php echo $is_empty_class; ?>">
                                                <?php echo $seat_name; ?>
                                            </div>
                                            <!-- デバッグ情報 -->
                                            <?php if (isset($_GET['debug']) && $_GET['debug'] == 1): ?>
                                            <div class="debug-info" style="display:none;">
                                                <?php if ($is_occupied): ?>
                                                <small>ID: <?php echo $seat_data['id']; ?></small><br>
                                                <small>Title: <?php echo $seat_title; ?></small><br>
                                                <small>Name: <?php echo $seat_name; ?></small><br>
                                                <small>Response ID: <?php echo $seat_data['response_id'] ?? 'none'; ?></small><br>
                                                <small>Companion ID: <?php echo $seat_data['companion_id'] ?? 'none'; ?></small><br>
                                                <small>Is Companion: <?php echo $seat_data['is_companion'] ?? '0'; ?></small>
                                                <?php else: ?>
                                                <small>Empty Seat</small>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endfor; } ?>
                                    </div>
                                </div>
                                
                                <div class="bridal-table bride">
                                    <div class="table-name">新婦</div>
                                    <div class="table-seats">
                                        <?php 
                                        // 新婦テーブルの座席を表示
                                        $bride_table = array_filter($seating_plan, function($t) { 
                                            return $t['table_name'] === '新婦'; 
                                        });
                                        
                                        if ($bride_table) {
                                            $table_id = key($bride_table);
                                            $table = current($bride_table);
                                            for ($i = 1; $i <= $table['capacity']; $i++): 
                                        ?>
                                        <div class="seat <?php echo $table['seats'][$i] ? 'occupied' : ''; ?>" 
                                             data-seat-number="<?php echo $i; ?>"
                                             data-table-id="<?php echo $table_id; ?>"
                                             data-assignment-id="<?php echo $table['seats'][$i]['id'] ?? ''; ?>">
                                            <div class="seat-layer">
                                                <?php echo $table['seats'][$i] ? htmlspecialchars($table['seats'][$i]['title'] ?? '肩書') : '肩書'; ?>
                                            </div>
                                            <div class="seat-guest <?php echo !$table['seats'][$i] ? 'empty' : ''; ?>">
                                                <?php echo $table['seats'][$i] ? htmlspecialchars($table['seats'][$i]['name']) : 'お名前'; ?>
                                            </div>
                                        </div>
                                        <?php endfor; } ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="guest-tables-area">
                            <div class="guest-tables-grid">
                                <?php 
                                // 一般テーブル（A-L）を表示
                                $regular_tables = array_filter($seating_plan, function($t) use($seating_plan) { 
                                    $table_name = $t['table_name'];
                                    return $table_name !== '新郎' && $table_name !== '新婦' && strlen($table_name) === 1;
                                });
                                
                                // テーブル名でソート
                                uksort($regular_tables, function($a, $b) use($seating_plan) {
                                    return strcmp($seating_plan[$a]['table_name'], $seating_plan[$b]['table_name']);
                                });
                                
                                foreach ($regular_tables as $table_id => $table):
                                ?>
                                <div class="guest-table" data-table-id="<?php echo $table_id; ?>">
                                    <div class="table-name"><?php echo htmlspecialchars($table['table_name']); ?></div>
                                    <div class="table-seats">
                                        <?php for ($i = 1; $i <= $table['capacity']; $i++): ?>
                                        <div class="seat <?php echo $table['seats'][$i] ? 'occupied' : ''; ?>" 
                                             data-seat-number="<?php echo $i; ?>"
                                             data-table-id="<?php echo $table_id; ?>"
                                             data-assignment-id="<?php echo $table['seats'][$i]['id'] ?? ''; ?>">
                                            <div class="seat-layer">
                                                <?php echo $table['seats'][$i] ? htmlspecialchars($table['seats'][$i]['title'] ?? '肩書') : '肩書'; ?>
                                            </div>
                                            <div class="seat-guest <?php echo !$table['seats'][$i] ? 'empty' : ''; ?>">
                                                <?php echo $table['seats'][$i] ? htmlspecialchars($table['seats'][$i]['name']) : 'お名前'; ?>
                                            </div>
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="seating-instructions">
                            <ul>
                                <li>★四角の上の細い欄に層書きを、下の広い欄にお名前をフルネームでご記入ください。</li>
                                <li>★ご両親へは敬称はつけません。お子様は「ちゃん・君」です。</li>
                                <li>★ご両親のテーブルの位置は一番下に配置してください。</li>
                                <li>★引出物の配り当てを見ながら、お名前の横に「引出物組み合わせパターンをご記入ください」</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">未割り当てゲスト</h5>
                </div>
                <div class="card-body">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="guestSearch" placeholder="ゲスト名や所属で検索...">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                    </div>
                    
                    <div class="guests-list">
                        <?php if (count($unassigned_people) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($unassigned_people as $person): ?>
                                    <div class="list-group-item list-group-item-action guest-item <?php echo $person['person_type']; ?>" 
                                         data-guest-id="<?php echo $person['person_id']; ?>"
                                         data-guest-name="<?php echo htmlspecialchars($person['person_name']); ?>"
                                         data-guest-title="<?php echo htmlspecialchars($person['person_title'] ?? ''); ?>"
                                         data-guest-group="<?php echo htmlspecialchars($person['group_name'] ?? ''); ?>"
                                         data-is-companion="<?php echo $person['person_type'] === 'companion' ? '1' : '0'; ?>"
                                         <?php if ($person['person_type'] === 'companion'): ?>
                                         data-response-id="<?php echo $person['response_id']; ?>"
                                         <?php endif; ?>>
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($person['person_name']); ?></h6>
                                            <?php if ($person['person_type'] === 'companion'): ?>
                                                <span class="badge badge-info">
                                                    <?php
                                                    switch ($person['age_group']) {
                                                        case 'adult':
                                                            echo '同伴者（大人）';
                                                            break;
                                                        case 'child':
                                                            echo '同伴者（子供）';
                                                            break;
                                                        case 'infant':
                                                            echo '同伴者（幼児）';
                                                            break;
                                                        default:
                                                            echo '同伴者';
                                                    }
                                                    ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <small>
                                            <?php if ($person['person_title']): ?>
                                                <span class="guest-title"><?php echo htmlspecialchars($person['person_title']); ?></span> | 
                                            <?php endif; ?>
                                            <?php if ($person['person_type'] === 'companion'): ?>
                                                <?php echo htmlspecialchars($person['relationship']); ?>の同伴者 | 
                                            <?php endif; ?>
                                            グループ: <?php echo htmlspecialchars($person['group_name'] ?? '未設定'); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">未割り当てのゲストはいません。</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 座席割り当てモーダル -->
<div class="modal fade custom-modal" id="assignSeatModal" tabindex="-1" 
     aria-labelledby="assignSeatModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignSeatModalLabel">座席割り当て</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="assignSeatForm" method="post">
                    <input type="hidden" name="assign_seat" value="1">
                    <input type="hidden" name="table_id" id="modal_table_id">
                    <input type="hidden" name="seat_number" id="modal_seat_number">
                    <input type="hidden" name="is_companion" id="modal_is_companion" value="0">
                    
                    <div class="form-group">
                        <label for="guest_id">ゲストを選択:</label>
                        <select class="form-control" name="guest_select" id="guest_select" required>
                            <option value="">-- ゲストを選択 --</option>
                            <?php foreach ($unassigned_people as $person): ?>
                                <option value="<?php echo $person['person_id']; ?>" 
                                        data-is-companion="<?php echo $person['person_type'] === 'companion' ? '1' : '0'; ?>"
                                        <?php if ($person['person_type'] === 'companion'): ?>
                                        data-response-id="<?php echo $person['response_id']; ?>"
                                        <?php endif; ?>>
                                    <?php echo htmlspecialchars($person['person_name']); ?>
                                    <?php if ($person['person_type'] === 'companion'): ?>
                                        (<?php echo htmlspecialchars($person['relationship']); ?>の同伴者)
                                    <?php endif; ?>
                                    - <?php echo htmlspecialchars($person['group_name'] ?? '未設定'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <input type="hidden" name="response_id" id="modal_response_id">
                    <input type="hidden" name="companion_id" id="modal_companion_id">
                    
                    <div class="seat-info mt-3 text-center">
                        <p>テーブル <span id="modal_table_name"></span> - 座席番号 <span id="modal_seat_number_display"></span></p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">キャンセル</button>
                <button type="submit" form="assignSeatForm" class="btn btn-primary">割り当て</button>
            </div>
        </div>
    </div>
</div>

<!-- 座席解除モーダル -->
<div class="modal fade custom-modal" id="removeSeatModal" tabindex="-1" 
     aria-labelledby="removeSeatModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="removeSeatModalLabel">座席割り当て解除</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="removeSeatForm" method="post">
                    <input type="hidden" name="remove_assignment" value="1">
                    <input type="hidden" name="assignment_id" id="modal_assignment_id">
                    
                    <p>次のゲストの座席割り当てを解除しますか？</p>
                    <div class="seat-info mt-3 text-center">
                        <p>ゲスト: <span id="modal_guest_name"></span></p>
                        <p>テーブル <span id="modal_remove_table_name"></span> - 座席番号 <span id="modal_remove_seat_number"></span></p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">キャンセル</button>
                <button type="submit" form="removeSeatForm" class="btn btn-danger">割り当て解除</button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery UI ライブラリの読み込み -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<!-- 外部JavaScriptファイルを読み込み -->
<script src="js/seating.js"></script>

<script>
// コンソールデバッグ用関数
function debugLog(message, data) {
    console.log('[DEBUG] ' + message, data || '');
}

// 既存のJavaScriptコードの後に追加
$(document).ready(function() {
    debugLog('DOM読み込み完了');
    
    // 座席のクリックイベントをデバッグ
    $('.seat').each(function(index) {
        debugLog(`座席要素 ${index+1} データ属性:`, {
            tableId: $(this).data('table-id'),
            seatNumber: $(this).data('seat-number'),
            assignmentId: $(this).data('assignment-id')
        });
    });
    
    // 座席をクリックした時の処理
    $('.seat').on('click', function(e) {
        debugLog('座席クリックイベント発生', e);
        const seat = $(this);
        const assignmentId = seat.data('assignment-id');
        debugLog('クリックされた座席:', {
            tableId: seat.data('table-id'),
            seatNumber: seat.data('seat-number'),
            assignmentId: assignmentId
        });
        
        if (assignmentId) {
            // 割り当て済みの座席の場合は解除モーダルを表示
            debugLog('座席割り当て解除モーダルを表示');
            showRemoveSeatModal(seat);
        } else {
            // 空席の場合は割り当てモーダルを表示
            debugLog('座席割り当てモーダルを表示');
            showAssignSeatModal(seat);
        }
    });
    
    // ドラッグ可能な要素の設定
    $('.guest-item').draggable({
        helper: 'clone',
        appendTo: 'body',
        zIndex: 1000,
        opacity: 0.7,
        cursor: 'move',
        cursorAt: { top: 20, left: 20 },
        start: function(event, ui) {
            debugLog('ドラッグ開始');
            $(this).addClass('dragging');
        },
        stop: function(event, ui) {
            debugLog('ドラッグ終了');
            $(this).removeClass('dragging');
        }
    });
    
    // ドロップ可能な座席の設定
    $('.seat:not(.occupied)').droppable({
        accept: '.guest-item',
        hoverClass: 'drop-active',
        drop: function(event, ui) {
            debugLog('ドロップイベント発生');
            const seat = $(this);
            const tableId = seat.data('table-id');
            const seatNumber = seat.data('seat-number');
            const tableName = seat.closest('.guest-table, .bridal-table').find('.table-name').text();
            
            const guest = $(ui.draggable);
            const guestId = guest.data('guest-id');
            const guestName = guest.data('guest-name');
            const isCompanion = guest.data('is-companion');
            
            debugLog('ドロップ情報:', { 
                tableId, 
                seatNumber, 
                tableName, 
                guestId, 
                guestName, 
                isCompanion 
            });
            
            // AJAX処理で割り当てを実行
            assignSeatByDragDrop(tableId, seatNumber, guestId, isCompanion, guestName, tableName);
        }
    });
    
    // 割り当てモーダルのゲスト選択変更時の処理
    $('#guest_select').on('change', function() {
        debugLog('ゲスト選択変更');
        const selectedOption = $(this).find('option:selected');
        const isCompanion = selectedOption.data('is-companion');
        const personId = selectedOption.val();
        
        $('#modal_is_companion').val(isCompanion);
        
        if (isCompanion === 1) {
            $('#modal_companion_id').val(personId);
            $('#modal_response_id').val(selectedOption.data('response-id'));
            debugLog('同伴者を選択', { companionId: personId, responseId: selectedOption.data('response-id') });
        } else {
            $('#modal_response_id').val(personId);
            $('#modal_companion_id').val('');
            debugLog('回答者を選択', { responseId: personId });
        }
    });
    
    // フォーム送信のデバッグ
    $('#assignSeatForm').on('submit', function(e) {
        debugLog('座席割り当てフォーム送信', {
            tableId: $('#modal_table_id').val(),
            seatNumber: $('#modal_seat_number').val(),
            isCompanion: $('#modal_is_companion').val(),
            responseId: $('#modal_response_id').val(),
            companionId: $('#modal_companion_id').val()
        });
    });
    
    $('#removeSeatForm').on('submit', function(e) {
        debugLog('座席解除フォーム送信', {
            assignmentId: $('#modal_assignment_id').val()
        });
    });
    
    // ゲスト検索機能
    $('#guestSearch').on('keyup', function() {
        const searchText = $(this).val().toLowerCase();
        $('.guest-item').each(function() {
            const guestName = $(this).data('guest-name').toLowerCase();
            const guestGroup = $(this).data('guest-group').toLowerCase();
            
            if (guestName.includes(searchText) || guestGroup.includes(searchText)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
});

function showAssignSeatModal(seatElement) {
    const tableId = seatElement.data('table-id');
    const seatNumber = seatElement.data('seat-number');
    const tableName = seatElement.closest('.guest-table, .bridal-table').find('.table-name').text();
    
    debugLog('座席割り当てモーダル表示準備', { tableId, seatNumber, tableName });
    
    $('#modal_table_id').val(tableId);
    $('#modal_seat_number').val(seatNumber);
    $('#modal_table_name').text(tableName);
    $('#modal_seat_number_display').text(seatNumber);
    
    $('#assignSeatModal').modal('show');
}

function showRemoveSeatModal(seatElement) {
    const assignmentId = seatElement.data('assignment-id');
    const tableId = seatElement.data('table-id');
    const seatNumber = seatElement.data('seat-number');
    const tableName = seatElement.closest('.guest-table, .bridal-table').find('.table-name').text();
    const guestName = seatElement.find('.seat-guest').text().trim();
    
    debugLog('座席解除モーダル表示準備', { assignmentId, tableId, seatNumber, tableName, guestName });
    
    $('#modal_assignment_id').val(assignmentId);
    $('#modal_remove_table_name').text(tableName);
    $('#modal_remove_seat_number').text(seatNumber);
    $('#modal_guest_name').text(guestName);
    
    $('#removeSeatModal').modal('show');
}
</script>

<?php include 'inc/footer.php'; ?> 