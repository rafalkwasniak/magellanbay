<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'shop_id' => Shop::factory(),
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'position' => fake()->numberBetween(0, 10),
            'published' => true,
        ];
    }

    /**
     * Strona niepublikowana (ukryta na storefroncie).
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'published' => false,
        ]);
    }
}
