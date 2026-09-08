---
name: magellan-cron-enabled
description: "Od 08.09.2026 Magellan MA wpis w cronie — CLAUDE.md wciąż twierdzi, że nie ma."
metadata: 
  node_type: memory
  type: project
  originSessionId: 2b1f7f3a-320a-4dca-b610-ef2de33c11b4
  modified: 2026-09-08T13:28:00.556Z
---

**08.09.2026 wpiąłem `schedule:run` Magellana do crontaba** na prośbę Rafała — maile z outboksu stały niewysłane, a klient testował sklep na żywo.

```
* * * * * /opt/alt/php85/usr/bin/php /home/host473413/domains/magellan.kwasniak.org/artisan schedule:run >> /dev/null 2>&1
```

Kopia crontaba sprzed zmiany: `~/crontab-backups/crontab-20260908-151756.bak` (16 linii). Odtworzenie: `crontab <plik>`.

**Why:** `CLAUDE.md` sekcja 2 nadal głosi „Brak wpisu w cronie — najważniejszy [bezpiecznik]. Bez `schedule:run` nie wyjdzie ani jeden mail, nie ruszy kolejka, nie zapyta InPostu." To jest już **nieprawda we wszystkich trzech członach**, a plik czytam na starcie każdej sesji. Ten sam wzorzec co [[gotcha-dedicated-mode-platform-leftovers]] — nic się nie wywraca, tylko dokumentacja kłamie.

**How to apply:** Zanim oprzesz cokolwiek na „stąd nic nie wyjdzie" — sprawdź `crontab -l`, nie `CLAUDE.md`. Pozostałe bezpieczniki trzymają: klucze Paynow / Fakturowni / InPostu puste, `BACKUP_ENABLED=false`, osobny webhook Discorda. **Poprawka akapitu w `CLAUDE.md` jest wciąż niezrobiona** — Rafał odłożył na 09.09, razem ze zmianą adresu logowania.

Powiązane, do zrobienia 09.09 u klienta: powiadomienia o zamówieniach idą na `$shop->owner->email` (konto logowania), **nie** na `contact_email` sklepu. Dziś to `magellan@kwasniak.org`, skrzynka **nie istnieje** → SMTP `550 No such recipient here`. Zmiana maila logowania naprawi to jednym ruchem. Odrzucony na razie wariant: `MAIL_DEBUG_BCC` + `bcc:` w `Envelope` w `OutboxMailable` (Laravel **nie ma** `alwaysBcc`).
