<?php

namespace Tests\Unit;

use App\Support\Color;
use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{
    public function test_light_colors_get_dark_ink(): void
    {
        $this->assertSame('#1A1A1A', Color::readableInkOn('#FFFFFF'));
        $this->assertSame('#1A1A1A', Color::readableInkOn('#E0A25E')); // jasny bursztyn
        $this->assertSame('#1A1A1A', Color::readableInkOn('#FCE7A2')); // pastel
    }

    public function test_dark_colors_get_white_ink(): void
    {
        $this->assertSame('#FFFFFF', Color::readableInkOn('#000000'));
        $this->assertSame('#FFFFFF', Color::readableInkOn('#3B82F6')); // błękit
        $this->assertSame('#FFFFFF', Color::readableInkOn('#101820')); // grafit
    }

    public function test_leading_hash_is_optional(): void
    {
        $this->assertSame('#FFFFFF', Color::readableInkOn('1E293B'));
    }

    public function test_readable_on_keeps_already_dark_colors(): void
    {
        // Ciemny kolor ma dość kontrastu na bieli — zwracany bez zmian.
        $this->assertSame('#1c1917', Color::readableOn('#1c1917'));
    }

    public function test_readable_on_darkens_light_colors_until_legible(): void
    {
        // Jasne/pastelowe kolory (w tym biel) są przyciemniane, aż osiągną próg
        // WCAG AA (4.5:1) na białej karcie maila.
        foreach (['#f59e0b', '#fce7a2', '#ffffff', '#7dd3fc'] as $light) {
            $result = Color::readableOn($light);
            $this->assertGreaterThanOrEqual(
                4.5,
                $this->contrastOnWhite($result),
                "Kolor {$light} powinien być czytelny na bieli, a dostał {$result}."
            );
        }
    }

    private function contrastOnWhite(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = array_map(static function (string $pair): float {
            $v = hexdec($pair) / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, [substr($hex, 0, 2), substr($hex, 2, 2), substr($hex, 4, 2)]);

        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        return (1.0 + 0.05) / ($luminance + 0.05);   // biel = jasność 1.0
    }
}
