<x-layouts.storefront :shop="$shop" title="Już wkrótce" :bare="true">
    {{-- Bez nagłówka i stopki (bare) — zostaje sam motyw (tło/kolory/fonty
         wybranego szablonu). Wyśrodkowany box zgodny ze sklepem, w środku nazwa,
         linia jak na stronach tekstowych i informacja o przygotowaniu. --}}
    <div class="flex min-h-screen items-center justify-center px-6 py-12">
        <div class="st-card st-border w-full max-w-lg rounded-3xl border px-8 py-12 text-center shadow-sm sm:p-14">
            @if ($logoPath = $shop->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath) }}"
                    alt="{{ $shop->name }}" class="mx-auto mb-8 h-16 w-auto object-contain">
            @endif

            {{-- Nazwa sklepu: font i rozmiar jak nagłówek „O sklepie". --}}
            <h1 class="st-brand font-serif text-4xl leading-tight tracking-tight sm:text-5xl">{{ $shop->name }}</h1>

            {{-- Cienka linia jak na stronach tekstowych. --}}
            <span class="mx-auto mt-6 block h-px w-16" style="background: color-mix(in srgb, var(--ink) 18%, transparent);"></span>

            <div class="mt-8 space-y-4 text-base leading-relaxed opacity-80">
                <p>Nasz sklep jest właśnie w przygotowaniu.</p>
                <p>Kompletujemy asortyment, dopracowujemy szczegóły i szykujemy wszystko tak, aby zakupy były dla Ciebie wygodne i przyjemne.</p>
                <p>Już niebawem otworzymy się na dobre..</p>
            </div>

            <p class="st-brand mt-10 font-serif text-2xl tracking-tight">Zapraszamy wkrótce</p>
            <p class="mt-3">
                <a href="https://{{ \Illuminate\Support\Facades\Request::getHost() }}"
                    class="text-base opacity-80 underline-offset-4 transition hover:underline hover:opacity-100">{{ \Illuminate\Support\Facades\Request::getHost() }}</a>
            </p>
        </div>
    </div>
</x-layouts.storefront>
