<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use App\Support\Mode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Strona tekstowa („Informacje") na storefroncie. URL w stylu PrestaShop:
 * /informacje/{id}-{slug} — szukamy po ID (stabilne), slug jest ozdobą SEO.
 * Zły/nieaktualny slug → 301 na kanoniczny adres. Strona jest scope'owana do
 * sklepu z subdomeny; niepublikowaną (i stronę sklepu-szkicu) widzą wyłącznie
 * właściciel i administrator (podgląd), reszta dostaje 404.
 */
class PageController extends Controller
{
    /**
     * Wirtualna strona „O sklepie" — treść pochodzi z `shop.description`, nie z
     * tabeli `pages`. Istnieje (renderuje) zawsze, gdy opis jest niepusty; pusty
     * → 404. Sklep-szkic widzi wyłącznie właściciel/administrator (podgląd).
     * O obecności w menu decyduje długość opisu (Shop::aboutInMenu) — nie tutaj.
     */
    /**
     * Landing działu „Informacje" (/informacje) — sam dział nie ma treści, więc
     * przekierowujemy na PIERWSZĄ pozycję menu (wg Shop::informationMenu). Cel
     * zależy od kolejności ustawionej przez sprzedawcę, więc redirect jest
     * tymczasowy (302), nie 301 — inaczej przeglądarka zapamiętałaby nieaktualny
     * cel po zmianie kolejności stron. Puste menu (teoretycznie) → 404.
     */
    public function index(Request $request): RedirectResponse
    {
        $shop = $request->attributes->get('shop');

        $menu = $shop->informationMenu();
        abort_if($menu === [], 404);

        return redirect()->to($menu[0]['url'], 302);
    }

    public function about(Request $request): View
    {
        $shop = $request->attributes->get('shop');

        abort_unless($shop->hasAbout(), 404);

        abort_if(! $shop->isVisible() && ! $shop->canBePreviewedBy($request->user()), 404);

        return view('storefront.about', [
            'shop' => $shop,
            'title' => config('pages.about.title'),
        ]);
    }

    /**
     * Polityka prywatności pod stałym adresem /informacje/polityka-prywatnosci.
     *
     * Renderowana zawsze w MOTYWIE sklepu i zawsze dostępna (to strona prawna —
     * bez bramki „już wkrótce"), jako ostatnia pozycja działu „Informacje".
     *
     * DWA ŹRÓDŁA TREŚCI, zależnie od tego, KTO jest administratorem danych:
     *
     * - Kramio: dokument PLATFORMY. Sklep działa na cudzej infrastrukturze,
     *   operator jest podmiotem przetwarzającym i to on opisuje, co się z danymi
     *   dzieje. Sprzedawca nie ma jak tego opisać ani za to odpowiadać.
     *
     * - Sklep dedykowany: strona systemowa SKLEPU. Nie ma platformy ani
     *   powierzenia — właściciel jest administratorem i gospodarzem serwera
     *   naraz. Dokument platformy byłby tu polityką cudzego podmiotu, wskazującą
     *   klientom obcą firmę jako administratora ich danych.
     */
    public function privacy(Request $request): View
    {
        $shop = $request->attributes->get('shop');

        if (Mode::dedicated()) {
            $page = $shop->pages()
                ->where('slug', config('pages.privacy.slug'))
                ->where('is_system', true)
                ->first();

            return view('storefront.privacy', [
                'shop' => $shop,
                'title' => config('pages.privacy.title'),
                'content' => $page?->published ? $page->content : null,
            ]);
        }

        return view('storefront.privacy', [
            'shop' => $shop,
            'title' => 'Polityka prywatności',
            'content' => LegalDocument::current(LegalDocumentType::Privacy)?->content,
        ]);
    }

    public function show(Request $request, string $page): View|RedirectResponse
    {
        $shop = $request->attributes->get('shop');

        $model = $shop->pages()->find((int) $page);

        abort_if($model === null, 404);

        // Publicznie widoczna tylko opublikowana strona w opublikowanym sklepie;
        // szkic sklepu i ukryta strona → tylko podgląd właściciela/administratora.
        $public = $shop->isVisible() && $model->published;
        abort_if(! $public && ! $shop->canBePreviewedBy($request->user()), 404);

        if ('/informacje/'.$page !== $model->storefrontPath()) {
            // Kanonizacja slugu zachowuje query, by nie zgubić kontekstu.
            $qs = $request->getQueryString();

            return redirect()->to($model->storefrontPath().($qs !== null ? '?'.$qs : ''), 301);
        }

        return view('storefront.page', [
            'shop' => $shop,
            'page' => $model,
        ]);
    }
}
