<?php

namespace Tests\Feature\Mail;

use App\Mail\OutboxMailable;
use App\Models\EmailMessage;
use App\Models\Shop;
use App\Support\MailBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stopka maila: dane firmowe NADAWCY — sklepu albo Kramio przy mailach platformy.
 *
 * Sedno reguły: dane firmowe sklepu są OPCJONALNE (w panelu wymagany jest tylko
 * kontakt), a `config/company.php` bywa niewypełniony. Stopka składa się więc
 * z tego, co jest, i chowa w CAŁOŚCI, gdy nie ma nic — zamiast zostawiać pustą
 * ramkę albo sieroce „NIP".
 *
 * Testujemy przez `render()`, bo reszta suity asertuje `intro_lines`/`outro_lines`
 * i skorupy maila w ogóle nie ogląda.
 */
class MailFooterTest extends TestCase
{
    use RefreshDatabase;

    private function render(?int $shopId): string
    {
        $message = EmailMessage::create([
            'shop_id' => $shopId,
            'to_email' => 'klient@example.com',
            'to_name' => 'Anna Nowak',
            'subject' => 'Twoje zamówienie',
            'intro_lines' => ['Cześć Anna,'],
        ]);

        return (new OutboxMailable($message))->render();
    }

    private function shopWithCompanyData(): Shop
    {
        return Shop::factory()->create([
            'company_name' => 'Red Paprika Rafał Kwaśniak',
            'nip' => '6252118589',
            'street' => 'Okrzei',
            'building_number' => '73',
            'postal_code' => '42-582',
            'city' => 'Rogoźnik',
            'contact_email' => 'kontakt@example.com',
            'contact_phone' => '48668196229',
        ]);
    }

    public function test_address_line_joins_street_number_and_city(): void
    {
        $this->assertSame(
            'Okrzei 73, 42-582 Rogoźnik',
            $this->shopWithCompanyData()->addressLine(),
        );
    }

    public function test_address_line_includes_apartment_number(): void
    {
        $shop = Shop::factory()->create([
            'street' => 'Okrzei', 'building_number' => '73', 'apartment_number' => '5',
            'postal_code' => '42-582', 'city' => 'Rogoźnik',
        ]);

        $this->assertSame('Okrzei 73/5, 42-582 Rogoźnik', $shop->addressLine());
    }

    public function test_address_line_is_null_without_address_data(): void
    {
        $shop = Shop::factory()->create([
            'street' => null, 'building_number' => null, 'apartment_number' => null,
            'postal_code' => null, 'city' => null,
        ]);

        $this->assertNull($shop->addressLine());
    }

    public function test_branding_carries_company_data_for_a_shop(): void
    {
        $brand = MailBranding::for($this->shopWithCompanyData()->id);

        $this->assertSame('Red Paprika Rafał Kwaśniak', $brand['company_name']);
        $this->assertSame('Okrzei 73, 42-582 Rogoźnik', $brand['company_address']);
        $this->assertSame('6252118589', $brand['company_nip']);
        $this->assertSame('kontakt@example.com', $brand['contact_email']);
        $this->assertSame('+48 668 196 229', $brand['contact_phone']);
    }

    /**
     * Dane Kramio NIE są fallbackiem dla sklepu — stopka maila „od sklepu" z adresem
     * platformy wprowadzałaby klienta w błąd co do tego, z kim ma do czynienia.
     */
    public function test_shop_without_company_data_does_not_borrow_kramio_data(): void
    {
        config(['company.name' => 'Kramio Sp. z o.o.', 'company.nip' => '1234567890']);

        $shop = Shop::factory()->create(['company_name' => null, 'nip' => null]);
        $brand = MailBranding::for($shop->id);

        $this->assertNull($brand['company_name']);
        $this->assertNull($brand['company_nip']);
    }

    public function test_footer_shows_full_company_block(): void
    {
        $html = $this->render($this->shopWithCompanyData()->id);

        $this->assertStringContainsString('Red Paprika Rafał Kwaśniak', $html);
        $this->assertStringContainsString('Okrzei 73, 42-582 Rogoźnik', $html);
        $this->assertStringContainsString('NIP 6252118589', $html);
        $this->assertStringContainsString('mailto:kontakt@example.com', $html);
        $this->assertStringContainsString('+48 668 196 229', $html);
    }

    /** Świeży sklep: kontaktu wymagamy, danych firmowych nie — stopka ma sam kontakt. */
    public function test_shop_without_company_data_shows_contact_only(): void
    {
        $shop = Shop::factory()->create([
            'company_name' => null, 'nip' => null,
            'street' => null, 'building_number' => null, 'postal_code' => null, 'city' => null,
            'contact_email' => 'kontakt@pracowniaswiec.pl', 'contact_phone' => '48600100200',
        ]);

        $html = $this->render($shop->id);

        $this->assertStringContainsString('mailto:kontakt@pracowniaswiec.pl', $html);
        $this->assertStringContainsString('+48 600 100 200', $html);
        // Żadnego sierocego „NIP" bez numeru.
        $this->assertStringNotContainsString('NIP', $html);
    }

    /**
     * `config/company.php` jest dziś pusty — dopóki Rafał nie poda danych, maile
     * platformy mają NIE mieć stopki, a nie pokazywać pustą ramkę.
     */
    public function test_platform_mail_hides_the_footer_when_config_is_empty(): void
    {
        config(['company.name' => '', 'company.address' => '', 'company.nip' => '', 'company.email' => '', 'company.phone' => '']);

        $html = $this->render(null);

        $this->assertStringNotContainsString('font-size:12px', $html);
        $this->assertStringNotContainsString('NIP', $html);
    }

    public function test_platform_mail_shows_the_footer_once_config_is_filled(): void
    {
        config([
            'company.name' => 'Kramio Sp. z o.o.',
            'company.address' => 'Przykładowa 1, 00-001 Warszawa',
            'company.nip' => '1234567890',
            'company.email' => 'kontakt@kramio.pl',
            'company.phone' => '+48 600 000 000',
        ]);

        $html = $this->render(null);

        $this->assertStringContainsString('Kramio Sp. z o.o.', $html);
        $this->assertStringContainsString('Przykładowa 1, 00-001 Warszawa', $html);
        $this->assertStringContainsString('NIP 1234567890', $html);
        $this->assertStringContainsString('mailto:kontakt@kramio.pl', $html);
    }

    /**
     * Dawna formułka „wysłana automatycznie / możesz zignorować" NIE wraca razem ze
     * stopką — nie mamy adresu noreply, na każdy mail da się odpowiedzieć.
     */
    public function test_the_old_ignore_me_footer_stays_gone(): void
    {
        $html = $this->render($this->shopWithCompanyData()->id);

        $this->assertStringNotContainsString('automatycznie', $html);
        $this->assertStringNotContainsString('zignoruj', $html);
    }
}
