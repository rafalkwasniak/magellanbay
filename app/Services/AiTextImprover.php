<?php

namespace App\Services;

use App\Services\Ai\AiClient;
use RuntimeException;

/**
 * Redakcja treści przez AI. Dwie ścieżki:
 * - improve(): zwykły tekst (krótki prompt) — pola bez formatowania.
 * - improveHtml(): fragment HTML z edytora (dłuższy prompt) — model poprawia
 *   wyłącznie tekst wewnątrz znaczników, nie ruszając samego HTML.
 *
 * Ta klasa odpowiada wyłącznie za instrukcje dla modelu; czym zostaną wykonane,
 * rozstrzyga zadanie `proofread` w `config/ai.php`. Poprawiane są tylko ortografia,
 * interpunkcja, styl i czytelność — bez dodawania nowych informacji.
 * Wzorzec przeniesiony z projektu kociaczek.com.pl.
 */
class AiTextImprover
{
    /** Zadanie z `config('ai.tasks')` obsługujące redakcję tekstu. */
    private const TASK = 'proofread';

    public function __construct(private readonly AiClient $ai) {}

    /**
     * Redakcja zwykłego tekstu.
     *
     * @throws RuntimeException gdy usługa nie jest skonfigurowana lub wywołanie zawiedzie.
     */
    public function improve(string $text, ?int $maxChars = null): string
    {
        $system = 'Jesteś redaktorem języka polskiego. Popraw przesłany tekst: ortografię, '
            .'interpunkcję, styl i czytelność; możesz poprawić formatowanie. Nie dodawaj żadnych '
            .'nowych informacji ani faktów, których nie ma w oryginale. Odpowiedz wyłącznie '
            .'poprawioną treścią, bez wstępu i komentarzy. Jeśli w wyrażeniu brakuje kluczowych '
            .'słów, dodaj je.';

        if ($maxChars !== null) {
            $system .= " Wynik nie może przekroczyć {$maxChars} znaków.";
        }

        return $this->ai->run(self::TASK, $system, $text);
    }

    /**
     * Redakcja fragmentu HTML — model pracuje WYŁĄCZNIE w obrębie tekstu, nie
     * zmieniając znaczników, ich kolejności ani atrybutów.
     *
     * @throws RuntimeException gdy usługa nie jest skonfigurowana lub wywołanie zawiedzie.
     */
    public function improveHtml(string $html, ?int $maxChars = null): string
    {
        $system = 'Jesteś redaktorem języka polskiego pracującym na fragmencie HTML. Otrzymujesz '
            .'treść z prostymi znacznikami formatowania (m.in. <strong>, <em>, <del>, <h2>, <ul>, '
            .'<ol>, <li>, <a>, <br>, <div>). Popraw WYŁĄCZNIE tekst widoczny '
            .'wewnątrz znaczników: ortografię, interpunkcję, styl i czytelność. Jeśli w zdaniu '
            .'brakuje kluczowych słów, dodaj je. ZACHOWAJ DOKŁADNIE strukturę HTML — nie dodawaj, '
            .'nie usuwaj ani nie zmieniaj znaczników, ich kolejności i atrybutów; nie zmieniaj '
            .'adresów w atrybucie href. Nie dodawaj nowych informacji ani faktów spoza oryginału. '
            .'Nie używaj Markdown ani bloków kodu. Odpowiedz wyłącznie poprawionym HTML, bez '
            .'wstępu i komentarzy.';

        if ($maxChars !== null) {
            $system .= " Wynik nie może przekroczyć {$maxChars} znaków.";
        }

        // Zdejmij ewentualne opakowanie w blok kodu (```html ... ```), gdyby model je dodał.
        $result = preg_replace(
            '/^```[a-z]*\s*|\s*```$/i',
            '',
            $this->ai->run(self::TASK, $system, $html)
        );

        return trim((string) $result);
    }
}
