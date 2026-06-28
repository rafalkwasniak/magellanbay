<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Redakcja treści przez API DeepSeek. Dwie ścieżki:
 * - improve(): zwykły tekst (krótki prompt) — pola bez formatowania.
 * - improveHtml(): fragment HTML z edytora (dłuższy prompt) — model poprawia
 *   wyłącznie tekst wewnątrz znaczników, nie ruszając samego HTML.
 *
 * Poprawiane są tylko ortografia, interpunkcja, styl i czytelność — bez dodawania
 * nowych informacji. Klucz API zostaje po stronie serwera, treści nie logujemy.
 * Wzorzec przeniesiony z projektu kociaczek.com.pl.
 */
class AiTextImprover
{
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

        return $this->ask($system, $text);
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
        $result = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $this->ask($system, $html));

        return trim((string) $result);
    }

    /**
     * Pojedyncze wywołanie API: wiadomość systemowa (instrukcja) + treść użytkownika.
     *
     * @throws RuntimeException
     */
    private function ask(string $system, string $content): string
    {
        $key = config('services.deepseek.key');

        if (empty($key)) {
            throw new RuntimeException('Usługa AI nie jest skonfigurowana.');
        }

        $response = Http::baseUrl(rtrim((string) config('services.deepseek.base_url'), '/'))
            ->withToken($key)
            ->timeout(30)
            ->post('/chat/completions', [
                'model' => config('services.deepseek.model', 'deepseek-chat'),
                'temperature' => 0.3,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $content],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Wywołanie AI zakończyło się błędem.');
        }

        $improved = $response->json('choices.0.message.content');

        if (! is_string($improved) || trim($improved) === '') {
            throw new RuntimeException('AI zwróciło pustą odpowiedź.');
        }

        return trim($improved);
    }
}
