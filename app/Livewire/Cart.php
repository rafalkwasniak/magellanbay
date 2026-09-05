<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\Shop;
use App\Services\CartService;
use App\Services\DiscountResolver;
use App\Support\DiscountResult;
use App\Support\Money;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Strona koszyka: lista pozycji, zmiana ilości (+/−), wpisywanie z palca,
 * usuwanie i suma. Stan trzyma CartService (sesja); komponent tylko go
 * modyfikuje i re-renderuje. Krok +/− zależy od jednostki produktu (1 szt.
 * albo 0,5 kg). Każda zmiana rozgłasza `cart-updated`, by licznik nadążał.
 */
class Cart extends Component
{
    public int $shopId;

    /** Kod wpisywany w polu „Mam kod rabatowy" (nie: kod już zastosowany). */
    public string $discountInput = '';

    public ?string $discountError = null;

    public function mount(int $shopId): void
    {
        $this->shopId = $shopId;
    }

    /*
     * POZYCJĄ RZĄDZI KLUCZ, NIE `product_id`. Magnes z imieniem „Zosia" i magnes
     * z imieniem „Antek" to jeden produkt w katalogu, ale dwie osobne pozycje
     * koszyka — z osobną ilością i osobnym koszem. Komponent operuje więc na
     * kluczu pozycji, a produkt odczytuje z niej dopiero po to, żeby poznać
     * jednostkę sprzedaży (krok i minimum).
     */
    public function increment(string $lineKey): void
    {
        $product = $this->productOfLine($lineKey);

        if ($product === null) {
            return;
        }

        $cart = app(CartService::class);
        $current = $cart->quantityOf($this->shopId, $lineKey);
        $cart->setQuantity($this->shopId, $lineKey, $current + $product->sale_unit->step());
        $this->dispatch('cart-updated');
    }

    public function decrement(string $lineKey): void
    {
        $product = $this->productOfLine($lineKey);

        if ($product === null) {
            return;
        }

        $cart = app(CartService::class);
        $current = $cart->quantityOf($this->shopId, $lineKey);
        $step = $product->sale_unit->step();

        // „−" schodzi tylko do minimum (1 szt. / 0,5 kg) — poniżej jest KOSZ,
        // żeby dwuklik nie skasował pozycji przez przypadek.
        if ($current - $step >= $product->sale_unit->minQuantity()) {
            $cart->setQuantity($this->shopId, $lineKey, $current - $step);
            $this->dispatch('cart-updated');
        }
    }

    /**
     * Produkt stojący za pozycją koszyka. Bierzemy go z SESJI, nie z parametru
     * wywołania — komponent Livewire da się wywołać z palca, a wtedy podany
     * `product_id` mógłby wskazywać co innego niż pozycja, którą zmieniamy.
     */
    private function productOfLine(string $lineKey): ?Product
    {
        $line = app(CartService::class)->raw($this->shopId)[$lineKey] ?? null;

        return $line === null ? null : $this->product($line['product_id']);
    }

    /**
     * Ilość wpisana z palca (pole w koszyku). Parsujemy polski zapis (przecinek,
     * spacje); CartService normalizuje wg jednostki, przycina do stanu i usuwa
     * pozycję, gdy zejdzie poniżej minimum.
     */
    public function updateQuantity(string $lineKey, string $value): void
    {
        $qty = (float) str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim($value));

