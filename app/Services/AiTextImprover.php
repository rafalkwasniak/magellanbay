<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Redakcja tekstu napisanego przez użytkownika przez API DeepSeek. Poprawia
 * wyłącznie ortografię, interpunkcję, styl i czytelność — nie dodaje nowych
 * informacji. Klucz API zostaje po stronie serwera, a treści nie logujemy.
 * Wzorzec przeniesiony z projektu kociaczek.com.pl.
 */
class AiTextImprover
{
    /**
     * Zwraca poprawioną wersję tekstu.
     *
     * @param  int|null  $maxChars  Opcjonalny twardy limit długości wyniku.
     *
     * @throws RuntimeException gdy usługa nie jest skonfigurowana lub wywołanie zawiedzie.
     */
    public function improve(string $text, ?int $maxChars = null): string
    {
        $key = config('services.deepseek.key');

        if (empty($key)) {
            throw new RuntimeException('Usługa AI nie jest skonfigurowana.');
        }

        $system = 'Jesteś redaktorem języka polskiego. Popraw przesłany tekst: ortografię, '
            .'interpunkcję, styl i czytelność; możesz poprawić formatowanie. Nie dodawaj żadnych '
            .'nowych informacji ani faktów, których nie ma w oryginale. Odpowiedz wyłącznie '
            .'poprawioną treścią, bez wstępu i komentarzy. Jeśli w wyrażeniu brakuje kluczowych '
            .'słów, dodaj je.';

        if ($maxChars !== null) {
            $system .= " Wynik nie może przekroczyć {$maxChars} znaków.";
        }

        $response = Http::baseUrl(rtrim((string) config('services.deepseek.base_url'), '/'))
            ->withToken($key)
            ->timeout(30)
            ->post('/chat/completions', [
                'model' => config('services.deepseek.model', 'deepseek-chat'),
                'temperature' => 0.3,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $text],
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
