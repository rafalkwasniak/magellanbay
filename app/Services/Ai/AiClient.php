<?php

namespace App\Services\Ai;

use App\Models\Shop;
use App\Services\AiQuota;
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
    public function __construct(private readonly AiQuota $quota) {}

    /**
     * Wykonaj zadanie: instrukcja systemowa + treść użytkownika → odpowiedź modelu.
     *
     * Sklep jest OBOWIĄZKOWY, choć technicznie do wywołania modelu niepotrzebny:
     * to jedyne miejsce w aplikacji rozmawiające z API, więc pobranie jednostki
     * limitu ma się tu odbyć zawsze. Gdyby parametr był opcjonalny, nowe miejsce
     * wołające AI ominęłoby limit przez zwykłe zapomnienie.
     *
     * `$taskId` scala fragmenty jednego kliknięcia w jedno zadanie (patrz AiQuota).
     *
     * @param  string  $task  Nazwa zadania z `config('ai.tasks')`.
     *
     * @throws \App\Exceptions\AiQuotaExceededException gdy sklep wyczerpał tygodniowy limit
     * @throws RuntimeException gdy zadanie nie jest skonfigurowane lub wywołanie zawiedzie
     */
    public function run(string $task, string $system, string $content, Shop $shop, ?string $taskId = null): string
    {
        $profile = AiProfile::forTask($task);

        if (! $profile->isConfigured()) {
            throw new RuntimeException("Usługa AI nie jest skonfigurowana (zadanie: {$task}).");
        }

        // Limit pobieramy PRZED wysłaniem żądania — po odpowiedzi byłoby za późno,
        // bo koszt już powstał.
        $this->quota->consume($shop, $taskId);

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
