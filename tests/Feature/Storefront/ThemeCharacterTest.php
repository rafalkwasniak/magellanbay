<?php

namespace Tests\Feature\Storefront;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Charakter sklepu — dwie osie niezależne od szablonu (config/themes.php):
 * krój nagłówków i stopień zaokrągleń. Obie działają przez nadpisanie zmiennych
 * CSS, z których korzysta zbudowany Tailwind, więc testujemy to, co realnie
 * ląduje w :root storefrontu.
 */
class ThemeCharacterTest extends TestCase
{
    use RefreshDatabase;

    private function url(Shop $shop): string
    {
        return 'http://'.$shop->slug.'.'.config('tenancy.central_domain').'/';
    }

    public function test_default_shop_keeps_the_decorative_font(): void
    {
        $shop = Shop::factory()->active()->create(['theme' => null]);

        $this->get($this->url($shop))
            ->assertOk()
            // Brak nadpisania = serif z @theme zostaje krojem nagłówków.
            ->assertDontSee('--font-serif: var(--font-sans)', false);
    }

    public function test_plain_font_swaps_the_serif_for_the_body_face(): void
    {
        $shop = Shop::factory()->active()->create(['theme' => ['font' => 'plain']]);

        $this->get($this->url($shop))
            ->assertOk()
            ->assertSee('--font-serif: var(--font-sans)', false);
    }

    public function test_plain_font_shrinks_the_heading_steps(): void
    {
        $shop = Shop::factory()->active()->create(['theme' => ['font' => 'plain']]);
        $sizes = config('themes.fonts.plain.sizes');

        $response = $this->get($this->url($shop))->assertOk();

        foreach ($sizes as $step => $size) {
            $response->assertSee('--text-'.$step.': '.$size, false);
            // Każdy stopień MUSI być mniejszy od domyślnego — inaczej korekta
            // byłaby atrapą (albo, po literówce w configu, powiększeniem).
            $this->assertLessThan(
                $this->tailwindDefault($step),
                (float) $size,
                "Stopień {$step} dla kroju prostego nie jest mniejszy od domyślnego."
            );
        }
    }

    public function test_decorative_font_leaves_heading_steps_untouched(): void
    {
        // Rozmiary w widokach są dobrane POD serif, więc krój dekoracyjny nie
        // ma czego korygować — żadna zmienna --text-* nie może się pojawić.
        $shop = Shop::factory()->active()->create(['theme' => ['font' => 'decorative']]);

        $this->get($this->url($shop))
            ->assertOk()
            ->assertDontSee('--text-4xl', false);
    }

    /**
     * Domyślne stopnie Tailwinda (rem) — punkt odniesienia dla korekty.
     */
    private function tailwindDefault(string $step): float
    {
        return match ($step) {
            'xl' => 1.25,
            '2xl' => 1.5,
            '3xl' => 1.875,
            '4xl' => 2.25,
            '5xl' => 3.0,
            '6xl' => 3.75,
            '7xl' => 4.5,
        };
    }

    public function test_roundness_overrides_the_tailwind_radius_variables(): void
    {
        $shop = Shop::factory()->active()->create(['theme' => ['radius' => 'small']]);
        $vars = config('themes.radii.small.vars');

        $response = $this->get($this->url($shop))->assertOk();

        foreach ($vars as $step => $size) {
            $response->assertSee('--radius-'.$step.': '.$size, false);
        }
    }

    public function test_each_step_renders_its_own_sizes(): void
    {
        // Trzy stopnie muszą realnie się różnić — inaczej wybór w panelu byłby
        // atrapą. Porównujemy najbardziej widoczny stopień (kafle, --radius-3xl).
        $rendered = [];

        foreach (['small', 'medium', 'large'] as $step) {
            $shop = Shop::factory()->active()->create(['theme' => ['radius' => $step]]);
            $size = config("themes.radii.{$step}.vars.3xl");

            $this->get($this->url($shop))
                ->assertOk()
                ->assertSee('--radius-3xl: '.$size, false);

            $rendered[] = $size;
        }

        $this->assertCount(3, array_unique($rendered));
    }

    public function test_character_is_independent_of_the_chosen_template(): void
    {
        // Ta sama para (font, radius) na dwóch różnych szablonach daje ten sam
        // charakter — szablon o tych osiach nic nie wie.
        foreach (['velvet_cloud', 'graphite_dusk'] as $template) {
            $shop = Shop::factory()->active()->create([
                'template' => $template,
                'theme' => ['font' => 'plain', 'radius' => 'medium'],
            ]);

            $this->get($this->url($shop))
                ->assertOk()
                ->assertSee('--font-serif: var(--font-sans)', false)
                ->assertSee('--radius-3xl: '.config('themes.radii.medium.vars.3xl'), false);
        }
    }
}
