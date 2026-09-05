<?php

namespace Tests\Feature\Seller;

use App\Enums\OptionGroupKind;
use App\Models\OptionGroup;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Zawartość grup opcji: pola formatki i biblioteka grafik (Etap 2, krok C3).
 *
 * Zapis idzie JEDNYM żądaniem dla całej listy — sprzedawca układa formatkę jak
 * listę i chce zobaczyć efekt raz, a nie po każdym polu z osobna. Ostatni wiersz
 * formularza jest zawsze pusty i to nim dodaje się kolejną pozycję; zostawiony
 * pusty jest po prostu pomijany.
 */
class OptionContentPanelTest extends TestCase
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

    private function formatka(Shop $shop): OptionGroup
    {
        return $shop->optionGroups()->create(['name' => 'Nadruk', 'kind' => OptionGroupKind::Text]);
    }

    private function biblioteka(Shop $shop): OptionGroup
    {
        return $shop->optionGroups()->create(['name' => 'Grawer', 'kind' => OptionGroupKind::Choice]);
    }

    // --- Pola formatki -----------------------------------------------------

    public function test_a_new_field_is_added_from_the_empty_row(): void
    {
        [$owner, $shop] = $this->sklep();
        $group = $this->formatka($shop);

        $this->actingAs($owner)
            ->from(route('seller.options.edit', $group))
            ->post(route('seller.options.fields', $group), [
                'items' => ['nowe' => ['label' => 'Imię', 'max_length' => '12', 'required' => '1']],
            ])
            // Sukces i błąd walidacji wracają pod TEN SAM adres, więc sam
            // `assertRedirect` niczego nie odróżnia — stąd druga asercja.
            ->assertRedirect(route('seller.options.edit', $group))
            ->assertSessionHasNoErrors();

        $field = $group->fields()->sole();

        $this->assertSame('Imię', $field->label);
        $this->assertSame(12, $field->max_length);
        $this->assertTrue($field->required);
    }

    /**
     * Formularz ma zawsze jeden pusty wiersz na dole. Zostawiony pusty nie jest
     * błędem — jest po prostu niewypełniony.
     */
    public function test_an_empty_row_is_simply_skipped(): void
    {
        [$owner, $shop] = $this->sklep();
        $group = $this->formatka($shop);

        $this->actingAs($owner)
            ->post(route('seller.options.fields', $group), [
                'items' => ['nowe' => ['label' => '', 'max_length' => '30']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $group->fields()->count());
    }

    /**
     * Kolejność bierze się z KOLEJNOŚCI WIERSZY, nie z ręcznie wpisywanej
     * liczby — sprzedawca układa listę, a nie numeruje ją.
     */
    public function test_order_follows_the_rows(): void
    {
        [$owner, $shop] = $this->sklep();
        $group = $this->formatka($shop);
        $imie = $group->fields()->create(['label' => 'Imię', 'max_length' => 12, 'position' => 0]);
        $data = $group->fields()->create(['label' => 'Data', 'max_length' => 10, 'position' => 1]);

        // Wysyłamy w odwrotnej kolejności.
        $this->actingAs($owner)->post(route('seller.options.fields', $group), [
            'items' => [
                $data->id => ['label' => 'Data', 'max_length' => '10'],
                $imie->id => ['label' => 'Imię', 'max_length' => '12'],
            ],
        ]);

        $this->assertSame(['Data', 'Imię'], $group->fields()->get()->pluck('label')->all());
    }

    /**
     * Kasowanie POLA jest bezpieczne: zamówienie niesie migawkę „etykieta →
     * wartość", a do wykonania nadruku potrzebny jest sam tekst, nie
     * identyfikator pola.
     */
    public function test_a_field_can_be_deleted(): void
    {
        [$owner, $shop] = $this->sklep();
        $group = $this->formatka($shop);
        $field = $group->fields()->create(['label' => 'Imię', 'max_length' => 12]);

        $this->actingAs($owner)->post(route('seller.options.fields', $group), [
            'items' => [$field->id => ['label' => 'Imię', 'max_length' => '12', '_delete' => '1']],
        ]);

        $this->assertSame(0, $group->fields()->count());
    }

    public function test_an_absurd_character_limit_is_refused(): void
    {
        [$owner, $shop] = $this->sklep();
        $group = $this->formatka($shop);

        $this->actingAs($owner)
            ->from(route('seller.options.edit', $group))
            ->post(route('seller.options.fields', $group), [
                'items' => ['nowe' => ['label' => 'Opis', 'max_length' => '1200']],
            ])
            ->assertSessionHasErrors('items.nowe.max_length');

        $this->assertSame(0, $group->fields()->count());
    }

    public function test_fields_cannot_be_saved_on_a_library_group(): void
    {
        [$owner, $shop] = $this->sklep();

        $this->actingAs($owner)
            ->post(route('seller.options.fields', $this->biblioteka($shop)), ['items' => []])
            ->assertNotFound();
    }

    // --- Biblioteka --------------------------------------------------------

    public function test_a_choice_is_added_with_a_graphic(): void
    {
        Storage::fake('public');
        [$owner, $shop] = $this->sklep();
        $group = $this->biblioteka($shop);
        $partner = $shop->licensors()->create(['name' => 'Bieg Gdański']);

        $this->actingAs($owner)
            ->from(route('seller.options.edit', $group))
            ->post(route('seller.options.choices', $group), [
                'items' => ['nowa' => [
                    'label' => 'Trasa Biegu',
                    'surcharge_gross' => '3,00',
                    'licence_fee_gross' => '8,00',
                    'licensor_id' => $partner->id,
                    'is_active' => '1',
                    'image' => UploadedFile::fake()->image('trasa.png', 800, 600),
                ]],
            ])
            // Sukces i błąd walidacji wracają pod TEN SAM adres, więc sam
            // `assertRedirect` niczego nie odróżnia — stąd druga asercja.
            ->assertRedirect(route('seller.options.edit', $group))
            ->assertSessionHasNoErrors();

        $choice = $group->choices()->sole();

        $this->assertSame('Trasa Biegu', $choice->label);
        $this->assertSame('3.00', $choice->surcharge_gross);
        $this->assertSame('8.00', $choice->licence_fee_gross);
        $this->assertSame($partner->id, $choice->licensor_id);

        // Zawsze WebP, niezależnie od formatu wejściowego.
        $this->assertStringEndsWith('.webp', $choice->image_path);
        Storage::disk('public')->assertExists($choice->image_path);
    }

    /**
     * Podmiana grafiki kasuje poprzednią — inaczej po roku katalog jest pełen
     * plików, których nic nie pokazuje i nikt nie sprząta.
     */
    public function test_replacing_a_graphic_removes_the_previous_file(): void
    {
        Storage::fake('public');
        [$owner, $shop] = $this->sklep();
        $group = $this->biblioteka($shop);

        $this->actingAs($owner)->post(route('seller.options.choices', $group), [
            'items' => ['nowa' => ['label' => 'Kotwica', 'is_active' => '1', 'image' => UploadedFile::fake()->image('a.png')]],
        ]);

        $stary = $group->choices()->sole()->image_path;

        $this->actingAs($owner)->post(route('seller.options.choices', $group), [
            'items' => [$group->choices()->sole()->id => [
                'label' => 'Kotwica', 'is_active' => '1', 'image' => UploadedFile::fake()->image('b.png'),
            ]],
        ]);

        $nowy = $group->choices()->sole()->fresh()->image_path;

        $this->assertNotSame($stary, $nowy);
        Storage::disk('public')->assertMissing($stary);
        Storage::disk('public')->assertExists($nowy);
    }

    /**
     * Zapis BEZ nowego pliku nie ma prawa zgubić grafiki, którą pozycja już ma.
     * To najłatwiejsza pomyłka w formularzu z uploadem: sprzedawca poprawia
     * kwotę i traci obrazek.
     */
    public function test_saving_without_a_file_keeps_the_existing_graphic(): void
    {
        Storage::fake('public');
        [$owner, $shop] = $this->sklep();
        $group = $this->biblioteka($shop);
        $choice = $group->choices()->create(['label' => 'Kotwica', 'image_path' => 'option-choices/1/x.webp']);

        $this->actingAs($owner)->post(route('seller.options.choices', $group), [
            'items' => [$choice->id => ['label' => 'Kotwica', 'surcharge_gross' => '5,00', 'is_active' => '1']],
        ]);

        $this->assertSame('option-choices/1/x.webp', $choice->fresh()->image_path);
        $this->assertSame('5.00', $choice->fresh()->surcharge_gross);
    }

    /**
     * WYGASZENIE zamiast kasowania. Zamówienie wskazuje grafikę po
     * identyfikatorze, więc skasowany wiersz zabiera ze sobą PLIK do
     * wygrawerowania.
     */
    public function test_a_choice_is_switched_off_not_deleted(): void
    {
        [$owner, $shop] = $this->sklep();
        $group = $this->biblioteka($shop);
        $choice = $group->choices()->create(['label' => 'Kotwica', 'is_active' => true]);

        $this->actingAs($owner)->post(route('seller.options.choices', $group), [
            'items' => [$choice->id => ['label' => 'Kotwica']],   // brak `is_active`
        ]);

        $this->assertSame(1, $group->choices()->count());
        $this->assertFalse($choice->fresh()->is_active);
    }

    public function test_a_partner_from_another_shop_is_refused(): void
    {
        [$owner, $shop] = $this->sklep();
        $group = $this->biblioteka($shop);
        $cudzy = Shop::factory()->create()->licensors()->create(['name' => 'Cudzy']);

        $this->actingAs($owner)
            ->from(route('seller.options.edit', $group))
            ->post(route('seller.options.choices', $group), [
                'items' => ['nowa' => ['label' => 'Kotwica', 'licensor_id' => $cudzy->id]],
            ])
            ->assertSessionHasErrors('items.nowa.licensor_id');

        $this->assertSame(0, $group->choices()->count());
    }

    public function test_a_non_image_file_is_refused(): void
    {
        Storage::fake('public');
        [$owner, $shop] = $this->sklep();
        $group = $this->biblioteka($shop);

        $this->actingAs($owner)
            ->from(route('seller.options.edit', $group))
            ->post(route('seller.options.choices', $group), [
                'items' => ['nowa' => ['label' => 'Kotwica', 'image' => UploadedFile::fake()->create('umowa.pdf', 100)]],
            ])
            ->assertSessionHasErrors('items.nowa.image');

        $this->assertSame(0, $group->choices()->count());
    }

    public function test_choices_cannot_be_saved_on_a_text_group(): void
    {
        [$owner, $shop] = $this->sklep();

        $this->actingAs($owner)
            ->post(route('seller.options.choices', $this->formatka($shop)), ['items' => []])
            ->assertNotFound();
    }

    public function test_content_of_another_shop_is_not_reachable(): void
    {
        [$owner] = $this->sklep();
        $cudza = Shop::factory()->create()->optionGroups()->create(['name' => 'Cudza', 'kind' => OptionGroupKind::Text]);

        $this->actingAs($owner)->post(route('seller.options.fields', $cudza), ['items' => []])->assertNotFound();
    }

    // --- Ekran -------------------------------------------------------------

    public function test_the_edit_screen_shows_the_right_editor(): void
    {
        [$owner, $shop] = $this->sklep();

        $this->actingAs($owner)
            ->get(route('seller.options.edit', $this->formatka($shop)))
            ->assertOk()
            ->assertSee('Pola do wypełnienia')
            ->assertDontSee('Biblioteka</h2>', false);

        $this->actingAs($owner)
            ->get(route('seller.options.edit', $this->biblioteka($shop)))
            ->assertOk()
            ->assertSee('Biblioteka')
            ->assertSee('Opłata licencyjna to co innego niż dopłata.');
    }
}
