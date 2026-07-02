<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Strona pojedynczego produktu na storefroncie. URL w stylu PrestaShop:
 * /produkt/{id}-{slug} — szukamy po ID (stabilne), slug jest tylko ozdobą SEO.
 * Zły/nieaktualny slug → 301 na kanoniczny adres (jedno miejsce w indeksie).
 * Produkt jest scope'owany do sklepu z subdomeny; nieaktywny widzą wyłącznie
 * właściciel i administrator (podgląd), reszta dostaje 404.
 */
class ProductController extends Controller
{
    /**
     * Dozwolone sortowania: klucz w URL (po polsku) → [kolumna, kierunek].
     * Nieznany klucz spada na pierwszy wpis (domyślne „najnowsze").
     */
    private const SORTS = [
        'najnowsze' => ['label' => 'Najnowsze', 'column' => 'created_at', 'direction' => 'desc'],
        'cena-rosnaco' => ['label' => 'Cena: od najniższej', 'column' => 'price_gross', 'direction' => 'asc'],
        'cena-malejaco' => ['label' => 'Cena: od najwyższej', 'column' => 'price_gross', 'direction' => 'desc'],
        'nazwa' => ['label' => 'Nazwa: A–Z', 'column' => 'name', 'direction' => 'asc'],
    ];

    /**
     * Wykaz produktów sklepu — pełny katalog aktywnych produktów (paginowany).
     * Sklep-szkic widzi publicznie tylko ekran „już wkrótce"; podgląd dla
     * właściciela/administratora. Kafel = ten sam klocek co na głównej.
     */
    public function index(Request $request): View
    {
        $shop = $request->attributes->get('shop');

        if (! $shop->isVisible() && ! $shop->canBePreviewedBy($request->user())) {
            return view('storefront.coming-soon', ['shop' => $shop]);
        }

        $sortKey = $this->resolveSort($request->query('sortowanie'));
        $sort = self::SORTS[$sortKey];

        // Tagi sklepu z liczbą aktywnych produktów; najpopularniejsze najpierw.
        $shopTags = $shop->tags()
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->get();

        // Wybrane tagi z URL (?tagi=a,b) — tylko realne tagi tego sklepu.
        $selected = $this->resolveTags($request->query('tagi'), $shopTags->pluck('slug'));

        $query = $shop->products()->where('is_active', true)->with('images');

        // Filtr AND: produkt musi mieć KAŻDY z wybranych tagów.
        foreach ($selected as $slug) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.slug', $slug));
        }

        $products = $query
            ->orderBy($sort['column'], $sort['direction'])
            ->paginate($shop->productsPerPage())
            ->withQueryString();

