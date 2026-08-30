---
name: livewire-override-signature-fatal
description: "Nadpisanie metody Livewire/frameworka OSTRZEJSZĄ sygnaturą = fatal przy ładowaniu klasy, a runner testów pokazuje tylko „Premature end of PHP process" BEZ przyczyny. Objaw myli — wygląda jak błąd widoku."
metadata:
  type: reference
---

**Objaw:** test renderujący stronę przerywa się komunikatem
`Fatal error: Premature end of PHP process when running Tests\...`
— bez stack trace, bez wyjątku, bez wpisu w logu. Wypisany HTML urywa się dokładnie w miejscu, gdzie miał się wyrenderować komponent, więc pierwsze podejrzenie pada na Blade.

**Przyczyna (incydent 2026-08-08, `App\Livewire\Seller\OrderShipment`):** nadpisałem hook Livewire jako
`protected function prepareForValidation(array $attributes): array`,
podczas gdy rodzic (`Livewire\Features\SupportValidation\HandlesValidation`) deklaruje
`protected function prepareForValidation($attributes)`.
**Dodanie typu parametru w klasie potomnej łamie kontrawariancję** → PHP zgłasza fatal **przy ładowaniu klasy**, czyli zanim cokolwiek zdąży go złapać.

**Jak rozpoznać szybko:**
1. `php -l` na skompilowanym widoku przechodzi → **to NIE jest Blade**. (Kompilacja widoku: `app("blade.compiler")->compileString(file_get_contents(...))`.)
2. Szukaj świeżo nadpisanych metod frameworka: `grep -rn "function <nazwa>" vendor/<paczka>/src/` i porównaj sygnaturę **znak w znak**.

**Reguła:** przy nadpisywaniu metody frameworka kopiuj sygnaturę dosłownie — bez dokładania typów parametrów i typu zwrotu, choćby kusiło. Typ zwrotu i typ parametru wolno dodać tylko wtedy, gdy rodzic już je ma.

Powiązane: [[plan-inpost-courier]] (przy tym module wypłynęło), [[build-fails-tell-rafal-immediately]].
