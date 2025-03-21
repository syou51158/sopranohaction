<?php
// 結婚式の設定を取得する関数
function get_wedding_venue_info() {
    global $pdo;
    $venue_info = [
        'name' => '',
        'address' => '',
        'map_url' => '',
        'map_link' => ''
    ];
    
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM wedding_settings WHERE setting_key IN ('venue_name', 'venue_address', 'venue_map_url', 'venue_map_link')");
        $stmt->execute();
        
        while ($row = $stmt->fetch()) {
            switch ($row['setting_key']) {
                case 'venue_name':
                    $venue_info['name'] = $row['setting_value'];
                    break;
                case 'venue_address':
                    $venue_info['address'] = $row['setting_value'];
                    break;
                case 'venue_map_url':
                    // iframeタグが含まれている場合、src属性からURLを抽出
                    if (strpos($row['setting_value'], '<iframe') !== false) {
                        preg_match('/src=["\']([^"\']+)["\']/', $row['setting_value'], $matches);
                        $venue_info['map_url'] = isset($matches[1]) ? $matches[1] : '';
                    } else {
                        $venue_info['map_url'] = $row['setting_value'];
                    }
                    break;
                case 'venue_map_link':
                    // iframeタグが含まれている場合、href属性からURLを抽出
                    if (strpos($row['setting_value'], '<a') !== false) {
                        preg_match('/href=["\']([^"\']+)["\']/', $row['setting_value'], $matches);
                        $venue_info['map_link'] = isset($matches[1]) ? $matches[1] : '';
                    } else {
                        $venue_info['map_link'] = $row['setting_value'];
                    }
                    break;
            }
        }
    } catch (PDOException $e) {
        // エラー処理（静かに失敗）
    }
    
    return $venue_info;
}

// 会場情報を取得
$venue_info = get_wedding_venue_info();

// デバッグ情報（一時的に有効化して問題を確認する場合に使用）
/*
echo '<div style="background: #fff; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
echo '<pre>';
print_r($venue_info);
echo '</pre>';
echo '</div>';
*/
?>

<section class="venue-section">
    <div class="venue-info">
        <h2 class="section-title">会場のご案内</h2>
        
        <div class="venue-details">
            <div class="venue-text">
                <h3><?= htmlspecialchars($venue_info['name']) ?></h3>
                <p class="venue-address">
                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($venue_info['address']) ?>
                </p>
                <p class="venue-directions">
                    <a href="<?= htmlspecialchars($venue_info['map_link']) ?>" target="_blank" class="venue-link">
                        <i class="fas fa-directions"></i> Google マップで見る
                    </a>
                </p>
            </div>
            
            <?php if (!empty($venue_info['map_url'])): ?>
            <div class="venue-map">
                <iframe 
                    src="<?= htmlspecialchars($venue_info['map_url']) ?>" 
                    width="100%" 
                    height="300" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
.venue-section {
    padding: 2rem 0;
    background-color: #f9f9f9;
}

.venue-info {
    max-width: 800px;
    margin: 0 auto;
    padding: 0 1rem;
}

.venue-details {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.venue-text {
    flex: 1;
}

.venue-map {
    flex: 1;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.venue-map iframe {
    display: block;
}

.venue-address {
    margin: 0.5rem 0;
    font-size: 1rem;
    color: #666;
}

.venue-link {
    display: inline-block;
    padding: 0.5rem 1rem;
    margin-top: 0.5rem;
    color: #fff;
    background-color: #4285F4;
    border-radius: 4px;
    text-decoration: none;
    transition: background-color 0.3s;
}

.venue-link:hover {
    background-color: #3367D6;
}

@media (min-width: 768px) {
    .venue-details {
        flex-direction: row;
    }
}
</style> 