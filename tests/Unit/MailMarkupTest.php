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

    public function test_turns_marked_link_into_anchor(): void
    {
        $this->assertSame(
            'Kliknij <a href="https://sklep.kramio.pl/zwrot/abc" style="color:inherit; text-decoration:underline;">tutaj</a> proszę',
            MailMarkup::inline('Kliknij [tutaj](https://sklep.kramio.pl/zwrot/abc) proszę'),
        );
    }

    public function test_link_text_can_be_bold(): void
    {
        $this->assertStringContainsString(
            '<a href="https://kramio.pl/x" style="color:inherit; text-decoration:underline;"><strong>wypełnij formularz</strong></a>',
            MailMarkup::inline('[**wypełnij formularz**](https://kramio.pl/x)'),
        );
    }

    public function test_ignores_links_with_dangerous_scheme(): void
    {
        // Tylko http(s). Inny schemat zostaje dosłownym tekstem, nie linkiem.
        $result = MailMarkup::inline('[kliknij](javascript:alert(1))');

        $this->assertStringNotContainsString('<a href', $result);
        $this->assertStringContainsString('[kliknij]', $result);
    }

    public function test_plain_url_without_marker_stays_text(): void
    {
        // Świadomie NIE zamieniamy gołych adresów w linki — o tym, co jest
        // odnośnikiem, decyduje autor treści maila, nie wykrywacz wzorców.
        $this->assertSame(
            'Adres: https://kramio.pl',
            MailMarkup::inline('Adres: https://kramio.pl'),
        );
    }
}
