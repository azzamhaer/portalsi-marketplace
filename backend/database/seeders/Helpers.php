<?php

namespace Database\Seeders;

class Helpers
{
    public static function productImage(string $emoji, string $bg1, string $bg2, string $label): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 400'>"
            . "<defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'>"
            . "<stop offset='0%' stop-color='{$bg1}'/><stop offset='100%' stop-color='{$bg2}'/></linearGradient>"
            . "<pattern id='p' width='40' height='40' patternUnits='userSpaceOnUse'><circle cx='20' cy='20' r='1.5' fill='rgba(255,255,255,0.18)'/></pattern></defs>"
            . "<rect width='400' height='400' fill='url(#g)'/><rect width='400' height='400' fill='url(#p)'/>"
            . "<text x='200' y='220' text-anchor='middle' font-size='160' font-family='Apple Color Emoji,Segoe UI Emoji'>{$emoji}</text>"
            . "<text x='200' y='340' text-anchor='middle' font-size='22' font-weight='700' fill='#fff' font-family='Inter,sans-serif' letter-spacing='2'>" . strtoupper($label) . "</text>"
            . "</svg>";
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }

    public static function categoryIcon(string $emoji, string $color): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>"
            . "<rect width='100' height='100' rx='24' fill='{$color}'/>"
            . "<text x='50' y='68' text-anchor='middle' font-size='50' font-family='Apple Color Emoji,Segoe UI Emoji'>{$emoji}</text></svg>";
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }

    public static function avatar(string $initial, string $color): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>"
            . "<rect width='100' height='100' rx='50' fill='{$color}'/>"
            . "<text x='50' y='65' text-anchor='middle' font-size='42' font-weight='800' fill='#fff' font-family='Inter,sans-serif'>{$initial}</text></svg>";
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }

    public static function banner(string $emoji, string $bg1, string $bg2, string $title, string $sub): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 300'>"
            . "<defs><linearGradient id='b' x1='0' y1='0' x2='1' y2='1'><stop offset='0%' stop-color='{$bg1}'/><stop offset='100%' stop-color='{$bg2}'/></linearGradient></defs>"
            . "<rect width='1200' height='300' fill='url(#b)'/>"
            . "<circle cx='950' cy='150' r='160' fill='rgba(255,255,255,0.12)'/>"
            . "<text x='950' y='200' text-anchor='middle' font-size='160' font-family='Apple Color Emoji,Segoe UI Emoji'>{$emoji}</text>"
            . "<text x='80' y='130' font-size='48' font-weight='900' fill='#fff' font-family='Inter,sans-serif'>" . htmlspecialchars($title) . "</text>"
            . "<text x='80' y='180' font-size='22' fill='rgba(255,255,255,0.9)' font-family='Inter,sans-serif'>" . htmlspecialchars($sub) . "</text>"
            . "</svg>";
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
}
