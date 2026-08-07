{{-- Publiczny formularz odstąpienia od umowy (14 dni). Wejście po tokenie —
     bez logowania, tak samo dla klienta z konta i dla gościa. `noindex`, bo
     strona dotyczy konkretnego zamówienia.

     Układ jak na potwierdzeniu zamówienia: pełna szerokość strony i siatka
     dwóch kolumn (co zwracasz | dane osoby odstępującej). Wąska kolumna z
     rejestracji tu nie pasuje — formularz niesie listę pozycji z ilościami,
     a ściśnięty wygląda jak zakładka do książki. --}}
<x-layouts.storefront :shop="$shop" title="Zwrot z zamówienia" :noindex="true">
    <main class="mx-auto max-w-6xl px-6 pt-10 pb-16">
        <x-storefront.breadcrumbs :items="[
            ['label' => $shop->name, 'url' => '/'],
            ['label' => 'Zwrot z zamówienia #'.$order->number],
        ]" />

        <h1 class="st-brand mt-4 font-serif text-4xl leading-tight tracking-tight sm:text-5xl">Zwrot z zamówienia #{{ $order->number }}</h1>

        <div class="st-border mt-8 border-t pt-8">
            <p class="max-w-3xl text-sm leading-relaxed opacity-70">
                Odstąpienie od umowy zawartej na odległość — bez podania przyczyny. Zaznacz, co chcesz oddać,
                uzupełnij dane i wyślij oświadczenie; sklep dostanie je od razu.
            </p>
        </div>

        @if (session('status'))
            <div class="st-card st-border mt-6 rounded-3xl border p-6 text-center">
                <p class="font-semibold text-emerald-600">✓ Oświadczenie przyjęte</p>
                <p class="mt-1 text-sm opacity-70">{{ session('status') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="mt-6 rounded-3xl border border-rose-200 bg-rose-50 px-6 py-4 text-sm text-rose-800">{{ session('error') }}</div>
        @endif

        {{-- Historia zgłoszeń nad siatką, na pełnej szerokości: klient widzi, co
             już oddał i za ile — żeby nie zgłaszał drugi raz tego samego. --}}
        @if ($order->returns->isNotEmpty())
            <div class="st-card st-border mt-6 rounded-3xl border p-6">
                <h2 class="font-semibold">Zgłoszone zwroty</h2>
                <ul class="mt-4 space-y-3">
                    @foreach ($order->returns as $return)
                        <li class="flex justify-between gap-3 text-sm">
                            <span class="min-w-0 opacity-80">
                                {{ $return->created_at->format('d.m.Y') }} —
                                {{ trans_choice('{1}:count pozycja|[2,4]:count pozycje|[5,*]:count pozycji', $return->items->count(), ['count' => $return->items->count()]) }}
                                @if ($return->isRefunded())
                                    <span class="text-emerald-600">· pieniądze zwrócone</span>
                                @else
                                    <span class="opacity-60">· czeka na rozliczenie</span>
                                @endif
                            </span>
                            <span class="shrink-0 tabular-nums">{{ \App\Support\Money::pln($return->refund_gross) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($order->acceptsReturns())
            @php($prefillName = old('customer_name') ?? trim($order->buyer_name.' '.$order->buyer_surname))
            @php($prefillStreet = trim($order->ship_street.' '.$order->ship_building_number.($order->ship_apartment_number ? '/'.$order->ship_apartment_number : '')))
            @php($prefillAddress = old('customer_address') ?? trim(collect([$prefillStreet, trim($order->ship_postal_code.' '.$order->ship_city)])->filter()->implode(', ')))

            {{-- Siatka siedzi WEWNĄTRZ formularza — obie kolumny to jedno
                 oświadczenie, wysyłane jednym przyciskiem pod spodem. --}}
            <form method="POST" action="/zwrot/{{ $order->paymentToken() }}">
                @csrf

                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    {{-- Lewa: pozycje zamówienia z ilościami do zwrotu --}}
                    <div class="st-card st-border rounded-3xl border p-6">
                        <h2 class="font-semibold">Co zwracasz?</h2>
                        <p class="mt-2 text-sm opacity-70">Wpisz ilość przy pozycjach, które chcesz zwrócić. Możesz oddać część zamówienia.</p>

                        <ul class="mt-5 space-y-4">
                            @foreach ($order->items as $item)
                                <li class="st-border flex flex-wrap items-end justify-between gap-3 border-t pt-4">
                                    <div class="min-w-0">
                                        <p class="break-words text-sm font-semibold">{{ $item->name }}</p>
                                        <p class="mt-1 text-xs opacity-60">
                                            Kupiono: {{ $item->sale_unit->formatQuantity((float) $item->quantity) }}
                                            @if ($item->hasReturns())
                                                · zwrócono: {{ $item->sale_unit->formatQuantity((float) $item->returned_quantity) }}
                                            @endif
                                        </p>
                                    </div>

                                    @if ($item->returnableQuantity() > 0)
                                        <div>
                                            <label for="qty-{{ $item->id }}" class="block text-xs opacity-60">Zwracam (maks. {{ $item->sale_unit->formatQuantity($item->returnableQuantity()) }})</label>
                                            <input type="number" id="qty-{{ $item->id }}" name="quantities[{{ $item->id }}]"
                                                value="{{ old('quantities.'.$item->id) }}"
                                                min="0" max="{{ $item->sale_unit->inputAmount($item->returnableQuantity()) }}" step="{{ $item->sale_unit->step() }}"
                                                class="st-border mt-1 block w-20 rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                        </div>
                                    @elseif (! $item->isWithdrawable())
                                        {{-- Wyjątek art. 38 (np. kwiaty, żywność, rzecz na zamówienie):
                                             prawa odstąpienia tu nie ma, więc pola nie pokazujemy. --}}
                                        <p class="text-xs opacity-50">Nie podlega zwrotowi (art. 38 ustawy o prawach konsumenta)</p>
                                    @else
                                        <p class="text-xs opacity-50">Zwrócono w całości</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @error('quantities')
                            <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Prawa: dane z ustawowego wzoru oświadczenia --}}
                    <div class="st-card st-border rounded-3xl border p-6">
                        <h2 class="font-semibold">Twoje dane</h2>
                        <p class="mt-2 text-sm opacity-70">Ustawowy wzór oświadczenia wymaga imienia, nazwiska i adresu osoby odstępującej od umowy.</p>

                        <div class="mt-5 space-y-4">
                            <div>
                                <label for="customer_name" class="block text-sm opacity-80">Imię i nazwisko</label>
                                <input type="text" id="customer_name" name="customer_name" value="{{ $prefillName }}" required
                                    class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                @error('customer_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="customer_address" class="block text-sm opacity-80">Adres</label>
                                <input type="text" id="customer_address" name="customer_address" value="{{ $prefillAddress }}" required
                                    class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                @error('customer_address')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="bank_account" class="block text-sm opacity-80">Numer konta <span class="opacity-60">— opcjonalnie</span></label>
                                <input type="text" id="bank_account" name="bank_account" value="{{ old('bank_account') }}" inputmode="numeric"
                                    class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                <p class="mt-1 text-xs opacity-60">Podaj, jeśli pieniądze nie mogą wrócić tą samą drogą, którą przyszły (np. płatność przy odbiorze).</p>
                                @error('bank_account')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="note" class="block text-sm opacity-80">Wiadomość dla sklepu <span class="opacity-60">— opcjonalnie</span></label>
                                <textarea id="note" name="note" rows="3"
                                    class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">{{ old('note') }}</textarea>
                                <p class="mt-1 text-xs opacity-60">Nie musisz podawać powodu zwrotu — prawo tego nie wymaga.</p>
                                @error('note')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 text-center">
                    <button type="submit" class="st-btn inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">
                        Wyślij oświadczenie o odstąpieniu
                    </button>
                </div>
            </form>
        @else
            <div class="st-card st-border mt-6 rounded-3xl border p-6 text-center">
                @if ($order->status === \App\Enums\OrderStatus::Cancelled)
                    <p class="font-semibold">Zamówienie zostało anulowane</p>
                    <p class="mt-1 text-sm opacity-70">Nie ma od czego odstąpić — jeśli zapłacono, skontaktuj się ze sklepem.</p>
                @elseif (! $order->hasBeenHandedOver())
                    {{-- Zamówienie jeszcze w drodze. Prawo do odstąpienia klientowi
                         przysługuje (istnieje od zawarcia umowy), ale formularz
                         zwrotu nie jest tu właściwą drogą: pomniejsza zamówienie
                         i mówi o odesłaniu rzeczy, której klient nie ma. Kierujemy
                         do sprzedawcy, bo to po prostu rezygnacja z zamówienia. --}}
                    <p class="font-semibold">Zwrot zgłosisz po otrzymaniu zamówienia</p>
                    <p class="mt-1 text-sm opacity-70">
                        To zamówienie jeszcze do Ciebie nie dotarło, więc nie ma czego odsyłać.
                        Formularz otworzy się, gdy sklep oznaczy je jako zrealizowane —
                        a {{ config('legal.withdrawal.days') }} dni na odstąpienie liczy się dopiero od chwili, gdy odbierzesz towar.
                    </p>
                    <p class="mt-3 text-sm opacity-70">
                        Chcesz zrezygnować już teraz? Napisz do sklepu — zamówienie da się jeszcze anulować.
                        @if (filled($shop->contact_email))
                            Adres kontaktowy: <span class="font-medium">{{ $shop->contact_email }}</span>.
                        @endif
                    </p>
                @elseif (! $order->withinWithdrawalWindow())
                    {{-- Świadomie NIE piszemy „prawo wygasło". Daty doręczenia nie
                         znamy — szacujemy ją z zamówienia, więc możemy zamknąć
                         formularz wcześniej, niż wygasa prawo klienta. Zamykamy
                         drogę automatyczną (żeby nikt nie mieszał w zamówieniu po
                         miesiącach), ale kierujemy do sprzedawcy. --}}
                    <p class="font-semibold">Formularz zwrotu jest już zamknięty</p>
                    <p class="mt-1 text-sm opacity-70">
                        Czas na odstąpienie szacujemy do {{ $order->withdrawalDeadline()->format('d.m.Y') }}
                        — {{ config('legal.withdrawal.days') }} dni od otrzymania przesyłki, z zapasem na jej dostarczenie.
                    </p>
                    <p class="mt-3 text-sm opacity-70">
                        Jeśli przesyłka dotarła do Ciebie później, prawo odstąpienia wciąż Ci przysługuje — liczy się od dnia doręczenia, którego my nie znamy.
                        Napisz wtedy wprost do sklepu, a sprzedawca przyjmie zwrot.
                        @if (filled($shop->contact_email))
                            Adres kontaktowy: <span class="font-medium">{{ $shop->contact_email }}</span>.
                        @endif
                    </p>
                    <p class="mt-3 text-sm opacity-70">Jeśli towar ma wadę, to osobne uprawnienie (reklamacja) i nie zależy od tego terminu.</p>
                @elseif ($order->hasReturns())
                    <p class="font-semibold">Wszystko już zgłoszone do zwrotu</p>
                    <p class="mt-1 text-sm opacity-70">Z tego zamówienia nie została żadna pozycja do oddania.</p>
                @else
                    <p class="font-semibold">To zamówienie nie podlega zwrotowi</p>
                    <p class="mt-1 text-sm opacity-70">Prawo odstąpienia nie obejmuje towarów szybko psujących się ani rzeczy wykonanych na indywidualne zamówienie (art. 38 ustawy o prawach konsumenta).</p>
                @endif
            </div>
        @endif

        {{-- Pouczenie o skutkach — obowiązek informacyjny sklepu; ta sama treść
             co w mailu po zakupie, żeby klient nigdzie nie trafił na inną wersję. --}}
        <div class="st-border mt-10 grid gap-6 border-t pt-6 text-xs opacity-70 md:grid-cols-2">
            <div>
                <p class="font-semibold">Odesłanie towaru</p>
                <p class="mt-2">Masz {{ config('legal.withdrawal.days') }} dni od otrzymania towaru na odstąpienie od umowy bez podania przyczyny. Po wysłaniu oświadczenia odeślij towar w ciągu {{ config('legal.withdrawal.days') }} dni — koszt odesłania ponosisz Ty, chyba że sklep ustalił inaczej.</p>
            </div>
            <div>
                <p class="font-semibold">Zwrot pieniędzy</p>
                <p class="mt-2">Sklep zwróci pieniądze w ciągu {{ config('legal.withdrawal.days') }} dni od otrzymania oświadczenia, wraz z kosztem najtańszej oferowanej dostawy przy zwrocie całego zamówienia. Może wstrzymać zwrot do chwili otrzymania towaru lub dowodu jego odesłania.</p>
            </div>
        </div>
    </main>
</x-layouts.storefront>
