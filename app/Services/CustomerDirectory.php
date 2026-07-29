<?php

namespace App\Services;

use App\Enums\ConsentChannel;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Support\Collection;

/**
 * Kartoteka klientów sklepu — kto u mnie kupował, ile razy i za ile.
 *
 * KLUCZEM JEST ADRES E-MAIL, nie konto klienta. Większość zamówień składają
 * goście, którzy konta nie zakładają; kartoteka oparta o tabelę `customers`
 * pokazałaby więc ułamek realnych klientów. Konto (jeśli istnieje) dokłada się
 * do wiersza jako dodatkowa informacja — razem ze zgodą marketingową.
 *
 * Adresy porównujemy bez względu na wielkość liter: ten sam człowiek raz wpisze
 * „Anna@…", raz „anna@…", a to musi być jedna pozycja w kartotece.
 *
 * DWIE LICZBY, KTÓRE ŁATWO POMYLIĆ:
 * - liczba zamówień = WSZYSTKIE, także anulowane (sprzedawca chce widzieć pełną
 *   historię kontaktu),
 * - wydatki = tylko zamówienia liczone jako zakup (bez anulowanych), bo za
 *   anulowane klient nie zapłacił. Ta sama zasada co w analityce.
 */
class CustomerDirectory
{
    /**
     * Wszyscy klienci sklepu, od ostatnio kupujących. Zwraca wiersze kartoteki —
     * nie modele, bo klient bez konta modelu nie ma.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function all(Shop $shop): Collection
    {
        $orders = Order::where('shop_id', $shop->id)
            ->whereNotNull('buyer_email')
            ->get(['id', 'customer_id', 'buyer_name', 'buyer_surname', 'buyer_email', 'buyer_phone', 'status', 'total_gross', 'created_at']);

        if ($orders->isEmpty()) {
            return collect();
        }

        $accounts = $this->accountsByEmail($shop);

        return $orders
            ->groupBy(fn (Order $order): string => $this->normalizeEmail($order->buyer_email))
            ->map(function (Collection $rows, string $email) use ($accounts): array {
                // Dane osobowe z NAJNOWSZEGO zamówienia — nazwisko czy telefon
                // mogły się zmienić, a kartoteka ma pokazywać stan bieżący.
                $latest = $rows->sortByDesc('id')->first();
                $account = $accounts->get($email);

                $paid = $rows->reject(fn (Order $order): bool => $order->status === OrderStatus::Cancelled);

                return [
                    'email' => $email,
                    'name' => trim(($account?->name ?? $latest->buyer_name).' '.($account?->surname ?? $latest->buyer_surname)),
                    'phone' => $account?->phone ?? $latest->buyer_phone,
                    'orders_count' => $rows->count(),
                    'cancelled_count' => $rows->count() - $paid->count(),
                    'total_spent' => round($paid->sum(fn (Order $order): float => (float) $order->total_gross), 2),
                    'last_order_at' => $rows->max('created_at'),
                    'first_order_at' => $rows->min('created_at'),
                    'customer' => $account,
                    'has_account' => $account !== null,
                    'has_consent' => $account?->hasConsent(ConsentChannel::Email) ?? false,
                ];
            })
            ->sortByDesc('last_order_at')
            ->values();
    }

    /**
     * Kartoteka po przefiltrowaniu frazą i posortowaniu. Fraza szuka w adresie,
     * imieniu, nazwisku i telefonie — sprzedawca pamięta klienta raz tak, raz tak.
     *
     * @param  array{konto?: bool|null, zgoda?: bool|null}  $filters  null/brak = bez znaczenia
     * @return Collection<int, array<string, mixed>>
     */
    public function search(Shop $shop, string $phrase = '', string $sort = 'ostatnie', array $filters = []): Collection
    {
        $rows = $this->all($shop);
        $phrase = trim($phrase);

        if ($phrase !== '') {
            $needle = mb_strtolower($phrase);

            $rows = $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['email']), $needle)
                || str_contains(mb_strtolower((string) $row['name']), $needle)
                || str_contains(mb_strtolower((string) $row['phone']), $needle));
        }

        // Filtry są trójstanowe: brak wartości = „bez znaczenia". Dzięki temu
        // sprzedawca może szukać zarówno tych z kontem, jak i tych bez, nie
        // tracąc możliwości zobaczenia wszystkich.
        if (($filters['konto'] ?? null) !== null) {
            $rows = $rows->where('has_account', (bool) $filters['konto']);
        }

        if (($filters['zgoda'] ?? null) !== null) {
            $rows = $rows->where('has_consent', (bool) $filters['zgoda']);
        }

        return match ($sort) {
            'wydatki' => $rows->sortByDesc('total_spent')->values(),
            'zamowienia' => $rows->sortByDesc('orders_count')->values(),
            'nazwa' => $rows->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            default => $rows->sortByDesc('last_order_at')->values(),
        };
    }

    /**
     * Pełna karta jednego klienta: wiersz kartoteki + jego zamówienia (od
     * najnowszego) i średnia wartość zakupu. `null`, gdy pod tym adresem nikt
     * w tym sklepie nie kupował.
     *
     * @return array<string, mixed>|null
     */
    public function profile(Shop $shop, string $email): ?array
    {
        $email = $this->normalizeEmail($email);
        $row = $this->all($shop)->firstWhere('email', $email);

        if ($row === null) {
            return null;
        }

        $orders = Order::where('shop_id', $shop->id)
            ->whereRaw('LOWER(buyer_email) = ?', [$email])
            ->withCount('items')
            ->latest('id')
            ->get();

        $paidCount = $row['orders_count'] - $row['cancelled_count'];

        return $row + [
            'orders' => $orders,
            // Średnia liczona z zamówień, za które klient zapłacił — anulowane
            // zaniżałyby ją do zera bez żadnego powodu.
            'average_order' => $paidCount > 0 ? round($row['total_spent'] / $paidCount, 2) : 0.0,
        ];
    }

    /**
     * Konta klientów tego sklepu, po znormalizowanym adresie — do dopięcia do
     * wierszy kartoteki jednym zapytaniem zamiast jednego na klienta.
     *
     * @return Collection<string, Customer>
     */
    private function accountsByEmail(Shop $shop): Collection
    {
        return $shop->customers()
            ->with('consents')
            ->get()
            ->keyBy(fn (Customer $customer): string => $this->normalizeEmail($customer->email));
    }

    private function normalizeEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }
}
