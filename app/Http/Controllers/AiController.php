<?php

namespace App\Http\Controllers;

use App\Exceptions\AiQuotaExceededException;
use App\Services\AiQuota;
use App\Services\AiTextImprover;
use App\Services\SeoDescriptionWriter;
use App\Support\Excerpt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Redakcja treści przez AI („Popraw przez AI"). Zwraca poprawioną wersję pola —
 * nigdy nie zapisuje; przy błędzie usługi front zostawia oryginał. Wzorzec
 * przeniesiony z kociaczek.com.pl.
 */
class AiController extends Controller
{
    public function improve(Request $request, AiTextImprover $ai): JsonResponse
    {
        // Pola, które AI może redagować: maksymalna długość + tryb (html|tekst).
        $fields = [
            'shop_description' => ['max' => (int) config('shop.description_max'), 'html' => true],
            'product_description' => ['max' => (int) config('shop.product_description_max'), 'html' => true],
            'page_content' => ['max' => (int) config('pages.content_max'), 'html' => true],
            'mailing_body' => ['max' => (int) config('bulk_mail.body_max'), 'html' => true],
        ];

        $field = (string) $request->input('field');
        $max = $fields[$field]['max'] ?? max(array_column($fields, 'max'));

        $validated = $request->validate([
            'field' => ['required', Rule::in(array_keys($fields))],
            'text' => ['required', 'string', 'max:'.$max],
            // Identyfikator KLIKNIĘCIA, wspólny dla wszystkich fragmentów jednego
            // pola. Dzięki niemu poprawa długiego opisu liczy się jako JEDNO
            // zadanie, a nie kilkanaście (ustalenie Rafała 2026-07-28).
            'task_id' => ['nullable', 'string', 'max:64'],
        ]);

        $config = $fields[$validated['field']];

        // Limit AI jest przypisany do SKLEPU, więc bez sklepu nie ma z czego go
        // pobrać. W praktyce nie zdarza się (sklep powstaje przy aktywacji konta),
        // ale lepiej powiedzieć to wprost niż udawać awarię usługi.
        $shop = $request->user()->shop;

        if ($shop === null) {
            return response()->json(['message' => 'Najpierw dokończ zakładanie sklepu.'], 403);
        }

        // Przychodzi FRAGMENT, nie całe pole — dzieli przeglądarka (resources/js/ai.js),
        // bo długi tekst w jednym wywołaniu przekroczyłby timeout. Limit długości
        // wyniku liczymy więc względem tego, co faktycznie przyszło: przy korekcie
        // tekst nie ma prawa spuchnąć. Limitu całego pola pilnuje walidacja zapisu.
        $maxOut = max(200, (int) ceil(mb_strlen($validated['text']) * 1.3));

        try {
            $improved = $config['html']
                ? $ai->improveHtml($validated['text'], $shop, $maxOut, $validated['task_id'] ?? null)
                : $ai->improve($validated['text'], $shop, $maxOut, $validated['task_id'] ?? null);
        } catch (AiQuotaExceededException $e) {
            return $this->quotaResponse($e);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Usługa AI jest chwilowo niedostępna. Spróbuj ponownie później.',
            ], 503);
        }

        // Licznik odsyłamy razem z wynikiem, żeby przeglądarka mogła go zbić
        // od razu — bez tego sprzedawca klika i widzi wciąż tę samą liczbę.
        return response()->json([
            'text' => $improved,
            'remaining' => app(AiQuota::class)->remaining($shop),
        ]);
    }

    /**
     * Napisanie opisu SEO na żądanie („Wygeneruj z AI" w boksie SEO). Zwraca sam
     * tekst — NIE zapisuje. Sprzedawca ma go zobaczyć, ewentualnie poprawić i
     * dopiero zatwierdzić przyciskiem „Zapisz"; inaczej kliknięcie byłoby ruchem
     * w ciemno.
     */
    public function seoDescription(Request $request, SeoDescriptionWriter $writer): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:20000'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $shop = $request->user()->shop;

        if ($shop === null) {
            return response()->json(['message' => 'Najpierw dokończ zakładanie sklepu.'], 403);
        }

        $source = Excerpt::plainText($validated['text']);

        if (! SeoDescriptionWriter::hasEnoughSource($source)) {
            return response()->json([
                'message' => 'Za mało treści, aby napisać opis. Uzupełnij opis i spróbuj ponownie.',
            ], 422);
        }

        try {
            $description = $writer->fromText($source, $shop, (string) ($validated['name'] ?? ''));
        } catch (AiQuotaExceededException $e) {
            return $this->quotaResponse($e);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Usługa AI jest chwilowo niedostępna. Spróbuj ponownie później.',
            ], 503);
        }

        return response()->json([
            'text' => $description,
            'remaining' => app(AiQuota::class)->remaining($shop),
        ]);
    }

    /**
     * Odpowiedź po wyczerpaniu tygodniowego limitu. Mówi KIEDY limit wraca i co
     * daje wyższy pakiet — to jedyny moment, w którym upsell jest na miejscu,
     * a suchy komunikat o błędzie zostawiłby sprzedawcę bez odpowiedzi na
     * pytanie „to kiedy znów mogę kliknąć?".
     */
    private function quotaResponse(AiQuotaExceededException $e): JsonResponse
    {
        return response()->json([
            // Ton informacyjny, nie karcący: sprzedawca nie zrobił nic złego,
            // tylko wykorzystał swoją pulę. Najpierw fakt, potem KIEDY wraca
            // (jedyne, co go teraz interesuje), na końcu delikatny upsell.
            'message' => 'Wykorzystałeś całą pulę AI na ten tydzień — '.$e->limit.' użyć. '
                .'Nowa czeka już w '.$e->resetsAt->translatedFormat('l, j F').'. '
                .'W wyższym pakiecie pula jest większa.',
        ], 429);
    }
}
