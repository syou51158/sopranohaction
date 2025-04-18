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

// エラーと成功メッセージの初期化
$error = '';
$success = '';

// XAMPP環境用のパス設定
$is_xampp = false;
$xampp_tmp_dir = '';

// XAMPPのパスを検出
if (strpos($_SERVER['DOCUMENT_ROOT'], 'xampp') !== false) {
    $is_xampp = true;
    // Macの場合のXAMPPのtmpディレクトリパス
    if (PHP_OS === 'Darwin') { // Mac OS
        $xampp_tmp_dir = '/Applications/XAMPP/xamppfiles/temp/';
    } else {
        // Windowsの場合
        $xampp_tmp_dir = 'C:/xampp/temp/';
    }
}

// ローカルとサーバーで使い分ける保存先の設定
$local_images_dir = $is_xampp ? $xampp_tmp_dir . 'wedding_images/' : '../images/';
$web_images_dir = '../images/';

// ディレクトリが存在しない場合は作成を試みる
if ($is_xampp && !is_dir($local_images_dir)) {
    @mkdir($local_images_dir, 0777, true);
}
if (!is_dir($web_images_dir) && !$is_xampp) {
    @mkdir($web_images_dir, 0755, true);
}

// ローカル環境での表示用URL設定
$ogp_display_dir = $is_xampp ? $local_images_dir : $web_images_dir;

// ローカル環境のための表示URL
$xampp_base_url = '';
if ($is_xampp) {
    // XAMPPのベースURLを設定
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $xampp_base_url = $protocol . $_SERVER['HTTP_HOST'] . '/';
}

