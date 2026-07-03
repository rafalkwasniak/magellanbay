<?php

namespace Database\Factories;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'number' => fake()->unique()->numberBetween(1, 999999),
            'status' => OrderStatus::New,
            'buyer_name' => fake()->firstName(),
            'buyer_surname' => fake()->lastName(),
            'buyer_email' => fake()->safeEmail(),
            'buyer_phone' => fake()->numerify('#########'),
            'is_company' => false,
            'delivery_method' => DeliveryMethod::Pickup,
            'delivery_cost' => 0,
            'payment_method' => PaymentMethod::PayOnPickup,
            'items_total' => 0,
            'total_net' => 0,
            'total_vat' => 0,
            'total_gross' => 0,
        ];
    }
}
