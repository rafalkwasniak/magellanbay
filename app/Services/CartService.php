<?php

namespace App\Services;

use App\Models\Product;
use App\Support\ProductConfiguration;
use Illuminate\Support\Collection;

/**
 * Koszyk gościa trzymany w sesji (a ta w bazie — SESSION_DRIVER=database).
 *
 * ZASADA BEZPIECZEŃSTWA, KTÓRA SIĘ NIE ZMIENIŁA: w sesji ląduje wyłącznie to, co
 * kupujący WYBRAŁ — produkt, ilość i konfiguracja personalizacji. Nazwę, cenę,
 * dopłaty i stan magazynowy pobieramy świeżo z bazy przy każdym renderze, więc
 * koszyka nie da się „zepsuć" starą ceną ani podmianą wartości w sesji.
 *
 * KLUCZEM POZYCJI PRZESTAŁ BYĆ `product_id`. Magnes z imieniem „Zosia" i magnes
 * z imieniem „Antek" to jeden produkt w katalogu, ale dwie różne rzeczy do
 * wykonania — muszą więc leżeć w koszyku osobno, z osobną ilością i osobnym
 * przyciskiem „usuń". Klucz liczy `ProductConfiguration::key()`; produkt bez
 * personalizacji zachowuje czytelne `p12`.
 *
 * KSZTAŁT SESJI:
 *
 *     [shop_id => [
 *         'p12'       => ['product_id' => 12, 'quantity' => 2.0, 'config' => []],
 *         'p12:a9f3…' => ['product_id' => 12, 'quantity' => 1.0, 'config' => [...]],
 *     ]]
 *
 * Koszyk jest kluczowany po `shop_id`: storefront to jeden sklep, a klucz i tak
 * zabezpiecza przed pomieszaniem, gdyby sesja była współdzielona.
 */
class CartService
{
    /** Sesyjny worek wszystkich koszyków: [shop_id => [lineKey => pozycja]]. */
    private const KEY = 'carts';

    /** Kody rabatowe przyklejone do koszyków: [shop_id => 'KOD']. */
    private const DISCOUNT_KEY = 'cart_discounts';

    /**
     * Surowa zawartość koszyka sklepu: [lineKey => ['product_id', 'quantity', 'config']].
     * Kolejność wstawiania zachowana.
     *
     * @return array<string, array{product_id: int, quantity: float, config: array<int, mixed>}>
     */
    public function raw(int $shopId): array
    {
        return $this->migrateLegacy(session()->get(self::KEY.'.'.$shopId, []));
    }

    /**
     * Koszyki sprzed personalizacji miały kształt `[product_id => qty]`.
     *
     * Sesje żyją tygodniami, więc w dniu wdrożenia część ludzi ma w przeglądarce
     * stary koszyk. Bez tego przejścia dostaliby błąd typu przy pierwszym
     * kliknięciu — a najgorszy moment na awarię koszyka to ten, w którym ktoś
     * właśnie kupuje. Przepisujemy w locie, bez ostrzeżeń: stara pozycja to po
     * prostu pozycja bez konfiguracji.
     *
     * @param  array<mixed>  $cart
     * @return array<string, array{product_id: int, quantity: float, config: array<int, mixed>}>
     */
    private function migrateLegacy(array $cart): array
    {
        $out = [];

        foreach ($cart as $key => $value) {
            if (is_array($value) && isset($value['product_id'])) {
                $out[(string) $key] = [
                    'product_id' => (int) $value['product_id'],
                    'quantity' => (float) $value['quantity'],
                    'config' => is_array($value['config'] ?? null) ? $value['config'] : [],
                ];

                continue;
            }

            $productId = (int) $key;
            $out[ProductConfiguration::key($productId, [])] = [
                'product_id' => $productId,
                'quantity' => (float) $value,
                'config' => [],
            ];
        }

        return $out;
    }

    /**
     * Liczba POZYCJI w koszyku (do licznika w nagłówku) — nie suma ilości, bo ta
     * miesza sztuki z kilogramami i dawałaby przy wadze ułamek („4,5"). Liczona
     * z samej sesji, bez zapytań; pełne uzgodnienie robi strona koszyka.
     */
    public function count(int $shopId): int
    {
        return count($this->raw($shopId));
    }

