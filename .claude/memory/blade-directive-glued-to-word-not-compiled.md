---
name: blade-directive-glued-to-word-not-compiled
description: "@if przyklejone do litery/cyfry NIE kompiluje się — @endif tak, i widok pada na 'unexpected token endif'"
metadata: 
  node_type: memory
  type: reference
  originSessionId: bf48269a-c52a-49c6-9a8e-6a7c2b21dfa6
  modified: 2026-09-05T19:55:06.689Z
---

Dyrektywa Blade poprzedzona **znakiem słownym bez spacji** (`aktywacyjny@if (...)`, `§1@if (...)`) **nie zostaje skompilowana** — zostaje w wyjściu jako zwykły tekst. Domykający `@endif` (poprzedzony `)`, `>` itp.) kompiluje się normalnie, więc w pliku wynikowym ląduje osierocony `<?php endif; ?>` i **cały widok** pada:

```
syntax error, unexpected token "endif", expecting end of file
```

Kompilator Blade zaczyna wzorzec dyrektywy od `\B` (nie-granica wyrazu). Między literą a `@` jest granica → dopasowanie przepada. Bez ostrzeżenia, bez wyjątku przy kompilacji.

**Objaw myli:** stack trace wskazuje skompilowany plik w `storage/framework/views/` i linię `endif` na końcu — daleko od faktycznej przyczyny. Szukaj `@if` sklejonego z tekstem, nie niezamkniętego bloku.

**Jak pisać:** fragment warunkowy licz **poza zdaniem** (zmienna w `@php`) albo rozbij na dwa pełne warianty zdania:

```blade
@if (filled($email))
    <p>Wysłaliśmy link aktywacyjny na <strong>{{ $email }}</strong>.</p>
@else
    <p>Wysłaliśmy link aktywacyjny.</p>
@endif
```

**Grep kontrolny** (przed commitem widoków ze zdaniami warunkowymi):

```bash
grep -rnPo '\w@(if|endif|else|elseif|foreach|unless|isset|empty|php|error|can|auth|guest)\b' --include=*.blade.php resources
```

**MOCNIEJSZE — audyt WSZYSTKICH widoków naraz** (łapie każdy błąd składni, nie tylko sklejone dyrektywy; sprawdzone 24.08 na 151 plikach):

```bash
php artisan view:clear && php artisan view:cache
for f in storage/framework/views/*.php; do php -l "$f" >/dev/null || echo "BŁĄD: $f"; done
```

**Wariant sklejenia od DRUGIEJ strony** (05.09, Magellan): `…obowiązkowa@endif` w formularzu produktu. Tu nieskompilowany zostaje `@endif` (poprzedzony literą), a otwierający `@if` kompiluje się normalnie → PHP widzi `if (…):` bez zamknięcia i pada na `unexpected token "endforeach", expecting "elseif" or "else" or "endif"`. **Reguła jest symetryczna: żadna dyrektywa nie może dotykać litery ani cyfry — ani z lewej, ani z prawej.** Wstawka warunkowa wewnątrz zdania to zawsze `{{ $warunek ? '…' : '' }}`, nigdy `@if`.

**Historia:** trafione 3× — przy kreatorze regulaminu sprzedawcy (komentarz w `resources/views/seller/legal/templates/regulamin.blade.php`) i 24.08 na produkcji w `storefront/auth/registered.blade.php` (ekran „Sprawdź skrzynkę" po rejestracji klienta wywracał się 500 dla KAŻDEGO rejestrującego się; konto i mail aktywacyjny szły poprawnie, padał tylko ekran potwierdzenia). Testy tego nie łapały, bo sprawdzały `assertRedirect` na adres, nigdy nie renderując widoku — **na ekran po redirectcie potrzebny jest osobny `get()` z `assertOk()`**.

Pokrewne: [[blade-php-block-breaks-inline-php]], [[blade-never-name-view-variable-errors]].
