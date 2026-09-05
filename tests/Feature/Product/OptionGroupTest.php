<?php

namespace Tests\Feature\Product;

use App\Enums\OptionGroupKind;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Silnik opcji produktu — fundament personalizacji (Etap 2, krok 1).
 *
 * PISANY POD KUBEK Z IMIENIEM, NIE POD MAGNES Z LOGO MARATONU. Pierwszym
 * odbiorcą jest Magellan Bay, ale to część generyczna, sprzedawalna kolejnym
 * klientom — dlatego testy mówią o grupach, polach i pozycjach biblioteki,
 * a nie o awersie, rewersie i grawerze.
 *
 * Ten krok jest w całości ADDYTYWNY: nowe tabele, zero zmian w koszyku
 * i w pozycjach zamówienia. Ścieżka pieniędzy przychodzi w krokach 2 i 3.
 */
class OptionGroupTest extends TestCase
{
    use RefreshDatabase;

    private function formatka(Shop $shop): OptionGroup
    {
        $group = $shop->optionGroups()->create([
            'name' => 'Nadruk na froncie',
            'kind' => OptionGroupKind::Text,
            'hint' => 'Wpisz tekst, który nadrukujemy.',
            'surcharge_gross' => 12.00,
        ]);

        $group->fields()->create(['label' => 'Imię', 'max_length' => 12, 'position' => 0]);
        $group->fields()->create(['label' => 'Data', 'max_length' => 10, 'position' => 1, 'required' => false]);

        return $group;
    }

    private function biblioteka(Shop $shop): OptionGroup
    {
        $group = $shop->optionGroups()->create([
            'name' => 'Grawer',
            'kind' => OptionGroupKind::Choice,
        ]);

        $group->choices()->create(['label' => 'Kotwica', 'surcharge_gross' => 20.00, 'position' => 0]);
        $group->choices()->create(['label' => 'Kompas', 'surcharge_gross' => 20.00, 'position' => 1]);

        return $group;
    }

    public function test_a_text_group_carries_its_fields_in_order(): void
    {
        $shop = Shop::factory()->create();
        $group = $this->formatka($shop);

        $this->assertTrue($group->isText());
        $this->assertFalse($group->isChoice());

        $labels = $group->fields->pluck('label')->all();
        $this->assertSame(['Imię', 'Data'], $labels);
    }

    /**
     * Limit znaków wynika z fizyki produktu — na magnes 70 × 50 mm wchodzi tyle
     * liter, ile wchodzi. Domyślna wartość ma być rozsądna, ale to sprzedawca
     * zna swój towar i musi móc ją zacieśnić.
     */
    public function test_fields_keep_their_character_limits(): void
    {
        $shop = Shop::factory()->create();
        $group = $this->formatka($shop);

        $this->assertSame(12, $group->fields->firstWhere('label', 'Imię')->max_length);
        $this->assertSame(10, $group->fields->firstWhere('label', 'Data')->max_length);
    }

    public function test_a_choice_group_carries_its_library(): void
    {
        $shop = Shop::factory()->create();
        $group = $this->biblioteka($shop);

        $this->assertTrue($group->isChoice());
        $this->assertCount(2, $group->choices);
        $this->assertSame('20.00', $group->choices->first()->surcharge_gross);
    }

    /**
     * Wycofanej pozycji NIE KASUJEMY, tylko gasimy. Skasowanie unieważniłoby
     * historyczne zamówienia, w których ktoś ją wybrał — a arkusz produkcyjny,
     * reklamacja i zwrot muszą wiedzieć, co dokładnie zamówiono.
     */
    public function test_a_withdrawn_choice_disappears_from_selection_but_stays_in_the_database(): void
    {
        $shop = Shop::factory()->create();
        $group = $this->biblioteka($shop);

        $group->choices()->where('label', 'Kotwica')->update(['is_active' => false]);

        $this->assertCount(2, $group->choices()->get());
        $this->assertCount(1, $group->choices()->selectable()->get());
        $this->assertSame('Kompas', $group->choices()->selectable()->first()->label);
    }

    /**
     * Sedno oszczędności: grupę definiuje się RAZ i przypina do wielu produktów.
     * Zmiana limitu znaków poprawia wtedy sto kart naraz, a nie sto razy jedną.
     */
    public function test_one_group_serves_many_products(): void
    {
        $shop = Shop::factory()->create();
        $group = $this->formatka($shop);

        $magnesy = Product::factory()->count(3)->for($shop)->create();
        foreach ($magnesy as $product) {
            $product->optionGroups()->attach($group);
        }

        $this->assertSame(3, $group->products()->count());

        $group->fields()->where('label', 'Imię')->update(['max_length' => 8]);

        foreach ($magnesy as $product) {
            $this->assertSame(
                8,
                $product->optionGroups()->first()->fields->firstWhere('label', 'Imię')->max_length
            );
        }
    }

