---
name: tests-never-touch-production-files
description: Suita skasowała realny awatar admina (users/1) — garda isolateDisks() w TestCase izoluje dyski public i local; nigdy jej nie zdejmować.
metadata: 
  node_type: memory
  type: project
  originSessionId: 3624fdda-9239-4291-8d29-363ee406554a
  modified: 2026-08-05T18:23:13.706Z
---

**INCYDENT 2026-08-04:** testy usuwania sklepu (`Seller/ShopDeletionTest`,
`Administrator/ShopDeletionTest`) nie miały `Storage::fake('public')`.
`ShopEraser` kasuje katalogi po ID (`users/{id}`, `products/{id}`), a baza testowa
sqlite `:memory:` rozdaje ID od 1 — suita skasowała **realny**
`storage/app/public/users/1`, czyli awatar admina. Zdjęcia klientów ocalały
tylko przypadkiem: produkcyjne ID produktów były już od 27 w górę.

**Why:** testy chodzą w katalogu produkcyjnym (shared hosting, jedna kopia kodu),
więc dysk `public` to te same pliki, które widzą klienci. Bezpieczeństwo nie może
zależeć od tego, czy autor testu pamiętał o fake'u — dokładnie ta sama lekcja co
przy [[tests-never-hit-real-apis]].

**How to apply:** `Tests\TestCase::isolateDisks()` (2026-08-05) przestawia dyski
`public` i `local` na `storage/framework/testing/disks/*` przy każdym `setUp()`
i **wywala suitę**, jeśli korzeń mimo to wskazuje poza piaskownicę. Pilnuje tego
`tests/Feature/StorageIsolationTest.php`, opis w `DB_SECURITY.md` → Warstwa 6.
**Nigdy nie zdejmować.**

**GOTCHA przy fake'owaniu dysków:** `Storage::fake()` bierze z oryginalnej
konfiguracji **wyłącznie `throw`** (`Storage::buildDiskConfiguration()`) — gubi
m.in. `url`, przez co `Storage::url()` zwraca ścieżkę względną zamiast adresu
z `APP_URL`. Dlatego `isolateDisks()` przekazuje konfigurację dysku jawnie
(bez `root`). Bez tego padał `MailBrandingTest` — logo w mailu przestawało być
absolutnym URL-em.

**Zostało jako świadomy dług** (Rafał wybrał 2026-08-05 tylko gardę):
`ShopEraser` nadal kasuje całe katalogi po ID zamiast konkretnych ścieżek z bazy;
kasowanie jest bezpowrotne (brak kosza); patrz [[open-no-file-backups]].
