<?php
/**
 * PWA Icon Generator
 * Generates icons for the PWA in various sizes
 */

// Create icons directory if it doesn't exist
$iconsDir = __DIR__ . '/icons';
if (!is_dir($iconsDir)) {
    mkdir($iconsDir, 0755, true);
}

// Icon sizes needed for PWA
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

// SVG template for the HRMS icon
$svgTemplate = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="{SIZE}" height="{SIZE}" viewBox="0 0 {SIZE} {SIZE}" fill="none" xmlns="http://www.w3.org/2000/svg">
  <rect width="{SIZE}" height="{SIZE}" rx="{RADIUS}" fill="#2563EB"/>
  <g transform="translate({PADDING},{PADDING})">
    <!-- Building/Office Icon -->
    <rect x="0" y="{BUILDING_Y}" width="{BUILDING_WIDTH}" height="{BUILDING_HEIGHT}" fill="#FFFFFF" rx="2"/>
    
    <!-- Windows -->
    <rect x="{WINDOW1_X}" y="{WINDOW_Y}" width="{WINDOW_SIZE}" height="{WINDOW_SIZE}" fill="#2563EB" rx="1"/>
    <rect x="{WINDOW2_X}" y="{WINDOW_Y}" width="{WINDOW_SIZE}" height="{WINDOW_SIZE}" fill="#2563EB" rx="1"/>
    <rect x="{WINDOW3_X}" y="{WINDOW_Y}" width="{WINDOW_SIZE}" height="{WINDOW_SIZE}" fill="#2563EB" rx="1"/>
    
    <!-- Door -->
    <rect x="{DOOR_X}" y="{DOOR_Y}" width="{DOOR_WIDTH}" height="{DOOR_HEIGHT}" fill="#2563EB" rx="1"/>
    
    <!-- HR Text or Symbol -->
    <text x="{TEXT_X}" y="{TEXT_Y}" font-family="Arial, sans-serif" font-size="{FONT_SIZE}" font-weight="bold" fill="#FFFFFF" text-anchor="middle">HR</text>
  </g>
</svg>';

function generateIcon($size) {
    global $svgTemplate;
    
    $radius = round($size * 0.15);
    $padding = round($size * 0.15);
    $contentSize = $size - ($padding * 2);
    
    $buildingWidth = round($contentSize * 0.8);
    $buildingHeight = round($contentSize * 0.6);
    $buildingY = round($contentSize * 0.4);
    
    $windowSize = round($contentSize * 0.12);
    $windowY = round($contentSize * 0.5);
    $window1X = round($contentSize * 0.15);
    $window2X = round($contentSize * 0.35);
    $window3X = round($contentSize * 0.55);
    
    $doorWidth = round($contentSize * 0.15);
    $doorHeight = round($contentSize * 0.25);
    $doorX = round(($contentSize - $doorWidth) / 2);
    $doorY = round($contentSize * 0.75);
    
    $fontSize = round($size * 0.15);
    $textX = round($contentSize / 2);
    $textY = round($contentSize * 0.3);
    
    $svg = str_replace([
        '{SIZE}', '{RADIUS}', '{PADDING}',
        '{BUILDING_WIDTH}', '{BUILDING_HEIGHT}', '{BUILDING_Y}',
        '{WINDOW_SIZE}', '{WINDOW_Y}', '{WINDOW1_X}', '{WINDOW2_X}', '{WINDOW3_X}',
        '{DOOR_WIDTH}', '{DOOR_HEIGHT}', '{DOOR_X}', '{DOOR_Y}',
        '{FONT_SIZE}', '{TEXT_X}', '{TEXT_Y}'
    ], [
        $size, $radius, $padding,
        $buildingWidth, $buildingHeight, $buildingY,
        $windowSize, $windowY, $window1X, $window2X, $window3X,
        $doorWidth, $doorHeight, $doorX, $doorY,
        $fontSize, $textX, $textY
    ], $svgTemplate);
    
    return $svg;
}

// Generate icons for each size
foreach ($sizes as $size) {
    $svg = generateIcon($size);
    $svgFile = $iconsDir . "/icon-{$size}x{$size}.svg";
    file_put_contents($svgFile, $svg);
    
    echo "Generated: icon-{$size}x{$size}.svg\n";
}

// Generate favicon.ico (basic 32x32)
$faviconSvg = generateIcon(32);
$faviconFile = $iconsDir . "/favicon.svg";
file_put_contents($faviconFile, $faviconSvg);

// Also create a simple favicon.ico using base64 encoded data
$faviconIco = base64_decode('AAABAAEAICAAAAEAIACoEAAAFgAAACgAAAAgAAAAQAAAAAEAIAAAAAAAABAAAAAAAAAAAAAAAAAAAAAAAAA=');
file_put_contents(__DIR__ . '/favicon.ico', $faviconIco);

echo "Generated: favicon.svg\n";
echo "Generated: favicon.ico\n";

// Generate apple-touch-icon
$appleTouchIcon = generateIcon(180);
$appleIconFile = $iconsDir . "/apple-touch-icon.svg";
file_put_contents($appleIconFile, $appleTouchIcon);

echo "Generated: apple-touch-icon.svg\n";

echo "\nAll PWA icons generated successfully!\n";
echo "Icons are available in the /icons/ directory.\n";
echo "For production, consider converting SVG files to PNG using an image conversion tool.\n";
?>
