<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
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
     * Polityka prywatności — treść należy do Kramio (administrator danych), ale
     * renderujemy ją W MOTYWIE sklepu, żeby wizualnie spinała się z resztą
     * storefrontu (footer linkuje tu lokalnie zamiast na centralę). To NASZA
     * strona, więc nie wchodzi do menu „Informacje" sprzedawcy — tylko stopka.
     * Zawsze dostępna (strona prawna) — bez bramki „już wkrótce".
     */
    public function privacy(Request $request): View
    {
        return view('storefront.privacy', [
            'shop' => $request->attributes->get('shop'),
            'title' => 'Polityka prywatności',
            'document' => LegalDocument::current(LegalDocumentType::Privacy),
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
