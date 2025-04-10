/**
 * 新席次表管理システム用JavaScript
 * 席の割り当て、解除、ドラッグ＆ドロップなどの機能を提供
 */

document.addEventListener('DOMContentLoaded', function() {
    let draggedItem = null;
    let currentGuestId = null;
    
    // 印刷ボタン
    document.getElementById('print-seating').addEventListener('click', function() {
        window.print();
    });
    
    // CSVエクスポート
    document.getElementById('export-csv').addEventListener('click', function() {
        exportToCSV();
    });
    
    // 全席リセットボタン
    document.getElementById('reset-all-seats').addEventListener('click', function() {
        $('#resetAllSeatsModal').modal('show');
    });
    
    // 全席リセット確認
    document.getElementById('confirm-reset-all').addEventListener('click', function() {
        resetAllSeats();
    });
    
    // ゲストアイテムのドラッグ開始イベント
    document.querySelectorAll('.guest-item').forEach(item => {
        item.addEventListener('dragstart', function(e) {
            draggedItem = this;
            currentGuestId = this.getAttribute('data-guest-id');
            setTimeout(() => {
                this.classList.add('dragging');
            }, 0);
        });
        
        item.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            draggedItem = null;
        });
    });
    
    // 席のドラッグオーバーイベント
    document.querySelectorAll('.seat').forEach(seat => {
        seat.addEventListener('dragover', function(e) {
            e.preventDefault();
            if (!this.classList.contains('occupied')) {
                this.classList.add('drag-over');
            }
        });
        
        seat.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });
        
        seat.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            // 席が既に割り当て済みでなければ
            if (!this.classList.contains('occupied') && draggedItem) {
                const tableId = this.getAttribute('data-table-id');
                const seatNumber = this.getAttribute('data-seat-number');
                const guestId = draggedItem.getAttribute('data-guest-id');
                const guestName = draggedItem.getAttribute('data-guest-name');
                const groupId = draggedItem.getAttribute('data-group-id');
                
                showAssignSeatConfirmation(guestId, guestName, tableId, seatNumber);
            }
        });
        
        // 席クリックで割り当て解除モーダル表示（席が割り当て済みの場合）
        seat.addEventListener('click', function() {
            if (this.classList.contains('occupied')) {
                const tableId = this.getAttribute('data-table-id');
                const seatNumber = this.getAttribute('data-seat-number');
                const guestName = this.querySelector('.guest-name').textContent;
                
                showResetSeatConfirmation(guestName, tableId, seatNumber);
            }
        });
    });
    
    /**
     * 席割り当て確認モーダルを表示
     */
    function showAssignSeatConfirmation(guestId, guestName, tableId, seatNumber) {
        document.getElementById('assign-guest-info').textContent = guestName;
        document.getElementById('assign-table-id').textContent = tableId;
        document.getElementById('assign-seat-number').textContent = seatNumber;
        
        $('#assignSeatModal').modal('show');
        
        // 割り当て確認ボタンのイベント
        document.getElementById('confirm-assign').onclick = function() {
            assignSeat(guestId, tableId, seatNumber);
        };
    }
    
    /**
     * 席割り当て解除確認モーダルを表示
     */
    function showResetSeatConfirmation(guestName, tableId, seatNumber) {
        document.getElementById('reset-guest-info').textContent = 
            guestName + '（テーブル: ' + tableId + ', 席番号: ' + seatNumber + '）';
        
        $('#resetSeatModal').modal('show');
        
        // 割り当て解除確認ボタンのイベント
        document.getElementById('confirm-reset').onclick = function() {
            resetSeat(tableId, seatNumber);
        };
    }
    
    /**
     * 席を割り当てる
     */
    function assignSeat(guestId, tableId, seatNumber) {
        $.ajax({
            url: '../api/assign_seat.php',
            type: 'POST',
            data: {
                guest_id: guestId,
                table_number: tableId,
                seat_number: seatNumber
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // モーダルを閉じる
                    $('#assignSeatModal').modal('hide');
                    
                    // ページをリロード
                    location.reload();
                } else {
                    alert('エラーが発生しました: ' + response.message);
                }
            },
            error: function() {
                alert('サーバーとの通信中にエラーが発生しました。');
            }
        });
    }
    
    /**
     * 席の割り当てを解除する
     */
    function resetSeat(tableId, seatNumber) {
        $.ajax({
            url: '../api/reset_seat.php',
            type: 'POST',
            data: {
                table_number: tableId,
                seat_number: seatNumber
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // モーダルを閉じる
                    $('#resetSeatModal').modal('hide');
                    
                    // ページをリロード
                    location.reload();
                } else {
                    alert('エラーが発生しました: ' + response.message);
                }
            },
            error: function() {
                alert('サーバーとの通信中にエラーが発生しました。');
            }
        });
    }
    
    /**
     * 全ての席の割り当てをリセットする
     */
    function resetAllSeats() {
        $.ajax({
            url: '../api/reset_all_seats.php',
            type: 'POST',
            data: {
                confirm: 'yes'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // モーダルを閉じる
                    $('#resetAllSeatsModal').modal('hide');
                    
                    // ページをリロード
                    location.reload();
                } else {
                    alert('エラーが発生しました: ' + response.message);
                }
            },
            error: function() {
                alert('サーバーとの通信中にエラーが発生しました。');
            }
        });
    }
    
    /**
     * 席次表をCSVファイルとしてエクスポート
     */
    function exportToCSV() {
        window.location.href = '../api/export_seating.php';
    }
}); 