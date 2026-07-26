<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Jedyne miejsce w aplikacji, które rozmawia z API modelu językowego.
 *
 * Wołający podaje ZADANIE, nie model — czym zostanie obsłużone, rozstrzyga
 * `config/ai.php`. Treści użytkowników nie trafiają do logów, klucz zostaje
 * po stronie serwera.
 */
class AiClient
{
    /**
     * Wykonaj zadanie: instrukcja systemowa + treść użytkownika → odpowiedź modelu.
     *
     * @param  string  $task  Nazwa zadania z `config('ai.tasks')`.
     *
     * @throws RuntimeException gdy zadanie nie jest skonfigurowane lub wywołanie zawiedzie.
     */
    public function run(string $task, string $system, string $content): string
    {
        $profile = AiProfile::forTask($task);

        if (! $profile->isConfigured()) {
            throw new RuntimeException("Usługa AI nie jest skonfigurowana (zadanie: {$task}).");
        }

        $payload = [
            'model' => $profile->model,
            'temperature' => $profile->temperature,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $content],
            ],
        ];

        if ($profile->reasoningEffort !== null) {
            $payload['reasoning_effort'] = $profile->reasoningEffort;
        }

        $response = Http::baseUrl($profile->baseUrl)
            ->withToken($profile->key)
            ->timeout($profile->timeout)
            ->post('/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException("Wywołanie AI zakończyło się błędem (zadanie: {$task}).");
        }

        // Modele rozumujące zwracają tok myślenia w osobnym polu
        // („reasoning_content") — nas interesuje wyłącznie właściwa odpowiedź.
        $answer = $response->json('choices.0.message.content');

        if (! is_string($answer) || trim($answer) === '') {
            throw new RuntimeException("AI zwróciło pustą odpowiedź (zadanie: {$task}).");
        }

        return trim($answer);
    }
}
