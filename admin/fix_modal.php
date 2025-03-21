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

$page_title = "モーダル・JavaScript診断";
include 'inc/header.php';
?>

<link rel="stylesheet" href="css/seating.css">
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?php echo $page_title; ?></h1>
        <div>
            <a href="seating.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> 席次表に戻る
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">診断ツール</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <p><strong>このページはモーダルとJavaScriptの機能を診断するためのものです。</strong></p>
                        <p>問題を診断するため、さまざまなテストを行います。</p>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>モーダルテスト</h5>
                                </div>
                                <div class="card-body">
                                    <button id="testModal" class="btn btn-primary mb-3">テストモーダルを表示</button>
                                    <button id="testAssignModal" class="btn btn-success mb-3">割り当てモーダルを表示</button>
                                    <button id="testRemoveModal" class="btn btn-danger mb-3">解除モーダルを表示</button>
                                    <div id="modalTestResult" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>ドラッグ＆ドロップテスト</h5>
                                </div>
                                <div class="card-body">
                                    <div class="test-drag-area mb-3" style="border: 1px solid #ccc; padding: 10px; height: 100px;">
                                        <div id="testDraggable" class="test-draggable" style="width: 100px; height: 50px; background-color: #f0f0f0; border: 1px solid #999; text-align: center; line-height: 50px; cursor: move;">
                                            ドラッグ可能
                                        </div>
                                    </div>
                                    
                                    <div class="test-drop-area" style="border: 1px solid #ccc; padding: 10px; height: 100px; margin-top: 20px;">
                                        <div id="testDroppable" style="width: 100%; height: 100%; background-color: #e9f5e9; text-align: center; line-height: 100px;">
                                            ここにドロップ
                                        </div>
                                    </div>
                                    
                                    <div id="dragDropTestResult" class="mt-3"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5>JavaScript環境診断</h5>
                                </div>
                                <div class="card-body">
                                    <button id="testJQuery" class="btn btn-info mb-3">jQueryテスト</button>
                                    <button id="testJQueryUI" class="btn btn-info mb-3">jQuery UIテスト</button>
                                    <button id="checkConsole" class="btn btn-warning mb-3">コンソールデバッグ</button>
                                    
                                    <div id="jsTestResult" class="mt-3"></div>
                                    
                                    <div class="alert alert-secondary mt-4">
                                        <h6>診断結果とアドバイス</h6>
                                        <div id="diagnosticResults"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- テスト用モーダル -->
