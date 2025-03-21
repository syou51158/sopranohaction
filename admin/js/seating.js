/**
 * 席次表管理システム - JavaScript
 */
$(document).ready(function() {
    // 座席をクリックした時の処理
    $('.seat').on('click', function() {
        const assignmentId = $(this).data('assignment-id');
        if (assignmentId) {
            // 割り当て済みの座席の場合は解除モーダルを表示
            showRemoveSeatModal($(this));
        } else {
            // 空席の場合は割り当てモーダルを表示
            showAssignSeatModal($(this));
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
            $(this).addClass('dragging');
        },
        stop: function(event, ui) {
            $(this).removeClass('dragging');
        }
    });
    
    // ドロップ可能な座席の設定
    $('.seat:not(.occupied)').droppable({
        accept: '.guest-item',
        hoverClass: 'drop-active',
        drop: function(event, ui) {
            const seat = $(this);
            const tableId = seat.data('table-id');
            const seatNumber = seat.data('seat-number');
            const tableName = seat.closest('.guest-table, .bridal-table').find('.table-name').text();
            
            const guest = $(ui.draggable);
            const guestId = guest.data('guest-id');
            const guestName = guest.data('guest-name');
            const isCompanion = guest.data('is-companion');
            
            // AJAX処理で割り当てを実行
            assignSeatByDragDrop(tableId, seatNumber, guestId, isCompanion, guestName, tableName);
        }
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
    
    // 割り当てモーダルのゲスト選択変更時の処理
    $('#guest_select').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const isCompanion = selectedOption.data('is-companion');
        const personId = selectedOption.val();
        
        $('#modal_is_companion').val(isCompanion);
        
        if (isCompanion === 1) {
            $('#modal_companion_id').val(personId);
            $('#modal_response_id').val(selectedOption.data('response-id'));
        } else {
            $('#modal_response_id').val(personId);
            $('#modal_companion_id').val('');
        }
    });
    
    // URLからデバッグモードを確認するためのグローバル変数
    const urlParams = new URLSearchParams(window.location.search);
    
    // URLからデバッグモード確認
    if (urlParams.get('debug') === '1') {
        console.log('デバッグモードが有効です');
        debugSeatingLayout();
        
        // 座席にクリックイベントを追加してデバッグ情報をコンソールに表示
        $('.seat').on('click', function() {
            const seatEl = $(this);
            console.log('クリックされた座席:', {
                tableId: seatEl.data('table-id'),
                seatNumber: seatEl.data('seat-number'),
                assignmentId: seatEl.data('assignment-id'),
                html: seatEl.html()
            });
        });
    }
    
    // 全ての座席データを更新
    updateSeatData();
});

// 座席割り当てモーダルを表示
function showAssignSeatModal(seatElement) {
    const tableId = seatElement.data('table-id');
    const seatNumber = seatElement.data('seat-number');
    const tableName = seatElement.closest('.guest-table, .bridal-table').find('.table-name').text();
    
    $('#modal_table_id').val(tableId);
    $('#modal_seat_number').val(seatNumber);
    $('#modal_table_name').text(tableName);
    $('#modal_seat_number_display').text(seatNumber);
    
    $('#assignSeatModal').modal('show');
}

// 座席解除モーダルを表示
function showRemoveSeatModal(seatElement) {
    const assignmentId = seatElement.data('assignment-id');
    const tableId = seatElement.data('table-id');
    const seatNumber = seatElement.data('seat-number');
    const tableName = seatElement.closest('.guest-table, .bridal-table').find('.table-name').text();
    const guestName = seatElement.find('.seat-guest').text().trim();
    
    $('#modal_assignment_id').val(assignmentId);
    $('#modal_remove_table_name').text(tableName);
    $('#modal_remove_seat_number').text(seatNumber);
    $('#modal_guest_name').text(guestName);
    
    $('#removeSeatModal').modal('show');
}

