<?php

namespace Database\Factories;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountCode>
 */
class DiscountCodeFactory extends Factory
{
    /**
     * Domyślnie: 10% na cały koszyk, bezterminowo, bez limitu — najprostszy
     * możliwy kod. Każde ograniczenie dokładamy jawnie stanem, żeby w teście
     * było widać, co się bada.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'code' => DiscountCode::randomCode(),
            'type' => DiscountType::Percent,
            'value' => 10,
            'scope' => DiscountScope::Cart,
            'is_active' => true,
        ];
    }

    /**
     * Rabat kwotowy (w złotówkach) zamiast procentowego.
     */
    public function amount(float $value = 20): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DiscountType::Amount,
            'value' => $value,
        ]);
    }

    /**
     * Kod na darmową wysyłkę — bez wartości, zawsze na cały koszyk.
     */
    public function freeShipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DiscountType::FreeShipping,
            'value' => null,
            'scope' => DiscountScope::Cart,
            'product_id' => null,
        ]);
    }

    /**
     * Kod działający wyłącznie na jeden produkt.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'shop_id' => $product->shop_id,
            'scope' => DiscountScope::Product,
            'product_id' => $product->id,
        ]);
    }

    /**
     * Kod imienny — do rekompensaty dla konkretnego klienta.
     */
    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'shop_id' => $customer->shop_id,
            'customer_id' => $customer->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Kod, którego okno ważności już się zamknęło.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }

    /**
     * Kod, który jeszcze nie wystartował.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    /**
     * Limit użyć (1 = kod jednorazowy).
     */
    public function limitedTo(int $uses): static
    {
        return $this->state(fn (array $attributes) => [
            'max_uses' => $uses,
        ]);
    }

    /**
     * Próg minimalnej wartości produktów w koszyku.
     */
    public function minimum(float $itemsTotal): static
    {
        return $this->state(fn (array $attributes) => [
            'min_items_total' => $itemsTotal,
        ]);
    }
}