        app(CartService::class)->setQuantity($this->shopId, $lineKey, $qty);
        $this->dispatch('cart-updated');
    }

    public function remove(string $lineKey): void
    {
        app(CartService::class)->remove($this->shopId, $lineKey);
        $this->dispatch('cart-updated');
    }

    /**
     * Wpisany kod rabatowy. Przyklejamy go do koszyka TYLKO gdy naprawdę działa —
     * kod odrzucony zostaje w polu razem z powodem, żeby klient mógł go poprawić,
     * a nie zgadywać, czy „się zapisał".
     */
    public function applyDiscount(): void
    {
        $shop = Shop::find($this->shopId);

        if ($shop === null) {
            return;
        }

        $result = app(DiscountResolver::class)->resolve(
            $shop,
            $this->discountInput,
            app(CartService::class)->lines($this->shopId),
            auth('customer')->user(),
        );

        if (! $result->accepted()) {
            $this->discountError = $result->error;

            return;
        }

        app(CartService::class)->setDiscountCode($this->shopId, $result->code->code);
        $this->discountInput = '';
        $this->discountError = null;
    }

    public function removeDiscount(): void
    {
        app(CartService::class)->clearDiscountCode($this->shopId);
        $this->discountError = null;
    }

    /**
     * Aktywny produkt tego sklepu (dla kroku/jednostki), lub null gdy zdjęty.
     */
    private function product(int $productId): ?Product
    {
        return Product::where('shop_id', $this->shopId)->where('is_active', true)->find($productId);
    }

    public function render()
    {
        // Jedno uzgodnienie na render: pozycje + komunikaty o korektach (spadł
        // stan / produkt zniknął), żeby klient nie zobaczył cicho zmienionego
        // koszyka bez wyjaśnienia.
        ['lines' => $lines, 'notices' => $notices] = app(CartService::class)->reconcile($this->shopId);

        // Przyklejony kod sprawdzamy PRZY KAŻDYM renderze, z aktualnym koszykiem.
        // Dzięki temu zniżka sama znika, gdy klient zejdzie poniżej progu albo
        // wyjmie produkt, którego kod dotyczył — i sama wraca, gdy dołoży.
        // Kody to funkcja płatna (Pawilon). Sklep bez uprawnienia nie pokazuje
        // nawet pola — obiecywanie zniżek, których nie ma jak wystawić, jest
        // gorsze niż brak pola.
        $discountsEnabled = (bool) Shop::find($this->shopId)?->entitlement('discount_codes');

        $discount = $discountsEnabled ? $this->resolveStoredDiscount($lines) : null;
        $itemsTotal = (float) $lines->sum('line_total');
        $itemsDiscount = $discount?->accepted() ? $discount->itemsDiscount : 0.0;

        return view('livewire.cart', [
            'lines' => $lines,
            'itemsTotal' => $itemsTotal,
            'discountsEnabled' => $discountsEnabled,
            'discount' => $discount,
            'discountCode' => $discountsEnabled ? app(CartService::class)->discountCode($this->shopId) : null,
            'discountIssue' => $this->discountIssue($discount),
            'discountNote' => $this->discountNote($discount),
            'total' => round($itemsTotal - $itemsDiscount, 2),
            'notices' => $notices,
        ]);
    }

    /**
     * Powód, dla którego kod nie działa — z wpisania („nie znamy takiego kodu")
     * albo z ponownego sprawdzenia przyklejonego kodu („koszyk zszedł poniżej
     * progu"). Dla klienta to ta sama sytuacja, więc i komunikat jest jeden.
     */
    private function discountIssue(?DiscountResult $discount): ?string
    {
        return $this->discountError
            ?? ($discount !== null && ! $discount->accepted() ? $discount->error : null);
    }

    /**
     * Potwierdzenie, że kod zadziałał — pokazywane w tym samym miejscu i w tej
     * samej ramce co powód odmowy; różni je tylko ikona.
     */
    private function discountNote(?DiscountResult $discount): ?string
    {
        if ($this->discountIssue($discount) !== null || $discount === null || ! $discount->accepted()) {
            return null;
        }

        return match (true) {
            $discount->freeShipping => 'Darmowa wysyłka — uwzględnimy ją w kasie.',
            $discount->itemsDiscount > 0 => 'Zniżka '.Money::pln($discount->itemsDiscount).' — już policzona poniżej.',
            default => null,
        };
    }

    /**
     * Wynik dla kodu zapisanego w sesji (null, gdy żadnego nie ma). Kodu, który
     * przestał działać, NIE odklejamy po cichu — pokazujemy powód, bo klient
     * zwykle może go przywrócić, dokładając coś do koszyka.
     *
     * @param  Collection<int, array{product: Product, quantity: float, unit_price: float, line_total: float}>  $lines
     */
    private function resolveStoredDiscount(Collection $lines): ?DiscountResult
    {
        $code = app(CartService::class)->discountCode($this->shopId);
        $shop = $code !== null ? Shop::find($this->shopId) : null;

        if ($code === null || $shop === null) {
            return null;
        }

        return app(DiscountResolver::class)->resolve($shop, $code, $lines, auth('customer')->user());
    }
}
