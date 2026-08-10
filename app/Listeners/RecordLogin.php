<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Stempluje `users.last_login_at` przy każdym zalogowaniu.
 *
 * Dwie rzeczy, na które trzeba uważać:
 *
 * 1. Zdarzenie `Login` leci z KAŻDEGO strażnika, także `customer` ze
 *    storefrontu. Klient sklepu nie jest `User` i nie ma tej kolumny, więc
 *    sprawdzenie typu jest warunkiem działania, nie ostrożnością na zapas.
 * 2. Zapis idzie z pominięciem znaczników czasu i zdarzeń modelu. `updated_at`
 *    na koncie znaczy „ktoś zmienił dane" — samo wejście do panelu zmianą nie
 *    jest, a przestemplowanie zacierałoby informację, kiedy konto naprawdę
 *    było edytowane.
 */
class RecordLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        User::withoutTimestamps(
            fn () => $user->forceFill(['last_login_at' => now()])->saveQuietly()
        );
    }
}
