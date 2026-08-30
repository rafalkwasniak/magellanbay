---
name: form-client-validation-convention
description: Każdy formularz dostaje klientową walidację UX przez resources/js/forms.js (zero zależności); backend Form Requesty zostają źródłem prawdy.
metadata: 
  node_type: memory
  type: project
  originSessionId: ad2d01af-7396-446a-bc53-3263cd9693a7
---

Wszystkie formularze w projekcie mają mieć klientową walidację oczywistych błędów (pola wymagane, format e-maila, zgodność haseł) ZANIM polecą na serwer — żeby nie tracić wpisanych danych (zwłaszcza hasła) na przeładowaniu.

Mechanizm: moduł `resources/js/forms.js` (czysty JS, zero zależności, w duchu modułu toastów; importowany w `resources/js/app.js`). Konwencja w markupie:
- `<form novalidate data-validate>` — włącza moduł, wyłącza natywne dymki przeglądarki
- `<input required>` — pole wymagane (działa też na checkbox)
- `data-msg-required` / `data-msg-email` / `data-msg-match` — własne komunikaty
- `data-match="idInnegoPola"` — wartość musi równać się polu o tym id (np. powtórz hasło)

Komunikaty renderują się inline pod polem w stylu serwerowego `@error` (`text-sm text-rose-600`), pole dostaje `aria-invalid` + `!border-rose-400`, błąd znika przy edycji. Po nieudanej walidacji fokus + scroll na pierwsze błędne pole.

**Why:** Rafał zgłosił, że niezaznaczony Regulamin → reload → znika wpisane hasło. Klientowa walidacja blokuje wysyłkę dla łapalnych błędów, więc reloadu nie ma.

**How to apply:** Nowy formularz = `novalidate data-validate` na `<form>`, `required` na polach wymaganych (też checkboxach), reszta dzieje się generycznie. NIE przenosimy reguł biznesowych na front — Form Requesty zostają jedynym źródłem prawdy (FOUNDATION sek. 5); JS to tylko warstwa UX. Wdrożone na razie w `auth/register.blade.php` i `auth/activation.blade.php`. Zgodne z [[frontend-stack-decision]] (Blade-first, lekko) i [[ui-design-direction]].
