<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OptionGroupKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\OptionGroupRequest;
use App\Models\OptionGroup;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Grupy opcji — biblioteka sprzedawcy, z której buduje się personalizację.
 *
 * DLACZEGO GRUPA NALEŻY DO SKLEPU, A NIE DO PRODUKTU: „Nadruk 3 linie"
 * definiuje się RAZ i przypina do stu magnesów. Zmiana limitu znaków poprawia
 * wtedy sto kart naraz, a nie sto razy jedną.
 *
 * KASOWANIE JEST OGRANICZONE. Grupa przypięta do produktów to działająca
 * personalizacja — skasowana, zabiera ze sobą pola i pozycje biblioteki
 * (kaskada), a produkty tracą możliwość personalizacji bez słowa ostrzeżenia.
 * Historia zamówień to przeżyje (pozycja niesie własną migawkę), ale plik do
 * graweru, wskazywany po identyfikatorze pozycji, już nie. Dlatego najpierw
 * odepnij grupę od produktów.
 */
class OptionGroupController extends Controller
{
    public function index(Request $request): Renderable
    {
        $shop = $request->user()->shop;
        abort_if($shop === null, 404);

        return view('seller.options.index', [
            'groups' => $shop->optionGroups()
                ->with('excludes')
                ->withCount(['fields', 'choices', 'products'])
                ->get(),
        ]);
    }

    public function create(Request $request): Renderable
    {
        $shop = $request->user()->shop;
        abort_if($shop === null, 404);

        return view('seller.options.form', [
            'group' => new OptionGroup(['required' => false, 'surcharge_gross' => 0]),
            'others' => $shop->optionGroups()->get(),
            'kinds' => OptionGroupKind::cases(),
            'licensors' => $shop->licensors()->active()->get(),
        ]);
    }

    public function store(OptionGroupRequest $request): RedirectResponse
    {
        $group = $request->user()->shop->optionGroups()->create($request->validated());

        /*
         * Po utworzeniu prowadzimy WPROST do zawartości grupy, a nie z powrotem
         * na listę. Sama grupa jeszcze nic nie robi — dopóki nie ma pól albo
         * pozycji biblioteki, jest pustym pytaniem i nie da się jej sensownie
         * przypiąć do produktu.
         */
        return redirect()->route('seller.options.edit', $group)
            ->with('success', 'Grupa utworzona. Dodaj teraz '.($group->isText() ? 'pola do wypełnienia.' : 'pozycje biblioteki.'));
    }

    public function edit(Request $request, OptionGroup $optionGroup): Renderable
    {
        $this->authorizeGroup($request, $optionGroup);

        return view('seller.options.form', [
            'group' => $optionGroup->load(['fields', 'choices.licensor']),
            'others' => $request->user()->shop->optionGroups()->whereKeyNot($optionGroup->id)->get(),
            'kinds' => OptionGroupKind::cases(),
            // Do wyboru przy grafice — tylko partnerzy AKTYWNI. Wygaszony
            // zostaje na pozycjach, ktore juz go maja, ale nie da sie go
            // przypisac na nowo.
            'licensors' => $request->user()->shop->licensors()->active()->get(),
        ]);
    }

    public function update(OptionGroupRequest $request, OptionGroup $optionGroup): RedirectResponse
    {
        $this->authorizeGroup($request, $optionGroup);

        $optionGroup->update($request->safe()->except('kind'));

        return redirect()->route('seller.options.edit', $optionGroup)->with('success', 'Zapisano zmiany.');
    }

    public function destroy(Request $request, OptionGroup $optionGroup): RedirectResponse
    {
        $this->authorizeGroup($request, $optionGroup);

        if ($optionGroup->products()->exists()) {
            return redirect()->route('seller.options.index')->with(
                'error',
                'Ta grupa jest przypięta do produktów. Odepnij ją najpierw — inaczej stracą personalizację bez ostrzeżenia.'
            );
        }

        $optionGroup->delete();

        return redirect()->route('seller.options.index')->with('success', 'Grupa usunięta.');
    }

    /**
     * Grupa musi należeć do sklepu zalogowanego sprzedawcy — inaczej 404
     * (nie zdradzamy istnienia cudzej biblioteki).
     */
    private function authorizeGroup(Request $request, OptionGroup $group): void
    {
        abort_unless($group->shop_id === $request->user()->shop?->id, 404);
    }
}
