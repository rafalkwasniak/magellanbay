<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\CartNeedsReviewException;
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
    ) {}

    /**
     * @param  array<string, mixed>  $data  zwalidowane dane kupującego + metody
     */
    public function place(Shop $shop, array $data): Order
    {
        $order = DB::transaction(function () use ($shop, $data): Order {
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
                $finalQty = $limited ? min($qty, $product->stock) : $qty;

                if ($finalQty < 1) {
                    $messages[] = 'Produkt „'.$product->name.'" jest wyprzedany i został usunięty z koszyka.';

                    continue;
                }

                if ($finalQty < $qty) {
                    $messages[] = 'Ilość „'.$product->name.'" została dostosowana do dostępności ('.$finalQty.' szt.).';
                }

                $reconciled[$productId] = $finalQty;
                $lines[] = ['product' => $product, 'quantity' => $finalQty];
            }

            if ($messages !== []) {
                // Zapisz uzgodniony koszyk i przerwij — klient sprawdza i składa ponownie.
                $this->cart->overwrite($shop->id, $reconciled);

                throw new CartNeedsReviewException(array_values(array_unique($messages)));
            }

            $order = $this->createOrder($shop, $data, $lines);

            $this->cart->clear($shop->id);

            return $order;
        });

        // Po pomyślnym commicie kolejkujemy maile (outbox → cron): potwierdzenie
        // dla klienta i powiadomienie dla sprzedawcy. Przerwana weryfikacja
        // (CartNeedsReviewException) rzuca w transakcji — tu już nie dojdziemy.
        $this->mailer->confirmToCustomer($order);
        $this->mailer->notifySeller($order);

        return $order;
    }

    /**
     * Buduje zamówienie z migawki pozycji i zdejmuje stan magazynowy.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{product: Product, quantity: int}>  $lines
     */
    private function createOrder(Shop $shop, array $data, array $lines): Order
    {
        $itemsTotal = 0.0;
        $totalNet = 0.0;
        $totalVat = 0.0;
        $itemRows = [];

        foreach ($lines as $line) {
            $product = $line['product'];
            $quantity = $line['quantity'];

            $unit = (float) $product->price_gross;
            $lineGross = round($unit * $quantity, 2);
            $lineNet = round($lineGross / (1 + $product->vat_rate->fraction()), 2);
            $lineVat = round($lineGross - $lineNet, 2);

            $itemsTotal += $lineGross;
            $totalNet += $lineNet;
            $totalVat += $lineVat;

            $itemRows[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'unit_price_gross' => $unit,
                'vat_rate' => $product->vat_rate->value,
                'quantity' => $quantity,
                'line_total_gross' => $lineGross,
            ];

            if ($product->track_stock && $product->stock !== null) {
                $product->decrement('stock', $quantity);
            }
        }

        $deliveryCost = 0.0;    // MVP: odbiór osobisty bez kosztu

        $order = $shop->orders()->create([
            'number' => $shop->allocateOrderNumber(),
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
            'delivery_cost' => $deliveryCost,
            'payment_method' => PaymentMethod::from($data['payment_method']),
            'items_total' => round($itemsTotal, 2),
            'total_net' => round($totalNet, 2),
            'total_vat' => round($totalVat, 2),
            'total_gross' => round($itemsTotal + $deliveryCost, 2),
            'note' => $data['note'] ?? null,
        ]);

        $order->items()->createMany($itemRows);

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
