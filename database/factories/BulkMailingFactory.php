<?php

namespace Database\Factories;

use App\Models\BulkMailing;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BulkMailing>
 */
class BulkMailingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph()."\n\n".fake()->paragraph(),
        ];
    }

    /**
     * Mailing już wysłany do klientów — nie da się go wysłać ponownie.
     */
    public function sent(int $recipients = 5): static
    {
        return $this->state(fn (): array => [
            'sent_at' => now(),
            'recipients_count' => $recipients,
        ]);
    }
}
