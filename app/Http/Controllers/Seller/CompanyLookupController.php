<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\CompanyLookup;
use App\Services\NipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pobranie danych firmy po NIP (Biała lista MF) do auto-uzupełnienia profilu.
 * Zwraca dane — nie zapisuje; przy braku wyniku front zostawia pola do ręcznego
 * uzupełnienia.
 */
class CompanyLookupController extends Controller
{
    public function __invoke(Request $request, CompanyLookup $lookup, NipService $nipService): JsonResponse
    {
        $nip = $nipService->normalize($request->input('nip'));

        if ($nip === null || ! $nipService->isValid($nip)) {
            return response()->json(['message' => 'Podaj prawidłowy NIP (10 cyfr).'], 422);
        }

        $data = $lookup->byNip($nip);

        if ($data === null) {
            return response()->json([
                'message' => 'Nie znaleziono firmy dla tego NIP. Uzupełnij dane ręcznie.',
            ], 404);
        }

        return response()->json($data);
    }
}
