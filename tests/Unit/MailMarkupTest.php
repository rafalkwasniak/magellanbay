<?php

namespace Tests\Unit;

use App\Support\MailMarkup;
use PHPUnit\Framework\TestCase;

class MailMarkupTest extends TestCase
{
    public function test_converts_double_asterisks_to_strong(): void
    {
        $this->assertSame(
            'Razem do zapłaty: <strong>56 412,00 zł</strong>',
            MailMarkup::inline('Razem do zapłaty: **56 412,00 zł**'),
        );
    }

    public function test_escapes_html_before_applying_bold(): void
    {
        // Dane od użytkownika (np. nazwa produktu z <script>) muszą zostać
        // zescapowane — pogrubienie nie może otworzyć furtki na wstrzyknięcie HTML.
        $this->assertSame(
            '<strong>Nagłówek</strong>: &lt;script&gt;alert(1)&lt;/script&gt;',
            MailMarkup::inline('**Nagłówek**: <script>alert(1)</script>'),
        );
    }

    public function test_leaves_plain_text_untouched(): void
    {
        $this->assertSame(
            'Numer konta: 43 1140 2004 0000 3502 7515 5558',
            MailMarkup::inline('Numer konta: 43 1140 2004 0000 3502 7515 5558'),
        );
    }
}
