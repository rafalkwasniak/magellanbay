<?php

namespace App\Console\Commands;

use App\Models\ReservedSlug;
use App\Models\Shop;
use App\Services\ShopEraser;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Codzienne domknięcie usuwania sklepów: kasuje te, którym minęła karencja, i
 * zwalnia adresy po zakończonej kwarantannie.
 *
 * Idempotentna — sklep bez terminu albo z terminem w przyszłości nie jest nawet
 * dotykany, więc powtórzony bieg (drugi cron, ręczne odpalenie) nic nie psuje.
 */
class PurgeShops extends Command
{
    protected $signature = 'shops:purge';

    protected $description = 'Usuwa sklepy po upływie karencji i zwalnia zarezerwowane adresy subdomen';

    public function handle(ShopEraser $eraser): int
    {
        $erased = 0;

        Shop::query()
            ->whereNotNull('deletion_scheduled_at')
            ->where('deletion_scheduled_at', '<=', Carbon::now())
            ->with('owner')
            // Bez chunkowania: kasowanie zmienia zbiór wyników pod kursorem,
            // a sklepów z minioną karencją są w każdym biegu jednostki.
            ->get()
            ->each(function (Shop $shop) use ($eraser, &$erased): void {
                $eraser->erase($shop);
                $erased++;
            });

        $released = ReservedSlug::where('released_at', '<=', Carbon::now())->delete();

        $this->info("Usunięte sklepy: {$erased}, zwolnione adresy: {$released}.");

        return self::SUCCESS;
    }
}
