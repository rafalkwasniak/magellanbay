{{-- POLA formularza zgłoszenia — jedna kopia dla obu szat.

     Formularz żyje w dwóch skórach: na centrali Kramio (bursztyn, klasy `stone-*`)
     i na storefroncie sklepu dedykowanego (kolory z palety sklepu, klasy `st-*`).
     Same POLA są identyczne — różnią się wyłącznie klasami, więc przychodzą one
     z zewnątrz w tablicy `$ui`. Druga kopia tych 80 linii rozjechałaby się przy
     pierwszej zmianie w walidacji.

     Wymagane zmienne: $ui (label, hint, input, error, notice, noticeText, button),
     $categories, $prefilledUrl, $urlPlaceholder. --}}
@csrf

{{-- Pułapka na boty — wzorzec z rejestracji. Styl wpisany w znacznik, nie
     w klasach Tailwinda: klasa, której zabrakłoby w zbudowanym CSS, po cichu
     nic nie robi i pole wyskoczyłoby ludziom w formularzu. --}}
<div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
    <label for="website">Nie wypełniaj tego pola</label>
    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off" value="">
</div>

@error('website')
    <p class="{{ $ui['notice'] }}">{{ $message }}</p>
@enderror

<div>
    <label for="url" class="{{ $ui['label'] }}">Adres zgłaszanej strony</label>
    <p class="{{ $ui['hint'] }}">Wklej pełny adres, razem z „https://" — im dokładniej, tym szybciej znajdziemy treść.</p>
    <input id="url" name="url" type="url" required value="{{ old('url', $prefilledUrl) }}"
        placeholder="{{ $urlPlaceholder }}"
        class="{{ $ui['input'] }}">
    @error('url')
        <p class="{{ $ui['error'] }}">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="category" class="{{ $ui['label'] }}">Czego dotyczy zgłoszenie</label>
    <select id="category" name="category" required class="{{ $ui['input'] }}">
        <option value="">— wybierz —</option>
        @foreach ($categories as $value => $label)
            <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @error('category')
        <p class="{{ $ui['error'] }}">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="justification" class="{{ $ui['label'] }}">Dlaczego uważasz tę treść za bezprawną</label>
    <p class="{{ $ui['hint'] }}">Napisz własnymi słowami. Jeśli chodzi o Twoje prawa (znak towarowy, zdjęcie, tekst), napisz też, skąd wynikają.</p>
    <textarea id="justification" name="justification" rows="6" required class="{{ $ui['input'] }}">{{ old('justification') }}</textarea>
    @error('justification')
        <p class="{{ $ui['error'] }}">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="reporter_email" class="{{ $ui['label'] }}">Twój adres e-mail</label>
    <p class="{{ $ui['hint'] }}">Potrzebny, żeby wysłać Ci potwierdzenie i rozstrzygnięcie.</p>
    <input id="reporter_email" name="reporter_email" type="email" required value="{{ old('reporter_email') }}"
        class="{{ $ui['input'] }}">
    @error('reporter_email')
        <p class="{{ $ui['error'] }}">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="reporter_name" class="{{ $ui['label'] }}">Imię i nazwisko lub nazwa <span class="font-normal opacity-60">— nieobowiązkowe</span></label>
    <input id="reporter_name" name="reporter_name" type="text" value="{{ old('reporter_name') }}"
        class="{{ $ui['input'] }}">
    @error('reporter_name')
        <p class="{{ $ui['error'] }}">{{ $message }}</p>
    @enderror
</div>

<div class="{{ $ui['notice'] }}">
    <label class="{{ $ui['noticeText'] }}">
        <input type="checkbox" name="good_faith" value="1" class="mt-0.5 shrink-0" required
            data-msg-required="Potwierdź, że zgłoszenie składasz w dobrej wierze."
            @checked(old('good_faith'))>
        <span>
            Oświadczam, że informacje w tym zgłoszeniu są, według mojej najlepszej wiedzy,
            <span class="font-semibold">prawidłowe i kompletne</span>, a zgłoszenie składam w dobrej wierze.
        </span>
    </label>
    @error('good_faith')
        <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
    @enderror
</div>

<button type="submit" class="{{ $ui['button'] }}">Wyślij zgłoszenie</button>
