---
name: blade-php-block-breaks-inline-php
description: "GOTCHA (dwie pułapki kompilacji Blade): blok @php…@endphp obok jednolinijkowego @php(...) rozwala widok; oraz @if przyklejone do słowa (sklepu@if) nie jest dyrektywą, więc @endif zamyka nadrzędny warunek."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 37f72f3b-963d-48a4-ba3e-06ca036f5d60
  modified: 2026-07-29T16:11:56.665Z
---

**Objaw (2026-07-26, koszyk):** widok nagle przestaje się kompilować z `syntax error, unexpected token "@" (View: …)`, a wskazana linia nie ma nic wspólnego z ostatnią zmianą.

**Przyczyna:** Blade najpierw wycina surowe bloki `@php … @endphp` wyrażeniem niezachłannym. Gdy WYŻEJ w pliku jest forma jednolinijkowa `@php($x = …)`, dopasowanie startuje od NIEJ i kończy się na `@endphp` mojego bloku — połykając cały kod pomiędzy. Efekt: `@php($product = …)` kompiluje się do `<?php($product = …)` i od tego miejsca plik jest połamany.

**Reguła:** w jednym pliku Blade trzymaj JEDNĄ formę. Nasze widoki masowo używają `@php(...)` w linii (np. `@php($product = $line['product'])`), więc **nie dokładaj do nich bloków `@php … @endphp`**.

**Jak robić zamiast tego:** logikę prezentacji policz w komponencie/kontrolerze i przekaż gotową zmienną do widoku. W koszyku tak właśnie powstały `discountIssue` / `discountNote` w `App\Livewire\Cart::render()` — przy okazji widok zrobił się czystszy, bo nie ma w nim `match()`.

**Diagnoza w 10 sekund**, gdy komunikat jest bezużyteczny:
```bash
php artisan tinker --execute="file_put_contents('/tmp/c.php', Blade::compileString(file_get_contents('resources/views/…blade.php')));"
php -l /tmp/c.php
```
Skompilowany plik pokazuje dokładne miejsce, w którym Blade się pogubił.

---

## Bliźniacza pułapka: dyrektywa PRZYKLEJONA do słowa (2026-07-29)

**Objaw:** `ParseError: syntax error, unexpected token "elseif"` w skompilowanym widoku, choć struktura `@if / @elseif / @else / @endif` w źródle jest poprawna.

**Przyczyna:** `@if` napisane bez spacji po tekście — `…napisz do sklepu@if (filled($shop->contact_email))` — NIE jest kompilowane jako dyrektywa (Blade chroni w ten sposób adresy e-mail i inne `słowo@coś`). Sam `@endif` kompiluje się normalnie, więc zamyka NADRZĘDNY warunek, a dalsze `@elseif` / `@else` zostają bez rodzica.

**Reguła:** dyrektywa Blade zawsze zaczyna linię albo ma spację przed `@`. Warunku wplecionego w środek zdania nie doklejaj do wyrazu — zrób z niego osobne zdanie w nowej linii albo policz tekst wcześniej (`{{ filled($x) ? '…' : '' }}`).

Złapały to testy strony (500 zamiast 200), więc każdy widok wart jest choć jednego testu, który go po prostu renderuje.

Powiązane: [[tailwind-classes-must-exist-in-build]] (druga cicha pułapka warstwy widoku).
