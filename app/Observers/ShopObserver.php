<?php

namespace App\Observers;

use App\Models\Shop;

/**
 * Zakłada nowemu sklepowi stronę systemową Regulamin (nieusuwalną). Szkielet
 * treści bierzemy z config/pages.php — sprzedawca uzupełnia go pod swój sklep.
 * `is_system` ustawiamy jawnie, bo nie jest mass-assignable na modelu Page.
 */
class ShopObserver
{
    public function created(Shop $shop): void
    {
        $regulamin = config('pages.regulamin');

        $page = $shop->pages()->make([
            'title' => $regulamin['title'],
            'slug' => $regulamin['slug'],
            'content' => $regulamin['content'],
            'position' => 0,
            'published' => true,
        ]);
        $page->is_system = true;
        $page->save();
    }
}
