<?php

namespace Tests\Feature\Seller;

use App\Livewire\ConfirmAction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Potwierdzenia akcji w kafelku produktu. Sedno nie jest kosmetyczne: okienko
 * przeglądarki mówiło „Usunąć produkt X?" i tyle, a obie akcje robią rzeczy,
 * których po kliknięciu nie widać — usunięcie bywa ukryciem albo skasowaniem
 * ze zdjęciami, a kopiowanie tworzy drugi, ukryty produkt pod własnym adresem.
 */
class ProductTileConfirmationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Shop}
     */
    private function sellerWithShop(): array
    {
        $seller = User::factory()->consented()->create();

        return [$seller, Shop::factory()->create(['owner_id' => $seller->id])];
    }

    public function test_list_asks_in_the_tile_instead_of_a_browser_alert(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Product::factory()->create(['shop_id' => $shop->id]);

        $this->actingAs($seller)
            ->get(route('seller.products.index'))
            ->assertOk()
            ->assertDontSee('confirm(', false)
            ->assertSee('wire:click="ask"', false);
    }

    /**
     * Obie akcje pytają. Kopiowanie też, bo jego przycisk stoi tuż obok
     * „Edytuj", a pomyłka kończy się drugim produktem, o którym sprzedawca
     * się nie dowie.
     */
    public function test_both_copying_and_deleting_are_confirmed(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        $product = Product::factory()->create(['shop_id' => $shop->id]);

        $html = $this->actingAs($seller)->get(route('seller.products.index'))->assertOk()->getContent();

        // Kopiowanie nie jest już gołym formularzem wysyłanym jednym kliknięciem.
        $this->assertStringNotContainsString(
            '<form method="POST" action="'.route('seller.products.duplicate', $product).'"',
            $html
        );
        $this->assertSame(2, substr_count($html, 'wire:click="ask"'));
        $this->assertStringContainsString('Kopiuj', $html);
        // Podpis przycisku potwierdzenia dociera do komponentu (Livewire zamienia
        // `confirm-label` na `confirmLabel`) — inaczej kopia pytałaby „Tak, usuń".
        $this->assertStringContainsString('Tak, kopiuj', $html);
    }

    public function test_overlay_appears_only_after_asking_and_goes_away_on_cancel(): void
    {
        Livewire::test(ConfirmAction::class, [
            'action' => '/sprzedawca/produkty/1/usun',
            'title' => 'Usunąć magnes Mauritius?',
            'lines' => ['Tej operacji nie da się cofnąć.'],
        ])
            ->assertDontSee('wire:click="cancel"', false)
            ->call('ask')
            ->assertSee('Usunąć magnes Mauritius?')
            ->assertSee('Tej operacji nie da się cofnąć.')
            ->assertSee('wire:click="cancel"', false)
            ->assertSee('/sprzedawca/produkty/1/usun', false)
            ->call('cancel')
            ->assertDontSee('wire:click="cancel"', false);
    }

    /**
     * Wydźwięk niesie znaczenie: różowy dla tego, co niszczy, bursztynowy dla
     * tego, co tylko wymaga uwagi. Klasy muszą być pełnymi napisami, bo build
     * Tailwinda nie znajdzie nazw sklejanych w locie.
     */
    public function test_tone_separates_a_destructive_action_from_a_careful_one(): void
    {
        Livewire::test(ConfirmAction::class, ['action' => '/x', 'title' => 'Usunąć?'])
            ->call('ask')
            ->assertSee('bg-rose-50', false)
            ->assertDontSee('bg-amber-50', false);

        Livewire::test(ConfirmAction::class, ['action' => '/x', 'title' => 'Skopiować?', 'tone' => 'amber'])
            ->call('ask')
            ->assertSee('bg-amber-50', false)
            ->assertDontSee('bg-rose-50', false);
    }

    /**
     * Produkt, który był zamawiany, zostaje ukryty dla historii; nigdy
     * niezamawiany znika razem ze zdjęciami. Potwierdzenie musi mówić, która
     * z tych dwóch rzeczy nastąpi — to jest cały powód tej nakładki.
     */
    public function test_delete_warning_matches_what_deletion_really_does(): void
    {
        [, $shop] = $this->sellerWithShop();
        $nietkniety = Product::factory()->create(['shop_id' => $shop->id]);
        $zamawiany = Product::factory()->create(['shop_id' => $shop->id]);

        OrderItem::factory()->create([
            'order_id' => Order::factory()->for($shop)->create()->id,
            'product_id' => $zamawiany->id,
        ]);

        $this->assertStringContainsString('zniknie na zawsze', $nietkniety->deletionConsequences()[0]);
        $this->assertStringContainsString('zostanie ukryty', $zamawiany->deletionConsequences()[0]);
    }

    /**
     * Ostrzeżenie przy kopiowaniu ma odpowiadać na pytanie „co właściwie zaraz
     * powstanie": drugi produkt, z tą samą zawartością, pod własnym adresem.
     */
    public function test_copy_warning_says_a_second_product_appears_under_its_own_address(): void
    {
        [, $shop] = $this->sellerWithShop();
        $lines = implode(' ', Product::factory()->create(['shop_id' => $shop->id])->duplicationConsequences());

        $this->assertStringContainsString('drugi produkt', $lines);
        $this->assertStringContainsString('oryginał zostaje bez zmian', $lines);
        $this->assertStringContainsString('własny adres', $lines);
        $this->assertStringContainsString('ukryta', $lines);
    }

    /**
     * Ostrzeżenie przy usuwaniu zależy od tego, czy produkt był zamawiany —
     * lista musi to wiedzieć bez pytania na każdy kafelek, bo kafelków bywa
     * kilkadziesiąt.
     */
    public function test_list_loads_the_order_flag_with_the_products(): void
    {
        [$seller, $shop] = $this->sellerWithShop();
        Product::factory()->count(3)->create(['shop_id' => $shop->id]);

        $response = $this->actingAs($seller)->get(route('seller.products.index'))->assertOk();

        foreach ($response->viewData('products') as $product) {
            $this->assertNotNull($product->getAttribute('order_items_exists'));
        }
    }
}
