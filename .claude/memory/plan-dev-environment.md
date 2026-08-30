---
name: plan-dev-environment
description: "KIERUNEK (05.08) — dev.kramio.pl jako środowisko do zabawy, kramio.pl zostaje produkcją; lista pułapek do ogarnięcia przed startem."
metadata: 
  node_type: memory
  type: project
  originSessionId: 3624fdda-9239-4291-8d29-363ee406554a
  modified: 2026-08-11T19:30:51.082Z
---

**AKTUALIZACJA 2026-08-11:** Rafał ma w planie **przeprowadzkę na INNY SERWER**,
na którym staną obok siebie dev i produkcja. To zmienia kształt sprawy: nie
chodzi już o drugi katalog na tym samym koncie hostingowym, tylko o nowe
środowisko od zera. Termin nieustalony.

**Konsekwencja dla backupu:** Etap 2 (kopia poza serwerem) świadomie ODŁOŻONY —
budowanie go pod obecny hosting znaczyłoby robotę do wyrzucenia. Patrz
[[open-no-file-backups]].

**Konsekwencja dla pułapek niżej:** część z nich może zniknąć razem z hostingiem
(limit procesów, ograniczenia współdzielonego konta) — przy planowaniu
przeprowadzki przejrzeć tę listę od nowa, a nie przepisywać jej w ciemno.

---

**Rafał, 2026-08-05:** „w najbliższym czasie pomyślę o zrobieniu dev.kramio.pl
i tam będziemy się bawić, a na kramio.pl będzie już produkcyjna wersja".
Decyzja jeszcze niepodjęta — to kierunek, nie zlecenie.

**Why:** dziś testy i eksperymenty chodzą W KATALOGU PRODUKCYJNYM. Jedyne, co je
dzieli od danych klientów, to gardy w kodzie ([[tests-never-touch-production-files]],
guard sqlite-only). Osobne środowisko zamienia to w granicę fizyczną.

**How to apply — pułapki do przemyślenia PRZED postawieniem:**

- **Limit procesów zostaje.** Dev siedzi na tym samym koncie hostingowym, więc
  pełna suita nadal może zdusić crony produkcji — patrz [[open-hosting-process-limit]].
  Dev tego NIE rozwiązuje.
- **Wildcard drugiego poziomu.** Storefronty to `{shop}.kramio.pl`, więc dev
  potrzebuje `*.dev.kramio.pl` w DNS i SSL. Bez tego multi-tenant nie ruszy.
- **Integracje bez sandboxa.** `Http::preventStrayRequests()` działa tylko
  w testach; klikanie po dev leci na żywo. Fakturownia nie ma sandboxa, DeepSeek
  to płatne tokeny, Paynow to realne płatności — klucze na dev puste/testowe.
- **Maile:** `MAIL_MAILER=log`, inaczej newsletter pójdzie do realnych ludzi.
- **Dane:** nie kopiować produkcyjnej bazy 1:1 (dane osobowe klientów w słabiej
  chronionym miejscu) — seed albo anonimizacja.
- **Google:** `noindex` + hasło na katalogu, inaczej duplikat zaszkodzi SEO.
- **`prohibitDestructiveCommands` jest przypięty do `APP_ENV=production`** — na dev
  będzie WYŁĄCZONY, więc produkcyjną bazę chroni wtedy tylko poprawny `.env` dev.
  Zweryfikować przed pierwszą migracją.

Gardy w `TestCase` zostają niezależnie od dev — kosztują zero.
