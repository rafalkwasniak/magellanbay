<?php

namespace Tests\Feature;

use App\Livewire\AddToCart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Wstrzymanie sprzedaży całej serii (Etap 2, krok E).
 *
 * Wprost ze specyfikacji: „zablokowanie sprzedaży całej serii jednym
 * przyciskiem, z komunikatem o terminie wznowienia".
 *
 * Wstrzymanie ma znaczyć NIE SPRZEDAJEMY, a nie NIE POKAZUJEMY — produkty
 * zostają widoczne (adres, opis, pozycja w wyszukiwarce), znika sama możliwość
 * kupienia. I znika naprawdę: także dla żądania wysłanego z pominięciem
 * przycisku i dla pozycji, która leżała już w koszyku.
 */
class SalesSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->consented()->create();
        $this->shop = Shop::factory()->sellable()->create(['owner_id' => $this->owner->id]);
    }

    private function seria(string $name = 'Kamień'): Category
    {
        return $this->shop->categories()->create([
            'axis' => 'kind',
            'name' => $name,
            'slug' => Str::slug($name),
        ]);
    }

    private function produkt(Category $seria, string $name = 'Magnes kamienny'): Product
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'name' => $name,
            'is_active' => true,
        ]);

        $product->categories()->attach($seria->id);

        return $product;
    }

    // --- Reguła --------------------------------------------------------------

    public function test_product_of_a_suspended_series_cannot_be_sold(): void
    {
        $seria = $this->seria();
        $product = $this->produkt($seria);

        $this->assertFalse($product->isSaleSuspended());

        $seria->update(['sales_suspended_at' => now()]);

        $this->assertTrue($product->fresh()->isSaleSuspended());
    }

    /**
     * WZNOWIENIE DZIEJE SIĘ SAMO. Data z przeszłości znaczy, że sprzedaż już
     * wróciła — nie ma zadania w tle, które musiałoby ją odwiesić, bo tu crona
     * nie ma z rozmysłu, a sklep, który nie wznowił sprzedaży, traci pieniądze
     * w ciszy.
     */
    public function test_sales_resume_by_themselves_once_the_date_passes(): void
    {
        $seria = $this->seria();
        $product = $this->produkt($seria);

        $seria->update(['sales_suspended_at' => now(), 'sales_resume_on' => Carbon::tomorrow()]);
        $this->assertTrue($product->fresh()->isSaleSuspended());

        // Nie ruszamy bazy — mija czas.
        $this->travelTo(Carbon::tomorrow()->addDay());

        $this->assertFalse($product->fresh()->isSaleSuspended());
    }

    public function test_suspension_without_a_date_lasts_until_resumed_by_hand(): void
    {
        $seria = $this->seria();
        $product = $this->produkt($seria);
        $seria->update(['sales_suspended_at' => now()]);

        $this->travelTo(now()->addYear());

        $this->assertTrue($product->fresh()->isSaleSuspended());
    }

    /**
     * Wstrzymanie osi wielokrotnej nie ma dobrej odpowiedzi: magnes stojący
     * w „Biegach" i w „UNESCO" byłby jednocześnie wstrzymany i dostępny.
     */
    public function test_suspending_a_multiple_choice_axis_has_no_effect(): void
    {
        $tematyka = $this->shop->categories()->create([
            'axis' => 'theme', 'name' => 'Biegi', 'slug' => 'biegi',
            'sales_suspended_at' => now(),
        ]);

        $product = Product::factory()->create(['shop_id' => $this->shop->id, 'is_active' => true]);
        $product->categories()->attach($tematyka->id);

        $this->assertFalse($product->fresh()->isSaleSuspended());
    }

    public function test_default_message_names_the_resumption_date(): void
    {
        $seria = $this->seria();
        $seria->update(['sales_suspended_at' => now(), 'sales_resume_on' => Carbon::parse('2026-12-24')]);

        $this->assertStringContainsString('24.12.2026', $seria->suspensionMessage());
    }

    public function test_sellers_own_message_wins(): void
    {
        $seria = $this->seria();
        $seria->update([
            'sales_suspended_at' => now(),
            'suspension_note' => 'Czekamy na dostawę kamienia z Włoch.',
        ]);

        $this->assertSame('Czekamy na dostawę kamienia z Włoch.', $seria->suspensionMessage());
    }

    // --- Bramka w koszyku ----------------------------------------------------

    public function test_suspended_product_does_not_enter_the_cart(): void
    {
        $seria = $this->seria();
        $product = $this->produkt($seria);
        $seria->update(['sales_suspended_at' => now()]);

        app(CartService::class)->add($product->fresh(), 1);

        $this->assertSame(0.0, app(CartService::class)->quantityOfProduct($this->shop->id, $product->id));
    }

    /**
     * Sprzedawca mógł wstrzymać serię, gdy pozycja już leżała w koszyku.
     * Zamówienie na wstrzymany towar byłoby zamówieniem, którego nie da się
     * zrealizować — mówimy to teraz, a nie po pobraniu pieniędzy.
     */
    public function test_suspension_removes_what_is_already_in_the_cart(): void
    {
        $seria = $this->seria();
        $product = $this->produkt($seria);

        $cart = app(CartService::class);
        $cart->add($product, 1);
        $this->assertSame(1.0, $cart->quantityOfProduct($this->shop->id, $product->id));

        $seria->update(['sales_suspended_at' => now()]);

        $result = $cart->reconcile($this->shop->id);

        $this->assertCount(0, $result['lines']);
        $this->assertStringContainsString('wstrzymana', implode(' ', $result['notices']));
    }

    public function test_livewire_button_refuses_to_add_a_suspended_product(): void
    {
        $seria = $this->seria();
        $product = $this->produkt($seria);
        $seria->update(['sales_suspended_at' => now()]);

        Livewire::test(AddToCart::class, ['product' => $product->fresh()])
            ->call('add');

        $this->assertSame(0.0, app(CartService::class)->quantityOfProduct($this->shop->id, $product->id));
    }

    // --- Co widzi kupujący ---------------------------------------------------

    public function test_product_page_stays_up_and_explains_itself(): void
    {
        $seria = $this->seria();
        $product = $this->produkt($seria);
        $seria->update([
            'sales_suspended_at' => now(),
            'suspension_note' => 'Czekamy na dostawę kamienia.',
        ]);

        $this->get('https://'.$this->shop->host().$product->storefrontPath())
            ->assertOk()
            ->assertSee('Magnes kamienny')
            ->assertSee('Czekamy na dostawę kamienia.')
            ->assertDontSee('Do koszyka');
    }

    public function test_category_page_shows_the_notice(): void
    {
        $seria = $this->seria();
        $this->produkt($seria);
        $seria->update(['sales_suspended_at' => now(), 'suspension_note' => 'Wracamy w marcu.']);

        $this->get('https://'.$this->shop->host().'/rodzaj/kamien')
            ->assertOk()
            ->assertSee('Sprzedaż wstrzymana')
            ->assertSee('Wracamy w marcu.');
    }

    // --- Panel ---------------------------------------------------------------

    public function test_one_click_suspends_and_saves_the_message(): void
    {
        $seria = $this->seria();

        $this->actingAs($this->owner)
            ->post(route('seller.categories.suspension', ['rodzaj', $seria]), [
                'suspension' => [$seria->id => [
                    'sales_resume_on' => Carbon::tomorrow()->format('Y-m-d'),
                    'suspension_note' => 'Czekamy na dostawę.',
                ]],
            ])
            ->assertSessionHasNoErrors();

        $seria->refresh();
        $this->assertTrue($seria->salesSuspended());
        $this->assertSame('Czekamy na dostawę.', $seria->suspension_note);
    }

    public function test_the_same_button_resumes_sales(): void
    {
        $seria = $this->seria();
        $seria->update(['sales_suspended_at' => now()]);

        $this->actingAs($this->owner)
            ->post(route('seller.categories.suspension', ['rodzaj', $seria]), [])
            ->assertSessionHasNoErrors();

        $this->assertFalse($seria->fresh()->salesSuspended());
    }

    /**
     * Formularz niesie CAŁĄ oś, więc pola są indeksowane identyfikatorem.
     * Przy wspólnej nazwie przeglądarka wysłałaby wartości ze wszystkich
     * wierszy i wygrałby ostatni — sprzedawca wstrzymywałby jedną serię,
     * wpisując datę z zupełnie innej.
     */
    public function test_data_from_another_row_does_not_leak_in(): void
    {
        $kamien = $this->seria('Kamień');
        $metal = $this->seria('Metal');

        $this->actingAs($this->owner)
            ->post(route('seller.categories.suspension', ['rodzaj', $kamien]), [
                'suspension' => [
                    $kamien->id => ['suspension_note' => 'Brak kamienia.'],
                    $metal->id => ['suspension_note' => 'Brak metalu.'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Brak kamienia.', $kamien->fresh()->suspension_note);
        $this->assertFalse($metal->fresh()->salesSuspended());
    }

    public function test_a_resumption_date_in_the_past_is_rejected(): void
    {
        $seria = $this->seria();

        $this->actingAs($this->owner)
            ->from(route('seller.categories.index', 'rodzaj'))
            ->post(route('seller.categories.suspension', ['rodzaj', $seria]), [
                'suspension' => [$seria->id => ['sales_resume_on' => now()->subDay()->format('Y-m-d')]],
            ])
            ->assertSessionHasErrors('suspension.'.$seria->id.'.sales_resume_on');

        $this->assertFalse($seria->fresh()->salesSuspended());
    }

    public function test_suspension_is_not_offered_on_a_multiple_choice_axis(): void
    {
        $tematyka = $this->shop->categories()->create(['axis' => 'theme', 'name' => 'Biegi', 'slug' => 'biegi']);

        $this->actingAs($this->owner)
            ->post(route('seller.categories.suspension', ['tematyka', $tematyka]), [])
            ->assertNotFound();
    }

    public function test_another_shops_series_cannot_be_suspended(): void
    {
        $cudza = Shop::factory()->create()->categories()->create([
            'axis' => 'kind', 'name' => 'Cudza seria', 'slug' => 'cudza-seria',
        ]);

        $this->actingAs($this->owner)
            ->post(route('seller.categories.suspension', ['rodzaj', $cudza]), [])
            ->assertNotFound();
    }

    public function test_panel_shows_the_suspension_control_only_where_it_makes_sense(): void
    {
        $this->seria();
        $this->shop->categories()->create(['axis' => 'theme', 'name' => 'Biegi', 'slug' => 'biegi']);

        $this->actingAs($this->owner)
            ->get(route('seller.categories.index', 'rodzaj'))
            ->assertOk()
            ->assertSee('Wstrzymaj sprzedaż tej serii');

        $this->actingAs($this->owner)
            ->get(route('seller.categories.index', 'tematyka'))
            ->assertOk()
            ->assertDontSee('Wstrzymaj sprzedaż tej serii');
    }
}
