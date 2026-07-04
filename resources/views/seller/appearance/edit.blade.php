<x-layouts.panel title="Wygląd">
    <x-slot:heading>Wygląd sklepu</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: formularz --}}
        <div class="lg:col-span-8">
            <form method="POST" action="{{ route('seller.appearance.update') }}" class="space-y-6" enctype="multipart/form-data" novalidate data-validate>
                @csrf

                {{-- Logo (grafika i elementy wizualne; docelowo też kolory/szablony) --}}
                <div id="logo" class="scroll-mt-24 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Logo sklepu</h2>
                    <p class="mt-1 text-sm text-stone-500">Wizytówka Twojej marki — pojawi się w sklepie i przy jego prezentacji.</p>

                    <div class="mt-6">
                        <label for="logo" class="block text-sm font-medium text-stone-700">Plik logo <span class="text-stone-400">(opcjonalnie)</span></label>
                        <div class="mt-1.5 flex items-center gap-4">
                            <div class="flex h-20 shrink-0 items-center">
                                <img id="logo-preview"
                                    src="{{ $shop->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($shop->logo_path) : '' }}"
                                    alt="Logo sklepu"
                                    class="h-20 w-auto max-w-[14rem] object-contain {{ $shop->logo_path ? '' : 'hidden' }}">
                                <span id="logo-placeholder" class="flex h-20 w-20 items-center justify-center rounded-2xl border border-dashed border-stone-300 text-2xl text-stone-400 {{ $shop->logo_path ? 'hidden' : '' }}">🛍️</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"
                                    class="block w-full text-sm text-stone-500 file:mr-4 file:rounded-xl file:border-0 file:bg-amber-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-amber-800 file:transition hover:file:bg-amber-200">
                                <p class="mt-1.5 text-xs text-stone-400">PNG, JPG lub WebP, do 2 MB. Najlepiej kwadratowe.</p>
                                @if ($shop->logo_path)
                                    <label class="mt-2 inline-flex items-center gap-2 text-sm text-stone-600">
                                        <input type="checkbox" name="remove_logo" value="1" class="shrink-0">
                                        <span>Usuń obecne logo</span>
                                    </label>
                                @endif
                            </div>
                        </div>
                        @error('logo')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Kolory i szablon --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Kolory i szablon</h2>
                    <p class="mt-1 text-sm text-stone-500">Wybierz wygląd swojego sklepu, a potem dobierz kolory. Zmiany zobaczysz w podglądzie od razu.</p>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach (collect(config('themes.templates'))->sortBy('order') as $slug => $template)
                            @php
                                $isActive = $slug === $shop->templateSlug();
                                $activePalette = $isActive ? $shop->themePalette() : $template['default_palette'];
                                $previewTokens = $template['palettes'][$activePalette]['tokens'];
                            @endphp
                            <div data-template-card="{{ $slug }}"
                                class="tpl-card flex flex-col overflow-hidden rounded-2xl border bg-white transition {{ $isActive ? 'border-amber-400 ring-2 ring-amber-400/60' : 'border-stone-200' }}">
                                <input type="radio" name="template" id="template-{{ $slug }}" value="{{ $slug }}" class="sr-only" data-template-input @checked($isActive)>

                                {{-- Klik w podgląd/nazwę = wybór szablonu --}}
                                <label for="template-{{ $slug }}" class="cursor-pointer">
                                    {{-- Mini-witryna produktu (żywy podgląd palety) --}}
                                    <div data-preview="{{ $slug }}" class="p-4"
                                        style="background: {{ $previewTokens['surface'] }}; color: {{ $previewTokens['ink'] }};">
                                        @if (! empty($previewImageUrl))
                                            {{-- Realne zdjęcie produktu sklepu — „to Twój sklep". --}}
                                            <img src="{{ $previewImageUrl }}" alt="Podgląd produktu"
                                                class="aspect-[4/3] w-full rounded-lg object-cover">
                                        @else
                                            {{-- Brak zdjęć — neutralny placeholder w kolorze palety (żywy). --}}
                                            <div data-preview-img class="flex aspect-[4/3] w-full items-center justify-center rounded-lg"
                                                style="background: {{ $previewTokens['brand'] }}; color: {{ $previewTokens['brand_ink'] }};">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-9 w-9 opacity-70" aria-hidden="true">
                                                    <rect x="3" y="4" width="18" height="16" rx="2" />
                                                    <circle cx="8.5" cy="9.5" r="1.5" fill="currentColor" stroke="none" />
                                                    <path d="M4 17l4.5-4.5 3 3L15 12l5 5" />
                                                </svg>
                                            </div>
                                        @endif
                                        <p class="mt-2.5 truncate text-xs opacity-70">Nazwa produktu</p>
                                        <p class="mt-0.5 text-sm font-semibold">49,00 zł</p>
                                        <span data-preview-btn class="mt-2 block rounded-lg py-1 text-center text-[11px] font-semibold"
                                            style="background: {{ $previewTokens['brand'] }}; color: {{ $previewTokens['brand_ink'] }};">Zobacz produkt</span>
                                    </div>

                                    <div class="border-t border-stone-100 px-4 pt-4">
                                        <div class="flex items-center justify-between gap-2">
                                            <h3 class="text-sm font-semibold text-stone-900">{{ $template['name'] }}</h3>
                                            <span data-check class="text-amber-500 transition {{ $isActive ? '' : 'opacity-0' }}">✓</span>
                                        </div>
                                        <p class="mt-1 text-xs leading-relaxed text-stone-500">{{ $template['description'] }}</p>
                                    </div>
                                </label>

                                {{-- Palety w ramach szablonu --}}
                                <div class="flex flex-wrap items-center gap-2 px-4 pb-4 pt-3">
                                    @foreach ($template['palettes'] as $key => $palette)
                                        <label class="cursor-pointer" title="{{ $palette['name'] }}">
                                            <input type="radio" name="palettes[{{ $slug }}]" value="{{ $key }}" class="peer sr-only"
                                                data-palette-input
                                                data-brand="{{ $palette['tokens']['brand'] }}"
                                                data-brand-ink="{{ $palette['tokens']['brand_ink'] }}"
                                                data-surface="{{ $palette['tokens']['surface'] }}"
                                                data-ink="{{ $palette['tokens']['ink'] }}"
                                                @checked($key === $activePalette)>
                                            <span class="block h-6 w-6 rounded-full border border-black/10 transition peer-checked:ring-2 peer-checked:ring-stone-800 peer-checked:ring-offset-1"
                                                style="background: {{ $palette['tokens']['brand'] }};"></span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                        Zapisz wygląd
                    </button>
                </div>
            </form>
        </div>

        {{-- Kolumna pomocnicza: wskazówki --}}
        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Wskazówki</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🖼️</span>
                        <span>Najlepsze logo to kwadrat (np. 512×512 px) na jednolitym tle.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">✨</span>
                        <span>Logo wzmacnia wizerunek marki — buduje rozpoznawalność i zaufanie klientów.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🎨</span>
                        <span>Najpierw wybierz szablon, potem dobierz paletę kolorów — podgląd zmienia się od razu.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🏬</span>
                        <span>Wygląd dotyczy Twojego sklepu widocznego dla klientów — panel sprzedawcy zostaje bez zmian.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>

    {{-- Podgląd logo na żywo (zero zależności). --}}
    <script>
        (function () {
            const input = document.getElementById('logo');
            const preview = document.getElementById('logo-preview');
            const placeholder = document.getElementById('logo-placeholder');
            if (!input || !preview) return;

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                if (!file) return;
                preview.src = URL.createObjectURL(file);
                preview.classList.remove('hidden');
                if (placeholder) placeholder.classList.add('hidden');
            });
        })();
    </script>

    {{-- Wybór szablonu i palety: ring zaznaczenia + żywy podgląd kolorów (zero zależności). --}}
    <script>
        (function () {
            const cards = document.querySelectorAll('[data-template-card]');
            if (!cards.length) return;

            function selectTemplate(slug) {
                cards.forEach(function (card) {
                    const active = card.getAttribute('data-template-card') === slug;
                    card.classList.toggle('border-amber-400', active);
                    card.classList.toggle('ring-2', active);
                    card.classList.toggle('ring-amber-400/60', active);
                    card.classList.toggle('border-stone-200', !active);
                    const check = card.querySelector('[data-check]');
                    if (check) check.classList.toggle('opacity-0', !active);
                });
            }

            document.querySelectorAll('[data-template-input]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    if (radio.checked) selectTemplate(radio.value);
                });
            });

            document.querySelectorAll('[data-palette-input]').forEach(function (inp) {
                inp.addEventListener('change', function () {
                    const card = inp.closest('[data-template-card]');
                    if (!card) return;
                    const slug = card.getAttribute('data-template-card');
                    const preview = card.querySelector('[data-preview]');
                    const img = card.querySelector('[data-preview-img]');
                    const btn = card.querySelector('[data-preview-btn]');
                    if (preview) {
                        preview.style.background = inp.dataset.surface;
                        preview.style.color = inp.dataset.ink;
                    }
                    if (img) {
                        img.style.background = inp.dataset.brand;
                        img.style.color = inp.dataset.brandInk;
                    }
                    if (btn) {
                        btn.style.background = inp.dataset.brand;
                        btn.style.color = inp.dataset.brandInk;
                    }
                    // Wybór palety oznacza też wybór jej szablonu.
                    const tpl = document.getElementById('template-' + slug);
                    if (tpl && !tpl.checked) {
                        tpl.checked = true;
                        selectTemplate(slug);
                    }
                });
            });
        })();
    </script>
</x-layouts.panel>
