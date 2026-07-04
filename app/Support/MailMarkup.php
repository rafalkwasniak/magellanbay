<?php

namespace App\Support;

/**
 * Minimalne, bezpieczne formatowanie inline dla treści maili. Wspiera wyłącznie
 * pogrubienie zapisem `**tekst**` (jak w Markdownie), nic więcej — treść maili
 * budujemy sami w kodzie, więc nie potrzebujemy pełnego parsera.
 *
 * Kolejność jest kluczowa dla bezpieczeństwa: NAJPIERW escapujemy cały tekst
 * (nazwy produktów, notatki i dane firmowe pochodzą od użytkownika), DOPIERO
 * potem zamieniamy nasze własne znaczniki `**` na `<strong>`. Dzięki temu żadne
 * dane wejściowe nie mogą wstrzyknąć HTML — najwyżej pokażą się dosłowne `**`.
 */
class MailMarkup
{
    public static function inline(string $text): string
    {
        $escaped = e($text);

        return preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped);
    }
}