<div class="modal fade" id="testModalDialog" tabindex="-1" aria-labelledby="testModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="testModalLabel">テストモーダル</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>このモーダルが表示されれば、Bootstrapモーダルは正常に機能しています。</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery UI ライブラリの読み込み -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
$(document).ready(function() {
    console.log('診断ツール: DOM読み込み完了');
    
    // コンソールメッセージ数をカウント
    let consoleCount = 0;
    const originalConsoleLog = console.log;
    console.log = function() {
        consoleCount++;
        originalConsoleLog.apply(console, arguments);
    };
    
    function updateDiagnostic() {
        let results = [];
        
        // jQueryが利用可能か
        if (typeof $ === 'function') {
            results.push('<p class="text-success">✓ jQuery は正常に読み込まれています。</p>');
        } else {
            results.push('<p class="text-danger">✗ jQuery が読み込まれていないか、エラーが発生しています。</p>');
        }
        
        // jQuery UIが利用可能か
        if (typeof $.ui !== 'undefined') {
            results.push('<p class="text-success">✓ jQuery UI は正常に読み込まれています。</p>');
        } else {
            results.push('<p class="text-danger">✗ jQuery UI が読み込まれていないか、エラーが発生しています。</p>');
        }
        
        // Bootstrapが利用可能か
        if (typeof $.fn.modal === 'function') {
            results.push('<p class="text-success">✓ Bootstrap モーダルは利用可能です。</p>');
        } else {
            results.push('<p class="text-danger">✗ Bootstrap モーダルが利用できません。</p>');
        }
        
        // ドラッグ＆ドロップが機能するか
        if (typeof $.fn.draggable === 'function' && typeof $.fn.droppable === 'function') {
            results.push('<p class="text-success">✓ ドラッグ＆ドロップ機能は利用可能です。</p>');
        } else {
            results.push('<p class="text-danger">✗ ドラッグ＆ドロップ機能が利用できません。</p>');
        }
        
        // ブラウザエラーのチェック
        if ($('#jsTestResult').hasClass('has-error')) {
            results.push('<p class="text-danger">✗ JavaScriptエラーが検出されました。ブラウザのコンソールを確認してください。</p>');
        } else {
            results.push('<p class="text-success">✓ 明らかなJavaScriptエラーは検出されていません。</p>');
        }
        
        // アドバイス
        results.push('<h6 class="mt-3">推奨される対策：</h6>');
        results.push('<ol>');
        results.push('<li>ブラウザのキャッシュをクリアして再読み込みしてください。</li>');
        results.push('<li>席次表ページで<code>?debug=1</code>をURLに追加して詳細なデバッグ情報を表示させてください。</li>');
        results.push('<li>コンソールログを確認して具体的なエラーメッセージがないか確認してください。</li>');
        results.push('<li>各モーダルが正しく表示されるか上のボタンでテストし、表示されない場合はHTMLの問題を調査してください。</li>');
        results.push('</ol>');
        
        $('#diagnosticResults').html(results.join(''));
    }
    
    // モーダルテスト
    $('#testModal').on('click', function() {
        try {
            $('#testModalDialog').modal('show');
            $('#modalTestResult').html('<div class="alert alert-success">テストモーダルは正常に機能しています。</div>');
        } catch (e) {
            $('#modalTestResult').html('<div class="alert alert-danger">エラー: ' + e.message + '</div>');
            $('#jsTestResult').addClass('has-error');
        }
    });
    
    $('#testAssignModal').on('click', function() {
        try {
            $('#assignSeatModal').modal('show');
            $('#modalTestResult').html('<div class="alert alert-success">割り当てモーダルは正常に機能しています。</div>');
        } catch (e) {
            $('#modalTestResult').html('<div class="alert alert-danger">エラー: ' + e.message + '。席次表ページに移動して再試行してください。</div>');
            $('#jsTestResult').addClass('has-error');
        }
    });
    
    $('#testRemoveModal').on('click', function() {
        try {
            $('#removeSeatModal').modal('show');
            $('#modalTestResult').html('<div class="alert alert-success">解除モーダルは正常に機能しています。</div>');
        } catch (e) {
            $('#modalTestResult').html('<div class="alert alert-danger">エラー: ' + e.message + '。席次表ページに移動して再試行してください。</div>');
            $('#jsTestResult').addClass('has-error');
        }
    });
    
    // ドラッグ＆ドロップテスト
    try {
        $('#testDraggable').draggable({
            revert: 'invalid',
            cursor: 'move'
        });
        
        $('#testDroppable').droppable({
            accept: '#testDraggable',
            hoverClass: 'bg-success',
            drop: function(event, ui) {
                $(this).addClass('bg-success text-white').text('ドロップ成功！');
                $('#dragDropTestResult').html('<div class="alert alert-success">ドラッグ＆ドロップは正常に機能しています。</div>');
            }
        });
    } catch (e) {
        $('#dragDropTestResult').html('<div class="alert alert-danger">エラー: ' + e.message + '</div>');
        $('#jsTestResult').addClass('has-error');
    }
    
    // jQueryテスト
    $('#testJQuery').on('click', function() {
        try {
            let version = $.fn.jquery;
            $('#jsTestResult').html('<div class="alert alert-success">jQuery バージョン: ' + version + '</div>');
        } catch (e) {
            $('#jsTestResult').html('<div class="alert alert-danger">エラー: ' + e.message + '</div>');
            $('#jsTestResult').addClass('has-error');
        }
    });
    
    // jQuery UIテスト
    $('#testJQueryUI').on('click', function() {
        try {
            let version = $.ui.version;
            $('#jsTestResult').html('<div class="alert alert-success">jQuery UI バージョン: ' + version + '</div>');
        } catch (e) {
            $('#jsTestResult').html('<div class="alert alert-danger">エラー: ' + e.message + '</div>');
            $('#jsTestResult').addClass('has-error');
        }
    });
    
    // コンソールデバッグ
    $('#checkConsole').on('click', function() {
        console.log('コンソールデバッグテスト');
        $('#jsTestResult').html('<div class="alert alert-info">コンソールに ' + consoleCount + ' 個のメッセージが出力されています。</div>');
    });
    
    // 診断結果を更新
    updateDiagnostic();
});
</script>

<?php include 'inc/footer.php'; ?> 