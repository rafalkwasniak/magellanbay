<x-layouts.panel title="Wygląd">
    <x-slot:heading>Wygląd sklepu</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        {{-- Główna kolumna: formularz --}}
        <div class="lg:col-span-8">
            <form method="POST" action="{{ route('seller.appearance.update') }}" class="space-y-6" enctype="multipart/form-data" novalidate data-validate>
                @csrf

                {{-- Logo (grafika i elementy wizualne; docelowo też kolory/szablony) --}}
                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
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

                {{-- Kolory i szablon — w przygotowaniu --}}
                <div class="rounded-3xl border border-dashed border-stone-200 bg-white/40 p-6">
                    <h2 class="font-semibold text-stone-500">Kolory i szablon</h2>
                    <p class="mt-1 text-sm text-stone-400">Wkrótce dobierzesz tu kolory i szablon swojego sklepu, aby dopasować go do marki.</p>
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
</x-layouts.panel>
