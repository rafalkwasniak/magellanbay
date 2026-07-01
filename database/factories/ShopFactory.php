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
        ]);
    }
}
