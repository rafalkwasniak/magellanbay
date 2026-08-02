<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Zgoda na ciasteczka analityczne — jedno miejsce, w którym pytamy o jej stan.
 *
 * SEDNO: decyzję czytamy PO STRONIE SERWERA, zanim powstanie HTML. Skrypt
 * Google nie ma prawa znaleźć się w wysłanej stronie, dopóki zgody nie ma —
 * blokowanie go w przeglądarce byłoby już za późno, bo plik zdążyłby zostać
 * pobrany, a Google zobaczyłoby żądanie.
 *
 * Zgoda dotyczy WYŁĄCZNIE ciasteczek nieniezbędnych (u nas: pomiar Google).
 * Sesja, koszyk, token formularzy i „zapamiętaj mnie" są konieczne do działania
 * usługi, o którą użytkownik sam poprosił, więc zgody nie wymagają — i nie są
 * przez ten mechanizm ruszane.
 */
class CookieConsent
{
    /**
     * Czy użytkownik zgodził się na ciasteczka analityczne.
     */
    public static function granted(): bool
    {
        return self::current() === config('cookies.consent.granted');
    }

    /**
     * Czy użytkownik w ogóle podjął decyzję. Dopóki nie podjął, pokazujemy baner.
     */
    public static function decided(): bool
    {
        return in_array(self::current(), [
            config('cookies.consent.granted'),
            config('cookies.consent.declined'),
        ], true);
    }

    /**
     * Ciasteczko zapisujące zgodę.
     *
     * Samo w sobie jest ciasteczkiem NIEZBĘDNYM — bez niego nie dałoby się
     * uszanować decyzji użytkownika, więc jego zapisanie nie wymaga zgody.
     */
    public static function accept(): SymfonyCookie
    {
        return self::make(
            (string) config('cookies.consent.granted'),
            (int) config('cookies.consent.accepted_days'),
        );
    }

    public static function decline(): SymfonyCookie
    {
        return self::make(
            (string) config('cookies.consent.declined'),
            (int) config('cookies.consent.declined_days'),
        );
    }

    /**
     * Cofnięcie decyzji — baner pojawia się znowu przy następnym żądaniu.
     */
    public static function forget(): SymfonyCookie
    {
        return Cookie::forget(self::name());
    }

    private static function make(string $value, int $days): SymfonyCookie
    {
        return Cookie::make(
            name: self::name(),
            value: $value,
            minutes: $days * 24 * 60,
            // `httpOnly: false` jest tu CELOWE: to nie jest sekret, a wartość
            // musi być czytelna dla skryptu, gdyby kiedyś doszedł tryb zgody
            // Google. Bezpieczeństwa nie osłabia — ciasteczko nie niesie
            // niczego, czym dałoby się przejąć sesję.
            httpOnly: false,
            sameSite: 'lax',
        );
    }

    private static function name(): string
    {
        return (string) config('cookies.consent.name');
    }

    private static function current(): ?string
    {
        $value = request()->cookie(self::name());

        return is_string($value) ? $value : null;
    }
}
