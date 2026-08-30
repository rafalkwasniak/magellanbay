---
name: handoff-2026-07-16-quick
description: "Sesja 2026-07-16 (szybkie tematy): oba priorytety kolejki zrobione — (1) tytuł maila w surowym kolorze przewodnim jak storefront, (2) kasa podpowiada wg ostatniego zamówienia klienta. 598 testów zielonych."
metadata: 
  node_type: memory
  type: project
  originSessionId: 8b701b2c-27a6-4c0c-a3bd-d0669cf2bb18
---

Krótka sesja, dwa domknięte tematy z ówczesnej kolejki „START NASTĘPNEJ SESJI".

## 1. Tytuł maila = surowy kolor przewodni sklepu
Rewizja commita 1900202. Tytuł maila „od sklepu" barwiony teraz **surowym tokenem `brand`** — dokładnie jak nazwy produktów i nagłówki storefrontu (`.st-brand { color: var(--brand) }` w `components/layouts/storefront.blade.php`) oraz przyciski. Wcześniej szedł przez `Color::readableOn()`, który przyciemniał do progu WCAG na białej karcie → tytuł i przycisk miały różne odcienie (Rafał zauważył). **Świadomy tradeoff:** spójność dekoru ważniejsza niż kontrast na bieli.
- Zmiana: `app/Support/MailBranding.php` `heading` = `$tokens['brand'] ?? $system['brand']`. Kolor systemowy Kramio bez zmian (ciemny `#1c1917`).
- Usunięty martwy po tym `Color::readableOn()` + prywatne `rgb`/`relativeLuminance` + ich testy. `Color::readableInkOn()` ZOSTAJE (tusz na przyciskach, używany przez Shop i panel Wygląd).

## 2. Kasa podpowiada wg ostatniego zamówienia klienta
`App\Livewire\Checkout::mount()` → nowa `prefillFromLastOrder(Customer)`. Dla ZALOGOWANEGO klienta bierze najświeższe zamówienie (`orders()->latest()->first()`, także anulowane — adres/FV wciąż poprawne) i ustawia:
- **FV:** `is_company` + całe `company_*` (blok firmy sam się rozwija, widok patrzy na `is_company`);
- **dostawa:** `delivery_method` — tylko jeśli nadal w `deliveryOptions()` (inaczej zostaje domyślna);
- **adres `ship_*`:** kopiowany tylko gdy WYBRANA dostawa jest kurierska (`shippedDelivery()` po ustawieniu metody);
- **płatność:** `payment_method` po dostawie (opcje płatności zależą od dostawy), tylko jeśli w `paymentOptions()`.

Dane kupującego (imię/nazwisko/e-mail/telefon) dalej z KONTA, nie z zamówienia. Gość bez zmian (nie podpowiadamy po e-mailu — nie ujawniać cudzego adresu). Powiązane: [[plan-customer-accounts]], [[plan-shipping]], [[per-shop-email-identity-branding]].

598 testów zielonych. Kolejnego „start" nie ustaliliśmy w tej sesji — następny duży temat to wysyłki [[plan-shipping]].
