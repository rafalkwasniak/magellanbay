---
name: brand-logo-assets
description: "Gdzie leza pliki logo i favicony Kramio i jakie decyzje za nimi stoja (plytka pod ikonka, favicon wspolny ze storefrontami)"
metadata: 
  node_type: memory
  type: project
  originSessionId: 976d73d7-2539-4c3c-b780-08419bb12ebc
  modified: 2026-08-03T17:19:09.878Z
---

Logo Kramio wdrozone 2026-08-03 w miejsce placeholdera ◐ ze szkieletu.

**Pliki** (`public/images/`):
- `kramio-logo.png` — 1107×339, przezroczyste, pelny lockup z taglinem „twoj sklep w 15 minut". Uzywane w 4 layoutach centrali (`h-12` na landingu/gosciu/publicznym, `h-9` w panelu) i w mailach platformy przez `MailBranding::system()`.
- `kramio-icon.png` — 512×512, sama torba na kremowej (`#FAF5EF`) zaokraglonej plytce (radius 113). Zrodlo dla `public/favicon.ico` (16/32/48).
- `og-kramio-2026-08.jpg` — karta OG centrali, wskazywana z `config/seo.php`.

**Decyzje, ktore nie sa oczywiste z kodu:**
- **Plytka pod ikonka jest celowa.** Logo ma granatowy korpus torby, ktory na ciemnej karcie przegladarki znikal — zostawal sam pomaranczowy uchwyt i usmiech. Wypelniony jasny ksztalt czyta sie tak samo w obu trybach. Nie zdejmowac plytki „bo brzydzi przezroczystosc".
- **Torba zajmuje ~76% pola ikonki.** Pierwsza wersja miala 64% i przy 16 px robila sie plamka. Zmniejszanie = utrata czytelnosci.
- **Favicon jest WSPOLNY ze storefrontami** (`components/layouts/storefront.blade.php`). Sklepy nie wgrywaja wlasnych ikonek — logo sklepu ma dowolne proporcje i w 16 px jest nieczytelne, a torba pasuje do kazdej branzy. Miejsce na ewentualna ikonke per sklep jest oznaczone komentarzem w tym layoucie.
- `favicon.ico` byl przedtem **pustym plikiem 0 B** ze szkieletu Laravela — zadna strona nie miala ikonki.

**Gotcha:** zmiana OG wymaga NOWEJ NAZWY pliku (Facebook cache'uje tygodniami) — udokumentowane w komentarzu `config/seo.php`. Favicony przegladarka tez trzyma agresywnie: po podmianie Ctrl+Shift+R.

**Zostalo do rozwazenia:** `apple-touch-icon` — iOS przy dodaniu do ekranu glownego podklada czarne tlo pod przezroczyste rogi; ikonka z plytka nadaje sie do tego idealnie.

Powiazane: [[ui-design-direction]], [[storefront-theme-system]], [[plan-shop-og-image-redesign]], [[mail-footer-company-data]]
