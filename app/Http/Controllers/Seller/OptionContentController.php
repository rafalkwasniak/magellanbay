<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\OptionGroup;
use App\Support\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Zawartość grupy opcji: pola formatki albo pozycje biblioteki.
 *
 * ZAPIS CAŁEJ ZAWARTOŚCI JEDNYM ŻĄDANIEM, a nie wiersz po wierszu. Sprzedawca
 * układa formatkę jak listę — poprawia dwa limity, przestawia kolejność
 * i dopisuje trzecie pole — i chce zobaczyć efekt raz, a nie po każdym polu
 * z osobna. Przy okazji nie ma stanu pośredniego, w którym połowa zmian
 * jest zapisana, a połowa nie.
 *
 * ---------------------------------------------------------------------------
 * DLACZEGO POLA WOLNO KASOWAĆ, A POZYCJI BIBLIOTEKI NIE
 *
 * Pozycja zamówienia niesie dwie migawki: `personalisation` (etykieta → wartość,
 * dla człowieka) i `configuration` (identyfikatory, dla maszyny).
 *
 * Skasowanie POLA gubi tylko drugą z nich, a przy nadruku nie ma to znaczenia:
 * do wykonania potrzebny jest sam tekst, który stoi w migawce dla człowieka.
 *
 * Skasowanie POZYCJI BIBLIOTEKI gubi PLIK GRAFICZNY, wskazywany właśnie po
 * identyfikatorze. Zamówienie sprzed miesiąca nadal mówiłoby „Trasa Biegu",
 * ale nie dałoby się jej wygrawerować. Dlatego pozycje się WYGASZA — znikają
 * z wyboru, zostają w historii.
 */
class OptionContentController extends Controller
{
    /**
     * Pola formatki — zapis całej listy.
     */
    public function saveFields(Request $request, OptionGroup $optionGroup): RedirectResponse
    {
        $this->authorizeGroup($request, $optionGroup);
        abort_unless($optionGroup->isText(), 404);

        $data = $request->validate([
            'items' => ['array'],
            'items.*.label' => ['nullable', 'string', 'max:80'],
            // Limit wynika z fizyki produktu — na magnes wchodzi tyle liter, ile
            // wchodzi. Sufit 500 jest po to, żeby literówka w rodzaju „1200"
            // nie przeszła jako limit dłuższy niż sam produkt.
            'items.*.max_length' => ['nullable', 'integer', 'min:1', 'max:500'],
            'items.*.placeholder' => ['nullable', 'string', 'max:120'],
        ], [
            'items.*.max_length.max' => 'Limit znaków jest podejrzanie wysoki — sprawdź, czy to na pewno tyle.',
        ]);

        $existing = $optionGroup->fields()->get()->keyBy('id');
        $position = 0;

        foreach ($data['items'] ?? [] as $key => $item) {
            $label = trim((string) ($item['label'] ?? ''));
            $current = $existing->get((int) $key);

            // Pusta etykieta w NOWYM wierszu to po prostu niewypełniony wiersz —
            // formularz ma zawsze jeden pusty na dole, więc nie robimy z tego błędu.
            if ($label === '') {
                if ($current !== null && $request->boolean('items.'.$key.'._delete')) {
                    $current->delete();
                }

                continue;
            }

            $attributes = [
                'label' => $label,
                'max_length' => (int) ($item['max_length'] ?? 30),
                'required' => $request->boolean('items.'.$key.'.required'),
                'placeholder' => trim((string) ($item['placeholder'] ?? '')) ?: null,
                'position' => $position++,
            ];

            if ($current !== null) {
                $request->boolean('items.'.$key.'._delete')
                    ? $current->delete()
                    : $current->update($attributes);

                continue;
            }

            $optionGroup->fields()->create($attributes);
        }

        return redirect()->route('seller.options.edit', $optionGroup)->with('success', 'Zapisano pola formatki.');
    }

