---
name: form-radio-groups-in-forms-js
description: "forms.js rozumie grupy radio od 15.08 — `required` na radiu znaczy „wybierz coś z grupy”. Wcześniej blokowało wysyłkę mimo wyboru."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 4d8bfafa-24ef-47c8-8b0f-9bb8f207d0e5
  modified: 2026-08-15T17:10:27.605Z
---

Do 2026-08-15 `resources/js/forms.js` traktował radio jak checkbox i sprawdzał **każdy przycisk osobno** („czy TEN jest zaznaczony”). Przy grupie oznaczało to, że wybranie jednej opcji zapalało błąd „Zaznacz to pole, aby kontynuować” przy pozostałych, a formularz **odmawiał wysyłki mimo dokonanego wyboru**.

Luka nie wychodziła latami, bo ekran rozstrzygania zgłoszeń (`administrator/reports/show`) był **jedyną grupą radio w aplikacji z `required`** — pozostałe używają Livewire albo mają domyślny wybór. Znalazł Rafał, klikając nowy moduł.

**Stan po naprawie (commit `4707d19`):**
- `firstViolation()` przy `type === 'radio'` sprawdza, czy **którykolwiek** przycisk o tej samej nazwie w tym formularzu jest zaznaczony (`groupChecked()`).
- `validate()` pomija kolejne przyciski tej samej nazwy → **jeden komunikat na grupę**, nie jeden pod każdą opcją.
- Czyszczenie błędu obejmuje **całą grupę** — komunikat wisi pod pierwszym przyciskiem, a kliknąć można dowolny.
- Osobny domyślny komunikat `DEFAULTS.requiredRadio` = „Wybierz jedną z opcji.”; nadpisywalny przez `data-msg-required`.
- Checkbox działa jak dotąd.

**WZORZEC WART POWTÓRZENIA:** zanim poszedł build, logikę sprawdziłem **symulacją DOM w czystym Node** (atrapa `form.querySelectorAll`, 6 przypadków: wybór pierwszej opcji, wybór drugiej, dwie niezależne grupy, puste pole obok, checkbox po staremu). Build na tym hoście jest kosztowny i zawodny — [[vite-build-rayon-threads]], [[build-fails-tell-rafal-immediately]] — więc nie wolno budować w ciemno, żeby dopiero wtedy sprawdzić, czy algorytm działa.

**SEKWENCJONOWANIE ZMIAN JS + BLADE:** przeglądarka dostaje zbudowany bundel, ale Blade renderuje się na bieżąco. Dodanie `required` w Bladzie PRZED udanym buildem przywraca usterkę na produkcji. Kolejność: zmiana w JS → build → dopiero potem atrybut w Bladzie (Blade nie wymaga przebudowy).

Powiązane: [[form-client-validation-convention]], [[tailwind-classes-must-exist-in-build]].
