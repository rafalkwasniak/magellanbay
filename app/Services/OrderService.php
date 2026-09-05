<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Exceptions\CartNeedsReviewException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Support\DiscountResult;
use App\Support\OrderFlow;
use App\Support\ProductConfiguration;
use Illuminate\Support\Facades\DB;

/**
 * Składanie zamówienia. Cała operacja w jednej transakcji z blokadą wierszy
 * produktów, żeby finalna weryfikacja dostępności i zdjęcie ze stanu były
 * atomowe (spec „Finalna weryfikacja zamówienia", „Rezerwacja produktów").
 * Gdy dostępność zmieniła się od widoku koszyka — uzgadniamy koszyk i rzucamy
 * CartNeedsReviewException; zamówienie nie powstaje.
 */
class OrderService
{
    public function __construct(
        private CartService $cart,
        private OrderMailer $mailer,
        private OrderTotals $totals,
        private CustomerActivationMailer $activationMailer,
        private DiscountResolver $discounts,
    ) {}

    /**
     * @param  array<string, mixed>  $data  zwalidowane dane kupującego + metody
     * @param  ?Customer  $authCustomer  zalogowany klient (jeśli jest) — do niego
     *                                   przypinamy zamówienie bez pytania o e-mail
     */
    public function place(Shop $shop, array $data, ?Customer $authCustomer = null): Order
    {
        // Ustalone w transakcji, użyte po commicie (mail aktywacyjny dla nowego konta).
        $customer = null;
        $needsActivation = false;

        $order = DB::transaction(function () use ($shop, $data, $authCustomer, &$customer, &$needsActivation): Order {
            $raw = $this->cart->raw($shop->id);

            if ($raw === []) {
                throw new CartNeedsReviewException(['Twój koszyk jest pusty.']);
            }

            // Blokada wierszy produktów — nikt równolegle nie zmieni stanu w trakcie.
            $products = Product::where('shop_id', $shop->id)
                ->whereIn('id', array_column($raw, 'product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $messages = [];
            $reconciled = [];
            $lines = [];

            /*
             * Stan magazynowy zużywany NARASTAJĄCO. Odkąd klucz pozycji przestał
             * być `product_id`, ten sam produkt bywa w koszyku kilka razy w różnych
             * konfiguracjach — sprawdzanie każdej z osobna przepuściłoby zamówienie
             * na 6 sztuk czegoś, czego sklep ma 3. To jest ten moment, w którym
             * taki błąd kosztuje najwięcej: zamówienie już przyjęte i opłacone.
             */
            $used = [];

            foreach ($raw as $lineKey => $line) {
                $product = $products->get($line['product_id']);
                $qty = (float) $line['quantity'];

                if ($product === null || ! $product->is_active) {
                    $messages[] = 'Jeden lub więcej produktów nie jest już dostępnych i został usunięty z koszyka.';

                    continue;
                }

                $limited = $product->track_stock && $product->stock !== null;
                $alreadyUsed = $used[$product->id] ?? 0.0;
                $finalQty = $limited
                    ? max(0.0, min($qty, (float) $product->stock - $alreadyUsed))
                    : $qty;

                if ($finalQty <= 0) {
                    $messages[] = 'Produkt „'.$product->name.'" jest wyprzedany i został usunięty z koszyka.';

                    continue;
                }

                if ($finalQty < $qty) {
                    $messages[] = 'Ilość „'.$product->name.'" została dostosowana do dostępności ('.$product->sale_unit->formatQuantity($finalQty).').';
                }

                // Konfigurację sprawdzamy PONOWNIE, na świeżych danych: sprzedawca
                // mógł w międzyczasie wycofać grafikę albo zacieśnić limit znaków,
                // a wtedy zamówienie byłoby niewykonalne w chwili przyjęcia.
                $config = ProductConfiguration::normalise($product, $line['config']);

                if ($config === null) {
                    $messages[] = 'Personalizacja produktu „'.$product->name.'" jest już niedostępna — pozycja została usunięta z koszyka.';

                    continue;
                }

                $used[$product->id] = $alreadyUsed + $finalQty;

                $reconciled[$lineKey] = [
                    'product_id' => $product->id,
                    'quantity' => $finalQty,
                    'config' => $config,
                ];

                $lines[] = [
                    'product' => $product,
                    'quantity' => $finalQty,
                    'configuration' => $config,
                    'surcharge' => ProductConfiguration::surcharge($product, $config),
                ];
            }

            if ($messages !== []) {
                // Zapisz uzgodniony koszyk i przerwij — klient sprawdza i składa ponownie.
                $this->cart->overwrite($shop->id, $reconciled);

                throw new CartNeedsReviewException(array_values(array_unique($messages)));
            }

            // Rozwiązanie konta klienta (po uzgodnieniu koszyka, bo tylko gdy
            // zamówienie faktycznie powstaje): zalogowany → jego konto; e-mail z
            // kontem → cicho dopisz; „załóż konto" na wolnym e-mailu → nowe konto
            // nieaktywne (mail aktywacyjny po commicie); inaczej gość.
            [$customer, $needsActivation] = $this->resolveCustomer($shop, $data, $authCustomer);

            $order = $this->createOrder($shop, $data, $lines, $customer);

            // Powiadomienie „nowe zamówienie" dla sprzedawcy — licznik na sklepie
            // rośnie atomowo (UPDATE … + 1), zeruje wejście na listę Zamówień.
            $shop->increment('unseen_orders_count');

            $this->cart->clear($shop->id);

            return $order;
        });

        // Po pomyślnym commicie kolejkujemy maile (outbox → cron): potwierdzenie
        // dla klienta i powiadomienie dla sprzedawcy. Przerwana weryfikacja
        // (CartNeedsReviewException) rzuca w transakcji — tu już nie dojdziemy.
        $this->mailer->confirmToCustomer($order);
        $this->mailer->notifySeller($order);

        // Nowe konto z kasy: link aktywacyjny „od sklepu" (double-opt-in), by klient
        // ustawił hasło. Zamówienie jest już przypięte do tego konta (customer_id).
        if ($needsActivation && $customer !== null) {
            $this->activationMailer->send($customer);
        }

        return $order;
    }

    /**
     * Ustala konto klienta dla zamówienia i czy trzeba wysłać link aktywacyjny.
     * Kolejność: (1) zalogowany klient tego sklepu wygrywa; (2) istniejące konto
     * na ten e-mail w tym sklepie — cicha migawka do historii (bez maila); (3)
     * zaznaczono „załóż konto" na wolnym e-mailu — nowe konto NIEAKTYWNE z danymi
     * z kasy (mail aktywacyjny po commicie); (4) w innym razie gość (null).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ?Customer, 1: bool}
     */
    private function resolveCustomer(Shop $shop, array $data, ?Customer $authCustomer): array
    {
        if ($authCustomer !== null && $authCustomer->shop_id === $shop->id) {
            return [$authCustomer, false];
        }

        $email = (string) ($data['buyer_email'] ?? '');

        $existing = $shop->customers()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        if (! empty($data['create_account']) && filled($email)) {
            $customer = $shop->customers()->create([
                'email' => $email,
                'name' => $data['buyer_name'] ?? null,
                'surname' => $data['buyer_surname'] ?? null,
                'phone' => $data['buyer_phone'] ?? null,
            ]);

            return [$customer, true];
        }

        return [null, false];
    }

    /**
     * Buduje zamówienie z migawki pozycji i zdejmuje stan magazynowy.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{product: Product, quantity: float}>  $lines
     */
    private function createOrder(Shop $shop, array $data, array $lines, ?Customer $customer = null): Order
    {
        $delivery = DeliveryMethod::from($data['delivery_method']);
        $payment = PaymentMethod::from($data['payment_method']);

        $itemRows = [];
        $itemsGross = 0.0;
        $discountLines = [];

        foreach ($lines as $line) {
            $product = $line['product'];
            $quantity = $line['quantity'];
            $configuration = $line['configuration'] ?? [];
            $surcharge = (float) ($line['surcharge'] ?? 0);

            /*
             * DOPŁATA WCHODZI W CENĘ JEDNOSTKOWĄ, nie w osobną pozycję zamówienia
             * (ustalenie z klientem: „dopłata do produktu"). Konsekwencje, dla
             * których to jest właściwy wybór: rabat procentowy obejmuje wtedy
             * całość tak samo jak cenę towaru, stawka VAT jest jedna (personalizacja
             * to świadczenie pomocnicze do dostawy towaru), a zwrot zdejmuje
             * pozycję w całości, zamiast zostawiać osierocony wiersz „grawer"
             * po zwróconym magnesie.
             */
            $unitPrice = round((float) $product->price_gross + $surcharge, 2);

            [$lineGross] = $this->totals->lineAmounts($unitPrice, $quantity, $product->vat_rate);
            $itemsGross += $lineGross;

            $discountLines[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineGross,
            ];

            $itemRows[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                // Migawka dla człowieka + odpowiedź maszynowa. Patrz migracja
                // `add_personalisation_to_order_items_table` — dwie kolumny,
                // bo mail i arkusz produkcyjny potrzebują czego innego.
                'personalisation' => ProductConfiguration::describe($product, $configuration) ?: null,
                'configuration' => $configuration ?: null,
                'unit_price_gross' => $unitPrice,
                'personalisation_surcharge_gross' => $surcharge,
                'vat_rate' => $product->vat_rate->value,
                'quantity' => $quantity,
                'sale_unit' => $product->sale_unit->value,
                'line_total_gross' => $lineGross,
            ];

            if ($product->track_stock && $product->stock !== null) {
                $product->decrement('stock', $quantity);
            }
        }

        // Kod rabatowy z koszyka sprawdzamy PONOWNIE, na finalnych pozycjach —
        // między koszykiem a kasą klient zmienia zawartość, a sprzedawca bywa
        // szybszy i wyłącza kod. Odmowa przerywa składanie (patrz metoda niżej).
        $discount = $this->resolveDiscount($shop, $discountLines, $customer);

        // Koszt dostawy = cennik WYBRANEJ metody per sklep (z progiem darmowej
        // dostawy liczonym od wartości produktów). Odbiór osobisty: 0.
        // Kod „darmowa wysyłka" zeruje go niezależnie od cennika i progu.
        $deliveryCost = $discount?->freeShipping
            ? 0.0
            : $shop->deliveryCostFor($delivery, $itemsGross);

        $order = $shop->orders()->create([
            'number' => $shop->allocateOrderNumber(),
            'customer_id' => $customer?->id,
            // Pierwszy krok ścieżki, nie sztywne „Nowe": przy przedpłacie
            // zamówienie startuje od razu w „Oczekuje na płatność".
            'status' => OrderFlow::for($payment, $delivery)->initial(),
            'buyer_name' => $data['buyer_name'],
            'buyer_surname' => $data['buyer_surname'],
            'buyer_email' => $data['buyer_email'],
            'buyer_phone' => $data['buyer_phone'] ?? null,
            'is_company' => $data['is_company'] ?? false,
            'company_name' => $this->companyField($data, 'company_name'),
            'company_nip' => $this->companyField($data, 'company_nip'),
            'company_street' => $this->companyField($data, 'company_street'),
            'company_building_number' => $this->companyField($data, 'company_building_number'),
            'company_apartment_number' => $this->companyField($data, 'company_apartment_number'),
            'company_postal_code' => $this->companyField($data, 'company_postal_code'),
            'company_city' => $this->companyField($data, 'company_city'),
            // Migawka adresu dostawy — tylko przy metodzie „pod adres" (kurier).
            'ship_street' => $this->shipField($data, 'ship_street', $delivery),
            'ship_building_number' => $this->shipField($data, 'ship_building_number', $delivery),
            'ship_apartment_number' => $this->shipField($data, 'ship_apartment_number', $delivery),
            'ship_postal_code' => $this->shipField($data, 'ship_postal_code', $delivery),
            'ship_city' => $this->shipField($data, 'ship_city', $delivery),
            // Migawka paczkomatu — tylko przy metodzie „do punktu". Wyklucza się
            // z adresem: zamówienie ma albo jedno, albo drugie, nigdy oba.
            'parcel_locker_code' => $this->lockerField($data, 'parcel_locker_code', $delivery),
            'parcel_locker_address' => $this->lockerField($data, 'parcel_locker_address', $delivery),
            'delivery_method' => $delivery,
            'delivery_cost' => $deliveryCost,
            'payment_method' => $payment,
            'items_total' => 0,
            // Relacja do liczenia użyć kodu + MIGAWKA (kod i kwota), która przeżyje
            // skasowanie kodu przez sprzedawcę. Kwotę i tak przelicza OrderTotals.
            'discount_code_id' => $discount?->code?->id,
            'discount_code' => $discount?->code?->code,
            'discount_amount' => $discount?->itemsDiscount ?? 0,
            'total_net' => 0,
            'total_vat' => 0,
            'total_gross' => 0,
            'note' => $data['note'] ?? null,
        ]);

        $order->items()->createMany($itemRows);

        // Sumy z jednego źródła (OrderTotals) — identycznie jak przy edycji zamówienia.
        $this->totals->recalculate($order->load('items'));

        return $order;
    }

    /**
     * Kod rabatowy przyklejony do koszyka, sprawdzony na FINALNYCH pozycjach
     * zamówienia (null, gdy klient żadnego nie użył).
     *
     * Kod, który przestał działać, PRZERYWA składanie zamówienia zamiast zniknąć
     * po cichu: klient widział w kasie inną kwotę, więc nie wolno obciążyć go
     * wyższą. Odklejamy kod i odsyłamy do podsumowania — ta sama ścieżka, co przy
     * zmianie dostępności produktów.
     *
     * @param  list<array{product: Product, quantity: float, unit_price: float, line_total: float}>  $lines
     */
    private function resolveDiscount(Shop $shop, array $lines, ?Customer $customer): ?DiscountResult
    {
        $code = $this->cart->discountCode($shop->id);

        if ($code === null) {
            return null;
        }

        $result = $this->discounts->resolve($shop, $code, collect($lines), $customer);

        if (! $result->accepted()) {
            $this->cart->clearDiscountCode($shop->id);

            throw new CartNeedsReviewException([
                'Kod rabatowy „'.$code.'" nie został naliczony — '
                    .mb_strtolower(mb_substr((string) $result->error, 0, 1)).mb_substr((string) $result->error, 1)
                    .' Sprawdź podsumowanie i złóż zamówienie ponownie.',
            ]);
        }

        return $result;
    }

    /**
     * Pole danych firmy: wartość tylko przy zakupie firmowym, puste → null.
     *
     * @param  array<string, mixed>  $data
     */
    private function companyField(array $data, string $key): ?string
    {
        if (! ($data['is_company'] ?? false)) {
            return null;
        }

        $value = $data[$key] ?? null;

        return filled($value) ? (string) $value : null;
    }

    /**
     * Pole adresu dostawy: wartość tylko przy metodzie „pod adres" (kurier),
     * puste → null. Przy odbiorze osobistym i paczkomacie adres nie istnieje —
     * dlatego bramką jest requiresShippingAddress(), a NIE isShipped(): paczkomat
     * jest wysyłką, ale paczka jedzie do skrytki, nie pod dom kupującego.
     *
     * @param  array<string, mixed>  $data
     */
    private function shipField(array $data, string $key, DeliveryMethod $delivery): ?string
    {
        if (! $delivery->requiresShippingAddress()) {
            return null;
        }

        $value = $data[$key] ?? null;

        return filled($value) ? (string) $value : null;
    }

    /**
     * Pole paczkomatu: wartość tylko przy metodzie „do punktu", puste → null.
     * Lustro shipField() — te dwa zestawy się wykluczają.
     *
     * @param  array<string, mixed>  $data
     */
    private function lockerField(array $data, string $key, DeliveryMethod $delivery): ?string
    {
        if (! $delivery->requiresParcelLocker()) {
            return null;
        }

        $value = $data[$key] ?? null;

        return filled($value) ? (string) $value : null;
    }
}
