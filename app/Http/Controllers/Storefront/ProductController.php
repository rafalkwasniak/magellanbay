<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Wybrane tagi z URL (?tagi=a,b) — tylko realne tagi tego sklepu.
        $selected = $this->resolveTags($request->query('tagi'), $shop->tags()->pluck('slug'));

        // Zbiór wynikowy: aktywne produkty mające KAŻDY z wybranych tagów (AND).
        $filtered = $shop->products()->where('is_active', true);
        foreach ($selected as $slug) {
            $filtered->whereHas('tags', fn ($q) => $q->where('tags.slug', $slug));
        }

        $products = $filtered->clone()
            ->with('images')
            ->orderBy($sort['column'], $sort['direction'])
            ->paginate($shop->productsPerPage())
            ->withQueryString();

        return view('storefront.products', [
            'shop' => $shop,
            'products' => $products,
            'sortKey' => $sortKey,
            'sortOptions' => $this->sortOptions($sortKey, $selected->all()),
            'tagCloud' => $this->tagCloud($shop, $filtered->clone(), $selected, $sortKey),
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
     * Chmura tagów fasetowa. Najpierw wybrane tagi (widoczne, klik je zdejmuje),
     * potem tagi WSPÓŁWYSTĘPUJĄCE w bieżącym zbiorze wyników — czyli takie, które
     * realnie go zawężają; martwe kombinacje (0 produktów) w ogóle nie wchodzą.
     * Liczba przy kandydacie = ile z aktualnych produktów też ma dany tag.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $filtered  zapytanie bieżącego zbioru (przed paginacją)
     * @param  \Illuminate\Support\Collection<int, string>  $selected
     * @return list<array{name: string, count: int|null, url: string, active: bool}>
     */
    private function tagCloud($shop, $filtered, $selected, string $sortKey): array
    {
        $cloud = [];

        // Wybrane tagi — na początku, jako aktywne pigułki (klik = usuń).
        $names = $shop->tags()->whereIn('slug', $selected->all())->pluck('name', 'slug');
        foreach ($selected as $slug) {
            $cloud[] = [
                'name' => $names[$slug] ?? $slug,
                'count' => null,
                'url' => $this->listUrl([
                    'sortowanie' => $sortKey,
                    'tagi' => $selected->reject(fn (string $s): bool => $s === $slug)->values()->all(),
                ]),
                'active' => true,
            ];
        }

        // Kandydaci: tagi obecne na produktach z bieżącego zbioru (poza wybranymi),
        // z liczbą współwystąpień; najsilniej zawężające najpierw.
        $facets = DB::table('product_tag')
            ->join('tags', 'tags.id', '=', 'product_tag.tag_id')
            ->whereIn('product_tag.product_id', $filtered->select('products.id'))
            ->when($selected->isNotEmpty(), fn ($q) => $q->whereNotIn('tags.slug', $selected->all()))
            ->groupBy('tags.slug', 'tags.name')
            ->select('tags.slug', 'tags.name', DB::raw('COUNT(DISTINCT product_tag.product_id) as cnt'))
            ->orderByDesc('cnt')
            ->orderBy('tags.name')
            ->get();

        foreach ($facets as $facet) {
            $cloud[] = [
                'name' => $facet->name,
                'count' => (int) $facet->cnt,
                'url' => $this->listUrl([
                    'sortowanie' => $sortKey,
                    'tagi' => $selected->merge([$facet->slug])->values()->all(),
                ]),
                'active' => false,
            ];
        }

        return $cloud;
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
            ->with(['images', 'priceHistory', 'tags'])
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
            // Tagi produktu jako klikalne wejścia do przefiltrowanego wykazu.
            'productTags' => $model->tags->map(fn ($tag): array => [
                'name' => $tag->name,
                'count' => null,
                'url' => '/produkty?tagi='.$tag->slug,
                'active' => false,
            ])->all(),
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
