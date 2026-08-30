---
name: plan-online-payments-mbank
description: "DUŻY TEMAT: płatności online przez Paynow (bramka mBanku), model PER-SKLEP. Plan 13 kroków ustalony 2026-07-18. NIE zaczęte kodowo. Sandbox-keys Rafała gotowe (do bazy sklepu ilikemybike, NIE do repo)."
metadata: 
  node_type: memory
  type: project
  originSessionId: d742d9f9-2a68-4405-a0fd-fa8cdd4f1c1d
  modified: 2026-07-18T19:58:56.037Z
---

**Bramka = Paynow (mBank), potwierdzone. Model = PER-SKLEP, potwierdzony przez Rafała 2026-07-18.** Plan ułożony, kod jeszcze nie ruszony.

**Kluczowa decyzja architektoniczna (Rafał 2026-07-18):**
- Sprzedawca podpina WŁASNE konto Paynow (jak Fakturownię): 2 klucze (**Klucz dostępu do API** + **Klucz obliczania podpisu**) + środowisko (sandbox/prod) → do `shop_integrations.config` (szyfrowane APP_KEY). Pieniądze płyną klient → Paynow → sprzedawca, BEZPOŚREDNIO. Kramio tylko orkiestruje redirect + słucha webhooka. **ZERO kluczy Paynow w `.env`.**
- Alternatywa „platforma-agregator” (Kramio zbiera, rozlicza) = status KIP/agenta rozliczeniowego KNF = ODRZUCONE.
- **Płatność za SaaS-a** (Kramio ↔ sprzedawcy, abonament za używanie platformy, [[plan-packages]]) = OSOBNY, PÓŹNIEJSZY temat. DOPIERO to kiedyś użyłoby kluczy platformy w `.env`. Świadomie poza zakresem tego wdrożenia.
- Pierwsze realne wdrożenie = JEDEN sklep Rafała (`ilikemybike.kramio.pl`), jego sandbox-klucze wklejone przez UI Integracji. Sekrety NIGDY do repo/pamięci.

**Plan 13 kroków (fazami):**
- **F0 decyzje:** (1) model per-sklep ✅. (2) OTWARTE: online od razu za paywallem czy najpierw działające+test na sklepie Rafała, paywall na końcu — rekomendacja: to drugie (sklep Rafała dostaje entitlement przez pakiet albo `comped`).
- **F1 dane:** (3) `PaymentMethod::Online` (case, `isPrepaid()=>true`, label „Płatność online (BLIK, karta, przelew)”). (4) `IntegrationType::Payments` (case, bez migracji).
- **F2 config bez sekretów:** (5) `config/services.php` blok `paynow` = adresy API sandbox/prod (nie sekrety) + kanał logów `paynow` wzorem `fakturownia`. `.env` nietknięty.
- **F3 podłączenie:** (6) formularz Paynow w `IntegrationController`+`IntegrationRequest` (2 klucze + środowisko → szyfrowany `config`), 1:1 z Fakturownią. (7) fiszka „Płatność online” w Ustawieniach (`enabled`); brama metody = `entitlement('online_payments')` ∧ integracja enabled ∧ skonfigurowana.
- **F4 serce:** (8) `PaynowService`: create payment (`POST /v1/payments`→redirectUrl), podpis HMAC-SHA256, `Idempotency-Key`, weryfikacja podpisu webhooka, mapowanie statusów; testy `Http::fake()`. (9) `Checkout::place()` dla Online: po utworzeniu zamówienia (status `AwaitingPayment`) tworzy płatność i REDIRECT na `redirectUrl` zamiast `/kasa/dziekujemy`; externalId=numer zamówienia, kwota=`total_gross` w groszach, buyer.email, continueUrl=powrót. (10) **webhook** `POST /platnosci/paynow/webhook` (bez CSRF/auth), weryfikuje `Signature`: `CONFIRMED`→`AwaitingPayment→Paid` przez `OrderStatusChanger` (mail „Opłacone” leci sam); `REJECTED/ERROR/EXPIRED`→zostaje AwaitingPayment; idempotencja + zapis paymentId/status (nowa kolumna na `orders`).
- **F5 domknięcie:** (11) `/kasa/dziekujemy` czyta stan Z ZAMÓWIENIA (webhook=źródło prawdy). (12) test E2E na sandbox na `ilikemybike`: realny BLIK/karta→webhook→`Paid`. (13) paywall na końcu: `online_payments` martwy→żywy (Kram nie, Stragan/Pawilon tak), wzorem `entitlement('invoices')`.

