---
name: handoff-2026-07-31-analytics
description: "Handoff 31.07.2026 (późny wieczór): Google Analytics na centrali (commit a63c85c) + wspólny komponent pomiaru. Wyszło z tego, że NIE MA banera cookies → następny temat. Testy 1236."
metadata: 
  node_type: memory
  type: project
  originSessionId: 5c4f5743-5e78-4788-bcf6-2be5ad5638e4
  modified: 2026-07-31T20:03:08.064Z
---

Domknięcie wieczoru po [[handoff-2026-07-31-security]]. Trzeci przypadek tego samego wzorca w ciągu jednego dnia.

## Co zrobione (`a63c85c`)

**Google Analytics na stronach centrali.** Sklepy sprzedawców miały własny pomiar od dawna, sama `kramio.pl` nie — dokładnie ten sam rodzaj luki co z grafiką OG rano tego samego dnia.

- Identyfikator w `.env` (`GOOGLE_ANALYTICS_ID`, parytet `.env.example` zachowany) → `config/services.php` → `services.google.analytics_id`. **Nie na sztywno w kodzie**, żeby kopia serwisu postawiona lokalnie nie dosypywała ruchu do statystyk produkcji. Brak wpisu = pomiar wyłączony.
- **Wspólny komponent** `resources/views/components/google-analytics.blade.php` (+ wariant `-noscript` dla GTM w `<body>`) — używany i przez centralę (ID z `.env`), i przez storefronty (ID sprzedawcy z Integracji). Storefront ZREFAKTOROWANY na ten komponent; wcześniej miał własną kopię skryptu. Rozpoznaje `GTM-` vs `G-` sam.
- Pomiar obejmuje strony PUBLICZNE: landing, logowanie, rejestrację, regulamin, politykę prywatności. Zweryfikowane na produkcji — ID obecne na wszystkich pięciu.
- **Panel NIE jest mierzony i to decyzja, nie przeoczenie**: narzędzie pracy za logowaniem, a adresy jego podstron niosą numery zamówień i klientów sprzedawcy. Zapisane komentarzem w `layouts/panel.blade.php` ORAZ pilnowane testem `test_panel_does_not_load_analytics`.
- Polityka prywatności centrali już wcześniej deklarowała cele analityczne (wprost „np. Google Analytics"), więc wdrożenie jest zgodne z tym, co obiecujemy.

Testy **1230 → 1236**. Build CSS niepotrzebny.

## WZORZEC DNIA — do zapamiętania

**Trzy razy tego samego 31.07:** grafika OG, zabezpieczenia formularzy, statystyki. Za każdym razem funkcja istniała DLA SKLEPÓW, a nie dla centrali — bo praca nad storefrontem nie obejmuje stron platformy i nikt ich osobno nie sprawdza.

> **Przy każdej funkcji „platformowej" pytać OSOBNO o centralę:** landing, logowanie, rejestracja, dokumenty prawne. Audyt ursalogic próbkował storefront sprzedawcy i to ukształtowało nasze nawyki.

Drugi wniosek, mniejszy: **dwa z trzech tematów zgłosili ludzie z zewnątrz** (Rafał — grafika, jego znajomy — zabezpieczenia), nie my.

## Co z tego wynikło: NASTĘPNY TEMAT

Przy weryfikacji wyszło, że **banera zgody na cookies nie ma nigdzie** — ani na centrali, ani na storefrontach mierzących ruch od dawna. Rafał uznał to za pilniejsze niż wysyłki i panel admina, ale zostawił na następny dzień.

→ **[[plan-cookie-consent]]** — analiza gotowa, 8 pytań otwartych do rozstrzygnięcia przed kodem. Kolejność w [[priorities-launch-first]] zaktualizowana.

## Sprawdzone przy okazji

- Na produkcji został **jeden sklep: `lemoniady`** (`ilikemybike` zniknął przy czystce 31.07). Nie ma włączonego GA, więc brak skryptu na jego storefroncie jest poprawny, nie regresją refaktoru.
- Fonty są LOKALNE (`public/fonts/`), zero ładowania z serwerów Google — przy temacie cookies to oszczędza pracy.
