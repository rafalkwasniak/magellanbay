<?php

namespace App\Http\Controllers;

use App\Services\AiTextImprover;
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
        ];

        $field = (string) $request->input('field');
        $max = $fields[$field]['max'] ?? max(array_column($fields, 'max'));

        $validated = $request->validate([
            'field' => ['required', Rule::in(array_keys($fields))],
            'text' => ['required', 'string', 'max:'.$max],
        ]);

        $config = $fields[$validated['field']];

        // Przychodzi FRAGMENT, nie całe pole — dzieli przeglądarka (resources/js/ai.js),
        // bo długi tekst w jednym wywołaniu przekroczyłby timeout. Limit długości
        // wyniku liczymy więc względem tego, co faktycznie przyszło: przy korekcie
        // tekst nie ma prawa spuchnąć. Limitu całego pola pilnuje walidacja zapisu.
        $maxOut = max(200, (int) ceil(mb_strlen($validated['text']) * 1.3));

        try {
            $improved = $config['html']
                ? $ai->improveHtml($validated['text'], $maxOut)
                : $ai->improve($validated['text'], $maxOut);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Usługa AI jest chwilowo niedostępna. Spróbuj ponownie później.',
            ], 503);
        }

        return response()->json(['text' => $improved]);
    }
}