    /**
     * Ilość konkretnej POZYCJI (produkt + konfiguracja).
     */
    public function quantityOf(int $shopId, string $lineKey): float
    {
        return (float) ($this->raw($shopId)[$lineKey]['quantity'] ?? 0);
    }

    /**
     * Łączna ilość danego PRODUKTU we wszystkich jego konfiguracjach.
     *
     * Do przycisku „w koszyku" na karcie produktu: kupującego interesuje, czy ma
     * już ten magnes, a nie w ilu wariantach napisu.
     */
    public function quantityOfProduct(int $shopId, int $productId): float
    {
        $sum = 0.0;

        foreach ($this->raw($shopId) as $line) {
            if ($line['product_id'] === $productId) {
                $sum += $line['quantity'];
            }
        }

        return $sum;
    }

    /**
     * Dodaje produkt w danej konfiguracji (zwiększa ilość o $qty; domyślnie o krok
     * jednostki — 1 szt. albo 0,5 kg). Ignoruje produkt spoza sklepu, nieaktywny,
     * taki, którego sklep nie ma czym dostarczyć albo czym opłacić, oraz
     * konfigurację nie do przyjęcia.
     *
     * Warunki są tu, a nie tylko w widoku: ukrycie przycisku to UX, nie
     * zabezpieczenie — komponent Livewire da się wywołać z palca.
     *
     * @param  array<mixed>  $configuration  surowe wejście z formularza
     */
    public function add(Product $product, ?float $qty = null, array $configuration = []): void
    {
        $qty ??= $product->sale_unit->step();

        if ($product->shop_id === null || ! $product->is_active || $qty <= 0) {
            return;
        }

        if ($product->shop?->acceptsOrders() !== true) {
            return;
        }

        /*
         * Wstrzymana seria nie wchodzi do koszyka. Blokada w widoku sama nie
         * wystarcza: przycisk znika, ale żądanie da się wysłać — a wstrzymanie
         * sprzedaży ma znaczyć „nie sprzedajemy", nie „nie pokazujemy".
         */
        if ($product->isSaleSuspended()) {
            return;
        }

        $config = ProductConfiguration::normalise($product, $configuration);

        // `null` = konfiguracja odrzucona (brak wymaganego pola, za długi tekst,
        // wygaszona pozycja biblioteki, wykluczające się grupy). Nie zgadujemy,
        // co kupujący miał na myśli — pozycja po prostu nie wchodzi do koszyka.
        if ($config === null) {
            return;
        }

        $key = ProductConfiguration::key($product->id, $config);
        $cart = $this->raw($product->shop_id);

        /*
         * Stan magazynowy dzieli się między WSZYSTKIE konfiguracje produktu.
         * Odkąd klucz przestał być `product_id`, ten sam magnes leży w koszyku
         * w kilku pozycjach — a przycinanie każdej osobno pozwoliłoby przy stanie
         * 3 sztuk mieć 3 „dla Zosi" i 3 „dla Antka". Sklep sprzedałby sześć
         * sztuk czegoś, czego ma trzy.
         */
        $inOtherLines = 0.0;
        foreach ($cart as $otherKey => $line) {
            if ($otherKey !== $key && $line['product_id'] === $product->id) {
                $inOtherLines += $line['quantity'];
            }
        }

        $wanted = ($cart[$key]['quantity'] ?? 0) + $qty;
        $allowed = $this->capToStock($product, $inOtherLines + $wanted) - $inOtherLines;

        if ($allowed <= 0) {
            return;
        }

        $cart[$key] = [
            'product_id' => $product->id,
            'quantity' => $allowed,
            'config' => $config,
        ];

        session()->put(self::KEY.'.'.$product->shop_id, $cart);
    }

