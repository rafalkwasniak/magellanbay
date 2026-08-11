<?php

namespace App\Support;

use App\Models\PackagePayment;
use App\Models\Shop;
use Illuminate\Support\Collection;

/**
 * Lista rzeczy do zrobienia wokół pakietów — to, czego z karty pojedynczego
 * sklepu nie widać, bo wymaga spojrzenia na całą platformę naraz.
 *
 * Powód istnienia: przy sprzedaży z ręki nikt nie przypomni, że komuś kończy
 * się abonament albo że opłata została bez faktury. Terminy pilnują się same
 * tylko przy automatycznym billingu — dopóki go nie ma, pilnuje ten ekran.
 *
 * Reguły stanu abonamentu (gratis, pakiet darmowy, karencja, pusta data)
 * bierzemy WYŁĄCZNIE z metod modelu `Shop`. Powtórzenie ich tutaj dałoby drugą
 * definicję tego samego i pierwszy sklep w nietypowym stanie trafiłby na
 * ekranie w inną grupę niż w rzeczywistości.
 */
class PackageAttention
{
    /**
     * Grupy w kolejności PILNOŚCI, nie chronologicznej: najpierw sklep, który
     * już stracił funkcje, na końcu porzucony koszyk w bramce.
     *
     * Grupy puste wypadają z wyniku — lista ma pokazywać robotę do zrobienia,
     * a nie pięć nagłówków z zerami.
     *
     * @return list<array{key: string, label: string, hint: string, tone: string, items: list<array{title: string, subtitle: string, note: string, url: ?string}>}>
     */
    public static function groups(): array
    {
        $shops = self::shopsInPlay();
        $noticeDays = (int) config('shop.subscription.notice_days');

        $groups = [
            [
                'key' => 'locked',
                // Słownictwo wspólne z ekranem sprzedawcy („Pakiet … wygasł",
                // „Termin minął"). Wewnętrzne „zamek" z komentarzy w kodzie na
                // ekran nie wychodzi — nikt poza nami nie wie, co znaczy.
                'label' => 'Wygasł',
                'hint' => 'Płatne funkcje wyłączone, sklep działa jak Kram.',
                'tone' => 'rose',
                'items' => self::shopItems($shops->filter(fn (Shop $shop): bool => ! $shop->subscriptionActive())),
            ],
            [
                'key' => 'grace',
                'label' => 'Po terminie',
                'hint' => 'Funkcje jeszcze działają. Ostatni moment na przypomnienie o przelewie.',
                'tone' => 'amber',
                'items' => self::shopItems(
                    $shops->filter(fn (Shop $shop): bool => $shop->inSubscriptionGrace()),
                    fn (Shop $shop): string => 'wygasa za '.$shop->graceDaysLeft().' '
                        .trans_choice('dzień|dni|dni', $shop->graceDaysLeft()),
                ),
            ],
            [
                'key' => 'expiring',
                'label' => 'Kończy się wkrótce',
                // Podpowiedzi są krótkie, bo lista mieszka w wąskiej kolumnie
                // bocznej — zdanie na trzy linijki spycha same sprawy poza ekran.
                'hint' => 'Wygasa w ciągu '.$noticeDays.' dni. Maile idą automatycznie, fakturę wystawiasz Ty.',
                'tone' => 'stone',
                'items' => self::shopItems($shops->filter(fn (Shop $shop): bool => self::expiresSoon($shop, $noticeDays))),
            ],
            [
                'key' => 'uninvoiced',
                'label' => 'Opłacone bez faktury',
                'hint' => 'Pieniądze wpłynęły, dokumentu nie ma.',
                'tone' => 'amber',
                'items' => self::paymentItems(
                    PackagePayment::query()
                        ->where('status', PackagePayment::STATUS_PAID)
                        // Numer bez `invoice_id` to faktura wystawiona poza
                        // systemem — dokument istnieje, więc nie ma o czym mówić.
                        ->whereNull('invoice_id')
                        ->whereNull('invoice_number')
                        ->with('shop.owner')
                        ->orderByDesc('paid_at')
                        ->get(),
                    fn (PackagePayment $payment): string => Money::pln($payment->amount)
                        .' · '.($payment->paid_at?->format('d.m.Y') ?? 'brak daty wpłaty'),
                ),
            ],
            [
                'key' => 'abandoned',
                'label' => 'Płatność wisi',
                'hint' => 'Kliknął „Kup" i nie wrócił z bramki. Zwykle nic nie znaczy.',
                'tone' => 'stone',
                'items' => self::paymentItems(
                    PackagePayment::query()
                        ->where('status', PackagePayment::STATUS_PENDING)
                        ->where('created_at', '<', now()->subHours((int) config('shop.package_payments.abandoned_after_hours')))
                        ->with('shop.owner')
                        ->orderByDesc('created_at')
                        ->get(),
                    fn (PackagePayment $payment): string => Money::pln($payment->amount)
                        .' · rozpoczęta '.$payment->created_at->format('d.m.Y'),
                ),
            ],
        ];

        return array_values(array_filter($groups, fn (array $group): bool => $group['items'] !== []));
    }

    /**
     * Sklepy, o które w ogóle warto się upominać. Sklep zlecony do usunięcia
     * odpada: jest już niewidoczny dla klientów i za chwilę zniknie, a wołanie
     * „przedłuż abonament" byłoby wtedy nie na miejscu.
     *
     * @return Collection<int, Shop>
     */
    private static function shopsInPlay(): Collection
    {
        return Shop::query()
            ->whereNull('deletion_scheduled_at')
            ->with('owner')
            ->orderBy('subscription_ends_at')
            ->get();
    }

    /**
     * Czy abonament wygasa w najbliższych dniach. Sklep W KARENCJI świadomie tu
     * nie wchodzi — ma własną, pilniejszą grupę, a pokazany dwa razy sugerowałby
     * dwie różne sprawy do załatwienia.
     */
    private static function expiresSoon(Shop $shop, int $days): bool
    {
        return $shop->subscription_ends_at !== null
            && $shop->subscriptionLocksAt() !== null
            && $shop->subscription_ends_at->isFuture()
            && $shop->subscription_ends_at->lessThanOrEqualTo(now()->addDays($days));
    }

    /**
     * @param  Collection<int, Shop>  $shops
     * @param  null|callable(Shop): string  $note
     * @return list<array{title: string, subtitle: string, note: string, url: ?string}>
     */
    private static function shopItems(Collection $shops, ?callable $note = null): array
    {
        return $shops->map(fn (Shop $shop): array => [
            'title' => $shop->name,
            'subtitle' => $shop->owner?->email ?? $shop->slug,
            'note' => $note !== null
                ? $note($shop)
                : ($shop->subscription_ends_at?->format('d.m.Y') ?? 'bez terminu'),
            'url' => route('administrator.shops.edit', $shop),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, PackagePayment>  $payments
     * @param  callable(PackagePayment): string  $note
     * @return list<array{title: string, subtitle: string, note: string, url: ?string}>
     */
    private static function paymentItems(Collection $payments, callable $note): array
    {
        return $payments
            // Opłata osieroconego sklepu (kaskada usunięcia) nie ma dokąd
            // prowadzić i nie da się jej załatwić — nie ma po co o niej mówić.
            ->filter(fn (PackagePayment $payment): bool => $payment->shop !== null)
            ->map(fn (PackagePayment $payment): array => [
                'title' => $payment->shop->name,
                'subtitle' => config("shop.packages.{$payment->target_package}.name", $payment->target_package),
                'note' => $note($payment),
                'url' => route('administrator.shops.edit', $payment->shop),
            ])->values()->all();
    }
}
