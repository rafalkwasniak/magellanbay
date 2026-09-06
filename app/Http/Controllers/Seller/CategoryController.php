<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Shop;
use App\Support\CatalogAxis;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Katalog w kilku niezależnych osiach — rodzaj, tematyka, geografia.
 *
 * JEDEN EKRAN NA WSZYSTKIE OSIE. Różnią się trzema ustawieniami z
 * `config/catalog.php`, więc trzy kopie tego samego kontrolera byłyby trzema
 * miejscami do poprawiania tej samej literówki. Oś przychodzi segmentem adresu
 * (`/sprzedawca/katalog/geografia`), a nieznany segment to 404.
 *
 * ---------------------------------------------------------------------------
 * ZAPIS CAŁEJ OSI JEDNYM ŻĄDANIEM
 *
 * Geografia to nie pięć pozycji, tylko kilkaset: kontynenty, kraje, miasta.
 * Ekran „edytuj → zapisz" przeklikany dwieście razy jest karą, a nie narzędziem.
 * Sprzedawca układa całą oś jak listę i zapisuje raz — przy okazji nie ma stanu
 * pośredniego, w którym połowa zmian jest w bazie, a połowa nie.
 *
 * ---------------------------------------------------------------------------
 * DLACZEGO SLUG NIE IDZIE ZA NAZWĄ
 *
 * Slug jest adresem: `/geografia/wlochy`. Poprawka literówki w nazwie nie może
 * przenieść kategorii pod inny adres, bo zabiłaby linki, które ktoś już
 * rozesłał, i pozycję w wyszukiwarce. Slug liczymy RAZ, przy utworzeniu.
 */
class CategoryController extends Controller
{
    public function index(Request $request, string $axis): Renderable|RedirectResponse
    {
        $shop = $request->user()->shop;
        abort_if($shop === null, 404);

        $current = CatalogAxis::bySegment($axis);
        abort_if($current === null, 404);

        $nodes = $shop->categories()->onAxis($current->key())->withCount('products')->get();

        return view('seller.categories.index', [
            'shop' => $shop,
            'axis' => $current,
            'axes' => CatalogAxis::all(),
            'rows' => Category::flatten($nodes),
            'nodes' => $nodes,
        ]);
    }

    /**
     * Zapis całej osi: poprawki istniejących węzłów, jeden nowy z pustego
     * wiersza i zaznaczone usunięcia.
     */
    public function save(Request $request, string $axis): RedirectResponse
    {
        $shop = $request->user()->shop;
        abort_if($shop === null, 404);

        $current = CatalogAxis::bySegment($axis);
        abort_if($current === null, 404);

        $data = $request->validate([
            'items' => ['array'],
            'items.*.name' => ['nullable', 'string', 'max:120'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'items.*.parent_id' => ['nullable', 'integer'],
        ]);

        $existing = $shop->categories()->onAxis($current->key())->get()->keyBy('id');
        $odpiete = 0;

        foreach ($data['items'] ?? [] as $key => $item) {
            $name = trim((string) ($item['name'] ?? ''));
            $node = $existing->get((int) $key);

            if ($name === '') {
                continue;
            }

            if ($node !== null && $request->boolean('items.'.$key.'._delete')) {
                // Węzeł z gałęzią pod spodem zabrałby ją ze sobą (kaskada
                // w bazie) — cicho i nieodwracalnie. Najpierw przenieś dzieci.
                if ($node->children()->exists()) {
                    return $this->back($current, 'error',
                        'Nie usunąłem „'.$node->name.'" — ma pod sobą inne pozycje. Przenieś je najpierw wyżej albo usuń.');
                }

                $odpiete += $node->products()->count();
                $node->delete();

                continue;
            }

            $attributes = [
                'name' => $name,
                'description' => trim((string) ($item['description'] ?? '')) ?: null,
                'position' => (int) ($item['position'] ?? 0),
            ];

            if ($current->hierarchical()) {
                $parent = $this->resolveParent($existing, $item['parent_id'] ?? null, $node, $current, $shop);

                if ($parent === false) {
                    return $this->back($current, 'error',
                        'Nie zapisałem „'.$name.'" — wskazany rodzic tworzyłby pętlę albo przekraczał dozwoloną głębokość.');
                }

                $attributes['parent_id'] = $parent?->id;
            }

            if ($node !== null) {
                $node->update($attributes);

                continue;
            }

            $attributes['axis'] = $current->key();
            $attributes['slug'] = $this->uniqueSlug($shop, $current, $name);

            $shop->categories()->create($attributes);
        }

        return $this->back($current, 'success', $odpiete > 0
            ? 'Zapisano. Usunięta pozycja przestała obejmować '.$odpiete.' '.$this->produkty($odpiete).' — same produkty zostały w katalogu.'
            : 'Zapisano podział „'.$current->label().'".');
    }

    /**
     * Rodzic ze zgłoszenia: `null` = korzeń, `false` = wskazanie niedozwolone.
     *
     * @param  Collection<int, Category>  $existing
     */
    private function resolveParent($existing, mixed $raw, ?Category $node, CatalogAxis $axis, Shop $shop): Category|null|false
    {
        if (blank($raw)) {
            return null;
        }

        $parent = $existing->get((int) $raw);

        if ($parent === null) {
            return false;
        }

        // Nowy węzeł jeszcze nie istnieje, więc nie może być własnym przodkiem —
        // sprawdzamy tylko głębokość. Istniejący przechodzi pełny test.
        $probe = $node ?? new Category(['axis' => $axis->key()]);
        $probe->shop_id ??= $shop->id;
        $probe->axis ??= $axis->key();

        return $probe->canHaveParent($parent) ? $parent : false;
    }

    /**
     * Slug unikalny w obrębie osi. Kolizja („Włochy" dwa razy) dostaje
     * przyrostek liczbowy zamiast błędu — sprzedawca wpisujący dwieście miast
     * nie ma szukać, które z nich się powtórzyło.
     */
    private function uniqueSlug(Shop $shop, CatalogAxis $axis, string $name): string
    {
        $base = Str::slug($name) ?: 'pozycja';
        $slug = $base;
        $n = 2;

        while ($shop->categories()->onAxis($axis->key())->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    private function back(CatalogAxis $axis, string $key, string $message): RedirectResponse
    {
        return redirect()->route('seller.categories.index', $axis->segment())->with($key, $message);
    }

    private function produkty(int $n): string
    {
        return $n === 1 ? 'produkt' : 'produktów';
    }
}