**Poza zakresem teraz:** zwrot online przy anulowaniu (Paynow refund API), „zapłać ponownie” z maila, płatność za SaaS-a.

**POSTĘP WDROŻENIA (2026-07-18, wszystko na main):**
- Fazy 1–3 ✅ (commity 2587939, d10f8b8): `PaymentMethod::Online`, `IntegrationType::Payments`, `config/services.php` blok paynow (adresy sandbox/prod, ZERO .env), kanał logów `paynow`, formularz Integracji + przełącznik Ustawień (BEZ bramy pakietu — dostępne dla każdego sklepu na tym etapie).
- Faza 4+5 ✅ (commit 66f5835): migracja `payment_external_id`+`payment_status` na orders (WYKONANA na prod). `PaynowService` (create payment `POST /v1/payments`, podpis V1 = base64(HMAC-SHA256(klucz_podpisu, surowe_ciało)) — ZWERYFIKOWANE z docs+SDK Paynow, spójna integracja v1; sign() wyizolowany na wypadek przełączenia na v3). Webhook `POST /platnosci/paynow/webhook` (publiczny, CSRF wyjęty w bootstrap/app.php, koreluje po paymentId, weryfikuje podpis, CONFIRMED→Paid przez OrderStatusChanger+mail, idempotentny). Inicjacja płatności PRZYCISKIEM z ekranu podsumowania (`POST /kasa/platnosc` → CheckoutController::pay), NIE auto-redirect z kasy. Kasa oferuje „Płatność online" gdy `onlinePaymentsEnabled()`. Adres webhooka do przeklejenia w Integracjach (Shop::paynowWebhookUrl, host sklepu). 703 testy zielone.
- **Webhook Rafał ustawił na `https://ilikemybike.kramio.pl/platnosci/paynow/webhook`** (subdomena sklepu — trasa domainless łapie, bo zarejestrowana przed grupą storefrontu).
- **ZOSTAŁO: test E2E na sandboksie** (Rafał zrobi po powrocie 2026-07-18): Integracje→wklej sandbox-klucze+Sandbox→Ustawienia włącz→zamów z „Płatność online"→Przejdź do płatności→BLIK sandbox→webhook→Paid+mail. Gdyby v3-only: poprawka lokalna w `PaynowService::sign()`+ścieżka. Logi: `storage/logs/paynow-*.log`.
- Poprawki UX 2026-07-18 (po feedbacku Rafała, przed E2E): środowisko sandbox to CHECKBOX „Włącz środowisko testowe (sandbox)" (nie select); karta produktu (delivery-summary) pokazuje paczkomat InPost + płatność online; etykieta metody = „Płatność online Paynow" (bo mogą dojść inne bramki); NAPRAWIONY błąd maila (online mówił „zapłacisz przy odbiorze" — teraz „czeka na opłacenie"/„opłacone", gałąź Online w OrderMailer::paymentBlock).

- **NADAL DO ZROBIENIA: brama pakietu** `online_payments` (martwy→żywy, Kram nie / Stragan+Pawilon tak) — świadomie na SAM koniec, po działającym E2E. Plus poza zakresem: zwrot online przy anulowaniu (Paynow refund API).

- **ZROBIONE 2026-07-18: jedna strona płatności z TOKENEM** (`GET/POST /platnosc/{token}`, PaymentController, widok storefront/payment.blade.php). Token = `Order::paymentToken()` = zaszyfrowany (Crypt/APP_KEY, URL-safe strtr) id zamówienia — ZERO kolumn, działa bez logowania (gość i zalogowany tak samo), scope do sklepu z subdomeny. Strona: podsumowanie + „Zapłać" gdy `isAwaitingOnlinePayment()`, „Opłacone ✓" gdy zapłacone, „anulowane" gdy anulowane. Linkują do niej: mail (przycisk akcji „Zapłać za zamówienie" → `paymentUrl()`, gdy nieopłacone online), ekran podziękowania, „Moje konto"→zamówienie (przycisk „Zapłać"). continueUrl Paynow = ta strona. STARA sesyjna `/kasa/platnosc` + `CheckoutController::pay` USUNIĘTE (zunifikowane). 708 testów.

