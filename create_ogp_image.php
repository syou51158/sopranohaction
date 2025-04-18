<?php
// settings
$width = 1200;
$height = 630;
$bgColor = [255, 255, 255]; // 白色
$textColor = [60, 60, 60]; // ダークグレー
$accentColor = [230, 180, 180]; // 薄いピンク

// creating the image
$image = imagecreatetruecolor($width, $height);

// fill background
$bg = imagecolorallocate($image, $bgColor[0], $bgColor[1], $bgColor[2]);
imagefill($image, 0, 0, $bg);

// accent color for decorative elements
$accent = imagecolorallocate($image, $accentColor[0], $accentColor[1], $accentColor[2]);

// draw decorative elements (simple border)
$borderWidth = 20;
imagefilledrectangle($image, 0, 0, $width, $borderWidth, $accent);
imagefilledrectangle($image, 0, $height - $borderWidth, $width, $height, $accent);
imagefilledrectangle($image, 0, 0, $borderWidth, $height, $accent);
imagefilledrectangle($image, $width - $borderWidth, 0, $width, $height, $accent);

// set the text color
$textColor = imagecolorallocate($image, $textColor[0], $textColor[1], $textColor[2]);

// Use a default font or try to find a nice font in the system
$fontFile = "C:/Windows/Fonts/meiryo.ttc"; // Windows default
if (!file_exists($fontFile)) {
    $fontFile = "/Library/Fonts/Arial Unicode.ttf"; // Mac default
}
if (!file_exists($fontFile)) {
    $fontFile = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"; // Linux default
}

// If we can't find any of these fonts, we'll use GD's default font
$useDefaultFont = !file_exists($fontFile);

// add title text
$title = "結婚式のご招待";
if (!$useDefaultFont) {
    $fontSize = 50;
    $textBox = imagettfbbox($fontSize, 0, $fontFile, $title);
    $textWidth = $textBox[2] - $textBox[0];
    $textHeight = $textBox[1] - $textBox[7];
    $textX = ($width - $textWidth) / 2;
    $textY = 200 + $textHeight;
    imagettftext($image, $fontSize, 0, $textX, $textY, $textColor, $fontFile, $title);
    
    // add subtitle
    $subtitle = "あかね & 村岡翔";
    $fontSize = 40;
    $textBox = imagettfbbox($fontSize, 0, $fontFile, $subtitle);
    $textWidth = $textBox[2] - $textBox[0];
    $textX = ($width - $textWidth) / 2;
    $textY = 300;
    imagettftext($image, $fontSize, 0, $textX, $textY, $textColor, $fontFile, $subtitle);
} else {
    // Use GD's default font
    $titleLength = strlen($title);
    $textX = ($width - $titleLength * 8) / 2; // 8 pixels per character approx.
    imagestring($image, 5, $textX, 200, $title, $textColor);
    
    $subtitle = "あかね & 村岡翔";
    $subtitleLength = strlen($subtitle);
    $textX = ($width - $subtitleLength * 8) / 2;
    imagestring($image, 4, $textX, 250, $subtitle, $textColor);
}

// Draw a heart
$heartSize = 100;
$heartX = ($width - $heartSize) / 2;
$heartY = 350;

// Create heart shape with bezier curves
$points = [];
// Top of the heart
$points[] = $heartX + $heartSize/2;
$points[] = $heartY;
// Right arc
$points[] = $heartX + $heartSize;
$points[] = $heartY - $heartSize/4;
$points[] = $heartX + $heartSize;
$points[] = $heartY + $heartSize/4;
$points[] = $heartX + $heartSize/2;
$points[] = $heartY + $heartSize/2;
// Bottom point
$points[] = $heartX + $heartSize/2;
$points[] = $heartY + $heartSize;
// Left arc
$points[] = $heartX;
$points[] = $heartY + $heartSize/2;
$points[] = $heartX;
$points[] = $heartY + $heartSize/4;
$points[] = $heartX;
$points[] = $heartY - $heartSize/4;
// Back to top
$points[] = $heartX + $heartSize/2;
$points[] = $heartY;

// Draw filled polygon as heart (simplified)
$heartColor = imagecolorallocate($image, 255, 150, 150);
imagefilledpolygon($image, $points, count($points)/2, $heartColor);

// add date
$date = date("Y.m.d");
if (!$useDefaultFont) {
    $fontSize = 30;
    $dateText = "結婚式: 2024年4月30日";
    $textBox = imagettfbbox($fontSize, 0, $fontFile, $dateText);
    $textWidth = $textBox[2] - $textBox[0];
    $textX = ($width - $textWidth) / 2;
    $textY = 500;
    imagettftext($image, $fontSize, 0, $textX, $textY, $textColor, $fontFile, $dateText);
} else {
    $dateText = "結婚式: 2024年4月30日";
    $textLength = strlen($dateText);
    $textX = ($width - $textLength * 8) / 2;
    imagestring($image, 3, $textX, 480, $dateText, $textColor);
}

// output the image
header('Content-Type: image/jpeg');
imagejpeg($image, 'images/ogp-image.jpg', 90);
imagejpeg($image, 'temp_assets/ogp-image.jpg', 90);
imagedestroy($image);

echo "OGP画像が正常に作成されました。";
?> 