    /**
     * Ustawia dokładną ilość POZYCJI. Ilość normalizowana wg jednostki produktu
     * (waga do 2 miejsc, sztuki do całkowitej); poniżej minimum lub ≤ 0 usuwa
     * pozycję.
     */
    public function setQuantity(int $shopId, string $lineKey, float $qty): void
    {
        $cart = $this->raw($shopId);
        $line = $cart[$lineKey] ?? null;

        if ($line === null) {
            return;
        }

        $product = Product::where('shop_id', $shopId)
            ->where('is_active', true)
            ->find($line['product_id']);

        if ($product === null) {
            $this->remove($shopId, $lineKey);

            return;
        }

        $qty = $product->sale_unit->normalizeQuantity($qty);

        if ($qty <= 0) {
            $this->remove($shopId, $lineKey);

            return;
        }

        $cart[$lineKey]['quantity'] = $this->capToStock($product, $qty);
        session()->put(self::KEY.'.'.$shopId, $cart);
    }

    public function remove(int $shopId, string $lineKey): void
    {
        $cart = $this->raw($shopId);
        unset($cart[$lineKey]);
        session()->put(self::KEY.'.'.$shopId, $cart);
    }

    public function clear(int $shopId): void
    {
        session()->forget(self::KEY.'.'.$shopId);
        $this->clearDiscountCode($shopId);
    }

    /**
     * Kod rabatowy przyklejony do koszyka. W sesji trzymamy WYŁĄCZNIE sam kod —
     * nigdy wyliczonej kwoty. Zniżkę przeliczamy przy każdym renderze z aktualnej
     * zawartości koszyka, więc nie da się jej zamrozić przez dołożenie i wyjęcie
     * produktów ani podmianę wartości w sesji (ta sama zasada co przy cenach).
     */
    public function discountCode(int $shopId): ?string
    {
        $code = session()->get(self::DISCOUNT_KEY.'.'.$shopId);

        return is_string($code) && $code !== '' ? $code : null;
    }

    public function setDiscountCode(int $shopId, string $code): void
    {
        session()->put(self::DISCOUNT_KEY.'.'.$shopId, mb_strtoupper(trim($code)));
    }

    public function clearDiscountCode(int $shopId): void
    {
        session()->forget(self::DISCOUNT_KEY.'.'.$shopId);
    }

    /**
     * Nadpisuje cały koszyk sklepu uzgodnioną mapą pozycji. Używane przy
     * finalnej weryfikacji dostępności przed złożeniem zamówienia.
     *
     * @param  array<string, array{product_id: int, quantity: float, config: array<int, mixed>}>  $items
     */
    public function overwrite(int $shopId, array $items): void
    {
        if ($items === []) {
            $this->clear($shopId);

            return;
        }

        session()->put(self::KEY.'.'.$shopId, $items);
    }

