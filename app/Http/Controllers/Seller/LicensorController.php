<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\LicensorRequest;
use App\Models\Licensor;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kartoteka licencjodawców — firm inkasujących opłatę za użycie swojego znaku
 * lub grafiki (organizator biegu, klub, wydawca).
 *
 * Widoczna WYŁĄCZNIE dla sprzedawcy. Kupujący widzi opłatę w rozbiciu ceny, ale
 * nie ma powodu wiedzieć, z kim sklep ma podpisaną umowę.
 *
 * KASOWANIE JEST OGRANICZONE, i to nie z ostrożności. Kartoteka jest adresatem
 * pieniędzy: skasowanie partnera, na którego poszła choć jedna sprzedaż,
 * zamieniłoby rozliczenie sprzed roku w listę kwot bez odbiorcy. Dlatego
 * kasować wolno tylko wpis NIEUŻYWANY — literówkę dodaną przed chwilą.
 * Partner, z którym skończyliśmy współpracę, jest GASZONY: znika z wyboru,
 * zostaje w historii.
 */
class LicensorController extends Controller
{
    public function index(Request $request): Renderable
    {
        $shop = $request->user()->shop;
        abort_if($shop === null, 404);

        return view('seller.licensors.index', [
            'licensors' => $shop->licensors()
                ->withCount(['choices', 'components'])
                ->get(),
        ]);
    }

    public function create(Request $request): Renderable
    {
        abort_if($request->user()->shop === null, 404);

        return view('seller.licensors.form', ['licensor' => new Licensor(['is_active' => true])]);
    }

    public function store(LicensorRequest $request): RedirectResponse
    {
        $request->user()->shop->licensors()->create($request->validated());

        return redirect()->route('seller.licensors.index')->with('success', 'Partner dodany do kartoteki.');
    }

    public function edit(Request $request, Licensor $licensor): Renderable
    {
        $this->authorizeLicensor($request, $licensor);

        return view('seller.licensors.form', ['licensor' => $licensor]);
    }

    public function update(LicensorRequest $request, Licensor $licensor): RedirectResponse
    {
        $this->authorizeLicensor($request, $licensor);

        $licensor->update($request->validated());

        return redirect()->route('seller.licensors.index')->with('success', 'Zapisano zmiany.');
    }

    /**
     * Wyłączenie/włączenie partnera. Wygaszony znika z wyboru przy grafikach
     * i produktach, ale zostaje w rozliczeniach — to jest normalne zakończenie
     * współpracy, nie kasowanie.
     */
    public function toggle(Request $request, Licensor $licensor): RedirectResponse
    {
        $this->authorizeLicensor($request, $licensor);

        $licensor->update(['is_active' => ! $licensor->is_active]);

        return redirect()->route('seller.licensors.index')->with(
            'success',
            $licensor->is_active ? 'Partner znów jest aktywny.' : 'Partner wygaszony — zostaje w rozliczeniach.'
        );
    }

    public function destroy(Request $request, Licensor $licensor): RedirectResponse
    {
        $this->authorizeLicensor($request, $licensor);

        /*
         * Kasujemy TYLKO wpis, na który nie poszła jeszcze żadna sprzedaż i do
         * którego nic nie jest przypięte. Reszta idzie do wygaszenia — inaczej
         * jedno kliknięcie zabiera adresata pieniędzy z historii.
         */
        if ($this->isInUse($licensor)) {
            return redirect()->route('seller.licensors.index')->with(
                'error',
                'Tego partnera nie da się usunąć — jest przypisany do produktów, grafik albo sprzedaży. Wygaś go zamiast kasować.'
            );
        }

        $licensor->delete();

        return redirect()->route('seller.licensors.index')->with('success', 'Partner usunięty z kartoteki.');
    }

    private function isInUse(Licensor $licensor): bool
    {
        return $licensor->components()->exists()
            || $licensor->choices()->exists()
            || $licensor->shop->products()->where('licensor_id', $licensor->id)->exists();
    }

    /**
     * Partner musi należeć do sklepu zalogowanego sprzedawcy — inaczej 404
     * (nie zdradzamy istnienia cudzej kartoteki).
     */
    private function authorizeLicensor(Request $request, Licensor $licensor): void
    {
        abort_unless($licensor->shop_id === $request->user()->shop?->id, 404);
    }
}
