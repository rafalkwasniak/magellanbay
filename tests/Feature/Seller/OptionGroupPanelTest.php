<?php

namespace Tests\Feature\Seller;

use App\Enums\OptionGroupKind;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Grupy opcji w panelu (Etap 2, krok C — część druga).
 *
 * Grupa należy do SKLEPU, nie do produktu: „Nadruk 3 linie" definiuje się raz
 * i przypina do stu magnesów. Ekran pilnuje dwóch rzeczy, w których pomyłka
 * jest kosztowna — niezmiennego rodzaju grupy i kasowania grup będących
 * w użyciu.
 */
class OptionGroupPanelTest extends TestCase
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
            'name' => 'Nadruk imienia',
            'kind' => OptionGroupKind::Text->value,
            'hint' => 'Wpisz imię, które nadrukujemy.',
            'surcharge_gross' => '10,00',
            'required' => '1',
            ...$override,
        ];
    }

    public function test_owner_creates_a_group_and_lands_on_its_contents(): void
    {
        [$owner, $shop] = $this->sklep();

        $this->actingAs($owner)
            ->from(route('seller.options.create'))
            ->post(route('seller.options.store'), $this->dane());

        $group = $shop->optionGroups()->sole();

        $this->assertSame('Nadruk imienia', $group->name);
        $this->assertSame(OptionGroupKind::Text, $group->kind);
        $this->assertSame('10.00', $group->surcharge_gross);
        $this->assertTrue($group->required);
    }

    /**
     * Kwoty wpisuje się po polsku — z przecinkiem. Wymaganie kropki od kogoś,
     * kto całe życie pisze „10,50", to pułapka, a nie walidacja.
     */
    public function test_a_polish_amount_with_a_comma_is_accepted(): void
    {
        [$owner, $shop] = $this->sklep();

        $this->actingAs($owner)
            ->post(route('seller.options.store'), $this->dane(['surcharge_gross' => '12,50']));

        $this->assertSame('12.50', $shop->optionGroups()->sole()->surcharge_gross);
    }

    /**
     * Zero to NORMALNA wartość: specyfikacja mówi wprost, że koszt nadruku na
     * awersie jest zawarty w cenie produktu i nie wykazujemy go osobno.
     */
    public function test_a_zero_surcharge_is_valid(): void
    {
        [$owner, $shop] = $this->sklep();

        $this->actingAs($owner)
            ->post(route('seller.options.store'), $this->dane(['surcharge_gross' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertSame('0.00', $shop->optionGroups()->sole()->surcharge_gross);
    }

    /**
     * RODZAJU NIE DA SIĘ ZMIENIĆ. Zmiana z formatki na bibliotekę osierociłaby
     * pola tekstowe, a w drugą stronę — pozycje biblioteki; grupa zostałaby
     * pusta, a produkty, które ją mają przypiętą, przestałyby dać się kupić.
     */
    public function test_the_kind_cannot_be_changed_after_creation(): void
    {
        [$owner, $shop] = $this->sklep();
        $group = $shop->optionGroups()->create(['name' => 'Nadruk', 'kind' => OptionGroupKind::Text]);

        $this->actingAs($owner)
            ->from(route('seller.options.edit', $group))
            ->post(route('seller.options.update', $group), $this->dane([
                'kind' => OptionGroupKind::Choice->value,
            ]))
            ->assertSessionHasErrors('kind');

        $this->assertSame(OptionGroupKind::Text, $group->fresh()->kind);
    }

    public function test_the_edit_screen_shows_the_kind_as_a_fact_not_a_field(): void
    {
        [$owner, $shop] = $this->sklep();
        $group = $shop->optionGroups()->create(['name' => 'Nadruk', 'kind' => OptionGroupKind::Text]);

        $this->actingAs($owner)
            ->get(route('seller.options.edit', $group))
            ->assertOk()
            ->assertSee('Rodzaju nie da się zmienić po utworzeniu')
            ->assertDontSee('name="kind"', false);
    }

    /**
     * Grupa wykluczająca się z samą sobą byłaby niemożliwa do wypełnienia.
     */
    public function test_a_group_cannot_exclude_itself(): void
    {
        [$owner, $shop] = $this->sklep();
        $group = $shop->optionGroups()->create(['name' => 'Grawer', 'kind' => OptionGroupKind::Text]);

        $this->actingAs($owner)
            ->from(route('seller.options.edit', $group))
            ->post(route('seller.options.update', $group), [
                'name' => 'Grawer',
                'surcharge_gross' => '0',
                'excludes_group_id' => $group->id,
            ])
            ->assertSessionHasErrors('excludes_group_id');
    }

    public function test_exclusion_can_only_point_at_a_group_from_the_same_shop(): void
    {
        [$owner, $shop] = $this->sklep();
        $moja = $shop->optionGroups()->create(['name' => 'Grawer', 'kind' => OptionGroupKind::Text]);
        $cudza = Shop::factory()->create()->optionGroups()->create(['name' => 'Cudza', 'kind' => OptionGroupKind::Text]);

        $this->actingAs($owner)
            ->from(route('seller.options.edit', $moja))
            ->post(route('seller.options.update', $moja), [
                'name' => 'Grawer',
                'surcharge_gross' => '0',
                'excludes_group_id' => $cudza->id,
            ])
            ->assertSessionHasErrors('excludes_group_id');
    }

    public function test_groups_from_another_shop_are_not_reachable(): void
    {
        [$owner] = $this->sklep();
        $cudza = Shop::factory()->create()->optionGroups()->create(['name' => 'Cudza', 'kind' => OptionGroupKind::Text]);

        $this->actingAs($owner)->get(route('seller.options.edit', $cudza))->assertNotFound();
        $this->actingAs($owner)->post(route('seller.options.destroy', $cudza))->assertNotFound();
    }

    public function test_an_unused_group_can_be_deleted(): void
    {
        [$owner, $shop] = $this->sklep();
        $group = $shop->optionGroups()->create(['name' => 'Pomyłka', 'kind' => OptionGroupKind::Text]);

        $this->actingAs($owner)
            ->from(route('seller.options.index'))
            ->post(route('seller.options.destroy', $group))
            ->assertRedirect(route('seller.options.index'));

        $this->assertSame(0, $shop->optionGroups()->count());
    }

    /**
     * Grupa przypięta do produktów to DZIAŁAJĄCA personalizacja — skasowana,
     * zabiera pola i pozycje biblioteki, a produkty tracą możliwość
     * personalizacji bez słowa ostrzeżenia.
     */
    public function test_a_group_attached_to_products_cannot_be_deleted(): void
    {
        [$owner, $shop] = $this->sklep();
        $group = $shop->optionGroups()->create(['name' => 'Nadruk', 'kind' => OptionGroupKind::Text]);
        Product::factory()->create(['shop_id' => $shop->id])->optionGroups()->attach($group);

        $this->actingAs($owner)
            ->from(route('seller.options.index'))
            ->post(route('seller.options.destroy', $group))
            ->assertSessionHas('error');

        $this->assertNotNull($group->fresh());
    }

    /**
     * Grupa bez zawartości to puste pytanie: w kasie wygląda jak usterka,
     * a przy „obowiązkowa" blokuje zakup zupełnie. Lista musi to powiedzieć,
     * zanim sprzedawca przypnie ją do stu produktów.
     */
    public function test_the_list_warns_about_an_empty_group(): void
    {
        [$owner, $shop] = $this->sklep();
        $shop->optionGroups()->create(['name' => 'Pusta', 'kind' => OptionGroupKind::Text]);

        $this->actingAs($owner)
            ->get(route('seller.options.index'))
            ->assertOk()
            ->assertSee('Ta grupa jest pusta');
    }

    public function test_a_filled_group_is_not_flagged_as_empty(): void
    {
        [$owner, $shop] = $this->sklep();
        $group = $shop->optionGroups()->create(['name' => 'Nadruk', 'kind' => OptionGroupKind::Text]);
        $group->fields()->create(['label' => 'Imię', 'max_length' => 12]);

        $this->actingAs($owner)
            ->get(route('seller.options.index'))
            ->assertOk()
            ->assertDontSee('Ta grupa jest pusta');
    }

    public function test_the_menu_links_to_personalisation(): void
    {
        [$owner] = $this->sklep();

        $this->actingAs($owner)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Personalizacja');
    }
}