    /**
     * Kolejność grup idzie z `position` GRUPY, nie z pivota: ten sam „Nadruk"
     * ma stać w tym samym miejscu na każdej karcie produktu, bo kupujący uczy
     * się układu, a nie zapamiętuje go osobno dla każdego magnesu.
     */
    public function test_groups_appear_in_the_same_order_on_every_product(): void
    {
        $shop = Shop::factory()->create();

        $grawer = $this->biblioteka($shop);
        $grawer->update(['position' => 2]);

        $nadruk = $this->formatka($shop);
        $nadruk->update(['position' => 1]);

        $product = Product::factory()->for($shop)->create();
        // Przypinamy w ODWROTNEJ kolejności, niż mają się wyświetlić.
        $product->optionGroups()->attach([$grawer->id, $nadruk->id]);

        $this->assertSame(
            ['Nadruk na froncie', 'Grawer'],
            $product->optionGroups()->pluck('name')->all()
        );
    }

    /**
     * Wykluczanie grup bierze się z realnego wymagania: grawer to grafika ALBO
     * tekst, nigdy oba. Zamiast wpisywać ten przypadek na sztywno, grupy
     * wskazują, że się wykluczają — ta sama mechanika obsłuży „nadruk albo haft".
     */
    public function test_groups_can_exclude_each_other(): void
    {
        $shop = Shop::factory()->create();
        $grafika = $this->biblioteka($shop);
        $tekst = $this->formatka($shop);

        $tekst->update(['excludes_group_id' => $grafika->id]);

        $this->assertTrue($grafika->is($tekst->fresh()->excludes));
    }

    /**
     * Skasowanie jednej grupy nie ma prawa zabrać drugiej — ma tylko znieść
     * wykluczenie. Inaczej usunięcie „grawera graficznego" zabrałoby przy okazji
     * „grawer tekstowy", którego nikt nie kasował.
     */
    public function test_deleting_a_group_only_lifts_the_exclusion(): void
    {
        $shop = Shop::factory()->create();
        $grafika = $this->biblioteka($shop);
        $tekst = $this->formatka($shop);
        $tekst->update(['excludes_group_id' => $grafika->id]);

        $grafika->delete();

        $this->assertNotNull($tekst->fresh());
        $this->assertNull($tekst->fresh()->excludes_group_id);
    }

    /**
     * Grupa bez pól albo bez ani jednej aktywnej pozycji jest pustym pytaniem —
     * w kasie wyglądałaby jak usterka, a przy `required` zablokowałaby zakup.
     */
    public function test_an_empty_group_is_not_ready_to_be_shown(): void
    {
        $shop = Shop::factory()->create();

        $pusta = $shop->optionGroups()->create(['name' => 'Nic', 'kind' => OptionGroupKind::Text]);
        $this->assertFalse($pusta->isReady());
        $this->assertTrue($this->formatka($shop)->isReady());

        $biblioteka = $this->biblioteka($shop);
        $this->assertTrue($biblioteka->isReady());

        $biblioteka->choices()->update(['is_active' => false]);
        $this->assertFalse($biblioteka->fresh()->isReady());
    }

    /**
     * Produkt bez grup to zwykły produkt i ma się tak zachowywać — sklep
     * Magellana sprzedaje jedno i drugie z tego samego katalogu.
     */
    public function test_a_product_without_groups_is_not_personalised(): void
    {
        $shop = Shop::factory()->create();
        $zwykly = Product::factory()->for($shop)->create();
        $personalizowany = Product::factory()->for($shop)->create();

        $personalizowany->optionGroups()->attach($this->formatka($shop));

        $this->assertFalse($zwykly->isPersonalised());
        $this->assertTrue($personalizowany->fresh()->isPersonalised());
    }

    /**
     * Grupy należą do SKLEPU, nie do platformy — biblioteka jednego sprzedawcy
     * nie ma prawa wyciec do drugiego. W sklepie dedykowanym sklep jest jeden,
     * ale reguła musi trzymać także w Kramio.
     */
    public function test_groups_are_scoped_to_their_shop(): void
    {
        $moj = Shop::factory()->create();
        $cudzy = Shop::factory()->create();

        $this->formatka($moj);
        $this->biblioteka($cudzy);

        $this->assertSame(1, $moj->optionGroups()->count());
        $this->assertSame('Nadruk na froncie', $moj->optionGroups()->first()->name);
    }

    /**
     * Skasowanie sklepu zabiera jego bibliotekę — inaczej po usunięciu zostałyby
     * osierocone grupy, których nikt nigdy nie zobaczy ani nie skasuje.
     */
    public function test_removing_a_shop_takes_its_library_with_it(): void
    {
        $shop = Shop::factory()->create();
        $group = $this->formatka($shop);

        $shop->delete();

        $this->assertSame(0, OptionGroup::query()->whereKey($group->id)->count());
    }
}
