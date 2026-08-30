---
name: plan-unclaimed-subdomain-landing
description: "WDROŻONE 12.08 - subdomena bez sklepu pokazuje strone \"ten adres jest wolny\" z zacheta do rejestracji zamiast 404"
metadata: 
  node_type: memory
  type: project
  originSessionId: 9b42a7e8-65de-49c5-a571-38f05836de8b
  modified: 2026-08-12T16:36:18.109Z
---

**WDROŻONE 2026-08-12.** Subdomena `{cokolwiek}.kramio.pl`, pod którą nie stoi żaden czynny sklep, zamiast ślepego 404 pokazuje kartę w stylu logowania/rejestracji. Dwa warianty:

- **adres wolny** → „Ten adres jest wolny" + przycisk „Zajmij ten adres za darmo" prowadzący na `kramio.pl/rejestracja?adres={slug}`, gdzie formularz ma już podpowiedzianą nazwę sklepu dającą dokładnie ten slug;
- **adres zajęty** (kwarantanna po usuniętym sklepie, etykieta zarezerwowana, zły kształt, sklep w karencji przed usunięciem) → „Nie ma tu sklepu" + zaproszenie bez obiecywania tego adresu.

Przy okazji: **`www.kramio.pl` przestało być 404** — pasowało do wzorca `{shop}.{central_domain}`. Teraz 301 na centralę z zachowaniem ścieżki.

Pliki: `app/Services/SubdomainAvailability.php`, `app/Support/Central.php` ([[gotcha-route-helpers-on-subdomain]]), `app/Http/Middleware/ResolveShop.php`, `resources/views/platform/unclaimed-subdomain.blade.php`, prefill w `RegisterController::create()`.

**Dwie decyzje, których nie ruszać bez powodu:**

1. **Status zostaje 404** (+ `X-Robots-Tag: noindex`) mimo ładnej treści. Pod wildcardem istnieje nieskończenie wiele takich adresów — z kodem 200 Google indeksowałby je jako osobne strony. `robots.txt` i `sitemap.xml` na takiej subdomenie zostają przy gołym 404 (sprawdzenie rozszerzenia w ścieżce), bo plik tekstowy z HTML-em w środku jest gorszy niż brak pliku.
2. **„Adres wolny" MUSI zgadzać się z walidacją rejestracji.** Obiecany adres, którego formularz odrzuci, jest gorszy niż brak obietnicy. `SubdomainAvailability` i reguła `slug` z `RegisterRequest` czytają te same źródła, a zgodności pilnuje test `test_availability_agrees_with_registration_validation` — zmiana reguł w jednym miejscu bez drugiego zapala się na czerwono.

Kontekst: powstało pod post na Facebooka, w którym `twojanazwa.kramio.pl` jest wołaczem do czytelnika ([[feedback-marketing-tone-kramio]]) — więc adres z reklamy musiał zacząć odpowiadać czymś sensownym.
