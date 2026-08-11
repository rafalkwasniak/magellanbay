<x-layouts.panel title="Pulpit administratora">
    {{-- Kafelki pieniędzy klikają w dział „Pakiety" — tam jest rozbicie, którego
         cztery liczby nie pomieszczą. Kafelek „Sklepy" prowadzi do listy sklepów. --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Sprzedane abonamenty', (string) $revenue['count'], 'Opłacone opłaty za pakiety', '🧾', route('administrator.packages.payments')],
            ['Przychód — sumarycznie', \App\Support\Money::pln($revenue['total']), 'Od początku platformy', '💰', route('administrator.packages.index')],
            ['Przychód — 12 mies.', \App\Support\Money::pln($revenue['last12m']), 'Ostatnie 12 miesięcy, licząc od dziś', '📈', route('administrator.packages.index')],
            ['Sklepy', (string) $shopsCount, $activeShopsCount.' '.trans_choice('aktywny|aktywne|aktywnych', $activeShopsCount), '🛍️', route('administrator.shops.index')],
        ] as [$label, $value, $hint, $icon, $url])
            <a href="{{ $url }}" class="block rounded-3xl border border-white/60 bg-white/70 p-5 backdrop-blur transition hover:bg-white">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-stone-500">{{ $label }}</p>
                    <span class="text-lg">{{ $icon }}</span>
                </div>
                <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums text-stone-900">{{ $value }}</p>
                <p class="mt-1 text-xs text-stone-400">{{ $hint }}</p>
            </a>
        @endforeach
    </div>

    @if ($attentionCount > 0)
        {{-- Pulpit jest pierwszym ekranem po zalogowaniu. Kończący się abonament
             musi się rzucić w oczy TU, a nie dopiero po wejściu w Pakiety —
             przy sprzedaży z ręki nikt inny o terminie nie przypomni. --}}
        <a href="{{ route('administrator.packages.index') }}"
            class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-amber-200 bg-amber-50/70 px-5 py-4 backdrop-blur transition hover:bg-amber-50">
            <span>
                <span class="block text-sm font-medium text-amber-900">
                    {{ $attentionCount }} {{ trans_choice('sprawa|sprawy|spraw', $attentionCount) }} wokół pakietów wymaga uwagi
                </span>
                <span class="mt-0.5 block text-sm text-amber-800">Kończące się abonamenty, zaległe wpłaty, opłaty bez faktury.</span>
            </span>
            <span class="shrink-0 text-sm font-medium text-amber-900">Zobacz →</span>
        </a>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur lg:col-span-2">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-stone-900">Najnowsze sklepy</h2>
                <a href="{{ route('administrator.shops.index') }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">Wszystkie →</a>
            </div>

            @if ($recentShops->isEmpty())
                <div class="mt-6 flex flex-col items-center justify-center rounded-2xl border border-dashed border-stone-300 bg-white/40 px-6 py-12 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🛍️</span>
                    <p class="mt-4 font-medium text-stone-700">Nie ma jeszcze żadnych sklepów</p>
                    <p class="mt-1 max-w-sm text-sm text-stone-500">Gdy sprzedawcy założą swoje sklepy, pojawią się tutaj wraz ze statusem i pakietem.</p>
                </div>
            @else
                <ul class="mt-4 divide-y divide-stone-100">
                    @foreach ($recentShops as $shop)
                        <li class="flex items-center gap-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-stone-900">{{ $shop->name }}</p>
                                <p class="truncate text-xs text-stone-400">{{ trim(($shop->owner?->name ?? '').' '.($shop->owner?->surname ?? '')) ?: $shop->owner?->email }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700">{{ $shop->packageName() }}</span>
                            <span @class([
                                'shrink-0 rounded-full px-2.5 py-1 text-xs font-medium',
                                'bg-emerald-100 text-emerald-700' => $shop->status === \App\Enums\ShopStatus::Active,
                                'bg-stone-100 text-stone-500' => $shop->status !== \App\Enums\ShopStatus::Active,
                            ])>{{ $shop->status->label() }}</span>
                            {{-- Podgląd storefrontu w nowej karcie — tak samo jak na pełnej
                                 liście sklepów, żeby ten sam wiersz działał wszędzie tak samo. --}}
                            <a href="https://{{ $shop->host() }}" target="_blank" rel="noopener"
                                title="Otwórz {{ $shop->host() }} w nowej karcie"
                                class="inline-flex shrink-0 items-center gap-1 rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 shadow-sm transition hover:bg-stone-100">
                                Zobacz
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3 text-stone-400" aria-hidden="true">
                                    <path d="M14 4h6v6M20 4l-8 8M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5" />
                                </svg>
                            </a>
                            <a href="{{ route('administrator.shops.edit', $shop) }}"
                                class="shrink-0 rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 shadow-sm transition hover:bg-stone-100">Zarządzaj</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="rounded-3xl bg-gradient-to-br from-amber-500 to-rose-500 p-6 text-white shadow-lg shadow-rose-500/20">
            <h2 class="text-lg font-semibold">Skróty</h2>
            <p class="mt-2 text-sm text-amber-50">Zarządzaj platformą i sprzedawcami.</p>
            <ul class="mt-6 space-y-3 text-sm">
                <li>
                    <a href="{{ route('administrator.shops.index') }}" class="flex items-center gap-3 rounded-2xl bg-white/15 px-4 py-2.5 backdrop-blur transition hover:brightness-105">
                        {{-- Było „Sklepy i pakiety" — od kiedy Pakiety są osobnym
                             działem, ta nazwa prowadziłaby nie tam, gdzie obiecuje. --}}
                        <span>🛍️</span><span class="flex-1">Sklepy</span><span>→</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('administrator.sellers.index') }}" class="flex items-center gap-3 rounded-2xl bg-white/15 px-4 py-2.5 backdrop-blur transition hover:brightness-105">
                        <span>👥</span><span class="flex-1">Sprzedawcy</span><span>→</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('administrator.packages.payments.create') }}" class="flex items-center gap-3 rounded-2xl bg-white/15 px-4 py-2.5 backdrop-blur transition hover:brightness-105">
                        <span>💰</span><span class="flex-1">Zarejestruj wpłatę</span><span>→</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</x-layouts.panel>
