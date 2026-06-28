<?php

namespace Tests\Unit;

use App\Services\SlugService;
use PHPUnit\Framework\TestCase;

class SlugServiceTest extends TestCase
{
    private SlugService $slugs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->slugs = new SlugService;
    }

    public function test_transliterates_polish_characters(): void
    {
        $this->assertSame('wiazanki-malgosi', $this->slugs->make('Wiązanki Małgosi'));
        $this->assertSame('laka-u-zolwia', $this->slugs->make('Łąka u Żółwia'));
    }

    public function test_collapses_separators_and_trims_hyphens(): void
    {
        $this->assertSame('kwiaty-ziola', $this->slugs->make('  Kwiaty & Zioła!!!  '));
    }

    public function test_returns_empty_string_for_symbols_only(): void
    {
        $this->assertSame('', $this->slugs->make('!!! ???'));
        $this->assertSame('', $this->slugs->make(null));
    }

    public function test_caps_length_to_dns_label_limit_without_trailing_hyphen(): void
    {
        $slug = $this->slugs->make(str_repeat('a', 40).' '.str_repeat('b', 40));

        $this->assertLessThanOrEqual(63, strlen($slug));
        $this->assertStringEndsNotWith('-', $slug);
    }
}