// OGP画像アップロード処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload_ogp_image') {
        // ファイルがアップロードされているか確認
        if (isset($_FILES['ogp_image']) && $_FILES['ogp_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['ogp_image'];
            
            // 画像の種類を確認
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowed_types)) {
                $error = 'アップロードできるのはJPEG、PNG、GIF画像のみです。';
            } else {
                // OGP画像のファイル名
                $filename = 'ogp-image.jpg';
                $destination = $local_images_dir . $filename;
                
                // 一時的なバックアップ
                $backup_success = false;
                if (file_exists($destination)) {
                    try {
                        // バックアップファイルが存在する場合は先に削除
                        if (file_exists($destination . '.bak')) {
                            @unlink($destination . '.bak');
                        }
                        
                        if (copy($destination, $destination . '.bak')) {
                            $backup_success = true;
                        }
                    } catch (Exception $e) {
                        // バックアップに失敗してもプロセスは続行
                    }
                }
                
                // 画像をリサイズしてアップロード
                try {
                    // 画像情報を取得
                    $image_info = getimagesize($file['tmp_name']);
                    
                    // 新しい画像サイズ（OGP推奨サイズ）
                    $width = 1200;
                    $height = 630;
                    
                    // 元の画像から新しい画像を作成
                    $src_image = null;
                    switch ($file['type']) {
                        case 'image/jpeg':
                            $src_image = imagecreatefromjpeg($file['tmp_name']);
                            break;
                        case 'image/png':
                            $src_image = imagecreatefrompng($file['tmp_name']);
                            break;
                        case 'image/gif':
                            $src_image = imagecreatefromgif($file['tmp_name']);
                            break;
                    }
                    
                    if ($src_image) {
                        // 透過処理のための設定
                        $src_width = imagesx($src_image);
                        $src_height = imagesy($src_image);
                        
                        // 新しい画像を作成
                        $new_image = imagecreatetruecolor((int)$width, (int)$height);
                        
                        // PNGの場合、透過処理
                        if ($file['type'] === 'image/png') {
                            imagealphablending($new_image, false);
                            imagesavealpha($new_image, true);
                            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
                            imagefilledrectangle($new_image, 0, 0, (int)$width, (int)$height, $transparent);
                        } else {
                            // 背景を白で塗りつぶす
                            $white = imagecolorallocate($new_image, 255, 255, 255);
                            imagefilledrectangle($new_image, 0, 0, (int)$width, (int)$height, $white);
                        }
                        
                        // アスペクト比を維持して画像をリサイズ
                        $src_aspect = $src_width / $src_height;
                        $dst_aspect = $width / $height;
                        
                        if ($src_aspect > $dst_aspect) {
                            // 横長の画像
                            $new_src_width = $src_height * $dst_aspect;
                            $new_src_height = $src_height;
                            $src_x = (int)(($src_width - $new_src_width) / 2);
                            $src_y = 0;
                        } else {
                            // 縦長の画像
                            $new_src_width = $src_width;
                            $new_src_height = $src_width / $dst_aspect;
                            $src_x = 0;
                            $src_y = (int)(($src_height - $new_src_height) / 2);
                        }
                        
                        // 画像のリサイズ
                        imagecopyresampled(
                            $new_image, $src_image,
                            0, 0, (int)$src_x, (int)$src_y,
                            (int)$width, (int)$height, (int)$new_src_width, (int)$new_src_height
                        );
                        
                        // 新しい画像を保存
                        $save_success = false;
                        try {
                            // 保存先ファイルに書き込み権限がない場合は変更を試みる
                            if (file_exists($destination) && !is_writable($destination)) {
                                @chmod($destination, 0644);
                            }
                            
                            if (imagejpeg($new_image, $destination, 90)) {
                                $save_success = true;
                            }
                        } catch (Exception $e) {
                            // 直接保存に失敗した場合
                            if ($debug_mode) {
                                $error .= ' 警告: ' . $e->getMessage();
                            }
                        }
                        
                        // 直接保存に失敗した場合、一時ファイルを使用
                        if (!$save_success) {
                            $temp_dir = sys_get_temp_dir();
                            $temp_destination = $temp_dir . '/ogp-image-temp.jpg';
                            
                            try {
                                if (imagejpeg($new_image, $temp_destination, 90)) {
                                    // 一時ファイルを作成できた場合、サイトディレクトリにコピーを試みる
                                    if (copy($temp_destination, $destination)) {
                                        $save_success = true;
                                        @unlink($temp_destination); // 一時ファイルを削除
                                    } else {
                                        // コピーできなかった場合、権限問題の可能性が高い
                                        $error = 'OGP画像を保存できませんでした。Macの場合、XAMPPのhtdocsフォルダの権限を確認してください。';
                                        $error .= '<br>手動で画像を移動する場合は、次の一時ファイルをコピーしてください: ' . $temp_destination;
                                        
                                        // バックアップから復元
                                        if ($backup_success && file_exists($destination . '.bak')) {
                                            @copy($destination . '.bak', $destination);
                                        }
                                    }
                                }
                            } catch (Exception $e) {
                                $error = '画像の処理に失敗しました。';
                                if ($debug_mode) {
                                    $error .= ' エラー: ' . $e->getMessage();
                                }
                                
                                // バックアップから復元
                                if ($backup_success && file_exists($destination . '.bak')) {
                                    @copy($destination . '.bak', $destination);
                                }
                            }
                        }
                        
                        // リソースを解放
                        imagedestroy($src_image);
                        imagedestroy($new_image);
                        
                        if ($save_success) {
                            // ローカルディレクトリとWebディレクトリが異なる場合、ファイルをWebディレクトリにコピー
                            if ($is_xampp && $destination !== $web_images_dir . 'ogp-image.jpg') {
                                try {
                                    // Webディレクトリが存在しない場合は作成
                                    if (!is_dir($web_images_dir)) {
                                        @mkdir($web_images_dir, 0755, true);
                                    }
                                    // ローカルで保存した画像をWebディレクトリにコピー
                                    if (copy($destination, $web_images_dir . 'ogp-image.jpg')) {
                                        $success = 'OGP画像をアップロードしました。';
                                    } else {
                                        // コピーに失敗した場合もローカルには保存されているのでエラーではなく警告
                                        $success = 'OGP画像をローカルにアップロードしました（Webディレクトリへのコピーに失敗）。';
                                        if ($debug_mode) {
                                            $success .= '<br>ローカルパス: ' . $destination;
                                        }
                                    }
                                } catch (Exception $e) {
                                    // エラーが発生してもローカルには保存できている
                                    $success = 'OGP画像をローカルにアップロードしました。';
                                    if ($debug_mode) {
                                        $success .= '<br>警告: ' . $e->getMessage();
                                    }
                                }
                            } else {
                                $success = 'OGP画像をアップロードしました。';
                            }
                        } elseif (empty($error)) {
                            $error = '画像の保存に失敗しました。';
                        }
                    } else {
                        $error = '画像の処理に失敗しました。';
                    }
                } catch (Exception $e) {
                    // エラー時はバックアップから復元
                    if (file_exists($destination . '.bak')) {
                        copy($destination . '.bak', $destination);
                        unlink($destination . '.bak');
                    }
                    
                    $error = '画像のアップロードに失敗しました。';
                    if ($debug_mode) {
                        $error .= ' エラー: ' . $e->getMessage();
                    }
                }
                
                // バックアップファイルを削除
                if (file_exists($destination . '.bak')) {
                    try {
                        @unlink($destination . '.bak');
                    } catch (Exception $e) {
                        // バックアップ削除に失敗しても問題ない
                        if ($debug_mode) {
                            $error .= ' 警告: バックアップファイルの削除に失敗しました。';
                        }
                    }
                }
            }
        } else {
            $error = 'ファイルのアップロードに失敗しました。';
            if ($debug_mode) {
                $error .= ' エラーコード: ' . $_FILES['ogp_image']['error'];
            }
        }
    } elseif ($_POST['action'] === 'generate_ogp_image') {
        // OGP画像生成処理
        $title = isset($_POST['title']) ? $_POST['title'] : '結婚式のご招待';
        $subtitle = isset($_POST['subtitle']) ? $_POST['subtitle'] : 'あかね & 村岡翔';
        $date = isset($_POST['date']) ? $_POST['date'] : '2024年4月30日';
        
        // 画像生成
        $width = 1200;
        $height = 630;
        $bgColor = [255, 255, 255]; // 白色
        $textColor = [60, 60, 60]; // ダークグレー
        $accentColor = [230, 180, 180]; // 薄いピンク
        
        // 画像を作成
        $image = imagecreatetruecolor((int)$width, (int)$height);
        
        // 背景を塗りつぶす
        $bg = imagecolorallocate($image, $bgColor[0], $bgColor[1], $bgColor[2]);
        imagefill($image, 0, 0, $bg);
        
        // アクセントカラーで装飾を描画
        $accent = imagecolorallocate($image, $accentColor[0], $accentColor[1], $accentColor[2]);
        
        // 枠線を描画
        $borderWidth = 20;
        imagefilledrectangle($image, 0, 0, (int)$width, (int)$borderWidth, $accent);
        imagefilledrectangle($image, 0, (int)$height - (int)$borderWidth, (int)$width, (int)$height, $accent);
        imagefilledrectangle($image, 0, 0, (int)$borderWidth, (int)$height, $accent);
        imagefilledrectangle($image, (int)$width - (int)$borderWidth, 0, (int)$width, (int)$height, $accent);
        
        // テキストカラーを設定
        $textColor = imagecolorallocate($image, $textColor[0], $textColor[1], $textColor[2]);
        
        // フォントファイルを探す
        $fontFile = "C:/Windows/Fonts/meiryo.ttc"; // Windows
        if (!file_exists($fontFile)) {
            $fontFile = "/Library/Fonts/Arial Unicode.ttf"; // Mac
        }
        if (!file_exists($fontFile)) {
            $fontFile = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"; // Linux
        }
        
        // フォントが見つからない場合はデフォルトフォントを使用
        $useDefaultFont = !file_exists($fontFile);
        
        // タイトルテキストを追加
        if (!$useDefaultFont) {
            $fontSize = 50;
            $textBox = imagettfbbox($fontSize, 0, $fontFile, $title);
            $textWidth = $textBox[2] - $textBox[0];
            $textHeight = $textBox[1] - $textBox[7];
            $textX = (int)(($width - $textWidth) / 2);
            $textY = 200 + $textHeight;
            imagettftext($image, $fontSize, 0, $textX, $textY, $textColor, $fontFile, $title);
            
            // サブタイトルを追加
            $fontSize = 40;
            $textBox = imagettfbbox($fontSize, 0, $fontFile, $subtitle);
            $textWidth = $textBox[2] - $textBox[0];
            $textX = (int)(($width - $textWidth) / 2);
            $textY = 300;
            imagettftext($image, $fontSize, 0, $textX, $textY, $textColor, $fontFile, $subtitle);
        } else {
            // デフォルトフォントを使用
            $titleLength = strlen($title);
            $textX = ($width - $titleLength * 8) / 2; // 1文字約8ピクセル
            imagestring($image, 5, $textX, 200, $title, $textColor);
            
            $subtitleLength = strlen($subtitle);
            $textX = ($width - $subtitleLength * 8) / 2;
            imagestring($image, 4, $textX, 250, $subtitle, $textColor);
        }
        
        // ハートを描画
        $heartSize = 100;
        $heartX = (int)(($width - $heartSize) / 2);
        $heartY = 350;
        
        // ベジェ曲線でハート形を作成
        $points = [];
        // ハートの上部
        $points[] = $heartX + $heartSize/2;
        $points[] = $heartY;
        // 右側の曲線
        $points[] = $heartX + $heartSize;
        $points[] = $heartY - $heartSize/4;
        $points[] = $heartX + $heartSize;
        $points[] = $heartY + $heartSize/4;
        $points[] = $heartX + $heartSize/2;
        $points[] = $heartY + $heartSize/2;
        // 下部の点
        $points[] = $heartX + $heartSize/2;
        $points[] = $heartY + $heartSize;
        // 左側の曲線
        $points[] = $heartX;
        $points[] = $heartY + $heartSize/2;
        $points[] = $heartX;
        $points[] = $heartY + $heartSize/4;
        $points[] = $heartX;
        $points[] = $heartY - $heartSize/4;
        // 上部に戻る
        $points[] = $heartX + $heartSize/2;
        $points[] = $heartY;
        
        // ハートを塗りつぶす
        $heartColor = imagecolorallocate($image, 255, 150, 150);
        // PHP 8以降の非推奨パラメータに対応
        imagefilledpolygon($image, $points, count($points)/2, $heartColor);
        
        // 日付を追加
        if (!$useDefaultFont) {
            $fontSize = 30;
            $dateText = "結婚式: " . $date;
            $textBox = imagettfbbox($fontSize, 0, $fontFile, $dateText);
            $textWidth = $textBox[2] - $textBox[0];
            $textX = (int)(($width - $textWidth) / 2);
            $textY = 500;
            imagettftext($image, $fontSize, 0, $textX, $textY, $textColor, $fontFile, $dateText);
        } else {
            $dateText = "結婚式: " . $date;
            $textLength = strlen($dateText);
            $textX = (int)(($width - $textLength * 8) / 2);
            imagestring($image, 3, $textX, 480, $dateText, $textColor);
        }
        
        // 画像を保存
        $destination = $web_images_dir . 'ogp-image.jpg';
        
        // ディレクトリが存在し、書き込み権限があることを確認
        $dir = $web_images_dir;
        if (!is_dir($dir)) {
            try {
                mkdir($dir, 0755, true);
            } catch (Exception $e) {
                // ディレクトリ作成に失敗した場合のエラーメッセージ
                $error = 'イメージディレクトリを作成できませんでした。手動で作成してください: ' . $dir;
                if ($debug_mode) {
                    $error .= ' エラー: ' . $e->getMessage();
                }
            }
        }
        
        // ディレクトリの権限を確認し、必要なら変更
        if (is_dir($dir) && !is_writable($dir)) {
            try {
                chmod($dir, 0755);
            } catch (Exception $e) {
                // 権限変更に失敗した場合は記録するだけ（続行する）
                if ($debug_mode) {
                    $error .= ' 警告: ディレクトリの権限を変更できませんでした。';
                }
            }
        }
        
        // ファイルが存在する場合は書き込み権限を確認
        if (file_exists($destination) && !is_writable($destination)) {
            try {
                chmod($destination, 0644);
            } catch (Exception $e) {
                // 権限変更に失敗した場合は記録するだけ（続行する）
                if ($debug_mode) {
                    $error .= ' 警告: ファイルの権限を変更できませんでした。';
                }
            }
        }
        
        // 元のディレクトリに保存を試みる
        $saveSuccess = false;
        try {
            if (imagejpeg($image, $destination, 90)) {
                $saveSuccess = true;
            }
        } catch (Exception $e) {
            // 失敗した場合は記録
            if ($debug_mode) {
                $error .= ' 警告: ' . $e->getMessage();
            }
        }
        
        // 元のディレクトリに保存できなかった場合、一時ディレクトリに保存を試みる
        if (!$saveSuccess) {
            $temp_dir = sys_get_temp_dir();
            $temp_destination = $temp_dir . '/ogp-image-temp.jpg';
            
            try {
                if (imagejpeg($image, $temp_destination, 90)) {
                    // 一時ファイルを作成できた場合、サイトディレクトリにコピーを試みる
                    if (copy($temp_destination, $destination)) {
                        $saveSuccess = true;
                        unlink($temp_destination); // 一時ファイルを削除
                    } else {
                        // コピーできなかった場合、権限問題の可能性が高い
                        $error = 'OGP画像を保存できませんでした。Macの場合、XAMPPのhtdocsフォルダの権限を確認してください。';
                        $error .= '<br>手動で画像を移動する場合は、次の一時ファイルをコピーしてください: ' . $temp_destination;
                    }
                }
            } catch (Exception $e) {
                $error = 'OGP画像の生成中にエラーが発生しました。';
                if ($debug_mode) {
                    $error .= ' エラー: ' . $e->getMessage();
                }
            }
        }
        
        // リソースを解放
        imagedestroy($image);
        
        if ($saveSuccess) {
            // ローカルディレクトリとWebディレクトリが異なる場合、ファイルをWebディレクトリにコピー
            if ($is_xampp && $destination !== $web_images_dir . 'ogp-image.jpg') {
                try {
                    // Webディレクトリが存在しない場合は作成
                    if (!is_dir($web_images_dir)) {
                        @mkdir($web_images_dir, 0755, true);
                    }
                    // ローカルで保存した画像をWebディレクトリにコピー
                    if (copy($destination, $web_images_dir . 'ogp-image.jpg')) {
                        $success = 'OGP画像を生成しました。';
                    } else {
                        // コピーに失敗した場合もローカルには保存されているのでエラーではなく警告
                        $success = 'OGP画像をローカルに生成しました（Webディレクトリへのコピーに失敗）。';
                        if ($debug_mode) {
                            $success .= '<br>ローカルパス: ' . $destination;
                        }
                    }
                } catch (Exception $e) {
                    // エラーが発生してもローカルには保存できている
                    $success = 'OGP画像をローカルに生成しました。';
                    if ($debug_mode) {
                        $success .= '<br>警告: ' . $e->getMessage();
                    }
                }
            } else {
                $success = 'OGP画像を生成しました。';
            }
        } elseif (empty($error)) {
            $error = 'OGP画像の保存に失敗しました。ディレクトリの権限を確認してください。';
        }
    }
}

