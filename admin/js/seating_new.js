/**
 * 席次表管理システム用JavaScript
 */
$(document).ready(function() {
    let selectedGuestId = null;
    let selectedTableId = null;
    let selectedSeatNumber = null;
    let draggedGuestId = null;

    // 1. ドラッグ＆ドロップ機能を初期化
    initDragAndDrop();
    
    // 2. 席クリックイベントの設定
    initSeatClickEvents();
    
    // 3. その他のボタンイベントの設定
    initButtonEvents();
    
    // 4. ゲスト検索機能
    initGuestSearch();

    /**
     * ドラッグ＆ドロップ機能の初期化
     */
    function initDragAndDrop() {
        // ゲストカードをドラッグ可能に
        $(".guest-card").draggable({
            helper: "clone",
            cursor: "move",
            cursorAt: { top: 25, left: 50 },
            start: function(event, ui) {
                draggedGuestId = $(this).data("guest-id");
                $(this).addClass("dragging");
            },
            stop: function(event, ui) {
                $(this).removeClass("dragging");
            }
        });

        // 席にドロップ可能に
        $(".seat").droppable({
            accept: ".guest-card",
            hoverClass: "drop-hover",
            drop: function(event, ui) {
                // すでに割り当てられた席の場合は処理しない
                if ($(this).hasClass("occupied")) {
                    alert("この席はすでに割り当てられています。");
                    return;
                }

                selectedTableId = $(this).data("table-id");
                selectedSeatNumber = $(this).data("seat-number");
                selectedGuestId = draggedGuestId;

                // ゲスト情報を取得して確認モーダルを表示
                getGuestInfo(selectedGuestId);
            }
        });
    }

    /**
     * 席クリックイベントの初期化
     */
    function initSeatClickEvents() {
        // 席をクリックした時の処理
        $(".seat").on("click", function() {
            // 割り当て済みの席の場合
            if ($(this).hasClass("occupied")) {
                selectedTableId = $(this).data("table-id");
                selectedSeatNumber = $(this).data("seat-number");
                
                // 席に割り当てられたゲスト情報を取得して解除モーダルを表示
                getSeatInfo(selectedTableId, selectedSeatNumber);
            }
        });
    }

    /**
     * ボタンイベントの初期化
     */
    function initButtonEvents() {
        // 席の割り当てを確定
        $("#confirm-assign").on("click", function() {
            assignSeat(selectedGuestId, selectedTableId, selectedSeatNumber);
            $("#assignSeatModal").modal("hide");
        });

        // 席の割り当て解除を確定
        $("#confirm-reset").on("click", function() {
            resetSeat(selectedTableId, selectedSeatNumber);
            $("#resetSeatModal").modal("hide");
        });

        // 全席リセットボタン
        $("#reset-all-seats").on("click", function() {
            $("#resetAllSeatsModal").modal("show");
        });

        // 全席リセットを確定
        $("#confirm-reset-all").on("click", function() {
            resetAllSeats();
            $("#resetAllSeatsModal").modal("hide");
        });

        // 印刷ボタン
        $("#print-seating").on("click", function() {
            window.print();
        });

        // CSVエクスポートボタン
        $("#export-csv").on("click", function() {
            window.location.href = "api/export_seating_csv.php";
        });
    }

    /**
     * ゲスト検索機能の初期化
     */
    function initGuestSearch() {
        $("#search-btn").on("click", function() {
            searchGuests();
        });

        $("#guest-search").on("keyup", function(e) {
            if (e.key === "Enter") {
                searchGuests();
            }
        });
    }

    /**
     * ゲスト検索の実行
     */
    function searchGuests() {
        const searchTerm = $("#guest-search").val().toLowerCase();
        
        if (searchTerm.length > 0) {
            $(".guest-card").each(function() {
                const guestName = $(this).find(".guest-card-name").text().toLowerCase();
                const guestGroup = $(this).find(".guest-card-group").text().toLowerCase();
                
                if (guestName.includes(searchTerm) || guestGroup.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        } else {
            // 検索キーワードがなければ全て表示
            $(".guest-card").show();
        }
    }

    /**
     * APIを使ってゲスト情報を取得
     */
    function getGuestInfo(guestId) {
        $.ajax({
            url: "api/get_guest_info.php",
            type: "GET",
            data: { guest_id: guestId },
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    $("#selected-table").text(selectedTableId);
                    $("#selected-seat").text(selectedSeatNumber);
                    $("#selected-guest-info").html(
                        `<div class="alert alert-info">
                            <p><strong>${response.data.name}</strong></p>
                            <p>${response.data.group_name}</p>
                        </div>`
                    );
                    $("#assignSeatModal").modal("show");
                } else {
                    alert("エラーが発生しました: " + response.message);
                }
            },
            error: function() {
                alert("ゲスト情報の取得に失敗しました。");
            }
        });
    }

    /**
     * APIを使って席情報を取得
     */
    function getSeatInfo(tableId, seatNumber) {
        $.ajax({
            url: "api/get_seat_info.php",
            type: "GET",
            data: { 
                table_number: tableId,
                seat_number: seatNumber
            },
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    $("#reset-guest-info").html(
                        `<div class="alert alert-info">
                            <p><strong>${response.data.guest_name}</strong></p>
                            <p>${response.data.group_name}</p>
                        </div>`
                    );
                    $("#resetSeatModal").modal("show");
                } else {
                    alert("エラーが発生しました: " + response.message);
                }
            },
            error: function() {
                alert("席情報の取得に失敗しました。");
            }
        });
    }

    /**
     * APIを使って席割り当てを実行
     */
    function assignSeat(guestId, tableId, seatNumber) {
        $.ajax({
            url: "api/assign_seat.php",
            type: "POST",
            data: {
                guest_id: guestId,
                table_number: tableId,
                seat_number: seatNumber
            },
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    location.reload(); // 成功したらページをリロード
                } else {
                    alert("エラーが発生しました: " + response.message);
                }
            },
            error: function() {
                alert("席の割り当てに失敗しました。");
            }
        });
    }

    /**
     * APIを使って席の割り当てを解除
     */
    function resetSeat(tableId, seatNumber) {
        $.ajax({
            url: "api/remove_seat_assignment.php",
            type: "POST",
            data: {
                table_number: tableId,
                seat_number: seatNumber
            },
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    location.reload(); // 成功したらページをリロード
                } else {
                    alert("エラーが発生しました: " + response.message);
                }
            },
            error: function() {
                alert("席の割り当て解除に失敗しました。");
            }
        });
    }

    /**
     * APIを使って全席リセット
     */
    function resetAllSeats() {
        $.ajax({
            url: "api/reset_all_seats.php",
            type: "POST",
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    location.reload(); // 成功したらページをリロード
                } else {
                    alert("エラーが発生しました: " + response.message);
                }
            },
            error: function() {
                alert("全席リセットに失敗しました。");
            }
        });
    }
}); 