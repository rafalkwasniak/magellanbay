---
name: plan-custom-brand-color
description: "WDROŻONE 2026-07-05 — własny „kolor przewodni\" sklepu (akcent nadpisujący brand) + palety rozszerzone do 5/szablon; 335 testów zielonych."
metadata: 
  node_type: memory
  type: project
  originSessionId: 958c232d-9cc7-45cc-a402-fd1b350a2309
---

Zatwierdzony plan (Rafał zaakceptował podejście „kolor = akcent, reszta z szablonu"). Robimy po jego powrocie, ~2h od 2026-07-05. Strona: `/sprzedawca/wyglad` ([seller/appearance/edit.blade.php](resources/views/seller/appearance/edit.blade.php)).

**Cel:** klient ustawia własny „kolor przewodni serwisu" (colorpicker) w boxie POD Logo. Gdy ustawiony — przy KAŻDYM szablonie dochodzi dodatkowe kółeczko z tym kolorem. Zabezpieczenie: gdy klient wyzeruje kolor, a była wybrana paleta `custom`, szablon spada na swoją domyślną paletę (bo kolor + szablon + paleta zapisują się jednym submitem).

**Dodatkowo w TYM SAMYM splicie:** rozszerzyć palety każdego szablonu z 2 do ~**5 kolorów pasujących do palety + 6. = kolor klienta**. Czyli config `themes.templates.*.palettes` dostaje po ~5 gotowców; custom to wirtualna 6. opcja.

**Decyzja projektowa (jak 1 kolor wypełnia 4 tokeny brand/brand_ink/surface/ink):** kolor własny nadpisuje TYLKO `brand` (akcent). `surface`/`ink` dziedziczy z BAZOWEJ (domyślnej) palety danego szablonu; `brand_ink` liczony automatycznie (czarny/biały wg jasności koloru — kontrast). Dzięki temu „Twój kolor" adaptuje się per szablon (jasny/ciemny) i sklep nigdy nie jest nieczytelny. Odrzucone: kolor sterujący tłem; pełny edytor 4 kolorów.

**Storage:** wszystko w istniejącym `shops.theme` JSON → `{ palette, brand_color }`. BEZ migracji. (`shops.template` bez zmian.)

**Do zrobienia — checklist:**
1. `config/themes.php` — dodać po ~5 palet do każdego z 3 szablonów (velvet_cloud, green_nook, graphite_dusk), kolory pasujące do klimatu.
2. `App\Support\Color::readableInkOn($hex)` — luminancja (0.299R+0.587G+0.114B)/255; >0.6 → `#1A1A1A`, else `#FFFFFF`. Mirror w JS.
3. `Shop` ([Shop.php](app/Models/Shop.php)): `brandColor(): ?string` (waliduje hex z theme JSON); `themePalette()` — obsłużyć `'custom'` (zwróć 'custom' tylko gdy brandColor≠null, inaczej fallback default); `themeTokens()` — dla `custom` bierz bazę = default_palette szablonu, nadpisz `brand`=brandColor, `brand_ink`=Color::readableInkOn.
4. `AppearanceRequest` — `brand_color` nullable regex `/^#[0-9A-Fa-f]{6}$/`; `palettes.<slug>` Rule::in musi dopuścić też `'custom'`.
5. `AppearanceController::update` — znormalizuj brand_color (upper); SAFEGUARD: `palette==='custom' && brandColor===null` → palette=null (default); zapisz theme `{palette?, brand_color?}` lub null.
6. Blade: nowy box „Kolor przewodni" pod Logo (color input + HEX mirror + Wyczyść); w pętli palet dorobić kółeczko `custom` (value=custom, hidden gdy brak koloru, surface/ink z default palety szablonu); w `$previewTokens` obsłużyć `custom` (użyj `$shop->themeTokens()`, bo `$template['palettes']['custom']` nie istnieje).
7. JS (zero zależności, jak reszta pliku): picker→ pokaż/ukryj kółeczka custom, ustaw kolor+data-brand/brand-ink (inkOn w JS); Wyczyść→ ukryj, a jeśli custom zaznaczony to przeskocz na 1. paletę szablonu + odśwież podgląd.
8. Testy: zapis własnego koloru; safeguard reset (custom bez koloru → default); kontrast brand_ink dla jasnego/ciemnego; walidacja złego hex.

**WDROŻONE 2026-07-05** (cały plan zrealizowany, 1 split, 335 testów zielonych):
- `config/themes.php`: każdy z 3 szablonów ma teraz 5 palet (velvet_cloud: sky/lavender/mint/blush/coral; green_nook: forest/moss/clay/olive/honey; graphite_dusk: ember/rose/gold/teal/orchid). Custom = wirtualna 6.
- `App\Support\Color::readableInkOn($hex)` (+ [tests/Unit/ColorTest.php](tests/Unit/ColorTest.php)); mirror w JS w widoku.
- `Shop::brandColor()` (waliduje hex z theme JSON, zwraca UPPER lub null); `themePalette()` obsługuje `custom` (tylko gdy brandColor≠null, inaczej default = safeguard); `themeTokens()` dla `custom` bierze surface/ink z default palety szablonu, nadpisuje `brand`, liczy `brand_ink`.
- `AppearanceRequest`: `brand_color` nullable regex `#RRGGBB`; `palettes.<slug>` dopuszcza `custom`.
- `AppearanceController::update`: normalizuje kolor (UPPER), safeguard (custom bez koloru → palette null), zapis `theme={palette?,brand_color?}` lub null. Kolor własny zapamiętywany też obok gotowca.
- Widok: box „Kolor przewodni" pod Logo (color input ↔ HEX ↔ Wyczyść), próbka `custom` (dashed outline) przy każdym szablonie, `$previewTokens` dla custom przez `$shop->themeTokens()`. Vanilla JS: picker sync + sanityzacja hex + propagacja na próbki + Wyczyść (przeskok na default gdy custom aktywny).
- Storage: wszystko w `shops.theme` JSON, BEZ migracji. Render storefrontu już czyta `themeTokens()` → kolor działa od razu.

Powiązane: [[plan-storefront-theming]], [[plan-shop-edit-tabs]], [[storefront-theme-system]], [[ui-design-direction]], [[form-client-validation-convention]].
