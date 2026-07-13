<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\CartNeedsReviewException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
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
                ->whereIn('id', array_keys($raw))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $messages = [];
            $reconciled = [];
            $lines = [];

            foreach ($raw as $productId => $qty) {
                $product = $products->get($productId);

                if ($product === null || ! $product->is_active) {
                    $messages[] = 'Jeden lub więcej produktów nie jest już dostępnych i został usunięty z koszyka.';

                    continue;
                }

                $limited = $product->track_stock && $product->stock !== null;
                $finalQty = $limited ? min((float) $qty, (float) $product->stock) : (float) $qty;

                if ($finalQty <= 0) {
                    $messages[] = 'Produkt „'.$product->name.'" jest wyprzedany i został usunięty z koszyka.';

                    continue;
                }

                if ($finalQty < $qty) {
                    $messages[] = 'Ilość „'.$product->name.'" została dostosowana do dostępności ('.$product->sale_unit->formatQuantity($finalQty).').';
                }

                $reconciled[$productId] = $finalQty;
                $lines[] = ['product' => $product, 'quantity' => $finalQty];
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
        $itemRows = [];

        foreach ($lines as $line) {
            $product = $line['product'];
            $quantity = $line['quantity'];

            [$lineGross] = $this->totals->lineAmounts((float) $product->price_gross, $quantity, $product->vat_rate);

            $itemRows[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'unit_price_gross' => (float) $product->price_gross,
                'vat_rate' => $product->vat_rate->value,
                'quantity' => $quantity,
                'sale_unit' => $product->sale_unit->value,
                'line_total_gross' => $lineGross,
            ];

            if ($product->track_stock && $product->stock !== null) {
                $product->decrement('stock', $quantity);
            }
        }

        $order = $shop->orders()->create([
            'number' => $shop->allocateOrderNumber(),
            'customer_id' => $customer?->id,
            'status' => OrderStatus::New,
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
            'delivery_method' => DeliveryMethod::from($data['delivery_method']),
            'delivery_cost' => 0.0,    // MVP: odbiór osobisty bez kosztu
            'payment_method' => PaymentMethod::from($data['payment_method']),
            'items_total' => 0,
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
}
