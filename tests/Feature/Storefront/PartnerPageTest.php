<?php

namespace Tests\Feature\Storefront;

use App\Enums\OptionGroupKind;
use App\Models\Licensor;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Publiczna strona partnera licencyjnego (Etap 2, krok F).
 *
 * Wprost ze specyfikacji: „musi być także ekran prezentujący wszystkie produkty
 * tylko wybranej firmy, np. Stowarzyszenia Tychy Razem". Organizator biegu
 * dostaje jeden link i rozsyła go uczestnikom.
 *
 * NAJWAŻNIEJSZE W TYCH TESTACH: co NIE wychodzi na zewnątrz. Publiczna jest
 * nazwa i lista produktów — i tak są na magnesie. Warunki umowy, kontakt
 * i stawki zostają w kartotece.
 */
class PartnerPageTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->sellable()->create();
    }

    private function partner(array $attributes = []): Licensor
    {
        return $this->shop->licensors()->create(array_merge([
            'name' => 'Stowarzyszenie Tychy Razem',
            'slug' => 'stowarzyszenie-tychy-razem',
        ], $attributes));
    }

    private function produkt(string $name, array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'shop_id' => $this->shop->id,
            'name' => $name,
            'is_active' => true,
        ], $attributes));
    }

    private function odwiedz(string $path)
    {
        return $this->get('https://'.$this->shop->host().$path);
    }

    // --- Co strona pokazuje --------------------------------------------------

    public function test_partner_page_lists_products_with_his_logo_on_the_front(): void
    {
        $partner = $this->partner();
        $this->produkt('Magnes tyski', ['licensor_id' => $partner->id, 'licence_fee_gross' => 5]);
        $this->produkt('Magnes obcy');

        $this->odwiedz('/partner/stowarzyszenie-tychy-razem')
            ->assertOk()
            ->assertSee('Stowarzyszenie Tychy Razem')
            ->assertSee('Magnes tyski')
            ->assertDontSee('Magnes obcy');
    }

    /**
     * Znak partnera bywa na REWERSIE — jako grafika graweru do wyboru.
     * Partner pytający „gdzie w tym sklepie jest mój znak" ma na myśli
     * obie strony magnesu.
     */
    public function test_products_with_his_engraving_available_also_count(): void
    {
        $partner = $this->partner();

        $group = $this->shop->optionGroups()->create(['name' => 'Grawer', 'kind' => OptionGroupKind::Choice]);
        $group->choices()->create([
            'label' => 'Logo Tychy Razem',
            'licensor_id' => $partner->id,
            'licence_fee_gross' => 4,
            'is_active' => true,
        ]);

        $product = $this->produkt('Magnes z grawerem do wyboru');
        $product->optionGroups()->attach($group->id);

        $this->odwiedz('/partner/stowarzyszenie-tychy-razem')
            ->assertOk()
            ->assertSee('Magnes z grawerem do wyboru');
    }

    /**
     * Wygaszonej grafiki nikt już nie wybierze, więc produkt nie jest
     * „produktem tego partnera".
     */
    public function test_withdrawn_engraving_does_not_pull_a_product_in(): void
    {
        $partner = $this->partner();

        $group = $this->shop->optionGroups()->create(['name' => 'Grawer', 'kind' => OptionGroupKind::Choice]);
        $group->choices()->create([
            'label' => 'Stare logo',
            'licensor_id' => $partner->id,
            'is_active' => false,
        ]);

        $product = $this->produkt('Magnes ze starym logo');
        $product->optionGroups()->attach($group->id);

        $this->odwiedz('/partner/stowarzyszenie-tychy-razem')
            ->assertOk()
            ->assertDontSee('Magnes ze starym logo');
    }

    public function test_inactive_product_stays_off_the_partner_page(): void
    {
        $partner = $this->partner();
        $this->produkt('Wycofany magnes', ['licensor_id' => $partner->id, 'is_active' => false]);

        $this->odwiedz('/partner/stowarzyszenie-tychy-razem')
            ->assertOk()
            ->assertDontSee('Wycofany magnes');
    }

    // --- Co NIE wychodzi na zewnątrz ----------------------------------------

    /**
     * Kartoteka zostaje prywatna. Kupujący widzi opłatę w rozbiciu ceny, ale
     * nie ma powodu znać warunków, na jakich sklep ją pobiera.
     */
    public function test_contract_details_never_reach_the_public_page(): void
    {
        $partner = $this->partner([
            'contact_email' => 'biuro@tychyrazem.example',
            'contact_person' => 'Anna Kowalska',
            'agreement_reference' => 'UM/2026/17',
            'notes' => 'Stawka negocjowana, do renegocjacji w grudniu.',
        ]);
        $this->produkt('Magnes tyski', ['licensor_id' => $partner->id]);

        $this->odwiedz('/partner/stowarzyszenie-tychy-razem')
            ->assertOk()
            ->assertDontSee('biuro@tychyrazem.example')
            ->assertDontSee('Anna Kowalska')
            ->assertDontSee('UM/2026/17')
            ->assertDontSee('do renegocjacji');
    }

    /**
     * Wygaszenie znaczy „już z nimi nie pracujemy" — strona byłaby ofertą
     * opartą na nieobowiązującej umowie.
     */
    public function test_withdrawn_partner_has_no_page(): void
    {
        $partner = $this->partner(['is_active' => false]);
        $this->produkt('Magnes tyski', ['licensor_id' => $partner->id]);

        $this->odwiedz('/partner/stowarzyszenie-tychy-razem')->assertNotFound();
    }

    public function test_unknown_partner_is_not_found(): void
    {
        $this->odwiedz('/partner/nie-ma-takiego')->assertNotFound();
    }

    /**
     * Partnerzy są per sklep — cudzy slug nie może otworzyć strony w naszym.
     */
    public function test_another_shops_partner_is_not_found_here(): void
    {
        $obcy = Shop::factory()->sellable()->create();
        $obcy->licensors()->create(['name' => 'Cudzy Partner', 'slug' => 'cudzy-partner']);

        $this->odwiedz('/partner/cudzy-partner')->assertNotFound();
    }

    // --- Drogi do strony -----------------------------------------------------

    public function test_product_page_links_to_its_partner(): void
    {
        $partner = $this->partner();
        $product = $this->produkt('Magnes tyski', ['licensor_id' => $partner->id, 'licence_fee_gross' => 5]);

        $this->odwiedz($product->storefrontPath())
            ->assertOk()
            ->assertSee('Stowarzyszenie Tychy Razem')
            ->assertSee('/partner/stowarzyszenie-tychy-razem', false);
    }

    public function test_product_page_does_not_link_a_withdrawn_partner(): void
    {
        $partner = $this->partner(['is_active' => false]);
        $product = $this->produkt('Magnes tyski', ['licensor_id' => $partner->id]);

        $this->odwiedz($product->storefrontPath())
            ->assertOk()
            ->assertDontSee('/partner/stowarzyszenie-tychy-razem', false);
    }

    public function test_filters_still_work_on_a_partner_page(): void
    {
        $partner = $this->partner();
        $kamien = $this->shop->categories()->create(['axis' => 'kind', 'name' => 'Kamień', 'slug' => 'kamien']);

        $a = $this->produkt('Kamienny tyski', ['licensor_id' => $partner->id]);
        $a->categories()->attach($kamien->id);
        $this->produkt('Metalowy tyski', ['licensor_id' => $partner->id]);

        // Zawężenie zostaje NA STRONIE PARTNERA, nie wyrzuca na pełny wykaz.
        $this->odwiedz('/partner/stowarzyszenie-tychy-razem')
            ->assertOk()
            ->assertSee('/partner/stowarzyszenie-tychy-razem?rodzaj=kamien', false);

        $this->odwiedz('/partner/stowarzyszenie-tychy-razem?rodzaj=kamien')
            ->assertOk()
            ->assertSee('Kamienny tyski')
            ->assertDontSee('Metalowy tyski');
    }

    // --- Panel ---------------------------------------------------------------

    public function test_slug_is_generated_when_a_partner_is_added(): void
    {
        $owner = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('seller.licensors.store'), ['name' => 'Bieg Gdański', 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame('bieg-gdanski', $shop->licensors()->firstOrFail()->slug);
    }

    /**
     * Slug jest adresem rozesłanym uczestnikom — poprawka literówki w nazwie
     * nie może go unieważnić.
     */
    public function test_renaming_a_partner_keeps_his_address(): void
    {
        $owner = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $owner->id]);
        $partner = $shop->licensors()->create(['name' => 'Bieg Gdanski', 'slug' => 'bieg-gdanski']);

        $this->actingAs($owner)
            ->post(route('seller.licensors.update', $partner), ['name' => 'Bieg Gdański', 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        $partner->refresh();
        $this->assertSame('Bieg Gdański', $partner->name);
        $this->assertSame('bieg-gdanski', $partner->slug);
    }

    public function test_panel_shows_the_link_only_for_active_partners(): void
    {
        $owner = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $owner->id]);
        $shop->licensors()->create(['name' => 'Aktywny Bieg', 'slug' => 'aktywny-bieg', 'is_active' => true]);
        $shop->licensors()->create(['name' => 'Wygaszony Bieg', 'slug' => 'wygaszony-bieg', 'is_active' => false]);

        $response = $this->actingAs($owner)->get(route('seller.licensors.index'))->assertOk();

        $response->assertSee('/partner/aktywny-bieg', false);
        $response->assertDontSee('/partner/wygaszony-bieg', false);
    }
}
