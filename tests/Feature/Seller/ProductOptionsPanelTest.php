<?php

namespace Tests\Feature\Seller;

use App\Enums\OptionGroupKind;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Przypięcie personalizacji do produktu i licencja za logotyp (Etap 2, krok C4).
 *
 * Do tej pory grupy opcji i partnerzy istnieli osobno — sprzedawca mógł je
 * zdefiniować, ale nie mógł powiedzieć, KTÓRY magnes ma o co pytać. To domyka
 * ten krok.
 */
class ProductOptionsPanelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sklep(): array
    {
        $owner = User::factory()->consented()->create();
        $shop = Shop::factory()->create(['owner_id' => $owner->id]);

        return [$owner, $shop];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Magnes Gdańsk',
            'price_gross' => '19,99',
            'vat_rate' => '23',
            'sale_unit' => 'piece',
            'track_stock' => '1',
            'stock' => '10',
            'is_active' => '1',
        ], $overrides);
    }

    /**
     * Grupa GOTOWA — z jednym polem, więc ma o co zapytać.
     */
    private function gotowaGrupa(Shop $shop, string $name = 'Nadruk'): OptionGroup
    {
        $group = $shop->optionGroups()->create(['name' => $name, 'kind' => OptionGroupKind::Text]);
        $group->fields()->create(['label' => 'Imię', 'max_length' => 12, 'required' => true, 'position' => 0]);

        return $group;
    }

    // --- Przypięcie grup ---------------------------------------------------

    public function test_seller_attaches_option_groups_when_creating_a_product(): void
    {
        [$owner, $shop] = $this->sklep();
        $group = $this->gotowaGrupa($shop);

        $this->actingAs($owner)
            ->post(route('seller.products.store'), $this->payload(['option_groups' => [$group->id]]))
            ->assertSessionHasNoErrors();

        $product = $shop->products()->firstOrFail();
        $this->assertTrue($product->optionGroups->contains($group->id));
        $this->assertTrue($product->isPersonalised());
    }

    public function test_seller_detaches_a_group_by_unchecking_it(): void
    {
        [$owner, $shop] = $this->sklep();
        $group = $this->gotowaGrupa($shop);
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $product->optionGroups()->attach($group->id);

        // Odznaczony checkbox NIE PRZYCHODZI w żądaniu — brak klucza musi więc
        // znaczyć „odepnij wszystko", a nie „zostaw jak było".
        $this->actingAs($owner)
            ->post(route('seller.products.update', $product), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertFalse($product->fresh()->optionGroups()->exists());
    }

    public function test_group_from_another_shop_is_rejected(): void
    {
        [$owner, $shop] = $this->sklep();
        [, $obcy] = $this->sklep();
        $cudza = $this->gotowaGrupa($obcy);

        $this->actingAs($owner)
            ->from(route('seller.products.create'))
            ->post(route('seller.products.store'), $this->payload(['option_groups' => [$cudza->id]]))
            ->assertSessionHasErrors('option_groups.0');

        $this->assertSame(0, $shop->products()->count());
    }

    /**
     * Pusta grupa nie trafia na listę do zaznaczenia: w kasie wyglądałaby jak
     * usterka, a oznaczona jako obowiązkowa zablokowałaby zakup całkiem.
     */
    public function test_empty_group_is_not_offered_on_the_product_form(): void
    {
        [$owner, $shop] = $this->sklep();
        $shop->optionGroups()->create(['name' => 'Pusta na razie', 'kind' => OptionGroupKind::Text]);
        $this->gotowaGrupa($shop, 'Nadruk trzy linie');

        $this->actingAs($owner)
            ->get(route('seller.products.create'))
            ->assertOk()
            ->assertSee('Nadruk trzy linie')
            ->assertDontSee('Pusta na razie');
    }

    // --- Licencja za logotyp ----------------------------------------------

    public function test_seller_sets_a_licence_fee_with_a_partner(): void
    {
        [$owner, $shop] = $this->sklep();
        $licensor = $shop->licensors()->create(['name' => 'Maraton Gdański']);

        $this->actingAs($owner)
            ->post(route('seller.products.store'), $this->payload([
                'licensor_id' => (string) $licensor->id,
                'licence_fee_gross' => '2,50',
            ]))
            ->assertSessionHasNoErrors();

        $product = $shop->products()->firstOrFail();
        $this->assertSame($licensor->id, $product->licensor_id);
        $this->assertSame('2.50', (string) $product->licence_fee_gross);
    }

    /**
     * OPŁATA BEZ PARTNERA TO PIENIĄDZE NALEŻNE NIKOMU — nie da się jej z nikim
     * rozliczyć, a w raporcie zniknęłaby bez śladu.
     */
    public function test_licence_fee_without_a_partner_is_rejected(): void
    {
        [$owner, $shop] = $this->sklep();

        $this->actingAs($owner)
            ->from(route('seller.products.create'))
            ->post(route('seller.products.store'), $this->payload(['licence_fee_gross' => '2,50']))
            ->assertSessionHasErrors('licensor_id');

        $this->assertSame(0, $shop->products()->count());
    }

    public function test_partner_without_a_fee_is_allowed(): void
    {
        [$owner, $shop] = $this->sklep();
        $licensor = $shop->licensors()->create(['name' => 'Bieg Gdański']);

        // Znak partnera na produkcie bez opłaty to normalna umowa — barter,
        // patronat, własna marka klienta. Zero nie może blokować zapisu.
        $this->actingAs($owner)
            ->post(route('seller.products.store'), $this->payload([
                'licensor_id' => (string) $licensor->id,
                'licence_fee_gross' => '0,00',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($licensor->id, $shop->products()->firstOrFail()->licensor_id);
    }

    public function test_partner_from_another_shop_is_rejected(): void
    {
        [$owner, $shop] = $this->sklep();
        [, $obcy] = $this->sklep();
        $cudzy = $obcy->licensors()->create(['name' => 'Cudzy Partner']);

        $this->actingAs($owner)
            ->from(route('seller.products.create'))
            ->post(route('seller.products.store'), $this->payload([
                'licensor_id' => (string) $cudzy->id,
                'licence_fee_gross' => '2,50',
            ]))
            ->assertSessionHasErrors('licensor_id');

        $this->assertSame(0, $shop->products()->count());
    }

    /**
     * Wygaszony partner znika z WYBORU, ale nie może zniknąć z produktu, który
     * już go ma — zapis formularza po cichu zerwałby przypisanie i produkt
     * przestałby się rozliczać.
     */
    public function test_withdrawn_partner_stays_selectable_on_a_product_that_has_him(): void
    {
        [$owner, $shop] = $this->sklep();
        $licensor = $shop->licensors()->create([
            'name' => 'Bieg Wygaszony',
            'is_active' => false,
        ]);
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'licensor_id' => $licensor->id,
            'licence_fee_gross' => 3,
        ]);

        $this->actingAs($owner)
            ->get(route('seller.products.edit', $product))
            ->assertOk()
            ->assertSee('Bieg Wygaszony');

        $this->actingAs($owner)
            ->post(route('seller.products.update', $product), $this->payload([
                'licensor_id' => (string) $licensor->id,
                'licence_fee_gross' => '3,00',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($licensor->id, $product->fresh()->licensor_id);
    }

    public function test_product_form_points_to_the_library_when_there_are_no_groups(): void
    {
        [$owner] = $this->sklep();

        $this->actingAs($owner)
            ->get(route('seller.products.create'))
            ->assertOk()
            ->assertSee('Nie masz jeszcze żadnej gotowej grupy opcji.');
    }
}
