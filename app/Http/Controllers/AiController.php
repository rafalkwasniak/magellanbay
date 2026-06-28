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
        // Pola, które AI może redagować, wraz z ich maksymalną długością.
        $limits = [
            'shop_description' => (int) config('shop.description_max'),
        ];

        $field = (string) $request->input('field');

        $validated = $request->validate([
            'field' => ['required', Rule::in(array_keys($limits))],
            'text' => ['required', 'string', 'max:'.($limits[$field] ?? max($limits))],
        ]);

        try {
            $improved = $ai->improve($validated['text'], $limits[$validated['field']]);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Usługa AI jest chwilowo niedostępna. Spróbuj ponownie później.',
            ], 503);
        }

        return response()->json(['text' => $improved]);
    }
}