// AJAXでドラッグ&ドロップによる座席割り当てを処理
function assignSeatByDragDrop(tableId, seatNumber, guestId, isCompanion, guestName, tableName) {
    const formData = new FormData();
    formData.append('assign_seat', '1');
    formData.append('table_id', tableId);
    formData.append('seat_number', seatNumber);
    
    // ゲストの肩書を取得
    const guestElement = $(`.guest-item[data-guest-id="${guestId}"]`);
    const guestTitle = guestElement.data('guest-title') || '肩書';
    
    console.log('ゲスト情報:', {
        id: guestId,
        name: guestName,
        title: guestTitle,
        isCompanion: isCompanion
    });
    
    if (isCompanion === 1) {
        formData.append('companion_id', guestId);
        formData.append('is_companion', '1');
        
        // 同伴者の場合は、元データからresponse_idを取得
        const responseId = $('.guest-item.companion[data-guest-id="' + guestId + '"]').data('response-id');
        formData.append('response_id', responseId);
    } else {
        formData.append('response_id', guestId);
        formData.append('is_companion', '0');
    }
    
    $.ajax({
        url: 'seating.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                // ページを再読み込みする代わりに、UIを直接更新
                const seat = $(`.seat[data-table-id="${tableId}"][data-seat-number="${seatNumber}"]`);
                
                // 座席をoccupiedクラスで表示
                seat.addClass('occupied');
                
                // データ属性を更新（サーバーから返されたassignment_idを使用）
                seat.attr('data-assignment-id', response.assignment_id); 
                
                // 肩書を更新
                seat.find('.seat-layer').text(guestTitle);
                
                // 名前を更新 - 空席の表示を削除してゲスト名を設定
                seat.find('.seat-guest').removeClass('empty').text(guestName);
                
                // 割り当てられたゲストをリストから削除
                $(`.guest-item[data-guest-id="${guestId}"]`).fadeOut(300, function() {
                    $(this).remove();
                    
                    // リストが空になったら通知
                    if ($('.guest-item:visible').length === 0) {
                        $('.guests-list').html('<div class="alert alert-success">未割り当てのゲストはいません。</div>');
                    }
                });
                
                // 追加: 座席データをAPI経由で取得して更新
                setTimeout(() => {
                    fetchSeatData(tableId, seatNumber)
                        .then(data => {
                            if (data.success && data.data) {
                                // 座席データを更新
                                updateSeatUI(seat, data.data);
                            }
                        });
                }, 500);
                
                // 成功メッセージを表示
                showAlert('座席が割り当てられました', 'success');
            } else {
                // エラー時はアラートを表示
                showAlert('座席割り当てに失敗しました。', 'danger');
            }
        },
        error: function(xhr, status, error) {
            // エラー時はアラートを表示
            showAlert('座席割り当てに失敗しました。ページを再読み込みして再試行してください。', 'danger');
        }
    });
}

/**
 * アラートメッセージを表示
 * @param {string} message - 表示するメッセージ
 * @param {string} type - アラートタイプ (success, danger, warning, info)
 */
