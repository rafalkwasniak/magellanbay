<?php

namespace Database\Factories;

use App\Models\PlatformMailing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformMailing>
 */
class PlatformMailingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph()."\n\n".fake()->paragraph(),
        ];
    }

    /**
     * Wiadomość już wysłana — nie da się jej edytować, skasować ani powtórzyć.
     */
    public function sent(int $recipients = 5): static
    {
        return $this->state(fn (): array => [
            'sent_at' => now(),
            'recipients_count' => $recipients,
        ]);
    }
}
