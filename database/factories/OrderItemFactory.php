<?php

namespace Database\Factories;

use App\Enums\SaleUnit;
use App\Enums\VatRate;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unit = fake()->randomFloat(2, 5, 500);
        $quantity = fake()->numberBetween(1, 5);

        return [
            'order_id' => Order::factory(),
            'product_id' => null,
            'name' => fake()->words(3, true),
            'unit_price_gross' => $unit,
            'vat_rate' => VatRate::R23,
            'quantity' => $quantity,
            'sale_unit' => SaleUnit::Piece,
            'line_total_gross' => round($unit * $quantity, 2),
        ];
    }
}
