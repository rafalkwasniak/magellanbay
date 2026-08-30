---
name: mail-footer-company-data
description: "WDROŻONE 2026-07-15 (820c92a): stopka maila = dane firmowe NADAWCY (sklep z shops, platforma z config/company.php); składa się z tego co jest, chowa gdy nic; logo 64px."
metadata: 
  node_type: memory
  type: project
  originSessionId: d597683e-ad06-4dba-a499-2ecb401092fa
---

Wdrożone 2026-07-15, commit `820c92a`, 558 testów. Zastąpiło notatkę „następna sesja".

**Reguła:** stopka niesie dane firmowe NADAWCY i **składa się z tego, co jest** — chowa się w całości, gdy nie ma nic. Dane firmowe sklepu są opcjonalne (`ShopProfileRequest` wymaga tylko `contact_email` + `contact_phone`), więc świeży sklep pokazuje sam kontakt, bez pustej ramki i bez sierocego „NIP".

**Skąd dane:** sklep → `shops` (`company_name`, `nip`, `Shop::addressLine()`, kontakt). Platforma (aktywacja, reset hasła) → `config/company.php` = dane Kramio, czyli **Red Paprika Rafał Kwaśniak** (ten sam podmiot co firma sklepu `ilikemybike`). Stała biznesowa, więc config, nie `.env`.

**Stopka = firma, adres, kontakt. BEZ NIP-u** (decyzja Rafała, `45c2d3a`) — klient nie ma z nim co zrobić, a mail mówi teraz tyle samo, co stopka storefrontu. Do tego `company_nip` było JUŻ zajęte na `Order::company_nip` = NIP KUPUJĄCEGO, który `OrderMailer` pokazuje w „Danych do faktury" (tam ma sens — klient weryfikuje swoje dane). Dwa testy trzymają to w równowadze: `MailFooterTest::test_footer_never_shows_the_senders_nip` i asercja `NIP: …` w `OrderPlacementTest`. **Nie ruszaj jednego bez drugiego.** Gdy dojdą faktury, NIP nadawcy wróci razem z nimi.

**Świadome decyzje:**
- Dane Kramio NIE są fallbackiem dla sklepu bez danych firmowych — stopka maila „od sklepu" z adresem platformy kłamałaby co do nadawcy. Przypięte testem.
- Formatowania NIP nie ma i nie było potrzebne (`NipService`: `normalize()`/`isValid()`). Oba zapisy (z myślnikami i bez) są standardem.
- Wartości w `config/company.php` trzymamy gotowe do wyświetlenia (adres jedną linią, telefon ze spacjami) — to plik pisany ręcznie, nie wejście z formularza.
- E-mail i telefon są linkami mimo stonowanego wyglądu: klienty pocztowe autolinkują gołe adresy własnym niebieskim, więc lepiej to kontrolować.
- Logo 64px z `width:auto` (**konieczne**: logo ma zmienną proporcję, samo `height` rozciąga je w Outlooku) + `height` jako atrybut ORAZ w `style`. Warianty bez logo → 28px, żeby nagłówek nie skakał między sklepami.
- Dawna formułka „wysłana automatycznie / możesz zignorować" NIE wraca — patrz [[open-mail-footer-contradiction]]. Pilnuje tego test.

**Gotcha na przyszłość:** `Shop::addressLine()` to pierwsze i jedyne miejsce składania adresu z kolumn. Stopka storefrontu ([[storefront-theme-system]], `layouts/storefront.blade.php` ~l. 191) składa go WŁASNYM kodem inline i w innym kształcie (3 linie zamiast jednej) — jeśli ruszasz format adresu, sprawdź oba. Storefront nie pokazuje NIP-u, mail pokazuje.

**Jak weryfikować maile:** testy asertują `intro_lines`/`outro_lines`, nie skorupę — renderuj przez `(new OutboxMailable($msg))->render()` i czytaj HTML. NIGDY tinkerem na produkcji: `EmailMessage::create()` wstawia wiersz, który cron NAPRAWDĘ wyśle. `MailFooterTest` pokrywa 4 warianty.

Powiązane: [[per-shop-email-identity-branding]], [[email-outbox-cron-pattern]], [[handoff-2026-07-15-promoted-pages]].
