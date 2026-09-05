<?php

namespace App\Livewire;

use App\Enums\PriceComponentKind;
use App\Enums\SaleUnit;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Services\CartService;
use App\Support\ProductConfiguration;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Przycisk „Do koszyka" wraz z FORMULARZEM PERSONALIZACJI.
 *
 * Zna stan magazynowy produktu i ile jego sztuk jest już w koszyku, dzięki
 * czemu pokazuje dostępną ilość, blokuje się po dodaniu wszystkich sztuk
 * i odróżnia „wyprzedane" od „masz już wszystko".
 *
 * ---------------------------------------------------------------------------
 * DLACZEGO FORMULARZ SIEDZI TUTAJ, A NIE W OSOBNYM KOMPONENCIE
 *
 * Bo to jedna decyzja kupującego, nie dwie. Rozdzielone, dałyby stan, w którym
 * ktoś wpisał imię i nie kliknął „dodaj" — a komponent obok już myśli, że coś
 * jest w koszyku. Konfiguracja i dodanie muszą wyjść jednym ruchem.
 *
 * ---------------------------------------------------------------------------
 * NA KAFLU (compact) FORMULARZA NIE MA
 *
 * W siatce produktów nie ma miejsca na trzy grupy pól, a wciśnięte tam
 * zamieniłyby wykaz w formularz. Produkt personalizowany dostaje na kaflu
 * przycisk prowadzący na KARTĘ, gdzie jest miejsce na wybór. Bez tego kliknięcie
 * „Do koszyka" nie robiłoby nic — `CartService` odrzuca konfigurację bez
 * wymaganych pól — i wyglądałoby na zepsuty przycisk.
 */
class AddToCart extends Component
{
    public int $productId = 0;

    public int $shopId = 0;

    public bool $active = true;

    public bool $trackStock = false;

    public ?float $stock = null;

    public SaleUnit $unit = SaleUnit::Piece;

    /** Wariant zwarty: mniejszy przycisk, bez linii „Dostępne: N szt.". */
    public bool $compact = false;

    /**
     * Czy pokazać formularz personalizacji.
     *
     * OSOBNA FLAGA OD `compact`, choć kusiło, żeby użyć tamtej. `compact` steruje
     * WYGLĄDEM przycisku i jest włączone także na karcie produktu — oparcie o nią
     * chowało formularz również tam, gdzie ma się pokazać. Wyszło dopiero przy
     * sprawdzeniu na żywym adresie.
     */
    public bool $withOptions = true;

    /** Adres karty produktu — cel przycisku na kaflu produktu personalizowanego. */
    public string $productPath = '';

    /**
     * Odpowiedzi kupującego: [groupId => ['fields' => [fieldId => tekst]]]
     * albo [groupId => ['choice' => choiceId]].
     *
     * @var array<int, mixed>
     */
    public array $config = [];

    /**
     * Grupy wczytane na czas TEGO żądania. `Locked`, bo to nie jest stan
     * komponentu do odesłania w przeglądarkę — zawartość i tak liczymy z bazy.
     */
    private ?Collection $groupsCache = null;

    public function mount(Product $product, bool $compact = false, bool $withOptions = true): void
    {
        $this->withOptions = $withOptions;
        $this->productId = $product->id;
        $this->shopId = (int) $product->shop_id;
        $this->active = (bool) $product->is_active;
        $this->trackStock = (bool) $product->track_stock;
        $this->stock = $product->stock !== null ? (float) $product->stock : null;
        $this->unit = $product->sale_unit;
        $this->compact = $compact;
        $this->productPath = $product->storefrontPath();
    }

    /**
     * Reguły budowane Z GRUP PRZYPIĘTYCH DO PRODUKTU, nie wpisane na sztywno.
     *
     * Sprzedawca zmienia limit znaków w panelu i walidacja idzie za nim tego
     * samego dnia — inaczej formularz przyjąłby tekst, którego `CartService`
     * i tak nie wpuści, a kupujący dostałby przycisk, który „nic nie robi".
     *
     * Źródłem prawdy pozostaje `ProductConfiguration::normalise()`. To tutaj
     * jest wyłącznie warstwa UX: ma powiedzieć KTÓRE pole jest nie tak,
     * zamiast odrzucać całość bez wyjaśnienia.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [];

        foreach ($this->groups() as $group) {
            $prefix = 'config.'.$group->id;

            if ($group->isChoice()) {
                $ids = $group->choices->where('is_active', true)->pluck('id')->all();

                $rules[$prefix.'.choice'] = [
                    $group->required ? 'required' : 'nullable',
                    Rule::in($ids),
                ];

                continue;
            }

            // Rodzeństwo pól w grupie — potrzebne do `required_with`. Grupa
            // nieobowiązkowa staje się obowiązkowa DOPIERO, gdy kupujący zaczął
            // ją wypełniać: inaczej „Data" bez „Tekstu" przeszłaby jako pusty
            // grawer, za który i tak doliczylibyśmy koszt wykonania.
            $siblings = $group->fields->map(fn ($f) => $prefix.'.fields.'.$f->id)->all();

            foreach ($group->fields as $field) {
                $key = $prefix.'.fields.'.$field->id;
                $others = array_values(array_diff($siblings, [$key]));

                $required = match (true) {
                    ! $field->required => 'nullable',
                    $group->required => 'required',
                    $others === [] => 'nullable',
                    default => 'required_with:'.implode(',', $others),
                };

                $rules[$key] = [$required, 'string', 'max:'.$field->max_length];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        $names = [];

        foreach ($this->groups() as $group) {
            $names['config.'.$group->id.'.choice'] = mb_strtolower($group->name);

            foreach ($group->fields as $field) {
                $names['config.'.$group->id.'.fields.'.$field->id] = mb_strtolower($field->label);
            }
        }

        return $names;
    }

    public function add(CartService $cart): void
    {
        $product = Product::where('is_active', true)->find($this->productId);

        if ($product === null) {
            return;
        }

        /*
         * Walidujemy TYLKO produkt z grupami opcji. Przy zwykłym produkcie
         * `rules()` jest puste, a Livewire pusty zestaw reguł traktuje jak ich
         * BRAK i rzuca wyjątkiem — czyli zwykłe „Do koszyka" przestałoby
         * działać przez funkcję, która go nie dotyczy.
         */
        if ($this->rules() !== []) {
            $this->validate();
        }

