---
name: app-timezone-warsaw
description: "2026-07-14: config('app.timezone') = Europe/Warsaw (było UTC). PHP i MySQL na hostingu zawsze były na Warsaw — odstawał tylko Laravel, przez co daty były młodsze o 2h."
metadata: 
  node_type: memory
  type: project
  originSessionId: bd2d4bfb-a65d-46ef-b991-378de6a45f4a
---

**`config('app.timezone')` = `Europe/Warsaw`** (zmienione 2026-07-14, wcześniej domyślne `UTC`).

**Co było nie tak:** trzy warstwy się rozjeżdżały — PHP (`php.ini`) i MySQL (`NOW()`, session tz = SYSTEM) chodziły na Europe/Warsaw, a Laravel na UTC. Ponieważ to Laravel stempluje `created_at` przez `now()`, każda data w bazie niosła godzinę UTC, którą potem pokazywaliśmy jako lokalną → wszystko młodsze o 2h (latem) / 1h (zimą). Wyszło na mailu o zmianie statusu, ale dotyczyło całego serwisu (oś czasu zamówienia, „Złożone", listy).

**Skutek uboczny, o którym warto pamiętać:** przed poprawką `now()` Laravela i `NOW()` MySQL-a różniły się o 2h. Każde miejsce porównujące jedno z drugim było cicho przekrzywione — m.in. okno „najniższej ceny z 30 dni" w Omnibusie ([[omnibus-lowest-price-30d]]).

**Historia:** przesunięta jednorazową migracją `2026_07_14_130000_shift_existing_timestamps_to_local_time` o +2h (15 tabel, kolumny wypisane z nazwiska). Legalne, bo cała baza zaczyna się 25.06.2026 → w całości czas letni, żaden wiersz nie przekracza granicy zmiany czasu. Migracja ma bramkę `DB::getDriverName() !== 'mysql'` — testy chodzą na SQLite (`phpunit.xml`), gdzie `DATE_ADD` nie istnieje.

**Nie ma klucza `APP_TIMEZONE` w `.env`** — wartość jest wpisana wprost w `config/app.php` (plik i tak trzymał ją na sztywno, a platforma jest z decyzji jednokrajowa, patrz [[naming-and-locale-convention]]). Parytet `.env` ↔ `.env.example` nietknięty.

**Why:** klasyczna pułapka Laravela na polskim hostingu — domyślne UTC wygląda niewinnie, dopóki ktoś nie porówna godziny w mailu z zegarkiem. Warto o tym pamiętać przy każdym nowym projekcie na tym hostingu.

**How to apply:** przy DST (koniec października) godziny 02:00–03:00 stają się dwuznaczne — dla stempli zamówień to kosmetyka, nie problem poprawnościowy. Gdyby kiedyś doszła wielokrajowość, wtedy dopiero rozważyć składowanie w UTC + konwersję na wyświetlaniu.
