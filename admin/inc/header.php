<?php
// セッションが開始されていない場合は開始
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ログインチェック
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 現在のページのファイル名を取得
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : '' ?>結婚式管理システム</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Bootstrap CSS (必要に応じて追加) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- jQuery と Bootstrap JS (必要に応じて追加) -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
    /* ページ遷移アニメーション用のスタイル */
    .page-transition-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(255, 255, 255, 0.5);
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease;
    }
    
    .page-transition-overlay.active {
        opacity: 1;
        pointer-events: all;
    }
    
    .page-transition-spinner {
        width: 50px;
        height: 50px;
        border: 5px solid rgba(74, 140, 202, 0.2);
        border-radius: 50%;
        border-top-color: var(--admin-primary);
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* コンテンツ領域がフェードインするアニメーション */
    .admin-content-wrapper {
        opacity: 1;
        transition: opacity 0.3s ease;
    }
    
    .admin-content-wrapper.loading {
        opacity: 0.6;
    }
    
    /* モバイル用のハンバーガーメニュー */
    .mobile-menu-toggle {
        display: none;
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 10px;
        margin-right: 10px;
    }
    
    /* モバイル時のサイドバー表示制御 */
    @media (max-width: 768px) {
        .mobile-menu-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .admin-sidebar {
            display: none;
            transition: transform 0.3s ease;
            position: fixed;
            top: 60px;
            left: 0;
            width: 80%;
            max-width: 300px;
            z-index: 1001;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .admin-sidebar.visible {
            display: block;
            transform: translateX(0);
        }
        
        .admin-dashboard-content {
            position: relative;
        }
        
        /* オーバーレイ */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        /* ヘッダーのレイアウト調整 */
        .admin-dashboard-header {
            justify-content: space-between;
            padding: 0 10px;
        }
        
        .admin-logo {
            font-size: 18px;
            flex-grow: 1;
            text-align: center;
        }
    }
    </style>
    <script>
    // サイドメニューのスクロール位置を管理するためのグローバル関数
    window.scrollToActiveMenuItem = function() {
        setTimeout(function() {
            const sidebar = document.querySelector('.admin-sidebar');
            const activeItem = document.querySelector('.admin-nav li.active');
            
            if (sidebar && activeItem) {
                // 保存されたスクロール位置があれば、それを優先
                const savedScrollPosition = localStorage.getItem('sidebarScrollPosition');
                if (savedScrollPosition && parseInt(savedScrollPosition) > 0) {
                    sidebar.scrollTop = parseInt(savedScrollPosition);
                } else {
                    // アクティブな項目の位置を取得
                    const sidebarRect = sidebar.getBoundingClientRect();
                    const activeItemRect = activeItem.getBoundingClientRect();
                    
                    // アクティブな項目が表示領域外にある場合は、表示領域内にスクロール
                    if (activeItemRect.top < sidebarRect.top || activeItemRect.bottom > sidebarRect.bottom) {
                        // アイテムの上端がサイドバーの中央に来るようにスクロール
                        const scrollTo = activeItem.offsetTop - (sidebar.clientHeight / 2) + (activeItem.clientHeight / 2);
                        sidebar.scrollTop = Math.max(0, scrollTo);
                        
                        // 新しいスクロール位置を保存
                        localStorage.setItem('sidebarScrollPosition', sidebar.scrollTop);
                    }
                }
            }
        }, 100); // 少し遅延させてDOM構築後に実行
    };
    
    // ページ読み込み完了時に実行
    document.addEventListener('DOMContentLoaded', function() {
        window.scrollToActiveMenuItem();
        
        // Ajaxナビゲーションを初期化
        initAjaxNavigation();
        
        // モバイルメニューを初期化
        initMobileMenu();
    });
    
    // Ajaxナビゲーションの初期化
    function initAjaxNavigation() {
        // オーバーレイ要素の作成
        if (!document.querySelector('.page-transition-overlay')) {
            const overlay = document.createElement('div');
            overlay.className = 'page-transition-overlay';
            const spinner = document.createElement('div');
            spinner.className = 'page-transition-spinner';
            overlay.appendChild(spinner);
            document.body.appendChild(overlay);
        }
        
        // サイドバーオーバーレイの作成
        if (!document.querySelector('.sidebar-overlay')) {
            const sidebarOverlay = document.createElement('div');
            sidebarOverlay.className = 'sidebar-overlay';
            document.body.appendChild(sidebarOverlay);
            
            // オーバーレイクリックでサイドバーを閉じる
            sidebarOverlay.addEventListener('click', function() {
                const sidebar = document.querySelector('.admin-sidebar');
                if (sidebar) sidebar.classList.remove('visible');
                this.classList.remove('active');
                
                // ハンバーガーアイコンを戻す
                const menuToggle = document.querySelector('.mobile-menu-toggle');
                if (menuToggle) {
                    const icon = menuToggle.querySelector('i');
                    if (icon) icon.className = 'fas fa-bars';
                }
            });
        }
        
        // 現在のURLをセッションストレージに保存
        sessionStorage.setItem('lastUrl', window.location.href);
        
        // ページの状態を保存
        window.addEventListener('beforeunload', function() {
            sessionStorage.setItem('lastScrollPos', window.scrollY);
        });
        
        // ページ読み込み完了時のトランジション
        window.addEventListener('load', function() {
            const overlay = document.querySelector('.page-transition-overlay');
            if (overlay) {
                setTimeout(function() {
                    overlay.classList.remove('active');
                }, 200);
            }
            
            // コンテンツに遷移アニメーションを適用
            const contentWrapper = document.querySelector('.admin-content-wrapper');
            if (contentWrapper) {
                contentWrapper.classList.add('fade-transition');
                contentWrapper.classList.remove('loading');
            }
            
            // 前回のスクロール位置を復元
            const lastScrollPos = sessionStorage.getItem('lastScrollPos');
            if (lastScrollPos) {
                window.scrollTo(0, parseInt(lastScrollPos));
            }
        });
        
        // アクティブなメニュー項目をハイライト
        highlightActiveMenuItem();
    }
    
    // アクティブなメニュー項目をハイライト
    function highlightActiveMenuItem() {
        const currentPath = window.location.pathname;
        const currentPage = currentPath.split('/').pop();
        
        const menuItems = document.querySelectorAll('.admin-nav a');
        menuItems.forEach(function(item) {
            const href = item.getAttribute('href');
            if (href === currentPage || 
                (href.indexOf('#') !== -1 && href.split('#')[0] === currentPage)) {
                item.parentElement.classList.add('active');
            }
        });
    }
    
    // モバイルメニューの初期化
    function initMobileMenu() {
        // ヘッダーを取得
        const header = document.querySelector('.admin-dashboard-header');
        
        // ハンバーガーメニューボタンを追加
        if (header && !document.querySelector('.mobile-menu-toggle')) {
            const menuToggle = document.createElement('button');
            menuToggle.className = 'mobile-menu-toggle';
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            menuToggle.setAttribute('aria-label', 'メニュー');
            
            // ロゴの前に配置
            const logo = header.querySelector('.admin-logo');
            if (logo) {
                header.insertBefore(menuToggle, logo);
            } else {
                header.prepend(menuToggle);
            }
            
            // メニュー開閉の処理
            menuToggle.addEventListener('click', function() {
                const sidebar = document.querySelector('.admin-sidebar');
                const sidebarOverlay = document.querySelector('.sidebar-overlay');
                
                sidebar.classList.toggle('visible');
                
                // オーバーレイの表示切替
                if (sidebarOverlay) {
                    if (sidebar.classList.contains('visible')) {
                        sidebarOverlay.classList.add('active');
                    } else {
                        sidebarOverlay.classList.remove('active');
                    }
                }
                
                // アイコンを切り替え
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('visible')) {
                    icon.className = 'fas fa-times';
                } else {
                    icon.className = 'fas fa-bars';
                }
            });
            
            // ウィンドウサイズが変わったときの処理
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    const sidebar = document.querySelector('.admin-sidebar');
                    const menuToggle = document.querySelector('.mobile-menu-toggle');
                    const sidebarOverlay = document.querySelector('.sidebar-overlay');
                    
                    if (sidebar) sidebar.classList.remove('visible');
                    if (sidebarOverlay) sidebarOverlay.classList.remove('active');
                    if (menuToggle) {
                        const icon = menuToggle.querySelector('i');
                        icon.className = 'fas fa-bars';
                    }
                }
            });
        }
    }
    </script>
</head>
<body class="admin-body">
    <div class="admin-dashboard">
        <header class="admin-dashboard-header">
            <div class="admin-logo">
                <h1>結婚式管理システム</h1>
            </div>
            <div class="admin-user">
                <span>ようこそ、<?php echo isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'ゲスト'; ?>様</span>
                <div class="admin-user-menu">
                    <button class="admin-user-menu-toggle">
                        <i class="fas fa-user-circle"></i>
                    </button>
                    <div class="admin-user-dropdown">
                        <a href="profile.php"><i class="fas fa-user-edit"></i> プロフィール</a>
                        <a href="settings.php"><i class="fas fa-cog"></i> 設定</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> ログアウト</a>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="admin-dashboard-content">
            <?php include 'sidebar.php'; ?>
            
            <div class="admin-main">
                <div class="admin-content-wrapper"> 