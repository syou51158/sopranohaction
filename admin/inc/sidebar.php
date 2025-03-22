<?php
// 現在のページのファイル名を取得（アクティブなメニュー項目を判定するために使用）
$current_page = basename($_SERVER['PHP_SELF']);

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
];
?>

<div class="admin-sidebar">
    <nav class="admin-nav">
        <ul>
            <?php foreach ($menu_items as $item): 
                // アクティブなメニュー項目かどうかを判定
                $is_active = false;
                
                // 完全一致の場合
                if ($item['href'] === $current_page) {
                    $is_active = true;
                }
                // dashboard.php#～ のような場合の処理
                elseif (strpos($item['href'], '#') !== false) {
                    $page_part = explode('#', $item['href'])[0];
                    if ($page_part === $current_page) {
                        // ダッシュボードの場合は特殊処理
                        if ($current_page === 'dashboard.php' && $item['href'] === 'dashboard.php') {
                            $is_active = true;
                        }
                    }
                }
                // 編集ページやその他特殊なページの処理
                elseif (($current_page === 'edit_guest.php' || $current_page === 'delete_guest.php') && $item['href'] === 'dashboard.php#guests') {
                    $is_active = true;
                }
                // ユーザー管理関連ページの処理
                elseif (($current_page === 'register.php' || $current_page === 'verify.php' || 
                        $current_page === 'forgot_password.php' || $current_page === 'reset_password.php') && 
                        $item['href'] === 'manage_users.php') {
                    $is_active = true;
                }
            ?>
                <li<?php echo $is_active ? ' class="active"' : ''; ?>>
                    <a href="<?php echo $item['href']; ?>">
                        <i class="<?php echo $item['icon']; ?>"></i> <?php echo $item['text']; ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <li class="admin-sidebar-item <?= strpos($_SERVER['PHP_SELF'], '/faq.php') !== false ? 'active' : '' ?>">
                <a href="faq.php"><i class="fas fa-question-circle"></i> FAQ管理</a>
            </li>
            <li class="admin-sidebar-item <?= strpos($_SERVER['PHP_SELF'], '/remarks.php') !== false ? 'active' : '' ?>">
                <a href="remarks.php"><i class="fas fa-sticky-note"></i> 備考・お願い管理</a>
            </li>
            <li class="admin-sidebar-divider"></li>
        </ul>
    </nav>
</div>

<div class="admin-tools">
    <a href="../dashboard.php" class="admin-tool<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? ' active' : '' ?>">
        <i class="fas fa-chart-line"></i> ダッシュボード
    </a>
    <a href="../guest-list.php" class="admin-tool<?= basename($_SERVER['PHP_SELF']) === 'guest-list.php' ? ' active' : '' ?>">
        <i class="fas fa-users"></i> ゲスト管理
    </a>
    <a href="../table-layout.php" class="admin-tool<?= basename($_SERVER['PHP_SELF']) === 'table-layout.php' ? ' active' : '' ?>">
        <i class="fas fa-th"></i> 席次表
    </a>
    <a href="../photos.php" class="admin-tool<?= basename($_SERVER['PHP_SELF']) === 'photos.php' ? ' active' : '' ?>">
        <i class="fas fa-images"></i> 写真管理
    </a>
    <a href="../message.php" class="admin-tool<?= basename($_SERVER['PHP_SELF']) === 'message.php' ? ' active' : '' ?>">
        <i class="fas fa-envelope"></i> メッセージ編集
    </a>
    <a href="../travel.php" class="admin-tool<?= basename($_SERVER['PHP_SELF']) === 'travel.php' ? ' active' : '' ?>">
        <i class="fas fa-plane"></i> 交通・宿泊情報
    </a>
    <a href="../inc/fix_permissions.php" class="admin-tool<?= basename($_SERVER['PHP_SELF']) === 'fix_permissions.php' ? ' active' : '' ?>">
        <i class="fas fa-tools"></i> パーミッション修正
    </a>
    <a href="../settings.php" class="admin-tool<?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? ' active' : '' ?>">
        <i class="fas fa-cog"></i> 設定
    </a>
</div> 