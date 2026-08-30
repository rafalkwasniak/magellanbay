---
name: per-shop-email-identity-branding
description: "ZROBIONE (2026-07-04) — maile ze sklepu niosą tożsamość sklepu: From-name=nazwa, Reply-To=contact_email, płaskie kolory motywu + logo/nazwa. Maile Kramio też spłaszczone. Cała funkcja domknięta."
metadata: 
  node_type: memory
  type: project
  originSessionId: 5012e4a9-f72a-42fb-a86c-3d1f251b9888
---

**Wymóg Rafała (2026-07-03) — ZROBIONE w całości (2026-07-04), zweryfikowane ponownie 2026-07-09.** Każdy mail wychodzący ze sklepu (potwierdzenie zamówienia, powiadomienia, statusy…) jest „od sklepu", nie od Kramio. „Jak ktoś zapłaci za sklep, nie chce dostać pomarańczowego maila". Stan faktyczny: nadawca-adres zawsze `sklep@kramio.pl` (celowo, SPF/DKIM), ale From-name = nazwa sklepu, Reply-To = contact_email sklepu, branding (logo/kolory/nazwa) z motywu sklepu. Rozwinięcie poniżej.

**Dwa aspekty:**

1. **Tożsamość nadawcy (koperta):** wysyłamy technicznie z naszej skrzynki platformy (SPF/DKIM na naszej domenie), ALE:
   - **From-name = nazwa sklepu** (np. „I like my bike"), nie „Kramio".
   - **Reply-To = adres e-mail sklepu** — odpowiedź klienta idzie do sprzedawcy, nie na naszą skrzynkę.
   - From-address może zostać nasz (deliverability), z czytelnym display-name sklepu. Rozważyć per-sklep alias/subdomenę w przyszłości.

2. **Branding wizualny (treść):** kolory sklepu (paleta motywu), logo sklepu, linki do jego storefrontu. Dziś `App\Support\MailBranding::for($shopId)` zwraca ZAWSZE `system()` (znak ◐, gradient amber→rose) — jest już TODO „per-sklep podłączymy przez MailBranding::for($shop)". Trzeba zmapować motyw/logo sklepu (themeTokens, logo_path) na `$brand` przekazywany do `<x-mail.message>`.

**Gdzie:** `OrderMailer` (i przyszłe mailery statusów) przekazują `shop_id` do `EmailMessage`; renderer (`OutboxMailable` + `MailBranding::for`) rozwiązuje branding. Trzeba: (a) w mailerze/rendererze ustawić From-name + Reply-To ze sklepu, (b) `MailBranding::for($shop)` zwracać realne kolory/logo/nazwę sklepu. Powiązane: [[email-outbox-cron-pattern]], [[storefront-theme-system]], model dostaw/zamówień.

---

**Plan 3 kroków (ustalony 2026-07-04) i decyzje Rafała:**

1. **Pola kontaktowe — ZROBIONE (2026-07-04).** Sklep dostał `contact_email` (wymagany) + `contact_phone` (wymagany, oba budują wiarygodność i zasilą stopkę storefrontu). Kolumny nullable w bazie, „wymagane" wymuszone w `ShopProfileRequest` (telefon normalizowany `PhoneService` → `48`+9 cyfr, reguła `regex:/^48[0-9]{9}$/`; e-mail `email`). Backfill `contact_email` z `owner->email` w migracji. Nowy box „Dane kontaktowe" w „Mój sklep" (`#dane-kontaktowe`, po „Dane podstawowe"), osobny — bo adres dociąga się z NIP, a te pola nie. Helper `Shop::formattedContactPhone()`. Migracja odpalona na żywej bazie.

2. **Koperta — ZROBIONE (2026-07-04).** Kolumny `from_name` + `reply_to` (nullable) na `email_messages`, zamrażane przy kolejkowaniu. `OrderMailer::senderIdentity($shop)` ustawia `from_name`=nazwa sklepu, `reply_to`=`contact_email` dla OBU maili (klient i sprzedawca). `OutboxMailable::envelope()`: `from` = `new Address(config('mail.from.address'), from_name ?: config('mail.from.name'))`, `replyTo` = `[reply_to]` lub `[]`. Puste = mail platformy → Kramio, bez Reply-To. Migracja odpalona na żywej bazie. Zweryfikowane: sklep → „I like my bike" <sklep@kramio.pl> + Reply-To kontakt sklepu; platforma → „Kramio", bez Reply-To.

3. **Wizualny branding — ZROBIONE (2026-07-04).** `MailBranding::for($shopId)` ładuje `Shop` i mapuje `themeTokens` (`brand`→przycisk/akcent/linki, `brand_ink`→tekst na brandzie, `ink`→text, `surface`→page_bg) + nazwa + logo. `muted` zostaje neutralny (czytelność). Paleta PŁASKA: klucze `gradient_from/to` wycofane, wprowadzone `brand`+`brand_ink`; `system()` (Kramio) też płaski amber `#f59e0b`. Layout: 3 gałęzie nagłówka — logo (img, absolutny URL z dysku `public`) / glyph ◐ w kółku + nazwa (Kramio, `glyph` set) / sama nazwa (sklep bez logo, `glyph`=null). Przycisk w `message` na płaski `brand` + `brand_ink`. Nieznany `shop_id` → fallback Kramio. Zweryfikowane renderem. Pliki: `app/Support/MailBranding.php`, `resources/views/components/mail/{layout,message}.blade.php`. Testy: `tests/Feature/Mail/MailBrandingTest.php`.

**Możliwe dopieszczenia na później (nie zrobione):** stopka maila mogłaby nieść dane kontaktowe sklepu (`contact_email`/`formattedContactPhone()`) i linki storefrontu; `muted`/odcienie można by wyprowadzać z tokenów zamiast trzymać neutralne. Na dziś świadomie prosto.

**GOTCHA — kolory tekstu maila vs ciemny motyw (naprawione 2026-07-16, commit 1900202):** karta treści maila jest ZAWSZE biała (`#ffffff` w `layout.blade`), więc tekst NA niej nie może brać `text`=`ink` motywu — przy ciemnym motywie `ink` jest jasny i tekst znikał na bieli (zgłoszone przez Rafała). Rozdzielone: **`text`** = tusz motywu, TYLKO dla nazwy w nagłówku, która leży na `page_bg`=`surface` (tam tusz i tło to dobrana para); **`heading`** = kolor przewodni sklepu przepuszczony przez `Color::readableOn()` (przyciemnia do WCAG 4.5:1 na bieli, gdy za jasny) — tytuł; **`ink_card`** = zawsze ciemny (`#1c1917`) — powitanie/treść. Body akapitów dalej na `muted` (neutralny). NIE używać `text` wewnątrz białej karty. Uwaga otwarta: stopka (`muted` na `page_bg`) przy bardzo ciemnym motywie może być przygaszona — ten sam `readableOn()` by to naprawił, ale Rafał tego nie zgłaszał. Estetyka: przy jasnym kolorze przewodnim tytuł (przyciemniony) i przycisk (surowy brand) mają różne odcienie — Rafał to zauważył, uznał za akceptowalne („ważne, że widoczne").
