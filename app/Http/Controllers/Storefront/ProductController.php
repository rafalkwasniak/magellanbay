<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\CatalogAxis;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        return $this->listing($request, null);
    }

    /**
     * Strona jednej kategorii — ten sam wykaz, tylko zawężony do gałęzi węzła
     * i opisany jego nazwą.
     *
     * WSPÓLNY MECHANIZM Z WYKAZEM, a nie kopia: dwa osobne filtrowania
     * rozjechałyby się przy pierwszej poprawce, a kupujący zobaczyłby inną
     * liczbę produktów w dwóch miejscach tego samego sklepu.
     *
     * Oś przychodzi z `defaults` trasy, nie z adresu — segment jest częścią
     * ścieżki (`/geografia/rzym`), więc nie ma czego zgadywać.
     */
    public function category(Request $request, string $category): View
    {
        $shop = $request->attributes->get('shop');
        $axis = CatalogAxis::find((string) $request->route()->defaults['axis']);
        abort_if($axis === null, 404);

        $node = $shop->categories()
            ->onAxis($axis->key())
            ->where('slug', $category)
            ->first();

        abort_if($node === null, 404);

        return $this->listing($request, $node);
    }

    /**
     * Wykaz produktów: wspólny dla /produkty i dla stron kategorii.
     *
     * `$node` niepuste zawęża zbiór do CAŁEJ GAŁĘZI węzła — „Włochy" pokazują
     * też magnesy przypięte wyłącznie do „Rzymu". Bez tego wyższy poziom
     * hierarchii byłby pusty i wyglądałby jak usterka, a nie jak decyzja.
     */
    private function listing(Request $request, ?Category $node): View
    {
        $shop = $request->attributes->get('shop');

        if (! $shop->isVisible() && ! $shop->canBePreviewedBy($request->user())) {
            return view('storefront.coming-soon', ['shop' => $shop]);
        }

        $sortKey = $this->resolveSort($request->query('sortowanie'));
        $sort = self::SORTS[$sortKey];

        // Wybrane tagi z URL (?tagi=a,b) — tylko realne tagi tego sklepu.
        $selected = $this->resolveTags($request->query('tagi'), $shop->tags()->pluck('slug'));

        // Wybrane kategorie z URL (?rodzaj=kamien&tematyka=biegi,unesco).
        $picked = $this->resolveCategories($request, $shop);

        // Zbiór wynikowy: aktywne produkty mające KAŻDY z wybranych tagów (AND).
        $filtered = $shop->products()->where('is_active', true);
        foreach ($selected as $slug) {
            $filtered->whereHas('tags', fn ($q) => $q->where('tags.slug', $slug));
        }

        // Filtr kategorii też jest iloczynem — „kamienne ORAZ z Włoch".
        foreach ($picked as $chosen) {
            $ids = $chosen->flatMap(fn (Category $c): array => $c->branchIds())->unique()->all();
            $filtered->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $ids));
        }

        if ($node !== null) {
            $filtered->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $node->branchIds()));
        }

        // Gęstość wykazu (kolumny + ile na stronę) skalowana do wielkości katalogu,
        // liczonej z CAŁEGO sklepu — nie ze zbioru po filtrze, żeby układ nie „pływał".
        $density = $shop->listingDensity();

        $products = $filtered->clone()
            ->with('images')
            ->orderBy($sort['column'], $sort['direction'])
            ->paginate($density['per_page'])
            ->withQueryString();

        $base = $node?->storefrontPath() ?? '/produkty';
        $query = ['sortowanie' => $sortKey, 'tagi' => $selected->all(), 'kategorie' => $picked];

        return view('storefront.products', [
            'shop' => $shop,
            'products' => $products,
            'columns' => $density['columns'],
            'sortKey' => $sortKey,
            'sortOptions' => $this->sortOptions($sortKey, $selected->all(), $picked, $base),
            'tagCloud' => $this->tagCloud($shop, $filtered->clone(), $selected, $sortKey, $picked, $base),
            'hasFilters' => $selected->isNotEmpty() || $picked !== [],
            'clearUrl' => $this->listUrl($base, ['sortowanie' => $sortKey]),
            // Kontekst kategorii: nagłówek, opis, okruszki i zejście niżej.
            'category' => $node,
            'crumbs' => $node?->path() ?? [],
            'children' => $node?->children ?? collect(),
            'axisPanels' => $this->axisPanels($shop, $filtered->clone(), $picked, $node, $sortKey, $selected, $base),
        ]);
    }

    /**
     * Wybrane tagi z URL sprowadzone do realnych slugów sklepu (odsiewa śmieci
     * i duplikaty; zachowuje kolejność podania).
     *
     * @param  Collection<int, string>  $validSlugs
     * @return Collection<int, string>
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
     * @param  Builder  $filtered  zapytanie bieżącego zbioru (przed paginacją)
     * @param  Collection<int, string>  $selected
     * @return list<array{name: string, count: int|null, url: string, active: bool}>
     */
    private function tagCloud($shop, $filtered, $selected, string $sortKey, array $picked, string $base): array
    {
        $cloud = [];

        // Wybrane tagi — na początku, jako aktywne pigułki (klik = usuń).
        $names = $shop->tags()->whereIn('slug', $selected->all())->pluck('name', 'slug');
        foreach ($selected as $slug) {
            $cloud[] = [
                'name' => $names[$slug] ?? $slug,
                'count' => null,
                'url' => $this->listUrl($base, [
                    'sortowanie' => $sortKey,
                    'tagi' => $selected->reject(fn (string $s): bool => $s === $slug)->values()->all(),
                    'kategorie' => $picked,
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
                'url' => $this->listUrl($base, [
                    'sortowanie' => $sortKey,
                    'tagi' => $selected->merge([$facet->slug])->values()->all(),
                    'kategorie' => $picked,
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
    private function sortOptions(string $current, array $selectedTags, array $picked, string $base): array
    {
        return collect(self::SORTS)->map(fn (array $sort, string $key): array => [
            'label' => $sort['label'],
            'url' => $this->listUrl($base, ['sortowanie' => $key, 'tagi' => $selectedTags, 'kategorie' => $picked]),
            'active' => $key === $current,
        ])->values()->all();
    }

    /**
     * Buduje adres wykazu z podanych filtrów. Świadomie POMIJA `page` — każda
     * zmiana filtra albo sortu wraca na pierwszą stronę. Domyślne „najnowsze"
     * i puste zestawy nie brudzą adresu.
     *
     * `$base` to ścieżka, na której stoimy: /produkty albo strona kategorii.
     * Dzięki temu zawężanie tagiem na /geografia/rzym zostaje w Rzymie,
     * zamiast wyrzucać kupującego z powrotem na pełny wykaz.
     *
     * @param  array{sortowanie?: string, tagi?: list<string>, kategorie?: array<string, Collection<int, Category>>}  $params
     */
    private function listUrl(string $base, array $params): string
    {
        $query = [];

        if (($params['sortowanie'] ?? 'najnowsze') !== 'najnowsze') {
            $query['sortowanie'] = $params['sortowanie'];
        }

        $tags = $params['tagi'] ?? [];
        if ($tags !== []) {
            $query['tagi'] = implode(',', $tags);
        }

        foreach ($params['kategorie'] ?? [] as $axisKey => $nodes) {
            $axis = CatalogAxis::find((string) $axisKey);
            $slugs = collect($nodes)->pluck('slug')->all();

            if ($axis !== null && $slugs !== []) {
                $query[$axis->segment()] = implode(',', $slugs);
            }
        }

        return $base.($query === [] ? '' : '?'.http_build_query($query));
    }

    /**
     * Kategorie wybrane w adresie, oś po osi (`?rodzaj=kamien&tematyka=biegi`).
     *
     * Nieznany slug po prostu wypada — adres z literówką ma pokazać szerszy
     * zbiór, a nie pustą stronę albo błąd.
     *
     * @return array<string, Collection<int, Category>>
     */
    private function resolveCategories(Request $request, $shop): array
    {
        $picked = [];

        foreach (CatalogAxis::all() as $axis) {
            $raw = $request->query($axis->segment());

            $slugs = collect(explode(',', is_string($raw) ? $raw : ''))
                ->map(fn (string $slug): string => trim($slug))
                ->filter()
                ->unique();

            if ($slugs->isEmpty()) {
                continue;
            }

            $nodes = $shop->categories()->onAxis($axis->key())->whereIn('slug', $slugs->all())->get();

            if ($nodes->isNotEmpty()) {
                $picked[$axis->key()] = $nodes;
            }
        }

        return $picked;
    }

    /**
     * Panele podziałów obok wykazu: co jeszcze da się wybrać na każdej osi
     * i ile produktów z bieżącego zbioru tam trafia.
     *
     * Liczba przy pozycji jest liczona z GAŁĘZI, nie z samego węzła — inaczej
     * „Włochy" pokazywałyby zero, mając pod sobą pełen Rzym.
     *
     * Martwe pozycje (zero produktów) w ogóle nie wchodzą: filtr, który
     * prowadzi do pustej strony, jest gorszy niż jego brak.
     *
     * @param  array<string, Collection<int, Category>>  $picked
     * @return list<array{label: string, items: list<array{name: string, count: int, url: string, active: bool}>}>
     */
    private function axisPanels($shop, $filtered, array $picked, ?Category $node, string $sortKey, $selectedTags, string $base): array
    {
        $counts = DB::table('category_product')
            ->whereIn('category_product.product_id', $filtered->select('products.id'))
            ->groupBy('category_product.category_id')
            ->select('category_product.category_id', DB::raw('COUNT(DISTINCT category_product.product_id) as cnt'))
            ->pluck('cnt', 'category_product.category_id');

        $all = $shop->categories()->get();
        $panels = [];

        foreach (CatalogAxis::all() as $axis) {
            $chosen = collect($picked[$axis->key()] ?? []);
            $items = [];

            foreach (Category::flatten($all->where('axis', $axis->key())) as $row) {
                $category = $row['category'];
                $active = $chosen->contains('id', $category->id);

                // Węzeł, na którego stronie właśnie stoimy, nie jest filtrem
                // do klikania — to nagłówek tej strony.
                if ($node !== null && $node->id === $category->id) {
                    continue;
                }

                $count = collect($category->branchIds())->sum(fn (int $id): int => (int) ($counts[$id] ?? 0));

                if ($count === 0 && ! $active) {
                    continue;
                }

                $next = $active
                    ? $chosen->reject(fn (Category $c): bool => $c->id === $category->id)
                    // `concat`, NIE `push`: push mutuje kolekcję w miejscu,
                    // więc każda kolejna pozycja panelu doklejała się do
                    // poprzedniej i link do „Polski" prowadził na
                    // ?geografia=europa,polska, choć nikt nie wybrał Europy.
                    : $chosen->concat([$category]);

                $items[] = [
                    'name' => str_repeat('· ', $row['depth']).$category->name,
                    'count' => $count,
                    'url' => $this->listUrl($base, [
                        'sortowanie' => $sortKey,
                        'tagi' => collect($selectedTags)->all(),
                        'kategorie' => array_merge($picked, [$axis->key() => $next]),
                    ]),
                    'active' => $active,
                ];
            }

            if ($items !== []) {
                $panels[] = ['label' => $axis->labelPlural(), 'items' => $items];
            }
        }

        return $panels;
    }

    public function show(Request $request, string $product): View|RedirectResponse
    {
        $shop = $request->attributes->get('shop');

        $model = $shop->products()
            ->with(['images', 'priceHistory', 'tags', 'categories'])
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
            // Kategorie produktu — po jednej grupie na os, kazda prowadzi na
            // wlasna strone dzialu. To druga droga powrotu do katalogu obok
            // tagow: tag jest luzna etykieta, kategoria miejscem.
            'productCategories' => CatalogAxis::all()
                ->map(fn (CatalogAxis $axis): array => [
                    'label' => $axis->label(),
                    'items' => $model->categoriesOn($axis->key())->all(),
                ])
                ->filter(fn (array $panel): bool => $panel['items'] !== [])
                ->values()
                ->all(),
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
