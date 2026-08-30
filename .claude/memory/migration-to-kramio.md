---
name: migration-to-kramio
description: Serwis przeniesiony z shop.kwasniak.org do docelowego kramio.pl (2026-06-28). Stary katalog USUNIĘTY 13.08.2026 — został tylko wpis domeny w panelu hostingu.
metadata: 
  node_type: memory
  type: project
  originSessionId: 1e0c21a1-c4b2-4e81-b4e1-b1f2b31e1467
  modified: 2026-08-13T04:24:12.960Z
---

2026-06-28 przenieśliśmy serwis z tymczasowego `shop.kwasniak.org` do docelowej domeny **`kramio.pl`** (oba katalogi na tym samym hostingu: `/home/host473413/domains/`).

- **Kod:** skopiowany `cp -a` (z `.git`, `.env`, `vendor`, `node_modules`, `public/build`) do `/home/host473413/domains/kramio.pl/`. Historia git nietknięta, repo wspólne (`origin` = GitHub `rafalkwasniak/shop`).
- **Baza BEZ ZMIAN:** ta sama MySQL (wtedy `host473413_shop`; 12.08.2026 przeniesiona zrzutem SQL do `host473413_kramio`) — oba katalogi gadały do tej samej bazy. SMTP też ten sam (`MAIL_FROM=rafal@kwasniak.org`).
- **`.env`:** `APP_URL=https://kramio.pl`, `APP_DOMAIN=kramio.pl` (reszta jak była).
- **Stary katalog `…/domains/shop.kwasniak.org` USUNIĘTY 13.08.2026** (155 MB). Powód: po przeniesieniu bazy do `host473413_kramio` jego `.env` (user `host473413_shop`) przestał się łączyć, a miał `SESSION_DRIVER=database`, `APP_NAME=Kramio` i **ten sam webhook Discorda** — każde wejście bota na `www.shop.kwasniak.org` wysyłało alert wyglądający na awarię Kramio (`Access denied for user 'host473413_shop'`). Przed kasowaniem sprawdzone: `git status` pusty, HEAD `9691cfb` na `origin/main` (247 commitów za produkcją), `storage/app` = same placeholdery `.gitignore`, brak zrzutów SQL, żaden cron tam nie celował.
- **ZOSTAŁO (Rafał):** usunąć wpis domeny `shop.kwasniak.org` w panelu hostingu — dziś adres zwraca 404 od Apache'a (cicho, bez Laravela).
- **Nauka:** kopia zapasowa aplikacji, która **nadal jest serwowana** i **ma produkcyjny webhook alertów**, to nie backup tylko druga produkcja. Odcinając kopię, najpierw zabij jej kanał alertów.
- **Document root:** domena `kramio.pl` musi w panelu hostingu wskazywać na `…/domains/kramio.pl/public` (jak wcześniej dla shop) — to działka Rafała; hosting domyślnie daje `public_html` (zignorowany w `.gitignore` razem z `private_html`, `public_ftp`).
- **Cron:** wpis schedulera przestawiony na `…/kramio.pl/artisan schedule:run` (reszta crona nietknięta).
- **Pamięć Claude:** skopiowana ze starego klucza projektu do `-home-host473413-domains-kramio-pl`. Stąd ciągłość mimo zmiany katalogu (Claude Code identyfikuje projekt po ścieżce).
- **TODO infra (Rafał):** wildcard DNS + wildcard SSL dla `*.kramio.pl` pod storefronty (centrala działa bez tego).

Powiązane: [[multitenant-subdomain-architecture]], [[shared-hosting-constraints]], [[handoff-activation-flow]].
