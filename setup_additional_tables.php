<?php
// 設定ファイルを読み込み
require_once 'config.php';

// SQLファイルの内容を読み込み
$sql = file_get_contents('create_additional_tables.sql');

try {
    // 複数のSQLステートメントを実行
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
    $pdo->exec($sql);
    
    echo "追加テーブルが正常に作成されました。<br>";
    
    // 初期データの挿入 - グループタイプ
    $group_types = [
        ['家族', '新郎新婦の家族や親戚'],
        ['友人', '新郎新婦の友人'],
        ['会社関係', '職場の同僚や上司'],
        ['学校関係', '学生時代の友人や先生'],
        ['その他', 'その他のゲスト']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO group_types (type_name, description) VALUES (?, ?)");
    
    foreach ($group_types as $type) {
        $stmt->execute($type);
    }
    
    echo "グループタイプの初期データが挿入されました。<br>";
    
    // FAQ初期データ
    $faqs = [
        ['ドレスコードはありますか？', '当日は自由な服装でお越しください。ただし、白い服はなるべく避けていただくようお願いします。', '服装', 1],
        ['子供を連れて行っても大丈夫ですか？', 'はい、お子様連れでも大歓迎です。ただし、式中は静かにお願いします。', '子供', 2],
        ['アレルギーがある場合はどうすればいいですか？', '出欠回答フォームにてアレルギー情報をご記入ください。できる限り対応いたします。', '食事', 3],
        ['会場までの交通手段は？', '会場へのアクセス方法は「交通・宿泊情報」ページをご確認ください。', '会場', 4],
        ['ご祝儀の相場はいくらですか？', 'ご祝儀につきましては、お気持ちでけっこうです。一般的には2万円〜3万円が相場とされています。', 'ご祝儀', 5]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO faq (question, answer, category, display_order) VALUES (?, ?, ?, ?)");
    
    foreach ($faqs as $faq) {
        $stmt->execute($faq);
    }
    
    echo "FAQの初期データが挿入されました。<br>";
    
    echo "<p>セットアップが完了しました。<a href='admin/dashboard.php'>管理ダッシュボード</a>に移動してください。</p>";
    
} catch (PDOException $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "<br>";
    echo "SQLステート: " . $e->errorInfo[0] . "<br>";
    echo "エラーコード: " . $e->errorInfo[1] . "<br>";
    echo "エラーメッセージ: " . $e->errorInfo[2] . "<br>";
}
?> 