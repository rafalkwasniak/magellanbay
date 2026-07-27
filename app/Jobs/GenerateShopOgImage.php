<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\OgImageGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Przerysowuje grafikę sklepu do social mediów po zmianie logo, nazwy albo
 * kolorów. W kolejce, bo składanie obrazka 1200×630 nie ma prawa opóźniać
 * zapisu formularza — sprzedawca ma widzieć „Zapisano" od razu.
 *
 * Nieudane generowanie NIE jest awarią sklepu: bez grafiki `og:image` spadnie
 * z powrotem na logo, więc job próbuje raz i odpuszcza.
 */
class GenerateShopOgImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public Shop $shop) {}

    public function handle(OgImageGenerator $generator): void
    {
        $path = $generator->generate($this->shop);

        // `og_image_path` NIE jest mass-assignable — to pole systemowe, nie dana
        // z formularza sprzedawcy.
        $this->shop->forceFill(['og_image_path' => $path])->save();
    }
}
