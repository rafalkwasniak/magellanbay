<?php

namespace App\Enums;

/**
 * Statusy zamówień (spec „Statusy zamówień"). Wspólny zestaw dla wszystkich
 * pakietów; maszynę przejść i powiadomienia budujemy w module statusów. Nowe
 * zamówienie startuje w `New`.
 */
enum OrderStatus: string
{
    case New = 'new';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case ReadyForPickup = 'ready_for_pickup';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nowe',
            self::AwaitingPayment => 'Oczekuje na płatność',
            self::Paid => 'Opłacone',
            self::Processing => 'W realizacji',
            self::ReadyForPickup => 'Gotowe do odbioru',
            self::Shipped => 'Wysłane',
            self::Completed => 'Zrealizowane',
            self::Cancelled => 'Anulowane',
        };
    }

    /**
     * Wszystkie sensowne przejścia z bieżącego statusu, pogrupowane wg
     * prawdopodobieństwa dla karty „Zmień status". Wszystkie statusy są od razu
     * widoczne (bez chowania), tylko poukładane:
     *  - `likely`  = kroki naprzód po kanonicznej ścieżce; PIERWSZY to zalecany
     *                kolejny krok. Rozwidlenie odbiór/wysyłka rozstrzyga metoda
     *                dostawy (`Shipped` vs `ReadyForPickup`).
     *  - `others`  = mniej prawdopodobne: korekty wstecz i wariant rozwidlenia
     *                niepasujący do dostawy.
     * `Cancelled` celowo pomijamy — to wrażliwy status, widok renderuje go osobno
     * na końcu, z potwierdzeniem.
     *
     * @return array{likely: array<int, self>, others: array<int, self>}
     */
    public function transitionChoices(DeliveryMethod $delivery): array
    {
        $fork = $delivery->isShipped() ? self::Shipped : self::ReadyForPickup;

        // Kanoniczna ścieżka „szczęśliwa".
        $pipeline = [
            self::New,
            self::AwaitingPayment,
            self::Paid,
            self::Processing,
            $fork,
            self::Completed,
        ];

        $index = array_search($this, $pipeline, true);
        $likely = $index === false ? [] : array_slice($pipeline, $index + 1);
        $likelyValues = array_map(fn (self $s) => $s->value, $likely);

        $others = array_values(array_filter(
            self::cases(),
            fn (self $s) => $s !== $this
                && $s !== self::Cancelled
                && ! in_array($s->value, $likelyValues, true),
        ));

        return ['likely' => $likely, 'others' => $others];
    }

    /**
     * Klasy Tailwind plakietki statusu (miękkie tło + tekst) — jedno źródło
     * kolorów dla listy i szczegółu zamówienia. Ciepła paleta panelu.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::New => 'bg-amber-100 text-amber-800',
            self::AwaitingPayment => 'bg-orange-100 text-orange-800',
            self::Paid => 'bg-emerald-100 text-emerald-800',
            self::Processing => 'bg-sky-100 text-sky-800',
            self::ReadyForPickup => 'bg-violet-100 text-violet-800',
            self::Shipped => 'bg-indigo-100 text-indigo-800',
            self::Completed => 'bg-green-100 text-green-800',
            self::Cancelled => 'bg-stone-200 text-stone-600',
        };
    }
}