// OGP画像パスとタイムスタンプ（キャッシュ対策）
$ogp_image_path = $is_xampp ? $local_images_dir . 'ogp-image.jpg' : $web_images_dir . 'ogp-image.jpg';
$ogp_image_url = $is_xampp ? $xampp_base_url . 'xamppfiles/temp/wedding_images/ogp-image.jpg' : $site_url . 'images/ogp-image.jpg';

// 画像が存在するかチェック
$ogp_image_exists = file_exists($ogp_image_path);

$timestamp = $ogp_image_exists ? '?t=' . time() : '';

// 結婚式の基本情報を取得
$wedding_info = [
    'bride_name' => 'あかね',
    'groom_name' => '村岡翔',
    'wedding_date' => '2024年4月30日'
];

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM wedding_settings WHERE setting_key IN ('bride_name', 'groom_name', 'wedding_date')");
    while ($row = $stmt->fetch()) {
        $wedding_info[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // エラー処理（静かに失敗）
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OGP画像設定 - <?= $site_name ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@300;400;500&family=Noto+Sans+JP:wght@300;400;500&family=Noto+Serif+JP:wght@300;400;500&family=Reggae+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .ogp-preview {
            margin-top: 20px;
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .ogp-preview h3 {
            margin-top: 0;
            color: #555;
        }
        
        .ogp-image-container {
            margin: 20px 0;
            text-align: center;
        }
        
        .ogp-image {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .ogp-preview-card {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .ogp-preview-image {
            width: 100%;
            height: auto;
        }
        
        .ogp-preview-content {
            padding: 15px;
        }
        
        .ogp-preview-title {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 5px;
            color: #333;
        }
        
        .ogp-preview-description {
            font-size: 14px;
            color: #666;
            margin: 0 0 10px;
        }
        
        .ogp-preview-url {
            font-size: 12px;
            color: #999;
            margin: 0;
        }
        
        .ogp-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .ogp-tab {
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 500;
            border: 1px solid transparent;
            border-bottom: none;
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
            transition: all 0.3s;
        }
        
        .ogp-tab.active {
            background: #f5f5f5;
            border-color: #ddd;
            color: #4285F4;
        }
        
        .ogp-tab:hover {
            background: #f9f9f9;
        }
        
        .ogp-tab-content {
            display: none;
        }
        
        .ogp-tab-content.active {
            display: block;
        }
        
        .ogp-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .ogp-form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-dashboard">
        <header class="admin-dashboard-header">
            <div class="admin-logo">
                <h1><i class="fas fa-heart"></i> 結婚式管理システム</h1>
            </div>
            <div class="admin-user">
                <span>ようこそ、<?= htmlspecialchars($_SESSION['admin_username']) ?> さん</span>
                <a href="logout.php" class="admin-logout"><i class="fas fa-sign-out-alt"></i> ログアウト</a>
            </div>
        </header>
        
        <div class="admin-dashboard-content">
            <?php include 'inc/sidebar.php'; ?>
            
            <div class="admin-main">
                <div class="admin-content-wrapper">
                    <section class="admin-section">
                        <h2><i class="fas fa-share-alt"></i> OGP画像設定</h2>
                        
                        <?php if (!empty($error)): ?>
                        <div class="admin-error">
                            <?= $error ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                        <div class="admin-success">
                            <?= $success ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="admin-info-box">
                            <p><i class="fas fa-info-circle"></i> <strong>OGP画像とは？</strong></p>
                            <p>OGP（Open Graph Protocol）画像は、Webサイトがソーシャルメディアで共有される際に表示される画像です。結婚式のサイトをLINEやTwitter、Facebookなどで共有する際に表示されるイメージ画像を設定できます。</p>
                            <?php if ($is_xampp): ?>
                            <p><strong>注意：</strong> ローカル環境で生成した画像は一時ディレクトリに保存されます。本番環境にアップロードする際は、改めて本番環境で画像を生成またはアップロードしてください。</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ogp-tabs">
                            <div class="ogp-tab active" data-tab="current">現在のOGP画像</div>
                            <div class="ogp-tab" data-tab="upload">画像をアップロード</div>
                            <div class="ogp-tab" data-tab="generate">画像を自動生成</div>
                        </div>
                        
                        <div class="ogp-tab-content active" id="current-tab">
                            <h3>現在のOGP画像</h3>
                            
                            <div class="ogp-image-container">
                                <?php if ($ogp_image_exists): ?>
                                    <img src="<?= $ogp_image_url . $timestamp ?>" alt="現在のOGP画像" class="ogp-image">
                                <?php else: ?>
                                    <p>OGP画像がまだ設定されていません。</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="ogp-preview">
                                <h3>ソーシャルメディアでの表示プレビュー</h3>
                                <div class="ogp-preview-card">
                                    <?php if ($ogp_image_exists): ?>
                                        <img src="<?= $ogp_image_url . $timestamp ?>" alt="OGPプレビュー" class="ogp-preview-image">
                                    <?php else: ?>
                                        <div style="height: 260px; background: #eee; display: flex; align-items: center; justify-content: center;">
                                            <span>画像なし</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="ogp-preview-content">
                                        <h4 class="ogp-preview-title"><?= $site_name ?></h4>
                                        <p class="ogp-preview-description">
                                            <?= $wedding_info['bride_name'] ?> & <?= $wedding_info['groom_name'] ?>の結婚式の招待状です。
                                        </p>
                                        <p class="ogp-preview-url"><?= $site_url ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ogp-tab-content" id="upload-tab">
                            <h3>OGP画像をアップロード</h3>
                            <p>推奨サイズは1200×630ピクセルです。異なるサイズの画像もこのサイズに自動的にリサイズされます。</p>
                            
                            <form action="" method="post" enctype="multipart/form-data" class="admin-form">
                                <input type="hidden" name="action" value="upload_ogp_image">
                                
                                <div class="admin-form-group">
                                    <label for="ogp_image">画像ファイル（JPEG, PNG, GIF）</label>
                                    <input type="file" id="ogp_image" name="ogp_image" accept="image/jpeg,image/png,image/gif" required>
                                    <small>最大ファイルサイズ: 5MB</small>
                                </div>
                                
                                <div class="admin-form-actions">
                                    <button type="submit" class="admin-button">
                                        <i class="fas fa-upload"></i> アップロード
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="ogp-tab-content" id="generate-tab">
                            <h3>OGP画像を自動生成</h3>
                            <p>以下の項目を入力して、OGP画像を自動生成することができます。</p>
                            
                            <form action="" method="post" class="admin-form">
                                <input type="hidden" name="action" value="generate_ogp_image">
                                
                                <div class="ogp-form-grid">
                                    <div class="admin-form-group">
                                        <label for="title">タイトル</label>
                                        <input type="text" id="title" name="title" value="結婚式のご招待" required>
                                    </div>
                                    
                                    <div class="admin-form-group">
                                        <label for="subtitle">サブタイトル</label>
                                        <input type="text" id="subtitle" name="subtitle" value="<?= $wedding_info['bride_name'] ?> & <?= $wedding_info['groom_name'] ?>" required>
                                    </div>
                                </div>
                                
                                <div class="admin-form-group">
                                    <label for="date">日付</label>
                                    <input type="text" id="date" name="date" value="<?= $wedding_info['wedding_date'] ?>" required>
                                </div>
                                
                                <div class="admin-form-actions">
                                    <button type="submit" class="admin-button">
                                        <i class="fas fa-magic"></i> 画像を生成
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
                
                <?php include 'inc/footer.php'; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // タブ切り替え
            const tabs = document.querySelectorAll('.ogp-tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // アクティブクラスを削除
                    tabs.forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.ogp-tab-content').forEach(content => {
                        content.classList.remove('active');
                    });
                    
                    // クリックされたタブとそのコンテンツをアクティブに
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId + '-tab').classList.add('active');
                });
            });
            
            // 画像アップロードプレビュー
            const imageInput = document.getElementById('ogp_image');
            if (imageInput) {
                imageInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            // プレビュー画像がある場合は更新
                            const previewImages = document.querySelectorAll('.ogp-preview-image');
                            previewImages.forEach(img => {
                                img.src = e.target.result;
                            });
                        }
                        
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
        });
    </script>
</body>
</html> 