- (kontekst pierwotnego pomysłu Rafała, już zrealizowany): jedna strona płatności z TOKENEM. Problem: linki do dokończenia płatności potrzebne w wielu miejscach (mail, historia zamówień w „Moje konto", a dla NIEzarejestrowanych nie ma gdzie kierować). Rozwiązanie: strona dostępna po tokenie (jak publiczny link do PDF faktury [[plan-fakturownia]]) — działa zalogowany czy nie; pokazuje podsumowanie + „Zapłać" gdy AwaitingPayment, „Opłacone" gdy zapłacone; z niej jedno wyjście = do Paynow. Wtedy WSZYSTKIE linki (mail „jeśli nie opłacono, kliknij"; historia zamówień; powrót z Paynow) kierują w to samo miejsce. Uwaga: Paynow wraca na continueUrl z `?paymentId=…&paymentStatus=…` — ta strona może to obsłużyć. Do czasu jej powstania mail NIE ma linku do płatności (świadomie). Rekomendacja: to następny split po udanym E2E.

- **ZROBIONE 2026-07-18: auto-FV po opłaceniu online.** Checkbox „Wystaw fakturę VAT automatycznie po opłaceniu" w karcie Paynow (Integracje), widoczny tylko gdy sklep ma dostęp do faktur (`entitlement('invoices')`); zapisywany do `shop_integrations` Payments `config['auto_invoice']`. Helper `Shop::autoInvoiceAfterPayment()`. W webhooku po przejściu na Paid: `if ($order->shop?->autoInvoiceAfterPayment()) $order->requestInvoice();` — a `requestInvoice()`/`canBeInvoiced()` samo pilnuje reszty (Fakturownia włączona, uprawnienie, brak dubla), więc flaga bez włączonej Fakturowni po prostu nic nie robi. Job GenerateInvoice [[plan-fakturownia]] (tries=1, KSeF!). Pokryte testami (Bus::fake): auto-FV gdy flaga+Fakturownia, brak gdy flaga off, brak gdy bez Fakturowni. Efekt: opłacone online → automatyczna faktura, zero klikania.

**Fundament JUŻ w kodzie (zweryfikowane 2026-07-18):** `OrderFlow` ma gotową ścieżkę przedpłaty `AwaitingPayment→Paid→Processing→handover→Completed` — online reużywa jej 1:1, różnica = webhook przenosi do Paid zamiast sprzedawcy, a mail o statusie idzie sam. `shop_integrations` (szyfr. config per sklep) + wzór Fakturowni (job/config/logi/formularz Integracji) = szablon do skopiowania. `online_payments` = istniejący MARTWY klucz entitlement w `config/shop.php` (Kram false, Stragan/Pawilon true).

Powiązane: [[plan-packages]], [[plan-shop-settings-storage]], [[plan-order-statuses]], [[plan-fakturownia]], [[vision-email-driven-orders]], [[next-orders-panel-tab]].
