---
name: plan-seller-service-notices
description: "WDROŻONE 24.08: wiadomość serwisowa do sprzedawcy z karty sklepu — świadomie POZA zgodą marketingową"
metadata: 
  node_type: memory
  type: project
  originSessionId: bf48269a-c52a-49c6-9a8e-6a7c2b21dfa6
  modified: 2026-08-24T09:03:46.175Z
---

**WDROŻONE 2026-08-24, commit `a1ad0e8`.** Kafelek „Napisz do sprzedawcy" w prawej kolumnie karty sklepu w konsoli admina (`/administrator/sklepy/{id}`). Temat + treść w `<x-rich-editor>`, wysyłka przez outbox, priorytet `Mid`.

**DWIE ŚCIEŻKI, KTÓRYCH NIE WOLNO ZLAĆ W JEDNĄ:**

| | dział „Wiadomości" (`PlatformMailService`) | karta sklepu (`SellerNoticeService`) |
|---|---|---|
| do czego | nowości, oferty, kody | awaria, sprawa konta, odpowiedź na zgłoszenie |
| zgoda marketingowa | **bramkuje** (`User::activeMarketingConsent()`) | **NIE sprawdzana — celowo** |
| stopka wypisu | obowiązkowa | brak |
| odbiorcy | wielu, zaznaczani | jeden właściciel sklepu |

**Brak bramki zgody to sens modułu, nie luka.** Wiadomość niezbędna do wykonania umowy należy się każdemu sprzedawcy niezależnie od zgody na oferty — awarii nie da się „nie zaprenumerować". Dokładanie tu `activeMarketingConsent()` przez analogię do drugiego modułu = zepsucie. Zapisane w docblocku klasy i pilnowane testem `test_admin_sends_notice_to_seller_without_marketing_consent`.

Granica jest w TREŚCI, nie w kodzie: tędy nie wolno wysyłać ofert. Podpowiedź pod polem treści mówi to administratorowi wprost — bez tego moduł po cichu stałby się obejściem zgód, a to problem prawny, nie techniczny.

**Powód powstania:** 24.08 trzeba było napisać do sprzedawcy o usterce ([[blade-directive-glued-to-word-not-compiled]]), a ten nie miał zgody marketingowej — z panelu nie dało się do niego napisać w ogóle. Poszło skryptem.

Szczegóły: nadawca = Kramio (`shop_id = null`), reply-to = adres piszącego administratora, powitanie w wołaczu.

**Nie ma (świadomie, do decyzji Rafała):** historii wysłanych przy sprzedawcy, podglądu do siebie, wysyłki do kilku sprzedawców naraz.

Pokrewne: [[plan-platform-mailing]], [[seller-marketing-consent]], [[email-outbox-cron-pattern]].
