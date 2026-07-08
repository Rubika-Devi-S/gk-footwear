<?php
/**
 * Simple Code 128B barcode SVG generator for bill barcode values.
 * Usage: barcode-image.php?code=BILL-000001
 */
$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
$code = preg_replace('/[^A-Za-z0-9\-_.\/]/', '', $code);
if ($code === '') { $code = 'INVALID'; }

function code128_svg($text, $barHeight = 54, $moduleWidth = 2)
{
    $patterns = array(
        '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213','221312','231212','112232','122132','122231','113222','123122','123221','223211','221132','221231','213212','223112','312131','311222','321122','321221','312212','322112','322211','212123','212321','232121','111323','131123','131321','112313','132113','132311','211313','231113','231311','112133','112331','132131','113123','113321','133121','313121','211331','231131','213113','213311','213131','311123','311321','331121','312113','312311','332111','314111','221411','431111','111224','111422','121124','121421','141122','141221','112214','112412','122114','122411','142112','142211','241211','221114','413111','241112','134111','111242','121142','121241','114212','124112','124211','411212','421112','421211','212141','214121','412121','111143','111341','131141','114113','114311','411113','411311','113141','114131','311141','411131','211412','211214','211232','2331112'
    );
    $text = (string)$text;
    if ($text === '') { $text = 'INVALID'; }
    $codes = array(104); // Start Code B
    $checksum = 104;
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $ord = ord($text[$i]);
        if ($ord < 32 || $ord > 126) { $ord = 63; }
        $value = $ord - 32;
        $codes[] = $value;
        $checksum += $value * ($i + 1);
    }
    $codes[] = $checksum % 103;
    $codes[] = 106; // stop

    $modules = 20; // quiet zones
    foreach ($codes as $code) {
        $pattern = $patterns[$code] ?? '212222';
        for ($i = 0; $i < strlen($pattern); $i++) { $modules += (int)$pattern[$i]; }
    }
    $width = $modules * $moduleWidth;
    $height = $barHeight + 24;
    $x = 10 * $moduleWidth;
    $bars = '';
    foreach ($codes as $code) {
        $pattern = $patterns[$code] ?? '212222';
        for ($i = 0; $i < strlen($pattern); $i++) {
            $w = ((int)$pattern[$i]) * $moduleWidth;
            if ($i % 2 === 0) { $bars .= '<rect x="' . $x . '" y="4" width="' . $w . '" height="' . $barHeight . '" fill="#000"/>'; }
            $x += $w;
        }
    }
    $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Barcode ' . $safe . '"><rect width="100%" height="100%" fill="#fff"/>' . $bars . '<text x="50%" y="' . ($barHeight + 18) . '" text-anchor="middle" font-family="Arial, sans-serif" font-size="12" font-weight="700" letter-spacing="1">' . $safe . '</text></svg>';
}

header('Content-Type: image/svg+xml; charset=UTF-8');
header('Cache-Control: public, max-age=86400');
echo code128_svg($code);
