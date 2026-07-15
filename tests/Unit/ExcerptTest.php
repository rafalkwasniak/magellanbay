<?php

namespace Tests\Unit;

use App\Support\Excerpt;
use PHPUnit\Framework\TestCase;

class ExcerptTest extends TestCase
{
    public function test_plain_text_strips_tags_entities_and_whitespace(): void
    {
        $this->assertSame(
            'Pogrubione i kursywa',
            Excerpt::plainText('<div><strong>Pogrubione</strong> i <em>kursywa</em></div>'),
        );

        $this->assertSame('A B', Excerpt::plainText("<div>A</div>\n\n<div>   B</div>"));
        $this->assertSame('Kawa & ciastko', Excerpt::plainText('<div>Kawa &amp; ciastko</div>'));
        $this->assertSame('', Excerpt::plainText(null));
        $this->assertSame('', Excerpt::plainText('<div></div>'));
    }

    public function test_short_text_is_shown_whole(): void
    {
        $excerpt = Excerpt::fromHtml('<div>Dwa zdania. Nic więcej.</div>', 400);

        $this->assertSame('Dwa zdania. Nic więcej.', $excerpt->text);
        $this->assertStringNotContainsString('...', $excerpt->text);
    }

    public function test_long_text_is_cut_on_a_whole_word(): void
    {
        $excerpt = Excerpt::fromHtml('<div>'.str_repeat('słowo ', 200).'</div>', 400);

        $this->assertStringEndsWith('...', $excerpt->text);
        // Ucięcie po pełnym słowie: żadnego „sło..." w środku wyrazu.
        $this->assertStringNotContainsString('sło...', $excerpt->text);
        $this->assertLessThanOrEqual(403, mb_strlen($excerpt->text));
    }

    /**
     * Kafelek pokazuje całość, więc „Czytaj więcej" prowadziłoby na stronę z tym
     * samym tekstem — ślepy zaułek.
     */
    public function test_short_prose_has_nothing_more_to_offer(): void
    {
        $excerpt = Excerpt::fromHtml(
            '<div>Mój najnowszy wywiad znajduje się w czasopiśmie „Wydawca", serdecznie zapraszam.</div>',
            400,
        );

        $this->assertFalse($excerpt->hasMore);
    }

    public function test_truncated_text_has_more(): void
    {
        $excerpt = Excerpt::fromHtml('<div>'.str_repeat('słowo ', 200).'</div>', 400);

        $this->assertTrue($excerpt->hasMore);
    }

    /**
     * Krótka strona „Wywiady" nie potrzebuje odnośnika mimo linków w treści:
     * skoro się mieści, kafelek renderuje ją w całości i linki są klikalne na
     * miejscu. Wykrywanie linków byłoby tu zbędną komplikacją.
     */
    public function test_short_text_with_links_still_has_nothing_more(): void
    {
        $excerpt = Excerpt::fromHtml(
            '<div>Wywiady: <a href="https://example.com" rel="nofollow noopener" target="_blank">Radio</a></div>',
            400,
        );

        $this->assertFalse($excerpt->hasMore);
    }

    /** Dokładnie na granicy nic nie ginie, więc nie ma po co linkować. */
    public function test_text_exactly_at_the_limit_has_nothing_more(): void
    {
        $excerpt = Excerpt::fromHtml('<div>'.str_repeat('a', 400).'</div>', 400);

        $this->assertFalse($excerpt->hasMore);
        $this->assertSame(400, mb_strlen($excerpt->text));
    }

    public function test_empty_content_is_empty_and_offers_nothing(): void
    {
        $excerpt = Excerpt::fromHtml('<div></div>', 400);

        $this->assertTrue($excerpt->isEmpty());
        $this->assertFalse($excerpt->hasMore);

        $this->assertTrue(Excerpt::fromHtml(null, 400)->isEmpty());
    }
}
