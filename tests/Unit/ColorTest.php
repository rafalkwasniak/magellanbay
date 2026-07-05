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
}
