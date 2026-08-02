@props([
    // Kto pyta o zgodę: nazwa sklepu na storefroncie, nazwa platformy w centrali.
    // Na storefroncie administratorem danych jest SPRZEDAWCA, nie Kramio.
    'owner' => null,
    // Adres polityki prywatności właściwej dla tego miejsca.
    'privacyUrl' => null,
    // Czy w tym miejscu jest w ogóle co blokować. Sklep bez włączonej analityki
    // ustawia wyłącznie ciasteczka niezbędne — pytanie o zgodę byłoby wtedy
    // tarciem bez powodu, a przy zakupach każde tarcie kosztuje.
    'needed' => true,
])

@if ($needed && ! \App\Support\CookieConsent::decided())
    {{-- Kolory bierzemy ze zmiennych CSS otoczenia (--surface, --ink, --brand,
         --brand-ink). Storefront wstrzykuje je z palety motywu sklepu, więc ten
         sam komponent wygląda „po sklepowemu" na każdej z dwudziestu palet.

         W centrali tych zmiennych nie ma i to jest ZAMIERZONE: wartości zapasowe
         poniżej są barwami Kramio (bursztyn marki, kremowa biel, grafit tekstu).
         Dzięki temu jeden komponent obsługuje oba światy bez zmiennych
         dublowanych w trzech layoutach i bez przebudowy arkusza.

         Styl wpisany w znacznik, nie w klasach Tailwinda: baner musi wyglądać
         poprawnie także wtedy, gdy ktoś zapomni przebudować CSS, a klasa,
         której zabraknie w buildzie, po cichu nic nie robi. --}}
    <div role="dialog" aria-live="polite" aria-label="Zgoda na pliki cookie"
        style="position:fixed;left:0;right:0;bottom:0;z-index:60;padding:1rem;">
        <div style="max-width:60rem;margin:0 auto;border-radius:1rem;padding:1.25rem 1.5rem;
                    background:var(--surface,#ffffff);color:var(--ink,#1c1917);
                    border:1px solid color-mix(in srgb, var(--ink,#1c1917) 15%, transparent);
                    box-shadow:0 10px 40px rgba(0,0,0,.18);">
            <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between;">
                <div style="flex:1 1 22rem;min-width:0;">
                    <p style="font-weight:600;margin:0 0 .35rem;">Ciasteczka</p>
                    <p style="margin:0;font-size:.875rem;line-height:1.5;opacity:.85;">
                        {{ $owner }} używa plików cookie, żeby {{ $slot->isNotEmpty() ? trim($slot) : 'koszyk i logowanie działały poprawnie' }}.
                        Za Twoją zgodą korzystamy też z narzędzia analitycznego Google, które pomaga zrozumieć,
                        co jest przydatne.
                        @if ($privacyUrl)
                            <a href="{{ $privacyUrl }}" style="text-decoration:underline;text-underline-offset:2px;">Szczegóły w Polityce prywatności</a>.
                        @endif
                    </p>
                </div>

                {{-- Oba przyciski jednakowej wagi i jedno kliknięcie każdy.
                     Odmowa schowana głębiej niż zgoda czyni baner wadliwym —
                     to wymóg, nie kwestia gustu. --}}
                <form method="POST" action="{{ route('cookies.store') }}"
                    style="display:flex;gap:.5rem;flex:0 0 auto;">
                    @csrf
                    <button type="submit" name="decision" value="decline"
                        style="border-radius:.75rem;padding:.625rem 1.1rem;font-size:.875rem;font-weight:600;
                               background:transparent;color:inherit;cursor:pointer;
                               border:1px solid color-mix(in srgb, var(--ink,#1c1917) 25%, transparent);">
                        Tylko niezbędne
                    </button>
                    <button type="submit" name="decision" value="accept"
                        style="border-radius:.75rem;padding:.625rem 1.1rem;font-size:.875rem;font-weight:600;
                               background:var(--brand,#d97706);color:var(--brand-ink,#ffffff);
                               border:1px solid transparent;cursor:pointer;">
                        Zgadzam się
                    </button>
                </form>
            </div>
        </div>
    </div>
@endif
