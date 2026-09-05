<div>
    @if ($limited && ! $compact)
        <p class="mb-2 text-sm opacity-70">
            Dostępne: <span class="font-semibold">{{ $unit->formatAmount($stock) }}</span> {{ $unit->abbreviation() }}
            @if ($inCart > 0)
                <span class="opacity-60">(w koszyku: {{ $unit->formatQuantity($inCart) }})</span>
            @endif
        </p>
    @endif

    {{-- FORMULARZ PERSONALIZACJI.

         W WYKAZIE go nie ma (`with-options=false`) — w siatce produktów nie ma
         miejsca na trzy grupy pól, a wciśnięte tam zamieniłyby wykaz w formularz.
         Produkt personalizowany dostaje tam przycisk prowadzący na KARTĘ (niżej).

         Osobna flaga, NIE `compact`: tamta steruje wyglądem przycisku i jest
         włączona także na karcie produktu. --}}
    @if ($withOptions && $groups->isNotEmpty())
        <div class="mb-5 space-y-5">
            @foreach ($groups as $group)
                <fieldset>
                    <legend class="text-sm font-semibold">
                        {{ $group->name }}
                        @unless ($group->required)
                            <span class="font-normal opacity-60">— nieobowiązkowe</span>
                        @endunless
                    </legend>
                    @if ($group->hint)
                        <p class="mt-0.5 text-xs opacity-70">{{ $group->hint }}</p>
                    @endif

                    @if ($group->isChoice())
                        <select wire:model.live="config.{{ $group->id }}.choice"
                            class="st-border mt-2 block w-full rounded-xl border bg-transparent px-4 py-2.5 text-sm focus:outline-none">
                            <option value="">— nie, dziękuję —</option>
                            @foreach ($group->choices->where('is_active', true) as $choice)
                                @php($dodatek = (float) $choice->surcharge_gross + (float) $choice->licence_fee_gross)
                                <option value="{{ $choice->id }}">{{ $choice->label }}@if ($dodatek > 0) (+{{ \App\Support\Money::pln($dodatek) }})@endif</option>
                            @endforeach
                        </select>
                        @error('config.'.$group->id.'.choice')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    @else
                        <div class="mt-2 space-y-3">
                            @foreach ($group->fields as $field)
                                <div>
                                    <label for="f-{{ $field->id }}" class="sr-only">{{ $field->label }}</label>
                                    <input id="f-{{ $field->id }}" type="text"
                                        wire:model.live.debounce.400ms="config.{{ $group->id }}.fields.{{ $field->id }}"
                                        maxlength="{{ $field->max_length }}"
                                        placeholder="{{ $field->placeholder ?: $field->label }}"
                                        class="st-border block w-full rounded-xl border bg-transparent px-4 py-2.5 text-sm focus:outline-none">
                                    {{-- Limit podany WPROST, a nie dopiero w komunikacie błędu:
                                         wynika z fizyki produktu (na magnes wchodzi tyle liter,
                                         ile wchodzi), więc kupujący ma go znać przed wpisaniem. --}}
                                    <p class="mt-0.5 text-xs opacity-60">{{ $field->label }} — do {{ $field->max_length }} znaków</p>
                                    @error('config.'.$group->id.'.fields.'.$field->id)
                                        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                        @error('config.'.$group->id.'.fields')
                            <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                        @enderror
                    @endif
                </fieldset>
            @endforeach

            {{-- CENA Z CZTERECH CZĘŚCI — wprost z zamówienia klienta. Rośnie
                 w chwili wyboru, a nie dopiero w koszyku, więc kupujący wie,
                 za co płaci, ZANIM kliknie. Pokazujemy dopiero, gdy jest co
                 rozbijać: sam produkt to nie rozbicie, tylko cena. --}}
            @if (count($breakdown) > 1)
                <dl class="st-card st-border rounded-xl border p-4 text-sm">
                    @foreach ($breakdown as $skladnik)
                        <div class="flex justify-between gap-3 @unless ($loop->first) mt-1 @endunless">
                            <dt class="opacity-70">
                                {{ $skladnik['label'] }}
                                @if ($skladnik['kind'] === $licenceKind)
                                    <span class="opacity-70">(opłata licencyjna)</span>
                                @endif
                            </dt>
                            <dd class="shrink-0 tabular-nums">{{ \App\Support\Money::pln($skladnik['amount']) }}</dd>
                        </div>
                    @endforeach
                    <div class="st-border mt-3 flex justify-between gap-3 border-t pt-3 font-semibold">
                        <dt>Razem</dt>
                        <dd class="shrink-0 tabular-nums">{{ \App\Support\Money::pln($total) }}</dd>
                    </div>
                </dl>
            @endif

            @error('config')
                <p class="text-sm text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    @endif

    @php($pad = $compact ? 'px-5 py-2.5' : 'px-8 py-3')

    @if ($needsCard)
        {{-- Produkt personalizowany na kaflu: wybór jest na karcie, bo tutaj nie
             ma na niego miejsca. Bez tego „Do koszyka" nie robiłoby NIC —
             konfiguracja bez wymaganych pól i tak zostaje odrzucona — i wyglądało
             jak zepsuty przycisk. --}}
        <a href="{{ $productPath }}" wire:navigate
            class="st-btn inline-flex items-center justify-center gap-2 rounded-full {{ $pad }} text-sm font-semibold shadow-sm transition hover:brightness-105">
            Wybierz opcje
        </a>
    @elseif ($canAdd)
        <button type="button" wire:click="add"
            x-data="{ done: false }"
            x-on:click="done = true; setTimeout(() => done = false, 1600)"
            class="st-btn inline-flex items-center justify-center gap-2 rounded-full {{ $pad }} text-sm font-semibold shadow-sm transition hover:brightness-105">
            <span wire:loading.remove wire:target="add">
                <span x-show="!done">Do koszyka</span>
                <span x-show="done" x-cloak>Dodano&nbsp;✓</span>
            </span>
            <span wire:loading wire:target="add" x-cloak>Dodaję…</span>
        </button>
    @elseif ($active && $limited && $inCart > 0)
        {{-- Wszystkie dostępne sztuki są już w koszyku. --}}
        <span class="inline-flex items-center justify-center gap-2 rounded-full border st-border {{ $compact ? 'px-4 py-2.5' : 'px-6 py-3' }} text-sm font-medium opacity-80">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 shrink-0" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
            {{ $compact ? 'Masz maksimum' : 'Masz w koszyku wszystko, co dostępne' }}
        </span>
    @else
        <span class="inline-flex items-center justify-center rounded-full border st-border {{ $compact ? 'px-4 py-2.5' : 'px-6 py-3' }} text-sm font-medium opacity-60">
            Chwilowo niedostępny
        </span>
    @endif
</div>
