<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Domyślnie: konto AKTYWOWANE (hasło ustawione, e-mail potwierdzony) —
     * gotowy klient. Rejestrację przed aktywacją oddaje stan `unactivated()`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+48#########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Konto tuż po rejestracji: bez hasła i bez potwierdzenia e-maila — czeka na
     * kliknięcie w link aktywacyjny. Nie może się jeszcze zalogować.
     */
    public function unactivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'email_verified_at' => null,
        ]);
    }
}
