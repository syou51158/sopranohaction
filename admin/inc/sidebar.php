<?php
// 現在のページのファイル名を取得（アクティブなメニュー項目を判定するために使用）
$current_page = basename($_SERVER['PHP_SELF']);

// リクエストURLからページを確認
$request_uri = $_SERVER['REQUEST_URI'];

// 各メニュー項目の定義
$menu_items = [
    [
        'href' => 'dashboard.php',
        'icon' => 'fas fa-tachometer-alt',
        'text' => 'ダッシュボード'
    ],
    [
        'href' => 'guestbook.php',
        'icon' => 'fas fa-book',
        'text' => 'ゲストブック管理'
    ],
    [
        'href' => 'checkin.php',
        'icon' => 'fas fa-qrcode',
        'text' => 'QRチェックイン'
    ],
    [
        'href' => 'checkin_list.php',
        'icon' => 'fas fa-clipboard-check',
        'text' => 'チェックイン履歴'
    ],
    [
        'href' => 'dashboard.php#guests',
        'icon' => 'fas fa-users',
        'text' => 'ゲスト管理'
    ],
    [
        'href' => 'dashboard.php#responses',
        'icon' => 'fas fa-reply-all',
        'text' => '回答一覧'
    ],
    [
        'href' => 'dashboard.php#add-guest',
        'icon' => 'fas fa-user-plus',
        'text' => '招待グループ追加'
    ],
    [
        'href' => 'seating.php',
        'icon' => 'fas fa-chair',
        'text' => '席次表管理'
    ],
    [
        'href' => 'guidance_settings.php',
        'icon' => 'fas fa-info-circle',
        'text' => 'チェックイン案内設定'
    ],
    [
        'href' => 'schedule.php',
        'icon' => 'fas fa-calendar-day',
        'text' => 'タイムスケジュール'
    ],
    [
        'href' => 'gifts.php',
        'icon' => 'fas fa-gift',
        'text' => 'ギフト管理'
    ],
    [
        'href' => 'photos.php',
        'icon' => 'fas fa-images',
        'text' => '写真管理'
    ],
    [
        'href' => 'videos.php',
        'icon' => 'fas fa-video',
        'text' => '動画管理'
    ],
    [
        'href' => 'faq.php',
        'icon' => 'fas fa-question-circle',
        'text' => 'Q&A管理'
    ],
    [
        'href' => 'travel.php',
        'icon' => 'fas fa-map-marked-alt',
        'text' => '交通・宿泊情報'
    ],
    [
        'href' => 'group_types.php',
        'icon' => 'fas fa-tags',
        'text' => 'グループタイプ'
    ],
    [
        'href' => 'fusen_settings.php',
        'icon' => 'fas fa-sticky-note',
        'text' => '付箋設定'
    ],
    [
        'href' => 'notifications.php',
        'icon' => 'fas fa-bell',
        'text' => '通知設定'
    ],
    [
        'href' => 'wedding_settings.php',
        'icon' => 'fas fa-cog',
        'text' => '結婚式設定'
    ],
    [
        'href' => 'manage_users.php',
        'icon' => 'fas fa-users-cog',
        'text' => 'ユーザー管理'
    ],
    [
        'href' => 'setup.php',
        'icon' => 'fas fa-cogs',
        'text' => 'セットアップ'
    ],
    [
        'href' => 'remarks.php',
        'icon' => 'fas fa-sticky-note',
        'text' => '備考・お願い管理'
    ],
];
?>

<div class="admin-sidebar">
    <div class="admin-sidebar-content">
        <div class="admin-nav">
            <ul>
                <?php foreach ($menu_items as $item): ?>
                    <?php
                    // 現在のページをチェック
                    $is_active = false;
                    if ($item['href'] === $current_page || 
                        (strpos($item['href'], '#') !== false && 
                         strpos($item['href'], $current_page) === 0) ||
                        (strpos($request_uri, $item['href']) !== false)
                    ) {
                        $is_active = true;
                    }
                    ?>
                    <li class="<?php echo $is_active ? 'active' : ''; ?>">
                        <a href="<?php echo $item['href']; ?>">
                            <i class="<?php echo $item['icon']; ?>"></i>
                            <?php echo $item['text']; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<script>
// サイドメニューのスクロール位置を保持するスクリプト
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.admin-sidebar');
    if (!sidebar) return;
    
    // サイドバーのスクロール位置を復元
    const savedScrollPosition = localStorage.getItem('sidebarScrollPosition');
    if (savedScrollPosition) {
        sidebar.scrollTop = parseInt(savedScrollPosition);
    }
    
    // スクロール位置を保存
    sidebar.addEventListener('scroll', function() {
        localStorage.setItem('sidebarScrollPosition', sidebar.scrollTop);
    });
    
    // メニュー項目クリック時にスクロール位置を保存
    const menuItems = document.querySelectorAll('.admin-nav a');
    menuItems.forEach(function(item) {
        item.addEventListener('click', function() {
            localStorage.setItem('sidebarScrollPosition', sidebar.scrollTop);
        });
    });
    
    // モバイル表示のためのクラス追加
    sidebar.classList.add('sidebar-ready');
    
    // タッチデバイス向けのスワイプ機能（オプション）
    if ('ontouchstart' in window) {
        let touchStartX = 0;
        let touchEndX = 0;
        
        // スワイプ開始
        document.body.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        
        // スワイプ終了
        document.body.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, { passive: true });
        
        // スワイプ処理
        function handleSwipe() {
            const menuToggle = document.querySelector('.mobile-menu-toggle');
            if (!menuToggle) return;
            
            // 左から右へのスワイプ（サイドバーを表示）
            if (touchEndX - touchStartX > 100 && touchStartX < 50) {
                sidebar.classList.add('visible');
                const icon = menuToggle.querySelector('i');
                if (icon) icon.className = 'fas fa-times';
            } 
            // 右から左へのスワイプ（サイドバーを非表示）
            else if (touchStartX - touchEndX > 100 && sidebar.classList.contains('visible')) {
                sidebar.classList.remove('visible');
                const icon = menuToggle.querySelector('i');
                if (icon) icon.className = 'fas fa-bars';
            }
        }
    }
});
</script>