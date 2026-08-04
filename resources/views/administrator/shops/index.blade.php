<x-layouts.panel title="Sklepy">
    <x-slot:actions>
        <span class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur">
            {{ $shops->total() }} {{ trans_choice('sklep|sklepy|sklepów', $shops->total()) }}
        </span>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
    @php($hasFilters = $filters['q'] !== '' || $filters['package'] !== '' || $filters['status'] !== '')
    @if ($shops->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-stone-300 bg-white/40 px-6 py-16 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-stone-100 text-2xl">🛍️</span>
            @if ($hasFilters)
                <p class="mt-4 font-medium text-stone-700">Brak sklepów dla tych filtrów</p>
                <p class="mt-1 max-w-sm text-sm text-stone-500">Zmień kryteria albo <a href="{{ route('administrator.shops.index') }}" class="font-medium text-stone-700 underline decoration-amber-300 underline-offset-2">wyczyść filtry</a>.</p>
            @else
                <p class="mt-4 font-medium text-stone-700">Nie ma jeszcze żadnych sklepów</p>
                <p class="mt-1 max-w-sm text-sm text-stone-500">Gdy sprzedawcy założą swoje sklepy, pojawią się tutaj wraz z pakietem i stanem abonamentu.</p>
            @endif
        </div>
    @else
        <div class="overflow-hidden rounded-3xl border border-white/60 bg-white/70 backdrop-blur">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" style="min-width: 58rem">
                    <thead class="border-b border-stone-200/70 text-xs uppercase tracking-wide text-stone-400">
                        <tr>
                            <th class="px-5 py-3 font-medium">Sklep</th>
                            <th class="px-5 py-3 font-medium">Właściciel</th>
                            <th class="px-5 py-3 font-medium">Pakiet</th>
                            <th class="px-5 py-3 text-right font-medium">Cena / rok</th>
                            <th class="px-5 py-3 text-right font-medium">Produkty</th>
                            <th class="px-5 py-3 font-medium">Abonament</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3"><span class="sr-only">Akcje</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($shops as $shop)
                            <tr class="transition hover:bg-white/60">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-stone-900">{{ $shop->name }}</p>
                                    <p class="text-xs text-stone-400">{{ $shop->slug }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="text-stone-700">{{ trim(($shop->owner?->name ?? '').' '.($shop->owner?->surname ?? '')) ?: '—' }}</p>
                                    <p class="text-xs text-stone-400">{{ $shop->owner?->email }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700">
                                        {{ $shop->packageName() }}
                                    </span>
                                    @if ($shop->comped)
                                        <span class="ml-1 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700" title="Dostęp gratisowy — nie wygasa">gratis</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums text-stone-700">
                                    @if ($shop->priceYearly() > 0)
                                        {{ number_format($shop->priceYearly(), 0, ',', ' ') }} zł
                                    @else
                                        <span class="text-stone-400">za darmo</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums text-stone-600">
                                    {{ $shop->products_count }} / {{ (int) $shop->entitlement('max_products') }}
                                </td>
                                <td class="px-5 py-3 text-stone-600">
                                    @if ($shop->comped)
                                        <span class="text-emerald-600">bezterminowo</span>
                                    @elseif ($shop->subscription_ends_at)
                                        do {{ $shop->subscription_ends_at->format('d.m.Y') }}
                                    @else
                                        <span class="text-stone-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($shop->deletion_scheduled_at)
                                        {{-- Sklep w karencji jest już niewidoczny dla klientów — status
                                             „Aktywny" mówiłby tu nieprawdę, więc plakietka go zastępuje. --}}
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700"
                                            title="Sprzedawca zlecił usunięcie — sklep jest już niewidoczny">
                                            usunięcie {{ $shop->deletion_scheduled_at->format('d.m') }}
                                        </span>
                                    @else
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
                                            'bg-emerald-100 text-emerald-700' => $shop->status === \App\Enums\ShopStatus::Active,
                                            'bg-stone-100 text-stone-500' => $shop->status !== \App\Enums\ShopStatus::Active,
                                        ])>{{ $shop->status->label() }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        {{-- Podgląd storefrontu w nowej karcie — admin nie traci listy,
                                             do której zwykle wraca po obejrzeniu kilku sklepów. --}}
                                        <a href="https://{{ $shop->host() }}" target="_blank" rel="noopener"
                                            title="Otwórz {{ $shop->host() }} w nowej karcie"
                                            class="inline-flex items-center gap-1 rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 shadow-sm transition hover:bg-stone-100">
                                            Zobacz
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3 text-stone-400" aria-hidden="true">
                                                <path d="M14 4h6v6M20 4l-8 8M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5" />
                                            </svg>
                                        </a>
                                        <a href="{{ route('administrator.shops.edit', $shop) }}"
                                            class="inline-flex items-center rounded-xl border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 shadow-sm transition hover:bg-stone-100">
                                            Zarządzaj
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($shops->hasPages())
            <div class="mt-6">{{ $shops->links() }}</div>
        @endif
    @endif
        </div>

        <aside class="lg:col-span-4 space-y-6">
            <form method="GET" action="{{ route('administrator.shops.index') }}" class="space-y-4 rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Filtry</h2>

                <div>
                    <label for="q" class="block text-sm font-medium text-stone-700">Szukaj</label>
                    <input type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Nazwa, właściciel, e-mail"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                </div>

                <div>
                    <label for="package" class="block text-sm font-medium text-stone-700">Pakiet</label>
                    <select id="package" name="package"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <option value="">Wszystkie</option>
                        @foreach ($packages as $slug => $pkg)
                            <option value="{{ $slug }}" @selected($filters['package'] === $slug)>{{ $pkg['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-stone-700">Status</label>
                    <select id="status" name="status"
                        class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-2.5 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                        <option value="">Wszystkie</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105">Filtruj</button>
                    @if ($filters['q'] !== '' || $filters['package'] !== '' || $filters['status'] !== '')
                        <a href="{{ route('administrator.shops.index') }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">Wyczyść</a>
                    @endif
                </div>
            </form>

            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Zarządzanie sklepami</h2>
                <p class="mt-2 text-sm text-stone-500">
                    Lista wszystkich sklepów platformy. Wejdź w <span class="font-medium text-stone-700">„Zarządzaj"</span>, aby ręcznie ustawić pakiet, cenę roczną, poszczególne moduły oraz datę końca abonamentu dla konkretnego sprzedawcy.
                </p>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">✨</span>
                        <span>Pakiet to tylko <span class="text-stone-700">preset</span> — realne uprawnienia trzyma każdy sklep u siebie i możesz je nadpisać.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-emerald-600">🎁</span>
                        <span>Plakietka <span class="text-stone-700">gratis</span> = dostęp <span class="text-stone-700">comped</span> (nie wygasa).</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
