<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Generates simple SVG product illustrations so the store has images without third-party assets. */
class PlaceholderImage
{
    /** Simple branded illustration so the demo store has product images without external assets. */
    public static function svg(string $name, string $icon, string $brand): string
    {
        $palettes = [['#0f3d5c', '#1f6f9f'], ['#3b1f5c', '#6d3fa0'], ['#5c1f2e', '#a03f55'], ['#1f5c3d', '#3fa06d'], ['#5c4a1f', '#a0823f'], ['#1f2f5c', '#3f5aa0']];
        [$a, $b] = $palettes[crc32($name) % count($palettes)];
        $glyphs = [
            'cpu' => '<rect x="150" y="150" width="200" height="200" rx="16" fill="none" stroke="#fff" stroke-width="14"/><rect x="200" y="200" width="100" height="100" rx="8" fill="#fff" opacity=".85"/><path d="M190 130v-30M250 130v-30M310 130v-30M190 370v30M250 370v30M310 370v30M130 190h-30M130 250h-30M130 310h-30M370 190h30M370 250h30M370 310h30" stroke="#fff" stroke-width="12" stroke-linecap="round"/>',
            'board' => '<rect x="120" y="120" width="260" height="260" rx="14" fill="none" stroke="#fff" stroke-width="12"/><rect x="160" y="160" width="90" height="90" fill="#fff" opacity=".85"/><path d="M280 165h60M280 195h60M280 225h60M160 300h180M160 335h120" stroke="#fff" stroke-width="12" stroke-linecap="round"/>',
            'memory' => '<rect x="90" y="190" width="320" height="110" rx="10" fill="none" stroke="#fff" stroke-width="12"/><path d="M130 225h40v40h-40zM200 225h40v40h-40zM270 225h40v40h-40zM340 225h30v40h-30z" fill="#fff" opacity=".85"/><path d="M110 300v30M150 300v30M190 300v30M230 300v30M270 300v30M310 300v30M350 300v30M390 300v30" stroke="#fff" stroke-width="8"/>',
            'gpu' => '<rect x="80" y="170" width="340" height="160" rx="18" fill="none" stroke="#fff" stroke-width="12"/><circle cx="180" cy="250" r="50" fill="none" stroke="#fff" stroke-width="10"/><circle cx="320" cy="250" r="50" fill="none" stroke="#fff" stroke-width="10"/><circle cx="180" cy="250" r="12" fill="#fff"/><circle cx="320" cy="250" r="12" fill="#fff"/>',
            'storage' => '<rect x="140" y="110" width="220" height="280" rx="20" fill="none" stroke="#fff" stroke-width="12"/><circle cx="250" cy="220" r="60" fill="none" stroke="#fff" stroke-width="10"/><circle cx="250" cy="220" r="10" fill="#fff"/><path d="M180 340h80" stroke="#fff" stroke-width="12" stroke-linecap="round"/>',
            'power' => '<rect x="110" y="150" width="280" height="200" rx="14" fill="none" stroke="#fff" stroke-width="12"/><circle cx="210" cy="250" r="60" fill="none" stroke="#fff" stroke-width="10"/><path d="M215 210l-20 45h30l-20 45" stroke="#fff" stroke-width="10" fill="none"/><path d="M310 200v100" stroke="#fff" stroke-width="12"/>',
            'case' => '<rect x="160" y="90" width="180" height="320" rx="16" fill="none" stroke="#fff" stroke-width="12"/><circle cx="250" cy="170" r="35" fill="none" stroke="#fff" stroke-width="8"/><circle cx="250" cy="270" r="35" fill="none" stroke="#fff" stroke-width="8"/><path d="M200 370h100" stroke="#fff" stroke-width="10" stroke-linecap="round"/>',
            'fan' => '<circle cx="250" cy="250" r="130" fill="none" stroke="#fff" stroke-width="12"/><path d="M250 250c0-70 60-90 80-60s-40 60-80 60zm0 0c-60 35-110 0-95-35s75-5 95 35zm0 0c60 35 50 100 15 100s-35-65-15-100z" fill="#fff" opacity=".85"/>',
            'desktop' => '<rect x="90" y="110" width="320" height="210" rx="14" fill="none" stroke="#fff" stroke-width="12"/><path d="M210 320l-15 60h110l-15-60M170 380h160" stroke="#fff" stroke-width="12" fill="none" stroke-linecap="round"/>',
            'laptop' => '<rect x="130" y="130" width="240" height="160" rx="12" fill="none" stroke="#fff" stroke-width="12"/><path d="M90 320h320l-25 40H115z" fill="#fff" opacity=".85"/>',
            'monitor' => '<rect x="70" y="110" width="360" height="220" rx="14" fill="none" stroke="#fff" stroke-width="12"/><path d="M250 330v45M180 380h140" stroke="#fff" stroke-width="12" stroke-linecap="round"/>',
            'tv' => '<rect x="60" y="120" width="380" height="230" rx="10" fill="none" stroke="#fff" stroke-width="12"/><path d="M160 390h180M250 350v40" stroke="#fff" stroke-width="12" stroke-linecap="round"/>',
            'phone' => '<rect x="180" y="80" width="140" height="340" rx="24" fill="none" stroke="#fff" stroke-width="12"/><path d="M230 110h40" stroke="#fff" stroke-width="10" stroke-linecap="round"/><circle cx="250" cy="385" r="10" fill="#fff"/>',
            'tablet' => '<rect x="130" y="90" width="240" height="320" rx="22" fill="none" stroke="#fff" stroke-width="12"/><circle cx="250" cy="385" r="9" fill="#fff"/>',
            'keyboard' => '<rect x="60" y="170" width="380" height="160" rx="16" fill="none" stroke="#fff" stroke-width="12"/><path d="M100 210h20M150 210h20M200 210h20M250 210h20M300 210h20M350 210h20M100 250h20M150 250h20M200 250h20M250 250h20M300 250h20M350 250h20M150 290h200" stroke="#fff" stroke-width="14" stroke-linecap="round"/>',
            'mouse' => '<rect x="175" y="110" width="150" height="270" rx="75" fill="none" stroke="#fff" stroke-width="12"/><path d="M250 115v90M175 205h150" stroke="#fff" stroke-width="10"/>',
            'headphone' => '<path d="M130 300v-50a120 120 0 0 1 240 0v50" fill="none" stroke="#fff" stroke-width="14"/><rect x="110" y="280" width="60" height="100" rx="20" fill="#fff" opacity=".85"/><rect x="330" y="280" width="60" height="100" rx="20" fill="#fff" opacity=".85"/>',
            'speaker' => '<rect x="160" y="90" width="180" height="320" rx="20" fill="none" stroke="#fff" stroke-width="12"/><circle cx="250" cy="300" r="60" fill="none" stroke="#fff" stroke-width="10"/><circle cx="250" cy="170" r="30" fill="none" stroke="#fff" stroke-width="10"/>',
            'camera' => '<rect x="90" y="170" width="320" height="200" rx="20" fill="none" stroke="#fff" stroke-width="12"/><circle cx="250" cy="270" r="65" fill="none" stroke="#fff" stroke-width="12"/><path d="M180 170l20-40h100l20 40" stroke="#fff" stroke-width="12" fill="none"/>',
            'router' => '<rect x="90" y="260" width="320" height="90" rx="14" fill="none" stroke="#fff" stroke-width="12"/><path d="M150 260l-30-130M350 260l30-130M250 260V120" stroke="#fff" stroke-width="12" stroke-linecap="round"/><circle cx="150" cy="305" r="10" fill="#fff"/><circle cx="190" cy="305" r="10" fill="#fff"/>',
            'gamepad' => '<path d="M150 180h200c50 0 80 60 80 130 0 50-50 60-80 20l-30-40H180l-30 40c-30 40-80 30-80-20 0-70 30-130 80-130z" fill="none" stroke="#fff" stroke-width="12"/><path d="M170 250h50M195 225v50" stroke="#fff" stroke-width="12" stroke-linecap="round"/><circle cx="310" cy="240" r="12" fill="#fff"/><circle cx="340" cy="270" r="12" fill="#fff"/>',
        ];
        $glyph = $glyphs[$icon] ?? $glyphs['desktop'];
        $label = e(Str::limit($name, 38));
        $brandLabel = e(strtoupper($brand));

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 500" width="500" height="500">
<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$a}"/><stop offset="1" stop-color="{$b}"/></linearGradient></defs>
<rect width="500" height="500" fill="url(#g)"/>
<circle cx="420" cy="80" r="140" fill="#fff" opacity=".06"/>
<g transform="translate(0 -20)">{$glyph}</g>
<text x="250" y="440" text-anchor="middle" font-family="Arial, sans-serif" font-size="20" fill="#fff" opacity=".95">{$label}</text>
<text x="30" y="50" font-family="Arial, sans-serif" font-size="22" font-weight="700" fill="#fff" opacity=".9">{$brandLabel}</text>
</svg>
SVG;
    }
}