    /**
     * Pozycje biblioteki — zapis całej listy razem z grafikami.
     */
    public function saveChoices(Request $request, OptionGroup $optionGroup): RedirectResponse
    {
        $this->authorizeGroup($request, $optionGroup);
        abort_unless($optionGroup->isChoice(), 404);

        $shopId = $request->user()->shop->id;

        /*
         * Kwoty normalizujemy PRZED walidacją, nie po.
         *
         * Reguła `numeric` odrzuca „3,00" — a to jest dokładnie ten zapis,
         * którego używa każdy polski sprzedawca i który przyjmujemy wszędzie
         * indziej. Normalizacja po walidacji znaczyła, że formularz odrzucał
         * poprawnie wpisaną kwotę i nie zapisywał nic. Złapały to testy.
         */
        $this->normaliseAmounts($request);

        $data = $request->validate([
            'items' => ['array'],
            'items.*.label' => ['nullable', 'string', 'max:120'],
            'items.*.surcharge_gross' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'items.*.licence_fee_gross' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'items.*.licensor_id' => [
                'nullable',
                Rule::exists('licensors', 'id')->where('shop_id', $shopId),
            ],
            'items.*.image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.config('shop.product_images.max_upload_kb')],
        ], [
            'items.*.image.image' => 'Grafika musi być plikiem obrazu (JPG, PNG albo WebP).',
            'items.*.image.max' => 'Grafika jest za duża.',
        ]);

        $existing = $optionGroup->choices()->get()->keyBy('id');
        $position = 0;

        foreach ($data['items'] ?? [] as $key => $item) {
            $label = trim((string) ($item['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $current = $existing->get((int) $key);

            $attributes = [
                'label' => $label,
                'surcharge_gross' => $this->amount($item['surcharge_gross'] ?? null),
                'licence_fee_gross' => $this->amount($item['licence_fee_gross'] ?? null),
                'licensor_id' => $item['licensor_id'] ?? null,
                'is_active' => $request->boolean('items.'.$key.'.is_active'),
                'position' => $position++,
            ];

            $file = $request->file('items.'.$key.'.image');

            if ($file instanceof UploadedFile) {
                $attributes['image_path'] = $this->storeImage($file, $optionGroup);

                // Podmiana grafiki kasuje poprzednią — inaczej po roku katalog
                // pełen jest plików, których nic nie pokazuje i nikt nie sprząta.
                if ($current !== null && filled($current->image_path)) {
                    Storage::disk('public')->delete($current->image_path);
                }
            }

            $current !== null
                ? $current->update($attributes)
                : $optionGroup->choices()->create($attributes);
        }

        return redirect()->route('seller.options.edit', $optionGroup)->with('success', 'Zapisano bibliotekę.');
    }

    /**
     * Przepisuje kwoty w żądaniu na postać, którą rozumie walidator.
     *
     * Dotyka WYŁĄCZNIE pól kwotowych i zostawia wszystko inne — w tym pliki,
     * które przy pełnym `merge()` zamieniłyby się w tablice i przestały być
     * rozpoznawane jako upload.
     */
    private function normaliseAmounts(Request $request): void
    {
        $items = $request->input('items', []);

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $key => $item) {
            foreach (['surcharge_gross', 'licence_fee_gross'] as $pole) {
                if (! isset($item[$pole])) {
                    continue;
                }

                $items[$key][$pole] = $this->amount($item[$pole]);
            }
        }

        $request->merge(['items' => $items]);
    }

    /**
     * Polski zapis kwoty: przecinek i spacje jako separator tysięcy.
     */
    private function amount(mixed $value): float
    {
        return (float) str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim((string) $value));
    }

    private function storeImage(UploadedFile $file, OptionGroup $group): string
    {
        $binary = ImageOptimizer::toWebp(
            $file,
            (int) config('shop.product_images.max_side', 1600),
            (int) config('shop.product_images.quality', 82),
        );

        $path = 'option-choices/'.$group->id.'/'.Str::uuid()->toString().'.webp';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function authorizeGroup(Request $request, OptionGroup $group): void
    {
        abort_unless($group->shop_id === $request->user()->shop?->id, 404);
    }
}
