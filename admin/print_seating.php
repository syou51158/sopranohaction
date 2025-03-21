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

try {
    // テーブル情報を取得
    $stmt = $pdo->query("SELECT * FROM seating_tables ORDER BY table_name");
    $tables = $stmt->fetchAll();
    
    // 座席割り当て情報を取得
    $stmt = $pdo->query("
        SELECT sa.*, st.table_name, st.capacity, st.table_type, r.name AS guest_name, g.group_name, 
               c.name AS companion_name, c.age_group
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
            $guest_info = [
                'name' => $assignment['is_companion'] ? $assignment['companion_name'] : $assignment['guest_name'],
                'is_companion' => $assignment['is_companion'],
                'group' => $assignment['group_name'],
                'age_group' => $assignment['age_group']
            ];
            $seating_plan[$table_id]['seats'][$seat_number] = $guest_info;
        }
    }
} catch (PDOException $e) {
    die('データベースエラー: ' . ($debug_mode ? $e->getMessage() : '席次表データの取得に失敗しました。'));
}

$page_title = "席次表印刷";
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - 結婚式管理システム</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #fff;
        }
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .print-header button {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .print-header button:hover {
            background-color: #45a049;
        }
        
        /* 会場レイアウトスタイル */
        .venue-layout {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            position: relative;
            border: 1px solid #ddd;
        }
        
        .venue-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .venue-header h2 {
            font-size: 24px;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .venue-date, .venue-place {
            font-size: 16px;
            margin: 5px 0;
        }
        
        /* 新郎新婦テーブル */
        .bridal-table-area {
            display: flex;
            justify-content: center;
            margin-bottom: 50px;
        }
        
        .bridal-table-container {
            display: flex;
            gap: 20px;
            max-width: 600px;
        }
        
        .bridal-table {
            border: 2px solid #000;
            padding: 10px;
            width: 200px;
            background-color: #fff;
        }
        
        /* ゲストテーブル */
        .guest-tables-area {
            margin-bottom: 30px;
        }
        
        .guest-tables-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
        .guest-table {
            border: 2px solid #000;
            padding: 10px;
            background-color: #fff;
        }
        
        .table-header {
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
            padding: 5px;
            border-bottom: 1px solid #ddd;
        }
        
        /* 座席のスタイル */
        .table-seats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px;
        }
        
        .seat-row {
            display: flex;
            flex-direction: column;
            border: 1px solid #000;
            height: 60px;
            margin-bottom: 5px;
        }
        
        .seat-layer {
            font-size: 12px;
            padding: 2px 5px;
            border-bottom: 1px solid #ccc;
            background-color: #f8f8f8;
            color: #777;
            text-align: center;
            height: 22px;
        }
        
        .seat-guest {
            padding: 5px;
            font-size: 14px;
            text-align: center;
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* 説明書き */
        .seating-instructions {
            margin-top: 30px;
            padding: 15px;
            border-top: 1px solid #eee;
        }
        
        .seating-instructions ul {
            list-style: none;
            padding: 0;
        }
        
        .seating-instructions li {
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
            }
            .venue-layout {
                border: none;
                box-shadow: none;
            }
        }
        
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
    </style>
</head>
<body>
    <div class="print-header no-print">
        <h1><?php echo $page_title; ?></h1>
        <div>
            <button onclick="window.print()">印刷する</button>
            <button onclick="window.location.href='seating.php'">席次表管理に戻る</button>
        </div>
    </div>

    <div class="venue-layout">
        <div class="venue-header">
            <h2>村岡家・健野家 結婚披露宴席次表</h2>
            <p class="venue-date">2025年4月30日</p>
            <p class="venue-place">スイートテラスに於いて</p>
        </div>
        
        <div class="bridal-table-area">
            <div class="bridal-table-container">
                <?php
                // 新郎・新婦テーブルを取得
                $special_tables = array_filter($seating_plan, function($t) {
                    return $t['table_type'] === 'special';
                });
                
                foreach ($special_tables as $table_id => $table):
                    $is_groom = $table['table_name'] === '新郎';
                ?>
                <div class="bridal-table <?php echo $is_groom ? 'groom' : 'bride'; ?>">
                    <div class="table-header"><?php echo htmlspecialchars($table['table_name']); ?></div>
                    <div class="table-seats">
                        <?php for ($i = 1; $i <= $table['capacity']; $i++): ?>
                        <div class="seat-row">
                            <div class="seat-layer">層書き</div>
                            <div class="seat-guest">
                                <?php if ($table['seats'][$i]): ?>
                                    <?php echo htmlspecialchars($table['seats'][$i]['name']); ?>
                                <?php else: ?>
                                    お名前
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="guest-tables-area">
            <div class="guest-tables-grid">
                <?php 
                // 一般テーブル（A-L）を取得
                $regular_tables = array_filter($seating_plan, function($t) { 
                    $table_name = $t['table_name'];
                    return $table_name !== '新郎' && $table_name !== '新婦' && strlen($table_name) === 1;
                });
                
                // テーブル名でソート
                uksort($regular_tables, function($a, $b) use($seating_plan) {
                    return strcmp($seating_plan[$a]['table_name'], $seating_plan[$b]['table_name']);
                });
                
                foreach ($regular_tables as $table_id => $table):
                ?>
                <div class="guest-table">
                    <div class="table-header"><?php echo htmlspecialchars($table['table_name']); ?></div>
                    <div class="table-seats">
                        <?php for ($i = 1; $i <= $table['capacity']; $i++): ?>
                        <div class="seat-row">
                            <div class="seat-layer">層書き</div>
                            <div class="seat-guest">
                                <?php if ($table['seats'][$i]): ?>
                                    <?php echo htmlspecialchars($table['seats'][$i]['name']); ?>
                                <?php else: ?>
                                    お名前
                                <?php endif; ?>
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 印刷ボタンの機能（代替手段）
        document.querySelector('.print-header button').addEventListener('click', function() {
            window.print();
        });
    });
    </script>
</body>
</html> 