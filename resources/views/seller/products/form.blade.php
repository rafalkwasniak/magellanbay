<x-layouts.panel :title="$product->exists ? 'Edytuj produkt' : 'Nowy produkt'">
    <x-slot:heading>{{ $product->exists ? 'Edytuj produkt' : 'Nowy produkt' }}</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <form method="POST"
                action="{{ $product->exists ? route('seller.products.update', ['product' => $product] + $listQuery) : route('seller.products.store') }}"
                class="space-y-6" enctype="multipart/form-data" novalidate data-validate>
                @csrf

                {{-- Dane podstawowe. relative z-20: podnosi kontekst tej karty ponad
                     kartę zdjęć poniżej, żeby dropdown podpowiedzi tagów nie chował
                     się pod nią (karty mają backdrop-blur = własny stacking context). --}}
                <div class="relative z-20 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Dane produktu</h2>
                    <p class="mt-1 text-sm text-stone-500">Nazwa i opis widoczne dla klientów.</p>

                    <div class="mt-6 space-y-5">
                        <div>
                            <label for="name" class="block text-sm font-medium text-stone-700">Nazwa</label>
                            <input id="name" name="name" type="text" required
                                value="{{ old('name', $product->name) }}"
                                data-msg-required="Podaj nazwę produktu."
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                            @error('name')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-stone-700">Opis <span class="text-stone-400">(opcjonalnie)</span></label>
                            <x-rich-editor name="description" :value="old('description', $product->description)" ai-field="product_description" :max="config('shop.product_description_max')">Opisz produkt — najważniejsze cechy, materiał, zastosowanie.</x-rich-editor>
                            @error('description')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-stone-700">Tagi <span class="text-stone-400">(opcjonalnie)</span></label>
                            <div data-tag-input data-suggestions='@json($tagSuggestions)' class="relative">
                                <input type="hidden" name="tags" data-tag-value
                                    value="{{ old('tags', $product->exists ? $product->tags->pluck('name')->implode(',') : '') }}">
                                <div data-tag-box class="mt-1.5 flex flex-wrap items-center gap-2 rounded-2xl border border-stone-200 bg-white/80 px-3 py-2.5 shadow-sm transition focus-within:border-amber-500 focus-within:ring-4 focus-within:ring-amber-500/15">
                                    <input type="text" data-tag-text placeholder="Dodaj tag i naciśnij Enter"
                                        class="min-w-[8rem] flex-1 border-0 bg-transparent p-0 text-sm placeholder:text-stone-400 focus:outline-none focus:ring-0">
                                </div>
                                <div data-tag-suggestions class="absolute z-20 mt-1 hidden max-h-56 w-full overflow-auto rounded-2xl border border-stone-200 bg-white py-1 shadow-lg shadow-stone-900/10"></div>
                            </div>
                            <p class="mt-1.5 text-xs text-stone-400">Enter lub przecinek dodaje tag. Małe litery, polskie znaki zostają. Podpowiadamy z Twoich tagów.</p>
                            @error('tags')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Zdjęcia produktu (akcje przez JS — bez zagnieżdżonych formularzy, więc karta jest wewnątrz formularza) --}}
                @if ($product->exists)
                    <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                        <div class="flex items-center justify-between">
                            <h2 class="font-semibold text-stone-900">Zdjęcia produktu</h2>
                            <span class="text-sm text-stone-500"><span data-gallery-count>{{ $product->images->count() }}</span> / 8</span>
                        </div>
                        <p class="mt-1 text-sm text-stone-500">Pierwsze zdjęcie jest główne. Przeciągnij miniatury lub użyj strzałek, aby zmienić kolejność.</p>

                        <div data-gallery
                            data-reorder-url="{{ route('seller.products.images.reorder', $product) }}"
                            data-store-url="{{ route('seller.products.images.store', $product) }}"
                            data-max="8"
                            class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4 {{ $product->images->isEmpty() ? 'hidden' : '' }}">
                            @foreach ($product->images as $image)
                                <div data-gallery-item data-id="{{ $image->id }}" draggable="true" class="relative cursor-move rounded-2xl border border-stone-200 bg-stone-50 p-2">
                                    <div class="flex h-28 items-center justify-center overflow-hidden rounded-xl bg-white">
                                        <img src="{{ $image->url() }}" alt="Zdjęcie produktu" draggable="false" class="h-full w-auto object-contain">
                                    </div>
                                    <span data-main-badge class="absolute left-3 top-3 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700 {{ $loop->first ? '' : 'hidden' }}">Główne</span>
                                    <div class="mt-2 flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1">
                                            <button type="button" data-move="prev" aria-label="Przesuń wcześniej" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs text-stone-600 transition hover:bg-stone-100 disabled:opacity-40">◀</button>
                                            <button type="button" data-move="next" aria-label="Przesuń później" class="rounded-lg border border-stone-200 bg-white px-2 py-1 text-xs text-stone-600 transition hover:bg-stone-100 disabled:opacity-40">▶</button>
                                        </div>
                                        <button type="button" data-gallery-delete data-url="{{ route('seller.products.images.destroy', [$product, $image]) }}" class="text-xs font-medium text-rose-700 transition hover:text-rose-800">Usuń</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-5" data-gallery-uploader>
                            <input type="file" data-gallery-upload multiple accept="image/png,image/jpeg,image/webp"
                                class="block w-full text-sm text-stone-500 file:mr-4 file:rounded-xl file:border-0 file:bg-amber-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-amber-800 file:transition hover:file:bg-amber-200">
                            <p class="mt-2 text-xs text-stone-400">PNG, JPG lub WebP, do 4 MB. Maksymalnie 8 zdjęć — wybierz, dodamy od razu.</p>
                        </div>
                        <p class="mt-5 hidden text-xs text-stone-400" data-gallery-full>Osiągnięto limit 8 zdjęć — usuń jedno, aby dodać nowe.</p>
                    </div>
                @else
                    {{-- Nowy produkt: zdjęcia lecą razem z formularzem (po zapisie produkt ma ID i je zapisujemy w kolejności dodania). --}}
                    <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                        <h2 class="font-semibold text-stone-900">Zdjęcia produktu <span class="text-sm font-normal text-stone-400">(opcjonalnie)</span></h2>
                        <p class="mt-1 text-sm text-stone-500">Wybierz do 8 zdjęć — dodamy je przy zapisie. Pierwsze będzie główne; kolejność zmienisz później w edycji.</p>

                        <div class="mt-5" data-new-images>
                            <input type="file" name="images[]" multiple accept="image/png,image/jpeg,image/webp" data-new-images-input
                                class="block w-full text-sm text-stone-500 file:mr-4 file:rounded-xl file:border-0 file:bg-amber-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-amber-800 file:transition hover:file:bg-amber-200">
                            <div data-new-images-preview class="mt-4 grid grid-cols-2 gap-4 hidden sm:grid-cols-4"></div>
                            <p class="mt-2 text-xs text-stone-400">PNG, JPG lub WebP, do 4 MB. Maksymalnie 8 zdjęć.</p>
                            @error('images')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                            @error('images.*')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endif

                {{-- Cena i stan --}}
                @php($selectedUnit = old('sale_unit', $product->sale_unit?->value ?? $defaultSaleUnit))
                @php($unitMeta = collect(\App\Enums\SaleUnit::cases())->mapWithKeys(fn ($u) => [$u->value => ['abbr' => $u->abbreviation(), 'step' => $u->step()]]))
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur"
                    data-price-unit data-units='@json($unitMeta)'>
                    <h2 class="font-semibold text-stone-900">Cena i dostępność</h2>

                    <div class="mt-6 grid grid-cols-12 gap-5">
                        <div class="col-span-12 sm:col-span-4">
                            <label for="price_gross" class="block text-sm font-medium text-stone-700">Cena brutto <span class="font-normal text-stone-400">za 1&nbsp;<span data-unit-name>{{ $unitMeta[$selectedUnit]['abbr'] ?? 'szt.' }}</span></span></label>
                            <div class="relative mt-1.5">
                                <input id="price_gross" name="price_gross" type="text" inputmode="decimal" required placeholder="0,00"
                                    value="{{ old('price_gross', $product->price_gross) }}"
                                    data-msg-required="Podaj cenę."
                                    class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 pr-10 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400">zł</span>
                            </div>
                            @error('price_gross')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <label for="sale_unit" class="block text-sm font-medium text-stone-700">Jednostka sprzedaży</label>
                            <select id="sale_unit" name="sale_unit" required data-sale-unit
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @foreach (\App\Enums\SaleUnit::cases() as $unit)
                                    <option value="{{ $unit->value }}" @selected($selectedUnit === $unit->value)>{{ $unit->label() }}</option>
                                @endforeach
                            </select>
                            @error('sale_unit')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <label for="vat_rate" class="block text-sm font-medium text-stone-700">Stawka VAT</label>
                            @php($selectedVat = old('vat_rate', $product->vat_rate?->value ?? $defaultVat))
                            <select id="vat_rate" name="vat_rate" required
                                class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @foreach (\App\Enums\VatRate::cases() as $rate)
                                    <option value="{{ $rate->value }}" @selected($selectedVat === $rate->value)>{{ $rate->label() }}</option>
                                @endforeach
                            </select>
                            @error('vat_rate')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-span-12 sm:col-span-6" data-stock-field>
                            <label for="stock" class="block text-sm font-medium text-stone-700">Stan magazynowy</label>
                            <div class="relative mt-1.5">
                                <input id="stock" name="stock" type="text" inputmode="decimal"
                                    value="{{ old('stock', $product->stock !== null ? rtrim(rtrim((string) $product->stock, '0'), '.') : '') }}"
                                    class="block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 pr-12 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-400" data-unit-name>{{ $unitMeta[$selectedUnit]['abbr'] ?? 'szt.' }}</span>
                            </div>
                            @error('stock')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="col-span-12">
                            <label class="inline-flex items-center gap-3 text-sm text-stone-600">
                                <input type="checkbox" name="track_stock" value="1" data-stock-toggle class="shrink-0"
                                    @checked(old('track_stock', $product->exists ? $product->track_stock : true))>
                                <span>Kontroluj stan magazynowy</span>
                            </label>
                            <p class="mt-1 text-xs text-stone-400">Wyłącz dla usług, produktów na zamówienie lub cyfrowych — wtedy pole stanu jest nieaktywne.</p>
                        </div>

                        {{-- Wyłączenie prawa odstąpienia (art. 38 ustawy o prawach
                             konsumenta). Domyślnie NIEZAZNACZONE — zwrot przysługuje,
                             a wyjątek sprzedawca zaznacza świadomie. --}}
                        <div class="col-span-12">
                            <label class="inline-flex items-start gap-3 text-sm text-stone-600">
                                <input type="checkbox" name="withdrawal_excluded" value="1" class="mt-0.5 shrink-0"
                                    @checked(old('withdrawal_excluded', $product->withdrawal_excluded))>
                                <span>Ten produkt nie podlega zwrotowi w ciągu 14 dni</span>
                            </label>
                            <p class="mt-1 text-xs text-stone-400">
                                Zaznacz tylko wtedy, gdy prawo na to pozwala (art. 38 ustawy o prawach konsumenta): towary szybko psujące się —
                                kwiaty i żywność, wykonane na indywidualne zamówienie oraz zapieczętowane ze względów higienicznych.
                                W pozostałych przypadkach klient ma prawo odstąpić od umowy bez podania przyczyny, a my informujemy go o tym w mailu.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Widoczność --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Widoczność</h2>
                    <div class="mt-5 space-y-3">
                        <label class="flex items-start gap-3 text-sm text-stone-600">
                            <input type="checkbox" name="is_active" value="1" class="mt-0.5 shrink-0"
                                @checked(old('is_active', $product->exists ? $product->is_active : true))>
                            <span>
                                <span class="font-medium text-stone-800">Aktywny</span> — widoczny w sklepie i dostępny do kupienia. Odznacz, aby ukryć.
                            </span>
                        </label>
                        <label class="flex items-start gap-3 text-sm text-stone-600">
                            <input type="checkbox" name="show_on_homepage" value="1" class="mt-0.5 shrink-0"
                                @checked(old('show_on_homepage', $product->show_on_homepage))>
                            <span>
                                <span class="font-medium text-stone-800">Wyróżnij na stronie głównej</span> — pokaż produkt na stronie głównej sklepu.
                                @isset($homepage)
                                    @php($slotsFull = $homepage['count'] >= $homepage['limit'] && ! old('show_on_homepage', $product->show_on_homepage))
                                    <span class="mt-1 block text-xs {{ $slotsFull ? 'text-rose-600' : 'text-stone-400' }}">
                                        Zajęte {{ $homepage['count'] }} z {{ $homepage['limit'] }} miejsc na stronie głównej.@if ($slotsFull) Odznacz inny produkt, aby zwolnić miejsce.@endif
                                    </span>
                                @endisset
                            </span>
                        </label>
                        @error('show_on_homepage')
                            <p class="text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- SEO: opis do wyników wyszukiwania. Podpowiedź w polu pokazuje,
                     co wystawimy automatycznie, gdy sprzedawca nic nie napisze. --}}
                <x-seller.seo-box
                    :value="$product->meta_description"
                    :preview="$product->exists ? \App\Support\Seo::productDescription($product, $product->shop) : null" />

                <div class="flex items-center justify-between gap-3">
                    <a href="{{ route('seller.products.index', $listQuery) }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">← Wróć do listy</a>
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                        {{ $product->exists ? 'Zapisz zmiany' : 'Dodaj produkt' }}
                    </button>
                </div>
            </form>
        </div>

        {{-- Kolumna pomocnicza --}}
        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Wskazówki</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">💰</span>
                        <span>Podaj cenę <span class="font-medium text-stone-700">brutto</span> — netto i VAT wyliczymy automatycznie.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">📦</span>
                        <span>Po wyczerpaniu stanu produkt staje się niedostępny do zakupu.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🖼️</span>
                        <span>Pierwsze zdjęcie jest główne — dodasz do 8 i ustawisz kolejność przeciąganiem.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🏷️</span>
                        <span>Tagi pomagają klientom odnaleźć produkt — podpowiadamy je z Twoich wcześniejszych.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">👁️</span>
                        <span>Odznacz <span class="font-medium text-stone-700">Aktywny</span>, aby ukryć produkt; <span class="font-medium text-stone-700">Wyróżnij</span>, aby pokazać go na stronie głównej.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>

    {{-- Pole stanu nieaktywne, gdy kontrola stanu wyłączona (zero zależności). --}}
    <script>
        (function () {
            const toggle = document.querySelector('[data-stock-toggle]');
            const field = document.querySelector('[data-stock-field]');
            const input = document.getElementById('stock');
            if (!toggle || !field || !input) return;

            const sync = () => {
                const on = toggle.checked;
                input.disabled = !on;
                field.classList.toggle('opacity-50', !on);
            };
            toggle.addEventListener('change', sync);
            sync();
        })();

        {{-- Jednostka sprzedaży: przyrostki „szt."/„kg" przy cenie i stanie idą za wyborem. --}}
        (function () {
            const container = document.querySelector('[data-price-unit]');
            const select = document.querySelector('[data-sale-unit]');
            if (!container || !select) return;

            const units = JSON.parse(container.dataset.units || '{}');
            const labels = container.querySelectorAll('[data-unit-name]');

            const sync = () => {
                const meta = units[select.value];
                if (!meta) return;
                labels.forEach((el) => { el.textContent = meta.abbr; });
            };
            select.addEventListener('change', sync);
            sync();
        })();
    </script>
</x-layouts.panel>
