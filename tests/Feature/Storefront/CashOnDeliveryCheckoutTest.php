<?php

namespace Tests\Feature\Storefront;

use App\Enums\DeliveryMethod;
use App\Enums\IntegrationType;
use App\Enums\PaymentMethod;
use App\Livewire\Checkout;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kasa przy dostawie za pobraniem. Trzy rzeczy, które muszą być prawdą:
 * klient nie wybiera płatności (wynika z dostawy), zamówienie dostaje metodę
 * `cash_on_delivery`, a kwota ponad limit InPostu jest odrzucana W KASIE — nie
 * dopiero przy nadawaniu, gdy zamówienie już istnieje.
 */
class CashOnDeliveryCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function shopWithCod(array $attributes = []): Shop
    {
        $shop = Shop::factory()->withCourierShipping()->create($attributes + [
            'street' => 'Kwiatowa', 'building_number' => '5', 'postal_code' => '00-001',
            'city' => 'Warszawa', 'province' => 'mazowieckie',
            'courier_cod_enabled' => true, 'courier_cod_cost' => 19.99,
            'parcel_locker_cod_enabled' => true, 'parcel_locker_cod_cost' => 16.99,
        ]);

        $shop->integrations()->create([
            'type' => IntegrationType::Shipping,
            'enabled' => true,
            'config' => ['token' => 'TAJNY-TOKEN', 'organization_id' => '6700', 'environment' => 'sandbox'],
        ]);

        return $shop->fresh();
    }

    private function cartProduct(Shop $shop, float $price = 40.00): Product
    {
        $product = Product::factory()->create([
            'shop_id' => $shop->id, 'is_active' => true,
            'track_stock' => false, 'stock' => null, 'price_gross' => $price,
        ]);
        app(CartService::class)->add($product, 1);

        return $product;
    }

    private function fill(Testable $component): Testable
    {
        return $component
            ->set('buyer_name', 'Jan')
            ->set('buyer_surname', 'Kowalski')
            ->set('buyer_email', 'jan@example.com')
            ->set('buyer_phone', '600100200')
            ->set('accept_terms', true)
            ->set('accept_privacy', true);
    }

    public function test_kasa_pokazuje_obie_metody_pobraniowe(): void
    {
        $shop = $this->shopWithCod();
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->assertSee('Kurier za pobraniem')
            ->assertSee('Paczkomat InPost za pobraniem');
    }

    public function test_przy_pobraniu_klient_nie_wybiera_platnosci(): void
    {
        $shop = $this->shopWithCod(['bank_transfer_enabled' => true, 'bank_account_number' => '12345678901234567890123456']);
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('delivery_method', DeliveryMethod::ParcelLockerCod->value)
            ->assertSee('Płatność za pobraniem')
            // Paczkomat gotówki nie przyjmie — obietnica w kasie musi być prawdziwa.
            ->assertSee('Paczkomat nie przyjmuje gotówki')
            ->assertDontSee('Przelew na konto')
            ->assertSet('payment_method', PaymentMethod::CashOnDelivery->value);
    }

    public function test_kurier_pobraniowy_mowi_o_gotowce_lub_karcie(): void
    {
        $shop = $this->shopWithCod();
        $this->cartProduct($shop);

        Livewire::test(Checkout::class, ['shopId' => $shop->id])
            ->set('delivery_method', DeliveryMethod::CourierCod->value)
            ->assertSee('gotówką lub kartą');
    }

    public function test_zamowienie_za_pobraniem_dostaje_wlasciwa_metode_platnosci(): void
    {
        $shop = $this->shopWithCod();
        $this->cartProduct($shop);

        $this->fill(Livewire::test(Checkout::class, ['shopId' => $shop->id]))
            ->set('delivery_method', DeliveryMethod::ParcelLockerCod->value)
            ->set('parcel_locker_code', 'KRA01A')
            ->call('place');

        $order = Order::query()->latest('id')->first();

        $this->assertNotNull($order);
        $this->assertSame(DeliveryMethod::ParcelLockerCod, $order->delivery_method);
        $this->assertSame(PaymentMethod::CashOnDelivery, $order->payment_method);
        // Koszt dostawy z WŁASNEGO cennika pobrania, nie ze zwykłego paczkomatu.
        $this->assertSame('16.99', $order->delivery_cost);
    }

    public function test_kwota_ponad_limit_inpostu_jest_odrzucana_w_kasie(): void
    {
        $shop = $this->shopWithCod();
        // Limit paczkomatu to 5 000 zł — koszyk celowo ponad próg.
        $this->cartProduct($shop, price: 5100.00);

        $this->fill(Livewire::test(Checkout::class, ['shopId' => $shop->id]))
            ->set('delivery_method', DeliveryMethod::ParcelLockerCod->value)
            ->set('parcel_locker_code', 'KRA01A')
            ->call('place')
            ->assertHasErrors('delivery_method');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_kurier_ma_wyzszy_limit_niz_paczkomat(): void
    {
        $shop = $this->shopWithCod();
        // 5 100 zł przechodzi kurierem (limit 15 000), choć paczkomatem nie.
        $this->cartProduct($shop, price: 5100.00);

        $this->fill(Livewire::test(Checkout::class, ['shopId' => $shop->id]))
            ->set('delivery_method', DeliveryMethod::CourierCod->value)
            ->set('ship_street', 'Cybernetyki')
            ->set('ship_building_number', '10')
            ->set('ship_postal_code', '02-677')
            ->set('ship_city', 'Warszawa')
            ->call('place')
            ->assertHasNoErrors();

        $this->assertSame(1, Order::query()->count());
    }
}
