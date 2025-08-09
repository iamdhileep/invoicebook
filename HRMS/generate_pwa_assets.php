<?php
// Generate PWA icons from SVG
$sizes = [72, 96, 128, 144, 192, 384, 512];

$svgIcon = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="{size}" height="{size}" viewBox="0 0 {size} {size}" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
            <stop offset="100%" style="stop-color:#764ba2;stop-opacity:1" />
        </linearGradient>
    </defs>
    <rect width="{size}" height="{size}" fill="url(#grad1)" rx="{radius}"/>
    <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="{fontSize}" font-weight="bold" 
          fill="white" text-anchor="middle" dominant-baseline="central">HRMS</text>
</svg>';

foreach ($sizes as $size) {
    $radius = $size * 0.125; // 12.5% radius for rounded corners
    $fontSize = $size * 0.25; // 25% of size for font
    
    $svg = str_replace(['{size}', '{radius}', '{fontSize}'], 
                      [$size, $radius, $fontSize], $svgIcon);
    
    // Save SVG
    file_put_contents("assets/icon-{$size}x{$size}.svg", $svg);
    
    // Convert to PNG using ImageMagick if available, or save as SVG renamed to PNG for basic compatibility
    $pngFile = "assets/icon-{$size}x{$size}.png";
    
    // Try ImageMagick conversion
    $command = "magick -background transparent assets/icon-{$size}x{$size}.svg {$pngFile}";
    $output = shell_exec($command . " 2>&1");
    
    if (!file_exists($pngFile)) {
        // If ImageMagick failed, create a simple PNG placeholder
        $im = imagecreatetruecolor($size, $size);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        
        // Create gradient effect
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $r = 102 + ($x / $size) * (118 - 102);
                $g = 126 + ($x / $size) * (75 - 126);
                $b = 234 + ($x / $size) * (162 - 234);
                $color = imagecolorallocate($im, $r, $g, $b);
                imagesetpixel($im, $x, $y, $color);
            }
        }
        
        // Add text
        $white = imagecolorallocate($im, 255, 255, 255);
        $font = 5; // Built-in font
        $text = "HRMS";
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        $x = ($size - $textWidth) / 2;
        $y = ($size - $textHeight) / 2;
        imagestring($im, $font, $x, $y, $text, $white);
        
        imagepng($im, $pngFile);
        imagedestroy($im);
    }
    
    echo "Generated icon: {$size}x{$size}\n";
}

// Create badge icon
$badgeSize = 72;
$badgeSvg = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="72" height="72" viewBox="0 0 72 72" xmlns="http://www.w3.org/2000/svg">
    <circle cx="36" cy="36" r="36" fill="#667eea"/>
    <text x="36" y="42" font-family="Arial" font-size="16" font-weight="bold" 
          fill="white" text-anchor="middle">HR</text>
</svg>';

file_put_contents("assets/badge-72x72.svg", $badgeSvg);

// Create simple PNG badge
$im = imagecreatetruecolor(72, 72);
$blue = imagecolorallocate($im, 102, 126, 234);
$white = imagecolorallocate($im, 255, 255, 255);
imagefill($im, 0, 0, $blue);
imagestring($im, 5, 22, 30, "HR", $white);
imagepng($im, "assets/badge-72x72.png");
imagedestroy($im);

echo "Generated badge icon\n";
echo "PWA assets created successfully!\n";
?>
