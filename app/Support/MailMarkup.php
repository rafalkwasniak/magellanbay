<?php

namespace App\Support;

/**
 * Minimalne, bezpieczne formatowanie inline dla treści maili. Wspiera dokładnie
 * dwa zapisy z Markdowna: pogrubienie `**tekst**` i odnośnik `[tekst](adres)`.
 * Nic więcej — treść maili budujemy sami w kodzie, więc nie potrzebujemy
 * pełnego parsera.
 *
 * Kolejność jest kluczowa dla bezpieczeństwa: NAJPIERW escapujemy cały tekst
 * (nazwy produktów, notatki i dane firmowe pochodzą od użytkownika), DOPIERO
 * potem zamieniamy nasze własne znaczniki na HTML. Dzięki temu żadne dane
 * wejściowe nie mogą wstrzyknąć HTML — najwyżej pokażą się dosłowne `**`.
 *
 * Odnośniki przyjmują WYŁĄCZNIE adresy http(s) — `javascript:` i spółka nie
 * przejdą przez wzorzec, więc nawet adres z nieoczekiwanego źródła nie zamieni
 * się w wykonywalny link.
 */
class MailMarkup
{
    public static function inline(string $text): string
    {
        $escaped = e($text);

        // Odnośnik przed pogrubieniem: tekst linku też ma prawo być pogrubiony.
        $withLinks = preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
            fn (array $m) => '<a href="'.$m[2].'" style="color:inherit; text-decoration:underline;">'.$m[1].'</a>',
            $escaped
        );

        return preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $withLinks);
    }
}
