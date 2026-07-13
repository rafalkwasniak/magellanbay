<div>
    @php($canOrder = count($deliveryOptions) > 0 && count($paymentOptions) > 0)

    @if ($lines->isEmpty())
        <div class="st-card st-border mx-auto max-w-lg rounded-3xl border px-8 py-16 text-center">
            <p class="text-lg font-semibold">Twój koszyk jest pusty</p>
            <p class="mt-1 opacity-70">Dodaj produkty, zanim przejdziesz do kasy.</p>
            <a href="/" wire:navigate class="st-btn mt-6 inline-block rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105">Wróć do sklepu</a>
        </div>
    @elseif (! $canOrder)
        <div class="st-card st-border mx-auto max-w-lg rounded-3xl border px-8 py-12 text-center">
            <p class="text-lg font-semibold">Sklep nie przyjmuje jeszcze zamówień</p>
            <p class="mt-1 opacity-70">Sprzedawca nie skonfigurował dostawy lub płatności. Zajrzyj później.</p>
            <a href="/koszyk" wire:navigate class="mt-6 inline-block text-sm underline underline-offset-4 opacity-70 hover:opacity-100">Wróć do koszyka</a>
        </div>
    @else
        {{-- Komunikaty finalnej weryfikacji (auto-korekta) --}}
        @if (! empty($reviewMessages))
            <div class="st-border mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
                <p class="font-semibold">Zawartość koszyka wymaga sprawdzenia:</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach ($reviewMessages as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
                <a href="/koszyk" wire:navigate class="mt-2 inline-block font-medium underline underline-offset-2">Przejdź do koszyka</a>
            </div>
        @endif

        <form wire:submit="place" class="grid gap-8 lg:grid-cols-3">
            {{-- Formularz --}}
            <div class="space-y-6 lg:col-span-2">
                {{-- Dane kupującego --}}
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="font-semibold">Dane kupującego</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="buyer_name" class="block text-sm opacity-80">Imię</label>
                            <input type="text" id="buyer_name" wire:model="buyer_name" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                            @error('buyer_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="buyer_surname" class="block text-sm opacity-80">Nazwisko</label>
                            <input type="text" id="buyer_surname" wire:model="buyer_surname" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                            @error('buyer_surname') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="buyer_email" class="block text-sm opacity-80">E-mail</label>
                            <input type="email" id="buyer_email" wire:model.blur="buyer_email" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                            @error('buyer_email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="buyer_phone" class="block text-sm opacity-80">Telefon</label>
                            <input type="text" id="buyer_phone" wire:model="buyer_phone" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                            @error('buyer_phone') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Kupuję jako firma --}}
                    <label class="mt-5 flex items-center gap-3 text-sm">
                        <input type="checkbox" wire:model.live="is_company" class="st-border h-5 w-5 rounded border" style="accent-color: var(--brand);">
                        Kupuję jako firma
                    </label>

                    @if ($is_company)
                        <div class="mt-4 space-y-4">
                            {{-- NIP + auto-uzupełnienie --}}
                            <div>
                                <label for="company_nip" class="block text-sm opacity-80">NIP</label>
                                <div class="mt-1 flex gap-2">
                                    <input type="text" id="company_nip" wire:model="company_nip" class="st-border block flex-1 rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                    <button type="button" wire:click="lookupCompany" wire:loading.attr="disabled" wire:target="lookupCompany"
                                        class="st-border shrink-0 rounded-xl border px-4 py-2.5 text-sm font-medium transition hover:brightness-95 disabled:opacity-60">
                                        <span wire:loading.remove wire:target="lookupCompany">Pobierz dane</span>
                                        <span wire:loading wire:target="lookupCompany">Pobieram…</span>
                                    </button>
                                </div>
                                @error('company_nip') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                <p class="mt-1 text-xs opacity-60">Wpisz NIP i kliknij „Pobierz dane", aby uzupełnić nazwę i adres firmy.</p>
                            </div>

                            <div>
                                <label for="company_name" class="block text-sm opacity-80">Nazwa firmy</label>
                                <input type="text" id="company_name" wire:model="company_name" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                @error('company_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Adres firmy do faktury (opcjonalny, niezależny od dostawy) --}}
                            <div class="grid grid-cols-6 gap-3">
                                <div class="col-span-6 sm:col-span-4">
                                    <label for="company_street" class="block text-sm opacity-80">Ulica</label>
                                    <input type="text" id="company_street" wire:model="company_street" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                </div>
                                <div class="col-span-3 sm:col-span-1">
                                    <label for="company_building_number" class="block text-sm opacity-80">Nr</label>
                                    <input type="text" id="company_building_number" wire:model="company_building_number" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                </div>
                                <div class="col-span-3 sm:col-span-1">
                                    <label for="company_apartment_number" class="block text-sm opacity-80">Lok.</label>
                                    <input type="text" id="company_apartment_number" wire:model="company_apartment_number" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                </div>
                                <div class="col-span-3 sm:col-span-2">
                                    <label for="company_postal_code" class="block text-sm opacity-80">Kod</label>
                                    <input type="text" id="company_postal_code" wire:model="company_postal_code" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                </div>
                                <div class="col-span-3 sm:col-span-4">
                                    <label for="company_city" class="block text-sm opacity-80">Miejscowość</label>
                                    <input type="text" id="company_city" wire:model="company_city" class="st-border mt-1 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                                </div>
                            </div>
                            <p class="text-xs opacity-60">Adres firmy do faktury — opcjonalny, niezależny od miejsca dostawy.</p>
                        </div>
                    @endif
                </div>

                {{-- Dostawa --}}
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="font-semibold">Dostawa</h2>
                    <div class="mt-4 space-y-2">
                        @foreach ($deliveryOptions as $value => $label)
                            <label class="st-border flex cursor-pointer items-center gap-3 rounded-xl border p-4 text-sm">
                                <input type="radio" wire:model="delivery_method" value="{{ $value }}" class="h-5 w-5 shrink-0" style="accent-color: var(--brand);">
                                <span>
                                    <span class="font-medium">{{ $label }}</span>
                                    @if ($value === 'pickup' && filled($pickupAddress))
                                        <span class="block text-xs opacity-60">{{ $pickupAddress }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('delivery_method') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                {{-- Płatność --}}
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="font-semibold">Płatność</h2>
                    <div class="mt-4 space-y-2">
                        @foreach ($paymentOptions as $value => $label)
                            <label class="st-border flex cursor-pointer items-center gap-3 rounded-xl border p-4 text-sm">
                                <input type="radio" wire:model="payment_method" value="{{ $value }}" class="h-5 w-5 shrink-0" style="accent-color: var(--brand);">
                                <span>
                                    <span class="font-medium">{{ $label }}</span>
                                    @if ($value === 'bank_transfer' && filled($bankName))
                                        <span class="block text-xs opacity-60">{{ $bankName }}</span>
                                    @elseif ($value === 'pay_on_pickup' && filled($pickupAddress))
                                        <span class="block text-xs opacity-60">Odbiór i płatność: {{ $pickupAddress }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('payment_method') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                {{-- Uwagi --}}
                <div class="st-card st-border rounded-3xl border p-6">
                    <label for="note" class="font-semibold">Uwagi do zamówienia <span class="text-sm font-normal opacity-60">(opcjonalnie)</span></label>
                    <textarea id="note" wire:model="note" rows="3" class="st-border mt-3 block w-full rounded-xl border bg-transparent px-3 py-2.5 text-sm focus:outline-none"></textarea>
                    @error('note') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Podsumowanie --}}
            <aside class="lg:col-span-1">
                <div class="st-card st-border rounded-3xl border p-6">
                    <h2 class="font-semibold">Twoje zamówienie</h2>
                    <ul class="mt-4 space-y-3">
                        @foreach ($lines as $line)
                            <li class="flex justify-between gap-3 text-sm">
                                <span class="opacity-80">{{ $line['product']->sale_unit->formatQuantity($line['quantity']) }} × {{ $line['product']->name }}</span>
                                <span class="shrink-0 tabular-nums">{{ \App\Support\Money::pln($line['line_total']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="st-border mt-4 flex items-baseline justify-between border-t pt-4">
                        <span class="opacity-70">Razem (brutto)</span>
                        <span class="text-xl font-bold tabular-nums">{{ $formattedTotal }}</span>
                    </div>
                    <p class="mt-1 text-right text-xs opacity-60">{{ $formattedNet }} netto</p>

                    <label class="mt-5 flex items-start gap-3 text-sm">
                        <input type="checkbox" wire:model="accept_terms" class="st-border mt-0.5 h-5 w-5 shrink-0 rounded border" style="accent-color: var(--brand);">
                        <span>
                            Akceptuję
                            @if ($termsUrl)
                                <a href="{{ $termsUrl }}" target="_blank" rel="noopener" class="underline underline-offset-2 hover:opacity-80">regulamin sklepu</a>.
                            @else
                                regulamin sklepu.
                            @endif
                        </span>
                    </label>
                    @error('accept_terms') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror

                    <label class="mt-3 flex items-start gap-3 text-sm">
                        <input type="checkbox" wire:model="accept_privacy" class="st-border mt-0.5 h-5 w-5 shrink-0 rounded border" style="accent-color: var(--brand);">
                        <span>
                            Akceptuję
                            <a href="{{ $privacyUrl }}" target="_blank" rel="noopener" class="underline underline-offset-2 hover:opacity-80">politykę prywatności</a>
                            i zasady przetwarzania danych.
                        </span>
                    </label>
                    @error('accept_privacy') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror

                    <button type="submit" wire:loading.attr="disabled" wire:target="place"
                        class="st-btn mt-6 w-full rounded-full px-8 py-3 text-sm font-semibold shadow-sm transition hover:brightness-105 disabled:opacity-60">
                        <span wire:loading.remove wire:target="place">Zamawiam i płacę</span>
                        <span wire:loading wire:target="place">Składam zamówienie…</span>
                    </button>
                </div>

                {{-- Konto klienta — osobny box pod podsumowaniem: zalogowany → info;
                     e-mail z kontem → dopiszemy do historii; wolny e-mail → opcja
                     założenia konta (mail aktywacyjny po zamówieniu). --}}
                <div class="st-card st-border mt-6 rounded-3xl border p-6">
                    <h2 class="font-semibold">Konto</h2>
                    @if ($this->authCustomer)
                        <p class="mt-3 text-sm opacity-70">
                            Zamawiasz jako <strong class="st-brand">{{ $this->authCustomer->email }}</strong> — zamówienie trafi do historii Twojego konta.
                        </p>
                    @elseif ($this->accountExists)
                        <p class="mt-3 text-sm opacity-70">
                            Ten e-mail ma już konto w tym sklepie — dopiszemy zamówienie do jego historii.
                            <a href="/logowanie" class="st-brand underline underline-offset-2">Zaloguj się</a>
                        </p>
                    @else
                        <label class="mt-3 flex items-start gap-3 text-sm">
                            <input type="checkbox" wire:model="create_account" class="st-border mt-0.5 h-5 w-5 shrink-0 rounded border" style="accent-color: var(--brand);">
                            <span>Załóż konto na ten e-mail — po zamówieniu wyślemy link do ustawienia hasła. Zobaczysz historię zamówień i szybciej złożysz kolejne.</span>
                        </label>
                    @endif
                </div>
            </aside>
        </form>
    @endif
</div>
