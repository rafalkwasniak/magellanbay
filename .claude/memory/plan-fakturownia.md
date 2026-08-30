---
name: plan-fakturownia
description: "Integracja z Fakturownią (FV/KSeF) — WDROŻONA i przetestowana realnie 2026-07-16. Cały plan 9 kroków w kodzie. Poniżej: stan końcowy + oryginalne rozeznanie z ursalogic.pl. UWAGA: brak sandboxa, realne FV idą do KSeF."
metadata: 
  node_type: memory
  type: project
  originSessionId: 8b701b2c-27a6-4c0c-a3bd-d0669cf2bb18
---

## ✅ WDROŻONE 2026-07-16 (cały plan 9 kroków, przetestowane na REALNEJ FV z odpiętym KSeF)

**Stan końcowy w kodzie (commity 5614a19 → 65318e6 + notka imienna):**
- **Konfiguracja per-sklep:** `IntegrationType::Invoicing` w `shop_integrations` (adres konta + token, szyfrowane). Ekran Integracje (pola obok siebie, token=sekret nieodbijany, GA zjechała pod spód). Włącznik w Ustawieniach (`invoicingEnabled` = checkbox + komplet danych) z notą „usługa zewnętrzna bywa płatna". Bramka `entitlement('invoices')` (na start `true` we WSZYSTKICH pakietach — płatność = przełączenie `stall`→false).
- **Serwis** `App\Services\FakturowniaService`: `createInvoice()` + `buildInvoicePayload()`. Pozycje z VAT per linia (zw→'zw'), **`quantity_unit`** = szt./kg (nazwa pola potwierdzona z ursalogic!), migawka nabywcy (firma: NIP+adres, osoba: bez NIP), dostawa osobną pozycją 23%. Kwota pozycji = zapisany `line_total_gross` (suma FV = suma zamówienia co do grosza). Kanał logów `fakturownia` (audyt req+resp, 365 dni, BEZ tokenu).
- **Kolumny na `orders`:** `invoice_id` (gard idempotencji), `invoice_number`, `invoice_token` (publiczny PDF), `invoiced_at`, `invoice_status` (enum `InvoiceStatus` pending/failed; null=idle/gotowe). Helpery: `hasInvoice/invoicePdfUrl/canBeInvoiced/isInvoicePending/invoiceFailed/markInvoicePending/requestInvoice`.
- **Job** `App\Jobs\GenerateInvoice` (ShouldQueue, `tries=1` — ZERO ślepych ponowień, żeby nie zdublować FV w KSeF; guard `hasInvoice`). Kolejka `database` drenowana `queue:work --stop-when-empty` w schedulerze (NIE demon, LVE-safe jak `email:dispatch`).
- **Mail** „Twoja Faktura VAT" — NASZ system ([[per-shop-email-identity-branding]]), przycisk = publiczny link tokenowy do PDF (`{konto}/invoice/{token}.pdf`).
- **UI:** JEDEN komponent `OrderInvoice` przy DANYCH KUPUJĄCEGO w karcie zamówienia (nie w prawej kolumnie!) — cały cykl: przycisk → potwierdzenie W MIEJSCU (nie natywny dymek) → „w przygotowaniu" (`wire:poll`) → „Pobierz fakturę VAT" → błąd+ponów.
- **Prawnie (ustalone):** przycisk widoczny DLA WSZYSTKICH (sprzedawca decyduje; konsument może żądać FV do 3 mies.). Konsument bez danych firmy → nota „faktura imienna na dane kupującego, bez NIP". Firmowa FV tylko z NIP zebranym w kasie (zasada NIP-na-paragonie spełniona). B2C nie idzie do KSeF.
- Testy: ~646 zielonych. Reset śladu FV na zamówieniu robi się `forceFill([invoice_* => null])`.

