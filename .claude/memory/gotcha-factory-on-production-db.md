---
name: gotcha-factory-on-production-db
description: "INCYDENT 16.08: User::factory() na produkcji zostawił konto sprzedawcy z hasłem `password`. Od 24.08 garda blokuje (DB_SECURITY Warstwa 7)"
metadata: 
  node_type: memory
  type: reference
  originSessionId: bf48269a-c52a-49c6-9a8e-6a7c2b21dfa6
  modified: 2026-08-24T09:23:32.034Z
---

**INCYDENT 2026-08-16, wykryty 24.08.** Ktoś wykonał `User::factory()->create()` na produkcyjnym połączeniu (najpewniej tinker, „tylko na chwilę"). Zostało konto: `skubiak@example.net`, „Klaudia Lewandowska”, rola **seller**, aktywne, z domyślnym hasłem fabryki **`password`**. Przeżyło 8 dni — wykryte dopiero, gdy konsola admina pokazała je jako „sprzedawca bez sklepu”. Usunięte 24.08; audyt całej bazy nie znalazł drugiego takiego.

**ZABEZPIECZONE od 24.08 (commit `ba2896a`)** — `AppServiceProvider::prohibitFactoriesInProduction()`, DB_SECURITY.md Warstwa 7. Każde `Model::factory()` na `APP_ENV=production` rzuca wyjątkiem. `DatabaseSeeder` opróżniony (trzymał `test@example.com` z hasłem `password`).

**METODA ŚLEDCZA — warta zapamiętania, bo pierwsza hipoteza była groźniejsza niż prawda:**

Podejrzenie „testy piszą do produkcyjnej bazy” obalone w jednym kroku: `phpunit.xml` wymusza sqlite `:memory:`. Seeder też odpadł — tworzy sztywny adres, a ten był losowy. Rozstrzygnął dopiero **`Hash::check('password', $user->password)`** — to sygnatura `UserFactory`. Przy podejrzanym koncie sprawdzaj hasło, nie tylko adres.

Mylący trop: w produkcyjnym `storage/logs/` z tamtego dnia były wpisy `testing.INFO`. To normalne — `phpunit.xml` nie nadpisuje kanału logów, więc suita pisze do produkcyjnego pliku logu. **Wpisy `testing.INFO` w logu NIE znaczą, że testy dotknęły produkcyjnej bazy.**

Pokrewne: [[defer-destructive-db-guard]], [[tests-never-touch-production-files]], [[tests-never-hit-real-apis]].
