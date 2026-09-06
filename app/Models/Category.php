<?php

namespace App\Models;

use App\Support\CatalogAxis;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Węzeł katalogu na jednej z osi podziału (rodzaj / tematyka / geografia).
 *
 * Nazwa jest celowo bezbarwna — `Category`, nie `ProductLine` ani `Region`.
 * Osie to konfiguracja, więc ten sam model obsługuje „Kamień", „UNESCO"
 * i „Rzym", a kolejny sklep doda sobie „Kolor" bez linijki kodu.
 *
 * @property-read string $axis
 */
#[Fillable(['axis', 'parent_id', 'name', 'slug', 'description', 'position', 'sales_suspended_at', 'sales_resume_on', 'suspension_note'])]
class Category extends Model
{
    protected function casts(): array
    {
        return [
            'sales_suspended_at' => 'datetime',
            'sales_resume_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->ordered();
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name');
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeOnAxis(Builder $query, string $axis): void
    {
        $query->where('axis', $axis);
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * Czy sprzedaż tej serii jest wstrzymana TERAZ.
     *
     * WZNOWIENIE DZIEJE SIĘ SAMO: data z przeszłości znaczy, że sprzedaż już
     * wróciła. Nie ma zadania w tle, które musiałoby ją odwiesić — bo gdyby
     * takie zadanie nie przeszło, sklep traciłby pieniądze w ciszy, a tu crona
     * nie ma z rozmysłu (CLAUDE.md sek. 2).
     *
     * Brak daty = wstrzymanie bezterminowe, do ręcznego wznowienia.
     */
    public function salesSuspended(): bool
    {
        if ($this->sales_suspended_at === null) {
            return false;
        }

        return $this->sales_resume_on === null || $this->sales_resume_on->isFuture();
    }

    /**
     * Zdanie dla kupującego — dlaczego nie da się tego kupić i kiedy wróci.
     *
     * Komunikat sprzedawcy ma pierwszeństwo; domyślny mówi to samo własnymi
     * słowami, żeby wstrzymanie bez wpisanego tekstu nie wyglądało jak usterka.
     */
    public function suspensionMessage(): string
    {
        if (filled($this->suspension_note)) {
            return (string) $this->suspension_note;
        }

        return $this->sales_resume_on !== null
            ? 'Sprzedaż tej serii jest chwilowo wstrzymana. Wznowienie planujemy '.$this->sales_resume_on->format('d.m.Y').'.'
            : 'Sprzedaż tej serii jest chwilowo wstrzymana.';
    }

    /**
     * Kanoniczny adres kategorii na storefroncie: /geografia/rzym.
     *
     * ADRES NIE NIESIE HIERARCHII — „Rzym" stoi pod `/geografia/rzym`, a nie
     * pod `/geografia/wlochy/rzym`. Przeniesienie Rzymu z Włoch do Europy jest
     * poprawką w panelu, a nie przeprowadzką całego adresu, po której padają
     * rozesłane linki i pozycja w wyszukiwarce. Ścieżkę pokazujemy okruszkami.
     *
     * Względny, jak przy produkcie: storefront to jeden host.
     */
    public function storefrontPath(): string
    {
        return '/'.($this->axis()?->segment() ?? 'katalog').'/'.$this->slug;
    }

    public function axis(): ?CatalogAxis
    {
        return CatalogAxis::find($this->axis);
    }

    /**
     * Ścieżka od korzenia do tego węzła włącznie — „Włochy › Rzym".
     *
     * Idziemy w górę po `parent`, a nie zapytaniem rekurencyjnym: drzewo ma
     * najwyżej `catalog.max_depth` poziomów, więc to najwyżej trzy odczyty,
     * a kod zostaje czytelny i przenośny między silnikami bazy.
     *
     * @return list<Category>
     */
    public function path(): array
    {
        $path = [$this];
        $node = $this;
        $guard = (int) config('catalog.max_depth', 3) + 2;

        // Licznik jest zabezpieczeniem przed cyklem w danych (A rodzicem B,
        // B rodzicem A). Walidacja go nie dopuszcza, ale pętla nieskończona
        // w widoku katalogu kosztowałaby cały serwer, a nie jedną stronę.
        while ($node->parent_id !== null && $guard-- > 0) {
            $node = $node->parent;

            if ($node === null) {
                break;
            }

            array_unshift($path, $node);
        }

        return $path;
    }

    public function depth(): int
    {
        return count($this->path());
    }

    /**
     * Identyfikatory tego węzła i wszystkiego, co pod nim.
     *
     * Po to, żeby „Włochy" pokazywały także magnesy przypięte wyłącznie do
     * „Rzymu". Bez tego wyższy poziom hierarchii byłby pusty i wyglądałby
     * jak usterka.
     *
     * @return list<int>
     */
    public function branchIds(): array
    {
        $ids = [$this->id];
        $level = [$this->id];
        $guard = (int) config('catalog.max_depth', 3) + 2;

        while ($level !== [] && $guard-- > 0) {
            $level = static::query()
                ->whereIn('parent_id', $level)
                ->pluck('id')
                ->all();

            $ids = array_merge($ids, $level);
        }

        return $ids;
    }

    /**
     * Czy węzeł może dostać `$candidate` za rodzica.
     *
     * Odrzuca samego siebie i własnych potomków — inaczej gałąź odrywa się od
     * drzewa i znika z katalogu razem z produktami, nie dając o sobie znać.
     */
    public function canHaveParent(?Category $candidate): bool
    {
        if ($candidate === null) {
            return true;
        }

        if ($candidate->axis !== $this->axis || $candidate->shop_id !== $this->shop_id) {
            return false;
        }

        if ($this->exists && in_array($candidate->id, $this->branchIds(), true)) {
            return false;
        }

        return $candidate->depth() < ($this->axis()?->maxDepth() ?? 1);
    }

    /**
     * Drzewo jednej osi sklepu, spłaszczone do listy z poziomem — do wyboru
     * w formularzu i do listy w panelu.
     *
     * Jedno zapytanie zamiast rekurencji po bazie: przy geografii liczonej
     * w setkach węzłów N+1 zabiłby ekran, na którym sprzedawca spędza czas.
     *
     * @param  Collection<int, Category>  $nodes
     * @return list<array{category: Category, depth: int}>
     */
    public static function flatten(Collection $nodes, ?int $parentId = null, int $depth = 0): array
    {
        $out = [];

        $level = $nodes->where('parent_id', $parentId)
            ->sortBy(fn (Category $node): string => sprintf('%08d %s', $node->position, $node->name));

        foreach ($level as $node) {
            $out[] = ['category' => $node, 'depth' => $depth];
            $out = array_merge($out, static::flatten($nodes, $node->id, $depth + 1));
        }

        return $out;
    }
}
