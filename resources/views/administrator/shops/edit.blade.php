<x-layouts.panel :title="'Sklep: '.$shop->name">
    <x-slot:actions>
        <a href="{{ route('administrator.shops.index') }}"
            class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur transition hover:bg-white">
            ← Wróć do listy
        </a>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <livewire:administrator.shop-manager :shop="$shop" />
        </div>

        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Jak to działa</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">✨</span>
                        <span><span class="text-stone-700">„Nadaj pakiet"</span> tylko wypełnia pola presetem. Zmiany zapisuje dopiero <span class="text-stone-700">„Zapisz"</span> — możesz wcześniej dopieścić pojedyncze opcje.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🧩</span>
                        <span>Każdy moduł włączasz <span class="text-stone-700">niezależnie od pakietu</span> — np. korespondencja seryjna dla dobrego klienta na Straganie.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔒</span>
                        <span>Zapis pisze <span class="text-stone-700">snapshot</span> tego sklepu. Przy odnowieniu uprawnienia zostają, a cena idzie za aktualnym cennikiem.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-emerald-600">🎁</span>
                        <span><span class="text-stone-700">Comped</span> = dostęp gratisowy, nie wygasa i omija auto-zejście.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
