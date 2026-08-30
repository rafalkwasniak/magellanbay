---
name: plan-shop-settings-storage
description: Propozycja (do zatwierdzenia) jak trzymać ustawienia sklepu — rozdział na kategorie; integracje w shop_integrations z zaszyfrowanym JSON.
metadata: 
  node_type: memory
  type: project
  originSessionId: ad2d01af-7396-446a-bc53-3263cd9693a7
---

PENDING DECISION (2026-06-28, do ustalenia z Rafałem na jego powrót). Pyt.: jak trzymać ustawienia sklepu (GA, dane płatności itp.). Po przeczytaniu docs/specyfikacja.md — wniosek: „ustawienia" to NIE jeden worek key/value, tylko 4 kategorie wg charakteru danych:

**TODO domyślny VAT — ZROBIONE (2026-06-29).** Kolumna `default_vat_rate` na `shops` (cast `VatRate`, default '23') — NIE osobna tabela (potwierdzona zasada: kat. 2 = typowane kolumny). Strona „Ustawienia" (`/sprzedawca/ustawienia`, `seller.settings.*`, pozycja ⚙️ w lewym menu) edytuje to pole. Formularz nowego produktu prefilluje się przez `ProductController::defaultVat()` → `$shop->default_vat_rate?->value ?? '23'`. Commit c60c30b.

1. Tożsamość/routing → tabela `shops` (jest): slug, domain, status, owner.
2. Profil i adres sklepu → TYPOWANE kolumny (na shops lub 1:1 shop_details): opis, logo, adres (kraj/województwo/miasto/kod/ulica/nr budynku/nr lokalu), telefon-publiczny (flaga), domyślny VAT, pakiet. Spec WYMAGA osobnych walidowanych pól (adres wymagany przy aktywacji sklepu). To nie key/value.
3. Integracje / ustawienia zaawansowane → tabela `shop_integrations` (to właściwa „tabela ustawień"): kolumny id, shop_id (FK cascade), type (enum: google_analytics|payu|przelewy24|inpost|fakturownia|…), enabled (bool), config (JSON, cast `encrypted:array`), timestamps, UNIQUE(shop_id,type). Jeden wiersz = jedna integracja. Nowa integracja = nowy case enuma + osobny Form Request + handler, BEZ migracji („rozbudowa bez przebudowy"). Sekrety szyfrowane APP_KEY-em; model `#[Hidden]`, nie logować configu. GA measurement_id nie jest sekretem, ale trzymamy jednym mechanizmem. Odczyt przez IntegrationManager (analogia config/services.php, ale per-najemca). Polityka cookies generuje się z aktywnych integracji.
4. Osobne byty (NIE ustawienia) → własne tabele później: strony informacyjne (O nas, Dostawa i płatności, Regulamin, Polityka prywatności/cookies), metody dostawy, licznik numeracji zamówień (per-sklep, od 1).

**UWAGA (2026-06-30): integracji (kat. 3) NIE ma w pakiecie darmowym — to funkcja wyższych pakietów ([[plan-packages]]).**

**ZROBIONE — kat. 3 fundament + GA (2026-07-03).** Tabela `shop_integrations` istnieje (id, shop_id FK cascade, type, enabled bool default false, config `encrypted:array` w kolumnie `text`, UNIQUE(shop_id,type)). Enum `IntegrationType` (na razie `google_analytics`), model `ShopIntegration`, relacja `Shop::integrations()`/`integration(type)` + helpery `googleAnalyticsId()` i `tracksWithGoogleAnalytics()` (efektywna bramka enabled && id, analogia do `bankTransferAvailable`). Strona **Integracje** (`/sprzedawca/integracje`, `seller.integrations.*`, poz. 🔌 w lewym menu) KONFIGURUJE (measurement ID), **Ustawienia** WŁĄCZAJĄ/wyłączają (checkbox jak przy przelewie, wyszarzony bez ID) — jeden wiersz, dwa formularze. Walidacja GA4 (`G-…`) i GTM (`GTM-…`) w `IntegrationRequest` (regex = kształt + bezpieczeństwo, bo wartość ląduje w `<script>`), ID normalizowane trim+uppercase; puste ID = usunięcie wiersza. Storefront (`x-layouts.storefront`) wstrzykuje gtag.js (G-) albo GTM snippet+noscript (GTM-) tylko gdy efektywnie aktywne. NIE zbudowano jeszcze `IntegrationManager` — na razie helpery na modelu wystarczają. **DECYZJA (Rafał 2026-07-03): GA jest WYJĄTKIEM — dostępne we WSZYSTKICH pakietach (też darmowym), bez bramki.** Testy: `tests/Feature/Seller/IntegrationsTest` + `Storefront/AnalyticsTest`, cały zestaw 254 zielone.

Rekomendowana kolejność prac: NAJPIERW profil+adres sklepu (kategoria 2, krok „Uzupełnij dane sklepu" z flow 5 minut, adres wymagany przy aktywacji), POTEM integracje (kategoria 3) przy „Ustawieniach zaawansowanych”.

Powiązane: [[multitenant-subdomain-architecture]], [[handoff-activation-flow]], FOUNDATION sek.5 (walidacja w Form Requestach, stałe w config — limity pakietów w config/, nie w kodzie).
