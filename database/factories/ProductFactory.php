<?php

namespace Database\Factories;

use App\Enums\VatRate;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'shop_id' => Shop::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price_gross' => fake()->randomFloat(2, 5, 999),
            'vat_rate' => VatRate::R23,
            'track_stock' => true,
            'stock' => fake()->numberBetween(0, 50),
            'is_active' => true,
            'show_on_homepage' => false,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
