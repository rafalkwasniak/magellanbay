<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\Shop;
use App\Services\SeoDescriptionWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Pisze opis SEO po zmianie treści — w KOLEJCE, bo zapis produktu nie może
 * czekać na model językowy ani wywrócić się, gdy dostawca AI nie odpowiada.
 *
 * Trzy warunki, przy których job nic nie robi (i dobrze):
 *  - sprzedawca napisał opis RĘCZNIE — jego tekst jest nietykalny;
 *  - treść źródłowa jest za krótka, żeby było co streszczać;
 *  - AI niedostępne — wtedy zostaje deterministyczny opis z faktów, który i tak
 *    zawsze mamy pod ręką (App\Support\Seo).
 *
 * `tries = 1`: nieudane napisanie opisu to nie awaria. Ponawianie kosztowałoby
 * tokeny za tekst, który przy najbliższej edycji i tak powstanie od nowa.
 */
class GenerateSeoDescription implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public Model $subject) {}

    public function handle(SeoDescriptionWriter $writer): void
    {
        $subject = $this->subject->fresh();

        if ($subject === null || $subject->meta_description_manual) {
            return;
        }

        try {
            $description = match (true) {
                $subject instanceof Product => $writer->forProduct($subject),
                $subject instanceof Shop => $writer->forShop($subject),
                default => null,
            };
        } catch (Throwable) {
            // Brak opisu nie psuje strony — `Seo` ma fallback z faktów.
            return;
        }

        if ($description === null || $description === '') {
            return;
        }

        // `forceFill` + zapis bez zdarzeń: aktualizacja opisu SEO nie może
        // wywołać obserwatora, który zleciłby kolejne generowanie w pętli.
        $subject->forceFill([
            'meta_description' => $description,
            'meta_description_manual' => false,
        ])->saveQuietly();
    }
}
