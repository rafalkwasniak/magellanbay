<?php

namespace App\Console\Commands;

use App\Jobs\GenerateShopOgImage;
use App\Models\Shop;
use Illuminate\Console\Command;

/**
 * Dorabia grafiki Open Graph sklepom, które powstały przed wprowadzeniem tej
 * funkcji. Uruchamiane RĘCZNIE (raz po wdrożeniu) — nie ma po co wisieć w
 * harmonogramie, bo bieżące zmiany łapie ShopObserver.
 */
class GenerateOgImages extends Command
{
    protected $signature = 'og:generate {--force : Przerysuj także sklepy, które grafikę już mają}';

    protected $description = 'Generuje grafiki Open Graph (1200×630) dla sklepów';

    public function handle(): int
    {
        $shops = Shop::query()
            ->when(! $this->option('force'), fn ($query) => $query->whereNull('og_image_path'))
            ->get();

        if ($shops->isEmpty()) {
            $this->info('Wszystkie sklepy mają już grafikę.');

            return self::SUCCESS;
        }

        foreach ($shops as $shop) {
            GenerateShopOgImage::dispatchSync($shop);
            $this->line('  '.$shop->slug.' → '.$shop->fresh()->og_image_path);
        }

        $this->info('Gotowe: '.$shops->count().' '.trans_choice('sklep|sklepy|sklepów', $shops->count()).'.');

        return self::SUCCESS;
    }
}