**Gotchy:** klasy Tailwinda muszą być w buildzie (`animate-spin`, `emerald-900`, `disabled:opacity-60` NIE były → wymienione/zdjęte); [[tailwind-classes-must-exist-in-build]]. Numer zamówienia ≠ id (URL /27 = zamówienie #23).

---

## Oryginalne rozeznanie (przed wdrożeniem) — zostaje jako referencja

**Temat na wieczór 2026-07-16 / następną sesję.** Rafał poprosił o zebranie mechanizmu wystawiania FV z DZIAŁAJĄCEGO wdrożenia `ursalogic.pl` (ten sam serwer), żeby nie testować za dużo na produkcyjnej Fakturowni. **KLUCZOWE RYZYKO: Fakturownia NIE ma sandboxa, a realne FV VAT idą do KSeF** — nie wolno narobić fejków.

## Mechanizm z ursalogic (wzorzec do adaptacji — NIE kopiować 1:1)
Pliki tam: `app/Services/FakturowniaService.php` (~100 lin.), `app/Http/Controllers/BillingController.php` (~304+), `config/services.php` (`fakturownia.url|token` ← `FAKTUROWNIA_URL`/`FAKTUROWNIA_TOKEN`), kolumny na `Order`: `invoice_id`, `invoice_number`, `invoice_url`.

- **createInvoice(Order)**: `POST {url}/invoices.json` z `api_token` + `invoice{}`: `kind:'vat'`, `status:'paid'`, `payment_type:'transfer'`, sell/issue/payment_to = dziś, `buyer_*` z zamówienia (tax_no tylko firma), `positions[]` (name, `tax:23`, `total_price_gross`=grosze/100, quantity, unit). Zwraca `{id,number,url(view_url)}` lub null. **Loguje request i response** (audyt).
- **sendInvoice(id)**: `POST .../invoices/{id}/send_by_email.json?api_token=...&email_pdf=true` (PDF mailem).
- **Trigger + idempotencja (najważniejsze):** wołane w webhooku płatności `confirmed` PO realnej wpłacie. Guard 1: `status===PAID` → return (webhook wielokrotny). Guard 2: faktura tylko gdy `!$order->invoice_id` → nigdy dwa razy. Kolejność: transakcja(PAID+kredyty) → faktura(poza transakcją) → zapis invoice_* → sendInvoice → mail+Discord.

## Bezpieczne TESTOWANIE bez śmieci w KSeF
**Procedura Rafała (2026-07-16):** on sam odepnie na chwilę konto od KSeF, zrobimy **max 4–5 realnych testów**, usunie testowe FV, włączy KSeF z powrotem. Fakturownia ma **limit FV** → realnych strzałów ma być jak najmniej.
**Nasza konsekwencja w kodzie:** CAŁĄ logikę (payload, mapowanie pozycji+VAT, guard `invoice_id`, job, mail, budowa linku PDF) pokrywamy testami na **`Http::fake()`** — zanim ruszymy realne API, wszystko działa „na sucho". Realne 4–5 wywołań = tylko potwierdzenie drutu, nie debugowanie. Warto podejrzeć/zalogować payload przed wysyłką (audyt, ew. „dry-run").
Dodatkowo (gdyby trzeba): darmowe konto testowe Fakturowni (inny token) lub `kind:'proforma'` (nie idzie do KSeF). Zawsze: log request+response; FV tylko od danego statusu + guard `invoice_id`.

## USTALENIA — przepływ (2026-07-16, Rafał)
- **Wyzwalanie ręczne przez sprzedawcę** w KARCIE ZAMÓWIENIA (panel, szczegóły). Przycisk **„Stwórz fakturę VAT"**. Widoczny/aktywny dopiero od odpowiedniego STATUSU (ten lub wyższy) — konkretny status dogadamy przy wdrożeniu.
- Po kliknięciu: **ładne potwierdzenie** („czy na pewno?"). Po potwierdzeniu **FV generowana W TLE** (job/queue=database), front od razu dostaje info „FV już czeka / w przygotowaniu".
- **Mail do klienta wysyła NASZ system, NIE Fakturownia** (świadomie, mimo że send_by_email byłby wygodniejszy) — spójność brandowa. Czyli NIE wołamy `sendInvoice`/send_by_email. Nasz mail (per-shop branding, [[per-shop-email-identity-branding]]) z **przyciskiem „Pobierz FV"**.
- **Pobieranie PDF = publiczny link tokenowy Fakturowni** (zweryfikowane w API docs 2026-07-16, github.com/fakturownia/api — Rafał miał rację): odpowiedź tworzenia FV zawiera pole **`token`**; publiczny PDF pod `https://{domena-sklepu}.fakturownia.pl/invoice/{token}.pdf` (+ `?inline=yes` do podglądu) — działa BEZ api_token i BEZ naszej autoryzacji, ściąga od razu. Więc **NIE robimy proxy ani podpisanych URL-i**. Przycisk „Pobierz FV" w naszym mailu i w panelu → wprost ten link. Zapisujemy `token` faktury i budujemy URL z domeny Fakturowni sklepu + tokenu. (Alternatywa z api_token: `{url}/invoices/{id}.pdf?api_token=...` — niepotrzebna, bo mamy publiczny token.)
- **FV tylko 1×** — twardy guard `invoice_id` (jak ursalogic). Po wygenerowaniu przycisk zmienia się w „Pobierz FV".
- **Token per-shop** (potwierdzone) w `shop_integrations` (zaszyfrowany — [[plan-shop-settings-storage]]); inaczej sprzedawca wystawia poza systemem. W ekranie integracji: nota **„usługa płatna"** + **link do operatora (Fakturownia)**.
- **Na przyszłość (NIE teraz):** przełącznik w ustawieniach „FV automatycznie po opłacie online" — dodać, gdy pojawi się płatność online. Teraz brak online → nie dodajemy.

## Pozostałe różnice/techniczne (do zaprojektowania)
- **VAT per pozycja:** `positions[]` z realnych `OrderItem` + `vat_rate` (nie sztywne 23%/1 pozycja jak ursalogic).
- **Nabywca:** migawka `is_company`/`company_*`/NIP na zamówieniu → 1:1 na `buyer_*`.
- **Ficzer PŁATNY:** Fakturownia w płatnym pakiecie → bramkować uprawnieniem ([[plan-packages]]).
- Kolumny FV na `orders`: `invoice_id`, `invoice_number`, `invoice_token` (do publicznego linku PDF), `invoiced_at` (+ ewentualnie status generowania dla UI „w tle"). `invoice_url` (view_url) opcjonalnie.

Powiązane: [[plan-packages]], [[plan-shop-settings-storage]], [[plan-order-statuses]], [[vision-email-driven-orders]].