function showAlert(message, type = 'info') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `;
    
    // 既存のアラートを削除
    $('.alert').alert('close');
    
    // 新しいアラートを追加
    $('.container-fluid').prepend(alertHtml);
    
    // 5秒後に自動的に閉じる
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
}

// デバッグ用の関数
function debugSeatingLayout() {
    console.log('=== デバッグ: 座席レイアウト情報 ===');
    
    // 座席データの確認
    const seats = document.querySelectorAll('.seat');
    console.log(`座席の総数: ${seats.length}`);
    
    // サンプルとして最初の5つの座席情報を詳細表示
    console.log('座席サンプル（先頭5つ）:');
    seats.forEach((seat, index) => {
        if (index < 5) {
            const tableId = seat.getAttribute('data-table-id');
            const seatNumber = seat.getAttribute('data-seat-number');
            const assignmentId = seat.getAttribute('data-assignment-id');
            const isOccupied = seat.classList.contains('occupied');
            
            // 座席内のコンテンツを取得
            const layerText = seat.querySelector('.seat-layer').textContent.trim();
            const guestText = seat.querySelector('.seat-guest').textContent.trim();
            
            console.log(`座席 ${index+1}:`, {
                tableId,
                seatNumber,
                assignmentId,
                isOccupied,
                layerText,
                guestText,
                classList: Array.from(seat.classList),
                styleDisplay: window.getComputedStyle(seat).display,
                layerStyle: {
                    display: window.getComputedStyle(seat.querySelector('.seat-layer')).display,
                    visibility: window.getComputedStyle(seat.querySelector('.seat-layer')).visibility,
                    color: window.getComputedStyle(seat.querySelector('.seat-layer')).color
                },
                guestStyle: {
                    display: window.getComputedStyle(seat.querySelector('.seat-guest')).display,
                    visibility: window.getComputedStyle(seat.querySelector('.seat-guest')).visibility,
                    color: window.getComputedStyle(seat.querySelector('.seat-guest')).color
                }
            });
        }
    });
    
    // 未割り当てゲストの確認
    const unassignedGuests = document.querySelectorAll('.guest-item');
    console.log(`未割り当てゲスト数: ${unassignedGuests.length}`);
    
    // サンプルとして最初の3つのゲスト情報を詳細表示
    console.log('ゲストサンプル（先頭3つ）:');
    unassignedGuests.forEach((guest, index) => {
        if (index < 3) {
            console.log(`ゲスト ${index+1}:`, {
                id: guest.getAttribute('data-guest-id'),
                name: guest.getAttribute('data-guest-name'),
                title: guest.getAttribute('data-guest-title'),
                isCompanion: guest.getAttribute('data-is-companion'),
                html: guest.innerHTML.substring(0, 100) + '...'
            });
        }
    });
}

// 座席データを更新する関数
function updateSeatData() {
    // すべての座席要素に対して処理
    $('.seat').each(function() {
        const seat = $(this);
        const tableId = seat.data('table-id');
        const seatNumber = seat.data('seat-number');
        
        // 座席IDが設定されている場合のみ処理
        if (tableId && seatNumber) {
            fetchSeatData(tableId, seatNumber)
                .then(data => {
                    if (data.success && data.data) {
                        // 座席データを更新
                        updateSeatUI(seat, data.data);
                    }
                })
                .catch(error => {
                    console.error('座席データの取得に失敗:', error);
                });
        }
    });
}

// 座席データを取得するAPI呼び出し
function fetchSeatData(tableId, seatNumber) {
    return fetch(`get_seat_data.php?table_id=${tableId}&seat_number=${seatNumber}`)
        .then(response => response.json())
        .catch(error => {
            console.error('API呼び出しエラー:', error);
            return { success: false, error: '通信エラー' };
        });
}

// 座席UI要素を更新
function updateSeatUI(seatElement, seatData) {
    // 層書きを肩書に修正
    let titleText = seatData.title === '層書き' ? '肩書' : seatData.title;
    
    // UIを更新
    seatElement.addClass('occupied');
    seatElement.attr('data-assignment-id', seatData.id);
    
    // 肩書を更新
    seatElement.find('.seat-layer').text(titleText);
    
    // 名前を更新
    seatElement.find('.seat-guest').removeClass('empty').text(seatData.name);
    
    // デバッグモードの場合、デバッグ情報も更新
    if (urlParams.get('debug') === '1') {
        const debugInfo = seatElement.find('.debug-info');
        if (debugInfo.length > 0) {
            debugInfo.html(`
                <small>ID: ${seatData.id}</small><br>
                <small>Title: ${titleText}</small><br>
                <small>Name: ${seatData.name}</small><br>
                <small>Response ID: ${seatData.response_id || 'none'}</small><br>
                <small>Companion ID: ${seatData.companion_id || 'none'}</small><br>
                <small>Is Companion: ${seatData.is_companion}</small>
            `);
        }
    }
}