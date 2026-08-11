<x-layouts.panel title="Nowa wpłata">
    <x-slot:actions>
        <a href="{{ route('administrator.packages.payments') }}"
            class="rounded-full bg-white/70 px-4 py-1.5 text-sm font-medium text-stone-600 backdrop-blur transition hover:bg-white">
            ← Rejestr opłat
        </a>
    </x-slot:actions>

    {{-- Cały układ (formularz + kolumna boczna) mieszka w komponencie Livewire,
         bo podsumowanie „co się zmieni" musi odświeżać się razem z polami. Tutaj
         zostaje samo zdanie wprowadzające. --}}
    <p class="max-w-2xl text-sm text-stone-500">
        Przelew na konto albo gotówka. Zapis robi dwie rzeczy naraz: wpisuje pieniądze do rejestru
        i <strong class="font-medium text-stone-700">ustawia sklepowi pakiet oraz termin</strong>.
    </p>

    <div class="mt-6">
        @livewire('administrator.package-payment-recorder')
    </div>
</x-layouts.panel>
