---
name: vision-email-driven-orders
description: "Wizja działu zamówień: maszyna stanów per scenariusz + auto-przejścia + zarządzanie zamówieniem PROSTO Z MAILA. Duży temat, na świeżą sesję."
metadata: 
  node_type: memory
  type: project
  originSessionId: 63e02968-6e45-4ba1-9acd-f7867df6a22e
---

> **NIEAKTUALNE W CZĘŚCI (2026-07-14):** Rafał rozpisał ścieżki statusów sam i wyszły DUŻO prostsze, niż zakładała ta wizja — obowiązuje [[plan-order-statuses]]. Z tej notatki zostają aktualne tylko: **auto-przejścia** (webhook płatności → `Opłacone`, API kuriera) i **zarządzanie zamówieniem z maila** (wraz z uwagą o signed URL: GET → potwierdzenie, POST → wykonanie). Ścieżki statusów poniżej (m.in. `Wysłane`, `Odebrane`) są ODRZUCONE.

**Zarządzanie zamówieniem = „być albo nie być" tego systemu.** Rafał (2026-07-05) świadomie odłożył głęboką część na następną sesję — trzeba do niej usiąść na świeżo. Ta notatka to brief startowy.

**KOLEJNOŚĆ (potwierdzone 2026-07-10): to jest temat na SAM KONIEC**, po zbudowaniu frontu/sklepu. Powód: cały system stoi na statusach, które muszą być super przemyślane i DOMKNIĘTE — bez tego nie ma sensu budować kanału mailowego ani auto-przejść. Najpierw storefront (patrz [[plan-storefront-editorial-and-pages]]), ten dział ostatni.

**Klient docelowy determinuje projekt:** sprzedawcy to ludzie pracujący, którzy NIE siedzą w panelu. **Mail jest głównym interfejsem, nie panel.** Cel: bezobsługowy system — jak najwięcej dzieje się automatycznie, a to, co wymaga decyzji sprzedawcy, załatwia się jednym kliknięciem **bezpośrednio z maila** (bez logowania do panelu). W mailu sprzedawca ma mieć „serio wszystko".

**Ścieżka happy-path (przykład, do rozpisania na warianty):**
1. Złożenie → `Nowe` (albo `Oczekuje na płatność`, jeśli włączona przedpłata/płatność online).
2. Płatność → `Opłacone` — **automatycznie z integracji płatności** (webhook providera).
3. Sprzedawca klika „Przygotowuję" → `W realizacji`.
4. Wysyłka → `Wysłane` — tu wchodzą **kody nadania / etykiety InPost itp.**
5. Kupujący odbiera → `Odebrane` (dziś mamy `Zrealizowane`/Completed — do rozstrzygnięcia, czy zmienić nazwę / rozdzielić na wysyłka→Odebrane vs odbiór→Odebrane).

**Kluczowe wymagania do zaprojektowania następnym razem:**
- **Ścieżki statusów PER SCENARIUSZ.** Konfiguracja sklepu (płatność online tak/nie, metoda dostawy: wysyłka vs odbiór osobisty, za pobraniem itd.) ma wyznaczać różne maszyny stanów. Przejrzeć WIELE scenariuszy i dla każdego zaprojektować własną ścieżkę.
- **Auto-przejścia** — część statusów ustawia się sama (płatność→Opłacone; potwierdzenie doręczenia→Odebrane z API kuriera). Reszta = akcja sprzedawcy.
- **Akcje z maila** — maile do sprzedawcy z przyciskami zmieniającymi status wprost: mail „nowe zamówienie" → akcja; mail „opłacone" → link „nadaj wysyłkę", który **generuje etykietę** i przechodzi w `Wysłane`. Etykieta (PDF) mogłaby przychodzić sprzedawcy na maila.
  - **UWAGA bezpieczeństwo/UX:** linki z maila = signed URL bez logowania, ale GET nie może zmieniać stanu (skanery poczty typu Outlook Safe Links klikają linki prefetchem → przypadkowe przejścia). Wzorzec: GET → strona potwierdzenia, POST → wykonanie; token jednorazowy / czas ważności. Rozstrzygnąć na starcie.

**Integracje docelowe (płatne, per pakiet — [[plan-packages]]):**
- Płatności (provider TBD) — webhook → auto `Opłacone`.
- Wysyłka: **InPost i/lub Furgonetka**. Furgonetka = broker wielu kurierów ([[shipping-aggregator-idea]]). Oba mają kompleksową integrację z **generowaniem etykiet** — etykieta na maila do sprzedawcy.

**Why:** to najważniejszy i najbardziej złożony dział; źle zaprojektowana maszyna stanów + kanał mailowy zablokują cały produkt. Wymaga świeżej głowy i rozrysowania scenariuszy przed kodem.

**How to apply:** następna sesja — zacząć od wypisania scenariuszy (kombinacje płatność × dostawa), potem per scenariusz ścieżka statusów, potem które przejścia auto vs ręczne, potem kanał mailowy z akcjami. Fundament już jest: ręczna zmiana statusu + oś czasu + `Order::changeStatus()` (jedyny mutator) — patrz [[next-orders-panel-tab]]. Powiązane: [[email-outbox-cron-pattern]], [[per-shop-email-identity-branding]], [[stock-availability-verification]] (atomowe zdjęcie ze stanu przy składaniu), [[bank-transfer-payment-method]].
