<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer;
    }

    public function test_keeps_allowed_formatting(): void
    {
        $this->assertSame('<strong>Cześć</strong>', $this->sanitizer->clean('<strong>Cześć</strong>'));
        $this->assertSame('<ul><li>raz</li><li>dwa</li></ul>', $this->sanitizer->clean('<ul><li>raz</li><li>dwa</li></ul>'));
    }

    public function test_heading_h1_is_converted_to_h2(): void
    {
        $this->assertSame('<h2>Tytuł</h2>', $this->sanitizer->clean('<h1>Tytuł</h1>'));
        $this->assertSame('<h2>Tytuł</h2>', $this->sanitizer->clean('<h2>Tytuł</h2>'));
    }

    public function test_unwraps_disallowed_tag_but_keeps_text(): void
    {
        $this->assertSame('Tekst', $this->sanitizer->clean('<span style="color:red">Tekst</span>'));
    }

    public function test_drops_script_entirely(): void
    {
        $this->assertSame('', $this->sanitizer->clean('<script>alert(1)</script>'));
    }

    public function test_strips_disallowed_attributes(): void
    {
        $this->assertSame('<div>Hej</div>', $this->sanitizer->clean('<div class="x" onclick="bad()">Hej</div>'));
    }

    public function test_safe_link_gets_rel_and_target(): void
    {
        $this->assertSame(
            '<a href="https://example.com" rel="nofollow noopener" target="_blank">link</a>',
            $this->sanitizer->clean('<a href="https://example.com">link</a>'),
        );
    }

    public function test_unsafe_link_is_dropped_keeping_text(): void
    {
        $this->assertSame('zło', $this->sanitizer->clean('<a href="javascript:alert(1)">zło</a>'));
    }

    public function test_empty_input_returns_empty(): void
    {
        $this->assertSame('', $this->sanitizer->clean(''));
        $this->assertSame('', $this->sanitizer->clean('   '));
    }
}
