<x-layouts.panel :title="$code === null ? 'Nowy kod rabatowy' : 'Kod rabatowy: '.$code->code">
    <x-slot:heading>{{ $code === null ? 'Nowy kod rabatowy' : 'Kod rabatowy: '.$code->code }}</x-slot:heading>

    <x-slot:actions>
        <a href="{{ route('seller.discounts.index', $listQuery) }}"
            class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur transition hover:bg-white">
            ← Wróć do listy
        </a>
    </x-slot:actions>

    <livewire:seller.discount-code-form :shop="$shop" :code="$code" :prefill="$prefill" :list-query="$listQuery" />
</x-layouts.panel>
