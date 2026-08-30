---
name: defer-destructive-db-guard
description: "WDROŻONE 2026-07-06: DB::prohibitDestructiveCommands + guard testów blokują destrukcyjne komendy DB na produkcji"
metadata: 
  node_type: memory
  type: project
  originSessionId: 95e26b90-4a53-4f6a-ad4d-f806f2cbc32c
---

WDROŻONE 2026-07-06 (wcześniej świadomie odroczone). Decyzja o odroczeniu została odwrócona po realnym incydencie „baza wystrzeliła w kosmos" — Rafał dodał przenośny dokument `DB_SECURITY.md` w katalogu głównym (5 warstw ochrony) i poprosił o wdrożenie.

**Co jest w kodzie (Warstwa 1 + 3 z DB_SECURITY.md):**
- `AppServiceProvider::boot()`: `DB::prohibitDestructiveCommands($this->app->environment('production'))` — blokuje `migrate:fresh/refresh/reset/rollback` i `db:wipe` (nie do obejścia `--force`). Zweryfikowane: obie komendy → exit 1 „prohibited"; `migrate --force` (addytywne) dalej działa.
- `tests/TestCase.php::setUp()`: guard sqlite-only — jeśli połączenie ≠ `sqlite::memory:`, suita pada (`$this->fail`) zamiast tknąć produkcję. 363 testy zielone.
- Warstwa 2 (`APP_ENV=production`+`APP_DEBUG=false` w `.env` i `.env.example`) była już wcześniej.

**Konsekwencja dla workflow:** `migrate:fresh`/`db:wipe` na tym katalogu są teraz MARTWE (środowisko = production, brak osobnego local). Przebudowa schematu od zera = ręcznie, świadomie, w narzędziu bazy (phpMyAdmin) lub osobnym uprzywilejowanym kontem. To celowe — pracujemy bezpośrednio na produkcyjnej `host473413_kramio` (do 12.08.2026 nazywała się `host473413_shop`).

**Poza aplikacją, działka Rafała (Warstwa 4+5 z DB_SECURITY.md), NIEZROBIONE:** produkcyjny user DB bez `DROP`/`ALTER` (migracje schematu osobnym kontem); automatyczny dzienny backup z przetestowanym restore. Przypominać.

Related: [[shared-hosting-constraints]].
