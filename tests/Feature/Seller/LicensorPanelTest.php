<?php

namespace Tests\Feature\Seller;

use App\Enums\OptionGroupKind;
use App\Models\Licensor;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kartoteka licencjodawców w panelu (Etap 2, krok C — część pierwsza).
 *
 * Kartoteka jest ADRESATEM PIENIĘDZY: to z niej wynika, komu należy się opłata
 * za użycie znaku. Stąd dwie reguły, których pilnują testy niżej — nazwa
 * unikalna w obrębie sklepu i zakaz kasowania wpisu, na który poszła sprzedaż.
 */
class LicensorPanelTest extends TestCase
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

    private function dane(array $override = []): array
    {
        return [
            'name' => 'Bieg Gdański',
            'contact_person' => 'Anna Nowak',
            'contact_email' => 'licencje@bieg.example',
            'agreement_reference' => 'UM/2026/014',
            'notes' => 'Rozliczenie kwartalne.',
            'is_active' => '1',
            ...$override,
        ];
    }

    public function test_owner_adds_a_partner(): void
    {
        [$owner, $shop] = $this->sklep();

        $this->actingAs($owner)
            ->from(route('seller.licensors.create'))
            ->post(route('seller.licensors.store'), $this->dane())
            ->assertRedirect(route('seller.licensors.index'));

        $licensor = $shop->licensors()->sole();

        $this->assertSame('Bieg Gdański', $licensor->name);
        $this->assertSame('UM/2026/014', $licensor->agreement_reference);
        $this->assertTrue($licensor->is_active);
    }

    /**
     * Dwa wpisy „Bieg Gdański" to przy rozliczeniu dwie kupki pieniędzy dla
     * jednej firmy — a reguła „nie sumujemy, liczy się wyższa" przestaje
     * działać, bo dla systemu to dwaj RÓŻNI partnerzy.
     */
    public function test_a_duplicate_name_is_refused(): void
    {
        [$owner, $shop] = $this->sklep();
        $shop->licensors()->create(['name' => 'Bieg Gdański']);

        $this->actingAs($owner)
            ->from(route('seller.licensors.create'))
            ->post(route('seller.licensors.store'), $this->dane())
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $shop->licensors()->count());
    }

    /**
     * Nazwa jest unikalna W OBRĘBIE SKLEPU, nie globalnie — dwa różne sklepy
     * mogą mieć umowę z tym samym organizatorem.
     */
    public function test_another_shop_may_use_the_same_name(): void
    {
        [$owner] = $this->sklep();
        Shop::factory()->create()->licensors()->create(['name' => 'Bieg Gdański']);

        $this->actingAs($owner)
            ->from(route('seller.licensors.create'))
            ->post(route('seller.licensors.store'), $this->dane())
            ->assertSessionHasNoErrors();
    }

    public function test_partners_from_another_shop_are_not_reachable(): void
    {
        [$owner] = $this->sklep();
        $cudzy = Shop::factory()->create()->licensors()->create(['name' => 'Cudzy Partner']);

        $this->actingAs($owner)->get(route('seller.licensors.edit', $cudzy))->assertNotFound();
        $this->actingAs($owner)->post(route('seller.licensors.update', $cudzy), $this->dane())->assertNotFound();
        $this->actingAs($owner)->post(route('seller.licensors.destroy', $cudzy))->assertNotFound();
    }

    /**
     * Zakończenie współpracy to WYGASZENIE, nie kasowanie: wpis znika z wyboru,
     * ale zostaje w rozliczeniach.
     */
    public function test_a_partner_can_be_switched_off_and_back_on(): void
    {
        [$owner, $shop] = $this->sklep();
        $licensor = $shop->licensors()->create(['name' => 'Bieg Gdański']);

        $this->actingAs($owner)->post(route('seller.licensors.toggle', $licensor));
        $this->assertFalse($licensor->fresh()->is_active);
        $this->assertSame(0, Licensor::query()->active()->count());

        $this->actingAs($owner)->post(route('seller.licensors.toggle', $licensor));
        $this->assertTrue($licensor->fresh()->is_active);
    }

    public function test_an_unused_partner_can_be_deleted(): void
    {
        [$owner, $shop] = $this->sklep();
        $licensor = $shop->licensors()->create(['name' => 'Pomylka']);

        $this->actingAs($owner)
            ->from(route('seller.licensors.index'))
            ->post(route('seller.licensors.destroy', $licensor))
            ->assertRedirect(route('seller.licensors.index'));

        $this->assertSame(0, $shop->licensors()->count());
    }

    /**
     * Partner przypięty do PRODUKTU jest w użyciu — skasowanie zdjęłoby opłatę
     * z produktu, który dziś ją nalicza.
     */
    public function test_a_partner_used_by_a_product_cannot_be_deleted(): void
    {
        [$owner, $shop] = $this->sklep();
        $licensor = $shop->licensors()->create(['name' => 'Bieg Gdański']);

        Product::factory()->create([
            'shop_id' => $shop->id, 'licensor_id' => $licensor->id, 'licence_fee_gross' => 5.00,
        ]);

        $this->actingAs($owner)
            ->from(route('seller.licensors.index'))
            ->post(route('seller.licensors.destroy', $licensor))
            ->assertSessionHas('error');

        $this->assertNotNull($licensor->fresh());
    }

    /**
     * Partner przypięty do GRAFIKI w bibliotece — tak samo.
     */
    public function test_a_partner_used_by_a_graphic_cannot_be_deleted(): void
    {
        [$owner, $shop] = $this->sklep();
        $licensor = $shop->licensors()->create(['name' => 'Bieg Gdański']);

        $group = $shop->optionGroups()->create(['name' => 'Grawer', 'kind' => OptionGroupKind::Choice]);
        $group->choices()->create([
            'label' => 'Trasa', 'licensor_id' => $licensor->id, 'licence_fee_gross' => 8.00,
        ]);

        $this->actingAs($owner)
            ->from(route('seller.licensors.index'))
            ->post(route('seller.licensors.destroy', $licensor))
            ->assertSessionHas('error');

        $this->assertNotNull($licensor->fresh());
    }

    public function test_the_list_shows_how_much_is_riding_on_each_partner(): void
    {
        [$owner, $shop] = $this->sklep();
        $licensor = $shop->licensors()->create(['name' => 'Bieg Gdański']);

        $group = $shop->optionGroups()->create(['name' => 'Grawer', 'kind' => OptionGroupKind::Choice]);
        $group->choices()->create(['label' => 'Trasa', 'licensor_id' => $licensor->id]);
        $group->choices()->create(['label' => 'Meta', 'licensor_id' => $licensor->id]);

        $this->actingAs($owner)
            ->get(route('seller.licensors.index'))
            ->assertOk()
            ->assertSee('Bieg Gdański')
            // Liczby mówią, czy partnera wolno skasować — bez nich sprzedawca
            // klika „Usuń", dostaje odmowę i nie wie dlaczego.
            ->assertSee('Grafik: 2')
            // Przycisk „Usuń" NIE pokazuje się tam, gdzie i tak nie zadziała.
            ->assertDontSee('Usunąć Bieg Gdański z kartoteki?');
    }

    public function test_the_menu_links_to_the_registry(): void
    {
        [$owner] = $this->sklep();

        $this->actingAs($owner)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Partnerzy');
    }
}
