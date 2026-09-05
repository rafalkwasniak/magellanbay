<?php

namespace Tests\Feature\Storefront;

use App\Enums\OptionGroupKind;
use App\Livewire\AddToCart;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Formularz personalizacji na karcie produktu (Etap 2, krok B).
 *
 * Jedyny ekran tego modułu, który widzi KUPUJĄCY — i dlatego jedyny, który musi
 * tłumaczyć się sam. Reguły walidacji budują się z grup przypiętych do produktu,
 * więc zmiana limitu znaków w panelu działa tego samego dnia; źródłem prawdy
 * pozostaje `ProductConfiguration::normalise()`, a to tutaj jest warstwą UX,
 * która ma powiedzieć KTÓRE pole jest nie tak.
 */
class PersonalisationFormTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Shop, 1: Product, 2: OptionGroup}
     */
    private function magnesZImieniem(array $groupAttributes = []): array
    {
        $shop = Shop::factory()->sellable()->create();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false,
            'name' => 'Magnes', 'price_gross' => 24.90,
        ]);

        $group = $shop->optionGroups()->create([
            'name' => 'Nadruk imienia',
            'kind' => OptionGroupKind::Text,
            'surcharge_gross' => 10.00,
            'required' => true,
            ...$groupAttributes,
        ]);
        $group->fields()->create(['label' => 'Imię', 'max_length' => 12, 'required' => true]);

        $product->optionGroups()->attach($group);

        return [$shop, $product->fresh(), $group];
    }

    private function pole(OptionGroup $group): string
    {
        return 'config.'.$group->id.'.fields.'.$group->fields->first()->id;
    }

    public function test_the_form_shows_the_group_and_its_character_limit(): void
    {
        [, $product, $group] = $this->magnesZImieniem();

        Livewire::test(AddToCart::class, ['product' => $product])
            ->assertSee('Nadruk imienia')
            // Limit podany WPROST, a nie dopiero w komunikacie błędu: wynika
            // z fizyki produktu, więc kupujący ma go znać przed wpisaniem.
            ->assertSee('do 12 znaków');

        $this->assertSame(12, $group->fields->first()->max_length);
    }

    public function test_a_filled_form_adds_the_configured_product(): void
    {
        [$shop, $product, $group] = $this->magnesZImieniem();

        Livewire::test(AddToCart::class, ['product' => $product])
            ->set($this->pole($group), 'Zosia')
            ->call('add')
            ->assertHasNoErrors()
            ->assertDispatched('cart-updated');

        $line = app(CartService::class)->lines($shop->id)->first();

        $this->assertSame('Zosia', $line['personalisation'][0]['value']);
        $this->assertSame(34.90, $line['unit_price']);
    }

    /**
     * Bez tego kupujący klika „Do koszyka" i NIC się nie dzieje — `CartService`
     * po cichu odrzuca konfigurację bez wymaganego pola. Przycisk, który nie
     * reaguje, jest gorszy niż komunikat o błędzie.
     */
    public function test_an_empty_required_field_explains_itself_instead_of_failing_silently(): void
    {
        [$shop, $product, $group] = $this->magnesZImieniem();

        Livewire::test(AddToCart::class, ['product' => $product])
            ->call('add')
            ->assertHasErrors($this->pole($group));

        $this->assertSame(0, app(CartService::class)->count($shop->id));
    }

    /**
     * Za długi tekst wskazuje KONKRETNE pole, a nie odrzuca całości bez słowa.
     * Nie przycinamy: magnes z uciętym imieniem to rzecz, której klient nie
     * może zwrócić (art. 38 pkt 3 u.p.k.).
     */
    public function test_a_too_long_value_points_at_the_field(): void
    {
        [$shop, $product, $group] = $this->magnesZImieniem();

        Livewire::test(AddToCart::class, ['product' => $product])
            ->set($this->pole($group), 'Konstantynopolitanka')
            ->call('add')
            ->assertHasErrors([$this->pole($group) => 'max']);

        $this->assertSame(0, app(CartService::class)->count($shop->id));
    }

    /**
     * Grupa nieobowiązkowa nie blokuje zakupu — sklep sprzedaje magnesy zwykłe
     * i personalizowane z tego samego katalogu.
     */
    public function test_an_optional_group_can_be_skipped(): void
    {
        [$shop, $product] = $this->magnesZImieniem(['required' => false]);

        Livewire::test(AddToCart::class, ['product' => $product])
            ->call('add')
            ->assertHasNoErrors();

        $line = app(CartService::class)->lines($shop->id)->first();

        $this->assertSame(24.90, $line['unit_price']);
        $this->assertSame([], $line['personalisation']);
    }

    /**
     * ROZBICIE CENY NA ŻYWO — „cena z czterech części widocznych klientowi przy
     * zamawianiu" z zamówienia klienta. Kwota rośnie w chwili wpisania, a nie
     * dopiero w koszyku.
     */
    public function test_the_price_breakdown_updates_while_typing(): void
    {
        [, $product, $group] = $this->magnesZImieniem();

        $component = Livewire::test(AddToCart::class, ['product' => $product]);

        // Przed wypełnieniem nie ma czego rozbijać — sam produkt to cena, nie rozbicie.
        $component->assertDontSee('Razem');

        $component->set($this->pole($group), 'Zosia')
            ->assertSee('Razem')
            ->assertSee('Nadruk imienia')
            ->assertSee('34,90');
    }

    public function test_the_licence_fee_is_named_in_the_breakdown(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false, 'price_gross' => 24.90,
        ]);

        $partner = $shop->licensors()->create(['name' => 'Bieg Gdański']);
        $group = $shop->optionGroups()->create(['name' => 'Logotyp', 'kind' => OptionGroupKind::Choice]);
        $choice = $group->choices()->create([
            'label' => 'Bieg Gdański 2026', 'licensor_id' => $partner->id, 'licence_fee_gross' => 5.00,
        ]);
        $product->optionGroups()->attach($group);

        Livewire::test(AddToCart::class, ['product' => $product->fresh()])
            ->set('config.'.$group->id.'.choice', $choice->id)
            ->assertSee('opłata licencyjna')
            ->assertSee('29,90');
    }

    /**
     * „Grawer to grafika ALBO tekst" — kupujący ma usłyszeć, co zrobić, a nie
     * zobaczyć nieruchomy przycisk.
     */
    public function test_mutually_exclusive_groups_say_so(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false,
        ]);

        $grafika = $shop->optionGroups()->create(['name' => 'Grawer — grafika', 'kind' => OptionGroupKind::Choice]);
        $choice = $grafika->choices()->create(['label' => 'Kotwica', 'surcharge_gross' => 20.00]);

        $tekst = $shop->optionGroups()->create([
            'name' => 'Grawer — tekst', 'kind' => OptionGroupKind::Text, 'excludes_group_id' => $grafika->id,
        ]);
        $field = $tekst->fields()->create(['label' => 'Tekst', 'max_length' => 20, 'required' => true]);

        $product->optionGroups()->attach([$grafika->id, $tekst->id]);

        Livewire::test(AddToCart::class, ['product' => $product->fresh()])
            ->set('config.'.$grafika->id.'.choice', $choice->id)
            ->set('config.'.$tekst->id.'.fields.'.$field->id, 'Zosia')
            ->call('add')
            ->assertSee('nie oba naraz');

        $this->assertSame(0, app(CartService::class)->count($shop->id));
    }

    /**
     * NA KAFLU formularza nie ma — w siatce nie ma na niego miejsca. Produkt
     * personalizowany dostaje przycisk prowadzący na kartę; bez tego kliknięcie
     * „Do koszyka" nie robiłoby nic i wyglądało jak zepsuty przycisk.
     */
    public function test_a_tile_sends_the_buyer_to_the_product_card(): void
    {
        [, $product] = $this->magnesZImieniem();

        Livewire::test(AddToCart::class, ['product' => $product, 'withOptions' => false])
            ->assertSee('Wybierz opcje')
            ->assertDontSee('Nadruk imienia')
            ->assertSee($product->storefrontPath(), false);
    }

    public function test_a_plain_product_tile_still_adds_straight_to_the_cart(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false,
        ]);

        Livewire::test(AddToCart::class, ['product' => $product, 'withOptions' => false])
            ->assertSee('Do koszyka')
            ->assertDontSee('Wybierz opcje')
            ->call('add')
            ->assertHasNoErrors();

        $this->assertSame(1, app(CartService::class)->count($shop->id));
    }

    /**
     * Wycofanie grafiki w trakcie wypełniania formularza. Kupujący ma usłyszeć,
     * co się stało, zamiast klikać przycisk, który przestał działać.
     */
    public function test_a_choice_withdrawn_mid_form_is_explained(): void
    {
        $shop = Shop::factory()->sellable()->create();
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true, 'track_stock' => false,
        ]);

        $group = $shop->optionGroups()->create(['name' => 'Grawer', 'kind' => OptionGroupKind::Choice]);
        $choice = $group->choices()->create(['label' => 'Kotwica', 'surcharge_gross' => 20.00]);
        $product->optionGroups()->attach($group);

        $component = Livewire::test(AddToCart::class, ['product' => $product->fresh()])
            ->set('config.'.$group->id.'.choice', $choice->id);

        $choice->update(['is_active' => false]);

        $component->call('add')->assertHasErrors('config.'.$group->id.'.choice');

        $this->assertSame(0, app(CartService::class)->count($shop->id));
    }
}