    /**
     * Uzgodnione pozycje koszyka + komunikaty o dokonanych korektach — do banera
     * na stronie koszyka. Zwraca świeże produkty z bazy, ilości przycięte do
     * stanu, pomija produkty nieistniejące/nieaktywne/wyprzedane (i czyści je
     * z sesji). Treść komunikatów jest ta sama co przy składaniu zamówienia
     * (OrderService), żeby klient słyszał jeden, spójny głos na obu etapach.
     *
     * `unit_price` to CENA POZYCJI: cena produktu plus dopłata za konfigurację.
     * Rozbicie zostaje osobno (`base_price`, `surcharge`), bo kasa ma pokazać
     * kupującemu, za co dopłaca, a nie samą sumę.
     *
     * @return array{lines: Collection<int, array<string, mixed>>, notices: list<string>}
     */
    public function reconcile(int $shopId): array
    {
        $raw = $this->raw($shopId);

        if ($raw === []) {
            return ['lines' => collect(), 'notices' => []];
        }

        $products = Product::where('shop_id', $shopId)
            ->with('categories')
            ->where('is_active', true)
            ->whereIn('id', array_column($raw, 'product_id'))
            ->get()
            ->keyBy('id');

        $reconciled = [];
        $notices = [];

        // Stan magazynowy zużywany NARASTAJĄCO przez kolejne pozycje tego samego
        // produktu — patrz komentarz w `add()`. Bez tego dwie konfiguracje jednego
        // magnesu przy stanie 3 dałyby razem 6 sztuk.
        $used = [];

        $lines = collect(array_keys($raw))
            ->map(function (string $lineKey) use ($raw, $products, &$reconciled, &$notices, &$used) {
                $line = $raw[$lineKey];
                $product = $products->get($line['product_id']);
                $requested = (float) $line['quantity'];

                if ($product === null) {
                    $notices[] = 'Jeden lub więcej produktów nie jest już dostępnych i został usunięty z koszyka.';

                    return null;    // zdjęty/usunięty — wypadnie z koszyka
                }

                /*
                 * Sprzedawca mógł wstrzymać serię, gdy pozycja już leżała
                 * w koszyku. Zamówienie na wstrzymany towar byłoby zamówieniem,
                 * którego nie da się zrealizować — mówimy to teraz, a nie po
                 * pobraniu pieniędzy.
                 */
                if ($product->isSaleSuspended()) {
                    $notices[] = 'Sprzedaż produktu „'.$product->name.'" została wstrzymana — pozycja wypadła z koszyka.';

                    return null;
                }

                $alreadyUsed = $used[$product->id] ?? 0.0;
                $qty = $this->capToStock($product, $alreadyUsed + $requested) - $alreadyUsed;

                if ($qty <= 0) {
                    $notices[] = 'Produkt „'.$product->name.'" jest wyprzedany i został usunięty z koszyka.';

                    return null;    // wyprzedany — wypada
                }

                if ($qty < $requested) {
                    $notices[] = 'Ilość „'.$product->name.'" została dostosowana do dostępności ('.$product->sale_unit->formatQuantity($qty).').';
                }

                /*
                 * Konfigurację przepuszczamy przez normalizację PONOWNIE, przy
                 * każdym uzgodnieniu. Sprzedawca mógł w międzyczasie wycofać
                 * grafikę albo zacieśnić limit znaków, a wtedy leżąca w sesji
                 * pozycja jest niewykonalna — lepiej powiedzieć to teraz niż
                 * przyjąć zamówienie, którego nie da się zrealizować.
                 */
                $config = ProductConfiguration::normalise($product, $line['config']);

                if ($config === null) {
                    $notices[] = 'Personalizacja produktu „'.$product->name.'" jest już niedostępna — pozycja została usunięta z koszyka.';

                    return null;
                }

                $used[$product->id] = ($used[$product->id] ?? 0.0) + $qty;

                $reconciled[$lineKey] = [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'config' => $config,
                ];

                $base = (float) $product->price_gross;
                $surcharge = ProductConfiguration::surcharge($product, $config);
                $unit = round($base + $surcharge, 2);

                return [
                    'key' => $lineKey,
                    'product' => $product,
                    'quantity' => $qty,
                    'configuration' => $config,
                    'personalisation' => ProductConfiguration::describe($product, $config),
                    'base_price' => $base,
                    'surcharge' => $surcharge,
                    'unit_price' => $unit,
                    'line_total' => round($unit * $qty, 2),
                ];
            })
            ->filter()
            ->values();

        // Zapisujemy uzgodnioną wersję z powrotem, żeby licznik i sesja nie
        // trzymały pozycji, których już nie ma.
        if ($reconciled !== $raw) {
            session()->put(self::KEY.'.'.$shopId, $reconciled);
        }

        return ['lines' => $lines, 'notices' => array_values(array_unique($notices))];
    }

    /**
     * Same pozycje koszyka, bez komunikatów o korektach (cienki wrapper na
     * reconcile()). Używane tam, gdzie liczy się tylko zawartość/suma.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function lines(int $shopId): Collection
    {
        return $this->reconcile($shopId)['lines'];
    }

    public function total(int $shopId): float
    {
        return $this->lines($shopId)->sum('line_total');
    }

    /**
     * Ilość ograniczona stanem magazynowym, gdy produkt go śledzi. Bez śledzenia
     * (usługa/nielimitowany) ilość bez ograniczeń.
     */
    private function capToStock(Product $product, float $qty): float
    {
        if ($product->track_stock && $product->stock !== null) {
            return max(0, min($qty, (float) $product->stock));
        }

        return max(0, $qty);
    }
}
