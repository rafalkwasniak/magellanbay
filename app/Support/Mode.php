<?php

namespace App\Support;

/**
 * Tryb pracy aplikacji — Kramio (wielu sprzedawców) albo sklep dedykowany
 * (jeden klient, jego serwer, jego domena).
 *
 * Po co osobna klasa zamiast `config('shop.mode') === 'dedicated'` rozsianego
 * po kodzie: porównanie tekstu literowałoby się w kilkunastu miejscach, a
 * literówka w takim warunku nie wywala aplikacji — po prostu cicho pokazuje
 * klientowi ekran platformy, którego nie miał zobaczyć. Jedno miejsce znaczy
 * też, że dołożenie trzeciego trybu nie jest polowaniem na stringi.
 *
 * ZASADA: pytamy „czy to sklep dedykowany", a nie „jaki mamy tryb". Warunki
 * czytają się wtedy jak zdanie i domyślnie zachowują się jak Kramio — nowy
 * ekran, o którym zapomnimy, zostanie widoczny w SaaS, a nie zniknie z niego.
 *
 * @see config/shop.php — opis obu trybów
 */
final class Mode
{
    public const SAAS = 'saas';

    public const DEDICATED = 'dedicated';

    /**
     * Sklep jednego klienta: bez rejestracji, cennika, pakietów i konsoli admina.
     */
    public static function dedicated(): bool
    {
        return config('shop.mode') === self::DEDICATED;
    }

    /**
     * Kramio — platforma dla wielu sprzedawców. Domyślny tryb.
     */
    public static function saas(): bool
    {
        return ! self::dedicated();
    }
}
