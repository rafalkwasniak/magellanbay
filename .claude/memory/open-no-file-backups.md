---
name: open-no-file-backups
description: "BACKUP WDROŻONY 2026-08-12: Etap 1 (nocny snapshot) + Etap 3 (strażnik, ekran, przećwiczone odtworzenie). Etap 2 (kopia poza serwerem) czeka na przeprowadzkę."
metadata: 
  node_type: memory
  type: project
  originSessionId: 9b42a7e8-65de-49c5-a571-38f05836de8b
  modified: 2026-08-13T04:29:47.687Z
---

**WDROŻONE 2026-08-12.** Nocna kopia działa, strażnik pilnuje, odtworzenie PRZEĆWICZONE na żywych danych.

**POTWIERDZONE 2026-08-13:** pierwszy w pełni autonomiczny przebieg (03:00, bez człowieka) wypadł czysto — i to zaraz po zmianie nazwy bazy. Zajrzenie do `kramio-2026-08-13_030013.tar.gz`: `baza.sql` z nagłówkiem `Database: host473413_kramio` (czyli zrzut poszedł z NOWEJ bazy, bez ręcznej poprawki), do tego `.env` i pliki `public/` (`users/1/*.jpg`, `shops/7/logo.webp`), 2,5 MB — tyle samo co ręczne przebiegi z 12.08, więc bez cichego obcięcia. Wniosek: kopia czyta konfigurację z `.env`, więc przeżyła zmianę nazwy bazy sama.

## Co działa

- **`backup:run`** (`dailyAt` z configu, domyślnie 03:00): `mysqldump --single-transaction` + `tar` zdjęć i `.env` w JEDNYM archiwum `kramio-{data}_{godzina}.tar.gz`. Cel: `/home/host473413/backups/kramio/`, katalog 700, pliki 600, retencja 14 dni. Pierwszy realny przebieg: 2,4 MB w 0,26 s.
- **`backup:check`** (09:00): alarm na Discorda, gdy nie ma UDANEJ kopii od 36 h. Pilnuje ŚLADU, nie przebiegu — awaria, przez którą `backup:run` w ogóle się nie uruchamia, nie zgłosi się sama.
- **Ekran Ustawień** czyta `platform_settings.last_backup_at` i świeci zielono TYLKO przy świeżej kopii. Koniec zaszytego `'ok' => false`.
- **Sterowanie z `.env`**: `BACKUP_ENABLED` (false = brak kopii i cichy strażnik; wpis harmonogramu w ogóle się nie rejestruje), `BACKUP_PATH` (pusty przy włączonych = głośny błąd), `BACKUP_RETENTION_DAYS`, `BACKUP_DAILY_AT`. Próg 36 h w `config/backup.php`.

Dwie reguły, których nie łamać: **data zapisywana dopiero PO spakowaniu** (jest dowodem, nie deklaracją — nieudany przebieg jej nie rusza) oraz **hasło do bazy wyłącznie przez `--defaults-extra-file`** (argumenty procesu widzi w `ps` każde konto serwera). Oba pokryte testami.

## Odtworzenie — przećwiczone 12.08

Baza ćwiczebna `host473413_kramio_backup` (użytkownik o tej samej nazwie, hasło jak główne — zakłada Rafał w panelu; przy ćwiczeniu 12.08 nazywała się jeszcze `host473413_shop_backup`). Wynik: **39 tabel = 39**, sumy kontrolne `orders`, `order_items`, `products`, `users`, `shops` **identyczne**, 22 zdjęcia bit w bit, `.env` identyczny. Po weryfikacji tabele skasowane, baza czeka pusta na następne ćwiczenie.

**GOTCHY z ćwiczenia:**
- Konto aplikacji ma `GRANT ALL` tylko na własną bazę → **nie założy bazy ćwiczebnej** (`ERROR 1044`); trzeba z panelu Hostido.
- Nowy użytkownik działa na `@localhost` (gniazdo). Połączenie przez `127.0.0.1` = `ERROR 1045`.
- Po odtworzeniu trzy tabele RÓŻNIĄ SIĘ i tak ma być: `sessions`, `cache_locks` (żyją cały czas) oraz `platform_settings` o jeden wiersz — to `last_backup_at` zapisany PO zrzucie. To nie usterka, tylko dowód, że kolejność jest właściwa.

## Etap 2 — wciąż otwarty

Kopia POZA serwerem, odłożona do przeprowadzki ([[plan-dev-environment]]). Dopóki jej nie ma, 1+3 chronią przed pomyłką i awarią aplikacji, **nie przed utratą konta** — wszystko leży na jednym dysku. Rekomendacja bez zmian: **Backblaze B2** (10 GB gratis, natywne API = dwa wywołania `Http::`), `gpg` przed wysyłką, region EU + umowa powierzenia. **Hasło szyfrowania w `.env` ORAZ offline — zgubione = backup wart zero.** Do sprawdzenia wtedy: na serwerze jest JetBackup (`/usr/local/jetapps`), ale z poziomu konta nie widać punktów przywracania — czy Hostido robi kopie konta, wie tylko panel.

## Próg skali

`tar` całości co noc jest właściwy do ~1 GB zdjęć (dziś 2,6 MB). Powyżej: pełny raz w tygodniu + dobowe dosypywanie różnicy.

## Zmiana rytmu (17.08.2026, commit `2a8f0d6`)

Kopia leci **dwa razy na dobę: 04:00 i 16:00** — okno utraty danych spada z ~24 h do ~12 h. Godziny nieprzypadkowe: o **06:10 chodzi `subscriptions:check`, o 06:20 `shops:purge`**, więc 06:00 odpadło.

**Retencja bez zmian, bo kasuje po WIEKU pliku, a nie po ich liczbie.** Druga kopia dziennie wygasa tak samo po 14 dniach — nie trzeba było niczego przeliczać.

**Świadomie NIE nadpisujemy kopii porannej popołudniową** (Rafał to rozważał). Dwa powody: nieudane nadpisanie skasowałoby jedyną dzisiejszą dobrą kopię, a szkoda zauważona po południu zabrałaby ze sobą czystą kopię sprzed niej. Cena ostrożności to ~35 MB — nie warto o to walczyć.

**Próg strażnika zszedł z 36 h na 24 h.** To nie kosmetyka: próg MUSI iść za częstotliwością, inaczej cicho przestaje pilnować. Przy dwóch kopiach dziennie stare 36 h przepuściłoby TRZY nieudane przebiegi. Pilnuje tego test.

`BACKUP_DAILY_AT` przyjmuje listę godzin po przecinku.

**Etap 2 (kopia poza serwerem) — Rafał odłożył 17.08 do czasu pierwszych klientów.** Świadoma decyzja, nie zaległość; dziś wszystko leży na jednym dysku.