        /*
         * Wykluczanie grup sprawdzamy PO walidacji pól, bo to warunek między
         * grupami, a nie w obrębie jednej. Komunikat wieszamy na tej grupie,
         * którą kupujący wypełnił jako drugą — na niej ma cofnąć wybór.
         */
        if (($kolizja = $this->conflictingGroup()) !== null) {
            $this->addError(
                'config.'.$kolizja['group']->id.'.'.($kolizja['group']->isChoice() ? 'choice' : 'fields'),
                'Możesz wybrać albo „'.$kolizja['other']->name.'", albo „'.$kolizja['group']->name.'" — nie oba naraz.'
            );

            return;
        }

        // Ostatnia bramka. Gdyby konfiguracja mimo wszystko nie przeszła (np.
        // sprzedawca wycofał grafikę w trakcie wypełniania formularza), mówimy
        // to wprost, zamiast zostawiać przycisk, który nic nie robi.
        if (ProductConfiguration::normalise($product, $this->config) === null) {
            $this->addError('config', 'Tej personalizacji nie da się już zamówić — odśwież stronę i wybierz ponownie.');

            return;
        }

        $cart->add($product, null, $this->config);
        $this->dispatch('cart-updated');
    }

    /**
     * Para grup, które się wykluczają, a obie zostały wypełnione.
     *
     * @return array{group: OptionGroup, other: OptionGroup}|null
     */
    private function conflictingGroup(): ?array
    {
        $groups = $this->groups()->keyBy('id');

        foreach ($groups as $group) {
            $otherId = $group->excludes_group_id;

            if ($otherId === null || ! $groups->has($otherId)) {
                continue;
            }

            if ($this->filled($group->id) && $this->filled($otherId)) {
                return ['group' => $group, 'other' => $groups->get($otherId)];
            }
        }

        return null;
    }

    private function filled(int $groupId): bool
    {
        $answer = $this->config[$groupId] ?? [];

        if (filled($answer['choice'] ?? null)) {
            return true;
        }

        foreach ($answer['fields'] ?? [] as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Grupy opcji produktu, wczytane raz na ŻĄDANIE.
     *
     * Cache siedzi na instancji, nie w `static` — i to nie jest szczegół stylu.
     * Statyczna tablica przeżywa cały proces PHP, więc trzymałaby grupy jednego
     * produktu dla wszystkich kolejnych o tym samym identyfikatorze i nigdy nie
     * zauważyłaby, że sprzedawca właśnie wycofał grafikę. Złapały to testy:
     * produkt bez personalizacji dostawał formularz po cudzym.
     *
     * Komponent Livewire renderuje się przy każdym wpisanym znaku, a bez cache'u
     * każde takie renderowanie pytałoby bazę dwa razy (raz `rules()`, raz widok).
     *
     * @return Collection<int, OptionGroup>
     */
    private function groups(): Collection
    {
        return $this->groupsCache ??= Product::find($this->productId)
            ?->optionGroups()->with(['fields', 'choices'])->get()
            ?? collect();
    }

    /** Utrzymuje przycisk w zgodzie z koszykiem, gdy zmieni go inny komponent. */
    #[On('cart-updated')]
    public function refresh(): void {}

    public function render()
    {
        $product = Product::find($this->productId);
        $groups = $this->groups();

        // Suma po WSZYSTKICH konfiguracjach produktu: kupującego interesuje,
        // czy ma już ten magnes i ile mu zostało ze stanu, a nie w ilu wariantach
        // napisu leży w koszyku.
        $inCart = app(CartService::class)->quantityOfProduct($this->shopId, $this->productId);
        $limited = $this->trackStock && $this->stock !== null;
        $remaining = $limited ? max(0, $this->stock - $inCart) : null;

        /*
         * ROZBICIE CENY NA ŻYWO — „cena z czterech części widocznych klientowi
         * przy zamawianiu" wprost z zamówienia klienta. Liczone z bieżących
         * odpowiedzi, więc kwota rośnie w chwili wyboru grafiki, a nie dopiero
         * w koszyku. Przy odpowiedziach jeszcze niekompletnych pokazujemy to,
         * co da się policzyć — czyli sam produkt.
         */
        $config = $product !== null ? ProductConfiguration::normalise($product, $this->config) : null;
        $breakdown = $product !== null
            ? ProductConfiguration::breakdown($product, $config ?? [])
            : [];

        return view('livewire.add-to-cart', [
            'limited' => $limited,
            'stock' => $this->stock,
            'inCart' => $inCart,
            'remaining' => $remaining,
            'canAdd' => $this->active && (! $limited || $remaining > 0),
            'groups' => $groups,
            // Tam, gdzie formularza nie ma (kafel w wykazie), przycisk prowadzi
            // na kartę produktu — inaczej „Do koszyka" nie robiłoby nic.
            'needsCard' => ! $this->withOptions && $groups->isNotEmpty(),
            'breakdown' => $breakdown,
            'total' => round(array_sum(array_column($breakdown, 'amount')), 2),
            'licenceKind' => PriceComponentKind::Licence,
        ]);
    }
}
