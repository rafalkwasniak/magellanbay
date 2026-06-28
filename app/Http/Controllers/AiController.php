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
        ];

        $field = (string) $request->input('field');
        $max = $fields[$field]['max'] ?? max(array_column($fields, 'max'));

        $validated = $request->validate([
            'field' => ['required', Rule::in(array_keys($fields))],
            'text' => ['required', 'string', 'max:'.$max],
        ]);

        $config = $fields[$validated['field']];

        try {
            $improved = $config['html']
                ? $ai->improveHtml($validated['text'], $config['max'])
                : $ai->improve($validated['text'], $config['max']);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Usługa AI jest chwilowo niedostępna. Spróbuj ponownie później.',
            ], 503);
        }

        return response()->json(['text' => $improved]);
    }
}
