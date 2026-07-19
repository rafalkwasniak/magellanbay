<?php

namespace Database\Factories;

use App\Enums\ShopStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();
        $package = config('shop.default_package');

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'status' => ShopStatus::Draft,
            // Snapshot pakietu domyślnego — jak w produkcji (Shop::assignPackage).
            'package' => $package,
            'entitlements' => config("shop.packages.{$package}.entitlements"),
            'price_yearly' => config("shop.packages.{$package}.price_yearly"),
        ];
    }

    /**
     * Sklep aktywny (opublikowany).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShopStatus::Active,
        ]);
    }

    /**
     * Sklep z danym pakietem (snapshot uprawnień z configu) — do testów tierów.
     */
    public function package(string $slug): static
    {
        return $this->state(fn (array $attributes) => [
            'package' => $slug,
            'entitlements' => config("shop.packages.{$slug}.entitlements"),
            'price_yearly' => config("shop.packages.{$slug}.price_yearly"),
        ]);
    }

    /**
     * Sklep z uprawnieniem do faktur (Fakturownia) — funkcja płatna (Stragan+).
     * Dokłada `invoices=true` do istniejącego snapshotu bez zmiany pakietu, więc
     * testy FV nie muszą znać tieru; izoluje samo uprawnienie.
     */
    public function withInvoicing(): static
    {
        return $this->state(fn (array $attributes) => [
            'entitlements' => array_merge(
                $attributes['entitlements'] ?? config('shop.packages.'.config('shop.default_package').'.entitlements'),
                ['invoices' => true],
            ),
        ]);
    }

    /**
     * Sklep z uprawnieniem do płatności online (Paynow) — funkcja płatna
     * (Stragan+). Dokłada `online_payments=true` do snapshotu bez zmiany pakietu.
     */
    public function withOnlinePayments(): static
    {
        return $this->state(fn (array $attributes) => [
            'entitlements' => array_merge(
                $attributes['entitlements'] ?? config('shop.packages.'.config('shop.default_package').'.entitlements'),
                ['online_payments' => true],
            ),
        ]);
    }

    /**
     * Sklep z uprawnieniem do wysyłki kurierem/paczkomatem (InPost + Furgonetka) —
     * funkcja płatna (Stragan+). Dokłada `courier_shipping=true` bez zmiany pakietu.
     */
    public function withCourierShipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'entitlements' => array_merge(
                $attributes['entitlements'] ?? config('shop.packages.'.config('shop.default_package').'.entitlements'),
                ['courier_shipping' => true],
            ),
        ]);
    }

    /**
     * Sklep z uprawnieniem do edycji zamówienia — funkcja TYLKO Pawilonu
     * (`order_editing`). Dokłada `order_editing=true` bez zmiany pakietu.
     */
    public function withOrderEditing(): static
    {
        return $this->state(fn (array $attributes) => [
            'entitlements' => array_merge(
                $attributes['entitlements'] ?? config('shop.packages.'.config('shop.default_package').'.entitlements'),
                ['order_editing' => true],
            ),
        ]);
    }
}
