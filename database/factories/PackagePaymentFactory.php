<?php

namespace Database\Factories;

use App\Models\PackagePayment;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Opłata za pakiet Kramio. Domyślnie wiersz OPŁACONY — testy niemal zawsze
 * pytają o pieniądze, które wpłynęły, a stan `pending` jest wyjątkiem, po który
 * sięga się świadomie przez `pending()`.
 *
 * @extends Factory<PackagePayment>
 */
class PackagePaymentFactory extends Factory
{
    protected $model = PackagePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'target_package' => 'booth',
            'amount' => 750,
            'credit' => 0,
            'new_ends_at' => now()->addYear(),
            'status' => PackagePayment::STATUS_PAID,
            'payment_id' => $this->faker->uuid(),
            'paid_at' => now(),
            'applied_at' => now(),
        ];
    }

    /**
     * Płatność rozpoczęta i nieopłacona — sprzedawca kliknął „Kup" i nie wrócił
     * z bramki. Bez `paid_at`, bo kasa jej nie widziała.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PackagePayment::STATUS_PENDING,
            'paid_at' => null,
            'applied_at' => null,
        ]);
    }
}
