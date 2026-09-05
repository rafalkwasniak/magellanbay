<?php

use App\Models\Page;
use App\Models\Shop;
use App\Support\Mode;
use Illuminate\Database\Migrations\Migration;

/**
 * Dokłada stronę systemową „Polityka prywatności" sklepom, które powstały,
 * zanim zaczął ją zakładać `ShopObserver`.
 *
 * DLACZEGO MIGRACJA DANYCH, A NIE SEEDER: seeder wdrożeniowy uruchamia się
 * dokładnie raz, na pustej bazie, i odmawia startu, gdy sklep już istnieje.
 * Instalacja robocza Magellana powstała wcześniej i nie ma jak do niej wrócić
 * inaczej niż migracją — a bez tej strony sklep publikuje klientom politykę
 * prywatności CUDZEGO podmiotu (dokument platformy) albo pustkę.
 *
 * TYLKO W TRYBIE DEDYKOWANYM. W Kramio pod tym adresem stoi nasz własny
 * dokument i druga strona o tym samym slugu byłaby tam wyłącznie zamieszaniem.
 *
 * Idempotentna: sklep, który stronę już ma, jest pomijany.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Mode::dedicated()) {
            return;
        }

        $definicja = config('pages.privacy');

        foreach (Shop::query()->get() as $shop) {
            $istnieje = $shop->pages()
                ->where('slug', $definicja['slug'])
                ->exists();

            if ($istnieje) {
                continue;
            }

            // Na koniec listy — nie przestawiamy kolejności stron, które
            // właściciel mógł już sobie ułożyć.
            $position = (int) $shop->pages()->max('position') + 1;

            $page = $shop->pages()->make([
                'title' => $definicja['title'],
                'slug' => $definicja['slug'],
                'content' => $definicja['content'],
                'position' => $position,
                'published' => true,
            ]);
            $page->is_system = true;
            $page->save();
        }
    }

    /**
     * Wycofanie kasuje WYŁĄCZNIE nietkniętą zaślepkę. Jeżeli właściciel zdążył
     * wpisać własną politykę, zostawiamy ją — cofnięcie migracji nie ma prawa
     * skasować cudzego dokumentu prawnego.
     */
    public function down(): void
    {
        if (! Mode::dedicated()) {
            return;
        }

        $definicja = config('pages.privacy');

        Page::query()
            ->where('slug', $definicja['slug'])
            ->where('is_system', true)
            ->where('content', $definicja['content'])
            ->delete();
    }
};
