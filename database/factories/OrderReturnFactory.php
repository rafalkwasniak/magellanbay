<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderReturn>
 */
class OrderReturnFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'customer_name' => fake()->firstName().' '.fake()->lastName(),
            'customer_address' => fake()->streetAddress().', '.fake()->postcode().' '.fake()->city(),
            'bank_account' => null,
            'note' => null,
            'refund_gross' => fake()->randomFloat(2, 10, 300),
        ];
    }

    /**
     * Zwrot rozliczony finansowo — sprzedawca oddał pieniądze.
     */
    public function refunded(): static
    {
        return $this->state(fn (): array => ['refunded_at' => now()]);
    }
}
