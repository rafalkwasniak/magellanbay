---
name: blade-never-name-view-variable-errors
description: "Zmienna widoku o nazwie $errors nadpisuje worek błędów walidacji Laravela i wywala każdy @error komunikatem, który nie wskazuje przyczyny"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 6850d835-d66a-4f18-acd6-ca84588d5ea8
  modified: 2026-08-11T19:14:24.963Z
---

**Nigdy nie przekazywać do widoku zmiennej o nazwie `errors`.** Laravel wstrzykuje tam `ViewErrorBag`; własna wartość pod tym kluczem go nadpisuje.

Objaw jest mylący: strona zwraca 500 z `Call to a member function getBag() on array`, ze śladem stosu wskazującym na **skompilowany** widok w `storage/framework/views/<hash>.php` — czyli plik, którego się nie pisało. Prawdziwa przyczyna to `@error('pole')`, które pod spodem woła `$errors->getBag()`.

Złapane 11.08.2026 przy ekranie Ustawień: kontroler podawał `'errors' => PlatformHealth::recentErrors()` (liczba błędów w logach), a formularz obok miał `@error('maintenance_notice')`.

**Why:** kolizja jest cicha do chwili, gdy w tym samym widoku pojawi się walidacja — więc ekran potrafi działać tygodniami i wywalić się dopiero po dołożeniu formularza.

**How to apply:** nazywać opisowo (`logErrors`, `errorDays`, `validationIssues`). Widząc `getBag() on array`, szukać nadpisanej zmiennej w kontrolerze, a nie błędu w Blade. Pokrewne: [[blade-php-block-breaks-inline-php]], [[livewire-override-signature-fatal]].
