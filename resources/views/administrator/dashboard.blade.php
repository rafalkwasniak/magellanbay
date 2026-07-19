<x-layouts.panel title="Pulpit administratora">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Sprzedane abonamenty', (string) $subscriptionsSold, 'Po uruchomieniu płatności za SaaS', '🧾'],
            ['Przychód — sumarycznie', number_format($saasRevenueTotal, 2, ',', ' ').' zł', 'Po uruchomieniu płatności za SaaS', '💰'],
            ['Przychód — 12 mies.', number_format($saasRevenue12m, 2, ',', ' ').' zł', 'Po uruchomieniu płatności za SaaS', '📈'],
            ['Sklepy', (string) $shopsCount, $activeShopsCount.' '.trans_choice('aktywny|aktywne|aktywnych', $activeShopsCount), '🛍️'],
        ] as [$label, $value, $hint, $icon])
            <div class="rounded-3xl border border-white/60 bg-white/70 p-5 backdrop-blur">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-stone-500">{{ $label }}</p>
                    <span class="text-lg">{{ $icon }}</span>
                </div>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-stone-900">{{ $value }}</p>
                <p class="mt-1 text-xs text-stone-400">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

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
                        <span>🛍️</span><span class="flex-1">Sklepy i pakiety</span><span>→</span>
                    </a>
                </li>
                <li class="flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-2.5 text-amber-50">
                    <span>👥</span><span class="flex-1">Sprzedawcy</span><span class="text-xs">wkrótce</span>
                </li>
            </ul>
        </div>
    </div>
</x-layouts.panel>
