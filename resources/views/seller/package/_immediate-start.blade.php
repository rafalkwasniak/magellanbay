{{-- Wyraźne żądanie rozpoczęcia świadczenia przed upływem 14 dni na odstąpienie
     (art. 15 ust. 3 ustawy o prawach konsumenta). Wchodzi do KAŻDEGO formularza
     zakupu — kupno, przedłużenie i zejście na mniejszy pakiet.

     NIE wolno tego zaznaczyć z góry ani zastąpić opisem pod przyciskiem: żądanie
     musi być odrębną czynnością sprzedawcy, inaczej §9 ust. 2 Regulaminu (zapłata
     za wykorzystany okres przy odstąpieniu) nie ma na czym stanąć i przy
     odstąpieniu oddajemy całość. Bramkę trzyma `PackagePurchaseRequest`.

     `$purchasePackage` — klucz pakietu kupowanego TYM formularzem. Osobna nazwa,
     bo w cenniku pętla trzyma własne `$package` (tablicę), a `@include` widzi
     zmienne rodzica.

     DWA POZIOMY WALIDACJI, obie potrzebne z różnych powodów:
     — `required` łapie brak zgody po stronie przeglądarki, w TYM formularzu,
       bez przeładowania strony (`forms.js` sprawdza formularze osobno);
     — `PackagePurchaseRequest` jest źródłem prawdy i broni się bez JS.

     Serwerowy komunikat zawężamy przez `form_package`. Bez tego worek błędów jest
     wspólny dla całej strony i odbicie z JEDNEGO przycisku zapalało czerwony tekst
     pod WSZYSTKIMI widocznymi zgodami naraz (złapane na ekranie przez Rafała:
     Pawilon i zejście na Stragan podświetlały się razem).

     Bursztynowa ramka nie jest ozdobą — bez niej zgoda czytała się jak trzecia
     linijka drobnego druku i ginęła w opisie pakietu pod spodem.

     Świadomie bez `accent-*` — tej klasy nie ma w zbudowanym CSS, więc cicho by
     nic nie zrobiła. Znacznik checkboxa jak przy innych polach w panelu. --}}
<input type="hidden" name="form_package" value="{{ $purchasePackage }}">
<div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3">
    <label class="flex items-start gap-3 text-xs leading-relaxed text-amber-900">
        <input type="checkbox" name="immediate_start" value="1" class="mt-0.5 shrink-0" required
            data-msg-required="Zaznacz tę zgodę, żeby przejść do płatności.">
        <span>
            Chcę, żeby pakiet ruszył <span class="font-semibold">od razu po opłaceniu</span>, jeszcze
            przed upływem 14 dni na odstąpienie od umowy. Wiem, że jeśli w tym czasie odstąpię,
            zapłacę za okres, z którego zdążyłem skorzystać.
        </span>
    </label>
    @error('immediate_start')
        @if (old('form_package') === $purchasePackage)
            <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
        @endif
    @enderror
</div>
