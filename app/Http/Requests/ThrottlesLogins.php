<?php

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Blokada logowania po serii nieudanych prób — wspólna dla centrali (sprzedawca,
 * administrator) i dla kont klientów w sklepach.
 *
 * Licznik idzie per KONTO, nie tylko per adres IP. To istotna różnica: limit na
 * samym IP obchodzi się siecią maszyn, a limit na koncie zatrzymuje zgadywanie
 * hasła do konkretnej skrzynki niezależnie od tego, skąd przychodzi.
 *
 * Klasa używająca podaje własny `throttleKey()`, bo „to samo konto" znaczy co
 * innego w centrali (e-mail) i w sklepie (e-mail W TYM sklepie — ten sam adres
 * może mieć konta u wielu sprzedawców).
 */
trait ThrottlesLogins
{
    abstract public function throttleKey(): string;

    /**
     * Przerywa żądanie, gdy limit prób jest wyczerpany. Wołane PRZED
     * sprawdzeniem hasła — po blokadzie nie wpuszczamy nawet z poprawnym.
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), (int) config('security.login.max_attempts'))) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        $message = $seconds >= 60
            ? 'Zbyt wiele prób logowania. Spróbuj ponownie za '.ceil($seconds / 60).' min.'
            : "Zbyt wiele prób logowania. Spróbuj ponownie za {$seconds} s.";

        throw ValidationException::withMessages([
            'email' => $message,
        ]);
    }

    /**
     * Nieudana próba — dolicza do licznika.
     */
    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), (int) config('security.login.decay_seconds'));
    }

    /**
     * Udane logowanie zeruje licznik, żeby ktoś, kto po prostu pomylił hasło,
     * nie zostawał z aktywną blokadą.
     */
    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }
}
