<?php

namespace App\Services;

use App\Enums\VatRate;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wystawianie faktur VAT przez API Fakturowni. Konfiguracja jest PER-SKLEP
 * (adres konta + token z shop_integrations, zaszyfrowane) — jeden sprzedawca =
 * jedno konto Fakturowni. Serwis buduje ciało faktury z migawki zamówienia
 * (pozycje z VAT per linia, dane nabywcy) i tworzy fakturę jednym POST-em.
 *
 * Świadome ograniczenia zakresu:
 *  - NIE sprawdza uprawnienia pakietu ani idempotencji („FV tylko raz") — to
 *    zadanie wołającego joba (guard `invoice_id`).
 *  - NIE wysyła maila z Fakturowni (`send_by_email`) — mail „Pobierz FV" wysyła
 *    nasz system, dla spójności brandu.
 *
 * Kwoty: pozycje niosą `total_price_gross` = zapisany `line_total_gross` linii,
 * więc suma faktury zgadza się co do grosza z zamówieniem (bez ponownego
 * zaokrąglania po stronie Fakturowni).
 */
class FakturowniaService
{
    /** Domyślna stawka VAT dostawy — nie trzymamy jej na zamówieniu (usługa). */
    private const DELIVERY_TAX = 23;

    /**
     * Tworzy fakturę VAT dla zamówienia. Zwraca ślad faktury
     * (`id`, `number`, `token` do publicznego PDF, `view_url`) albo null, gdy
     * brak konfiguracji lub API zwróciło błąd. Loguje żądanie i odpowiedź do
     * kanału `fakturownia` (bez tokenu — sekretu nigdy nie zapisujemy).
     *
     * @return array{id: int|null, number: string|null, token: string|null, view_url: string|null}|null
     */
    public function createInvoice(Order $order): ?array
    {
        $order->loadMissing(['items', 'shop']);
        $shop = $order->shop;

        $accountUrl = $shop?->fakturowniaAccountUrl();
        $token = $shop?->fakturowniaToken();

        if (blank($accountUrl) || blank($token)) {
            Log::channel('fakturownia')->warning('FV pominięta: brak konfiguracji Fakturowni.', [
                'order_id' => $order->id,
                'shop_id' => $shop?->id,
            ]);

            return null;
        }

        $payload = $this->buildInvoicePayload($order);

        // Audyt PRZED wysyłką: ciało faktury bez api_token (sekret trzymamy z boku).
        Log::channel('fakturownia')->info('FV: wysyłka żądania.', [
            'order_id' => $order->id,
            'shop_id' => $shop->id,
            'invoice' => $payload['invoice'],
        ]);

        try {
            $response = Http::acceptJson()
                ->timeout(20)
                ->post(rtrim($accountUrl, '/').'/invoices.json', ['api_token' => $token] + $payload);
        } catch (\Throwable $e) {
            Log::channel('fakturownia')->error('FV: wyjątek połączenia z Fakturownią.', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::channel('fakturownia')->error('FV: Fakturownia zwróciła błąd.', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();

        Log::channel('fakturownia')->info('FV: faktura utworzona.', [
            'order_id' => $order->id,
            'invoice_id' => $data['id'] ?? null,
            'number' => $data['number'] ?? null,
        ]);

        return [
            'id' => $data['id'] ?? null,
            'number' => $data['number'] ?? null,
            'token' => $data['token'] ?? null,
            'view_url' => $data['view_url'] ?? null,
        ];
    }

    /**
     * Buduje ciało faktury (klucz `invoice`, bez `api_token`). Wydzielone od HTTP,
     * by testować samo mapowanie. Daty sprzedaży/wystawienia/płatności = dziś;
     * `status: paid` + `payment_type: transfer` (jak wdrożenie ursalogic —
     * sprzedawca wystawia FV świadomie z karty zamówienia).
     *
     * @return array{invoice: array<string, mixed>}
     */
    public function buildInvoicePayload(Order $order): array
    {
        $today = now()->toDateString();

        $invoice = [
            'kind' => 'vat',
            'sell_date' => $today,
            'issue_date' => $today,
            'payment_to' => $today,
            'status' => 'paid',
            'payment_type' => 'transfer',
            'positions' => $this->positions($order),
        ] + $this->buyer($order);

        return ['invoice' => array_filter($invoice, fn ($value): bool => $value !== null)];
    }

    /**
     * Dane nabywcy z migawki zamówienia. Firma: nazwa firmy + NIP + adres
     * rozliczeniowy. Osoba prywatna: imię i nazwisko + adres dostawy (jeśli był),
     * bez NIP. Puste pola odpadają w `array_filter` wyżej.
     *
     * @return array<string, string|null>
     */
    private function buyer(Order $order): array
    {
        if ($order->is_company) {
            return [
                'buyer_name' => $order->company_name,
                'buyer_tax_no' => $order->company_nip,
                'buyer_email' => $order->buyer_email,
                'buyer_street' => $this->addressLine($order->company_street, $order->company_building_number, $order->company_apartment_number),
                'buyer_post_code' => $order->company_postal_code,
                'buyer_city' => $order->company_city,
            ];
        }

        return [
            'buyer_name' => trim($order->buyer_name.' '.$order->buyer_surname),
            'buyer_email' => $order->buyer_email,
            'buyer_street' => $this->addressLine($order->ship_street, $order->ship_building_number, $order->ship_apartment_number),
            'buyer_post_code' => $order->ship_postal_code,
            'buyer_city' => $order->ship_city,
        ];
    }

    /**
     * Pozycje faktury: każda pozycja zamówienia + (gdy > 0) dostawa jako osobna
     * pozycja usługowa. Kwota linii to zapisany `line_total_gross` — bez
     * przeliczania, żeby suma FV = suma zamówienia. `tax` w formacie Fakturowni:
     * liczba (23/8/5/0) albo „zw" dla zwolnionego.
     *
     * @return list<array<string, mixed>>
     */
    private function positions(Order $order): array
    {
        $positions = $order->items->map(fn ($item): array => [
            'name' => $item->name,
            'tax' => $this->tax($item->vat_rate),
            'quantity' => (float) $item->quantity,
            'total_price_gross' => (float) $item->line_total_gross,
        ])->values()->all();

        if ((float) $order->delivery_cost > 0) {
            $positions[] = [
                'name' => 'Dostawa: '.$order->delivery_method->label(),
                'tax' => self::DELIVERY_TAX,
                'quantity' => 1,
                'total_price_gross' => (float) $order->delivery_cost,
            ];
        }

        return $positions;
    }

    /**
     * Stawka VAT w formacie Fakturowni: zwolniony → „zw", inaczej liczba całkowita
     * (23/8/5/0).
     */
    private function tax(VatRate $vat): int|string
    {
        return $vat === VatRate::Zw ? 'zw' : (int) $vat->value;
    }

    /**
     * Ulica z numerem domu/lokalu w jednej linii („Okrzei 73/5"). null, gdy nie
     * ma nawet ulicy — adres na fakturze jest opcjonalny.
     */
    private function addressLine(?string $street, ?string $building, ?string $apartment): ?string
    {
        if (blank($street)) {
            return null;
        }

        $line = trim($street.' '.$building);

        return filled($apartment) ? $line.'/'.$apartment : $line;
    }
}