        return view('storefront.products', [
            'shop' => $shop,
            'products' => $products,
            'sortKey' => $sortKey,
            'sortOptions' => $this->sortOptions($sortKey, $selected->all()),
            'tagCloud' => $this->tagCloud($shopTags, $selected, $sortKey),
            'hasFilters' => $selected->isNotEmpty(),
            'clearUrl' => $this->listUrl(['sortowanie' => $sortKey]),
        ]);
    }

    /**
     * Wybrane tagi z URL sprowadzone do realnych slugów sklepu (odsiewa śmieci
     * i duplikaty; zachowuje kolejność podania).
     *
     * @param  \Illuminate\Support\Collection<int, string>  $validSlugs
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function resolveTags(mixed $raw, $validSlugs)
    {
        return collect(explode(',', is_string($raw) ? $raw : ''))
            ->map(fn (string $slug): string => trim($slug))
            ->filter()
            ->unique()
            ->intersect($validSlugs)
            ->values();
    }

    /**
     * Chmura tagów dla widoku: równe pigułki, najpopularniejsze najpierw.
     * Pokazujemy tag mający aktywne produkty albo aktualnie wybrany (żeby dało
     * się go odznaczyć, nawet gdy wynik jest pusty). URL każdej pigułki
     * PRZEŁĄCZA jej tag, zachowując resztę filtrów i sort.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Tag>  $shopTags
     * @param  \Illuminate\Support\Collection<int, string>  $selected
     * @return list<array{name: string, count: int, url: string, active: bool}>
     */
    private function tagCloud($shopTags, $selected, string $sortKey): array
    {
        return $shopTags
            ->filter(fn ($tag): bool => $tag->products_count > 0 || $selected->contains($tag->slug))
            ->map(function ($tag) use ($selected, $sortKey): array {
                $active = $selected->contains($tag->slug);

                $toggled = $active
                    ? $selected->reject(fn (string $s): bool => $s === $tag->slug)->values()
                    : $selected->merge([$tag->slug])->values();

                return [
                    'name' => $tag->name,
                    'count' => (int) $tag->products_count,
                    'url' => $this->listUrl(['sortowanie' => $sortKey, 'tagi' => $toggled->all()]),
                    'active' => $active,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Sprowadza wartość z URL do dozwolonego klucza sortowania (whitelist).
     */
    private function resolveSort(mixed $key): string
    {
        return is_string($key) && isset(self::SORTS[$key]) ? $key : array_key_first(self::SORTS);
    }

    /**
     * Opcje sortowania dla widoku: etykieta, URL (bez `page` — zmiana sortu
     * wraca na stronę 1) i flaga aktywności.
     *
     * @param  list<string>  $selectedTags
     * @return list<array{label: string, url: string, active: bool}>
     */
    private function sortOptions(string $current, array $selectedTags): array
    {
        return collect(self::SORTS)->map(fn (array $sort, string $key): array => [
            'label' => $sort['label'],
            'url' => $this->listUrl(['sortowanie' => $key, 'tagi' => $selectedTags]),
            'active' => $key === $current,
        ])->values()->all();
    }

    /**
     * Buduje URL wykazu z podanych filtrów. Świadomie POMIJA `page` — każda
     * zmiana filtra/sortu resetuje paginację do pierwszej strony. Domyślne
     * „najnowsze" i pusty zestaw tagów nie brudzą adresu.
     *
     * @param  array{sortowanie?: string, tagi?: list<string>}  $params
     */
    private function listUrl(array $params): string
    {
        $query = [];

        if (($params['sortowanie'] ?? 'najnowsze') !== 'najnowsze') {
            $query['sortowanie'] = $params['sortowanie'];
        }

        $tags = $params['tagi'] ?? [];
        if ($tags !== []) {
            $query['tagi'] = implode(',', $tags);
        }

        return '/produkty'.($query === [] ? '' : '?'.http_build_query($query));
    }

    public function show(Request $request, string $product): View|RedirectResponse
    {
        $shop = $request->attributes->get('shop');

        $model = $shop->products()
            ->with(['images', 'priceHistory'])
            ->find((int) $product);

        abort_if($model === null, 404);

        // Publicznie widoczny tylko aktywny produkt w opublikowanym sklepie;
        // szkic sklepu i ukryty produkt widzą wyłącznie właściciel/administrator.
        $public = $shop->isVisible() && $model->is_active;
        abort_if(! $public && ! $shop->canBePreviewedBy($request->user()), 404);

        if ('/produkt/'.$product !== $model->storefrontPath()) {
            // Kanonizacja slugu zachowuje query (np. `powrot`), by nie zgubić kontekstu.
            $qs = $request->getQueryString();

            return redirect()->to($model->storefrontPath().($qs !== null ? '?'.$qs : ''), 301);
        }

        return view('storefront.product', [
            'shop' => $shop,
            'product' => $model,
            'back' => $this->safeBack($request->query('powrot')),
        ]);
    }

    /**
     * Adres powrotu z produktu na listę. Przyjmujemy WYŁĄCZNIE lokalną ścieżkę
     * (zaczyna się od pojedynczego „/") — to zamyka open-redirect (odrzuca
     * `//host`, `/\host`, `http://…`). Brak/nieprawidłowy → strona główna sklepu.
     */
    private function safeBack(mixed $back): string
    {
        if (is_string($back)
            && str_starts_with($back, '/')
            && ! str_starts_with($back, '//')
            && ! str_starts_with($back, '/\\')) {
            return $back;
        }

        return '/';
    }
}
