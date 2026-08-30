---
name: plan-sitemap-search-console
description: "WDROŻONE 03.08: mapa strony per host (centrala + każdy sklep) + robots.txt per host + weryfikacja Search Console w każdym pakiecie. Zawiera dwie pułapki, które łatwo cicho zepsuć."
metadata:
  node_type: memory
  type: project
  originSessionId: 5c4f5743-5e78-4788-bcf6-2be5ad5638e4
  modified: 2026-08-03T18:50:26.849Z
---

**WDROŻONE 2026-08-03** (commity `d3e05e7` + `4321a05`). Zgłoszone przez Rafała 02.08, omówione i zaklepane 03.08.

## Jak to działa

Każdy host ma **własną** mapę i **własny** `robots.txt` z jedną linią `Sitemap:` wskazującą tylko siebie. Robot pobiera `robots.txt` przy pierwszej wizycie, więc **mapa działa BEZ udziału sprzedawcy** — nic nie zgłasza, nie zakłada konta. Nie ma zbiorczej mapy: adresy mają leżeć na tym samym hoście co mapa, a lista wszystkich map byłaby publicznym spisem sklepów.

`App\Http\Controllers\SitemapController` i `RobotsController` — po jednej klasie na oba konteksty. Mapa sklepu: strona główna, `/produkty`, karty aktywnych produktów, pozycje z `informationMenu()`. Sklep w szkicu → 404.

## DWIE PUŁAPKI (obie przykryte testem, ale łatwo je cicho zepsuć)

1. **`public/robots.txt` MUSI NIE ISTNIEĆ.** `.htaccess` oddaje istniejące pliki z pominięciem Laravela — odtworzenie go „dla porządku" ucisza trasę bez żadnego objawu. Pilnuje `test_static_robots_file_must_not_exist`.
2. **Trasy centrali stoją na SAMYM KOŃCU `routes/web.php`, za grupą subdomen.** Nie są przypięte do hosta (centrala odpowiada też na `www`), więc wyżej przechwyciłyby storefronty i każdy sklep serwowałby mapę centrali — przy normalnie wyglądającej stronie. Pilnuje `test_storefront_robots_points_at_its_own_sitemap_not_the_central_one`.

Trzecia rzecz: osobny test sprawdza, że **każda reguła `Disallow` wskazuje istniejącą trasę**. Tak weszło `Disallow: /konto` przy trasie `/moje-konto` — plik wyglądał poprawnie i nic nie blokował.

## Weryfikacja Search Console — BEZ bramki pakietu

Karta „Google Search Console" w Integracjach jest **jedyną dostępną w darmowym Kramie**, świadomie inaczej niż GA. Powód: sprzedawca nie może potwierdzić Google własności subdomeny żadną inną drogą — pliku nie wgra, rekordu DNS w `*.kramio.pl` nie doda, a weryfikacja przez GA jest płatna od Straganu. Bez tego pola mapa byłaby dla Kramu nie do zgłoszenia. **Nie dokładać tu bramki uprawnienia.**

Integracja nie ma włącznika w Ustawieniach: meta tag niczego nie śledzi i nie stawia ciasteczek. Form Request wyłuskuje `content` z wklejonego całego znacznika — ludzie kopiują z Google całą linijkę.

Centrala: `GOOGLE_SITE_VERIFICATION` w `.env` → `services.google.site_verification` → komponent `x-google-verification` w landingu, layoucie gościa i publicznym. **Weryfikacja kramio.pl przeszła 03.08.** Kod jest tylko w `.env` na serwerze — nowe środowisko wymaga wpisania ręcznie.

## Decyzje techniczne (Rafał zgodził się z rekomendacją)

1. `lastmod` z `updated_at` — DAJEMY. Ryzyko szumu przy masowej zmianie realne, ale takie operacje są rzadkie i da się je wykonać bez dotykania `updated_at`.
2. Produkt bez stanu magazynowego — ZOSTAJE w mapie.
3. Indeks map / paginacja — NIE. Największy pakiet to 96 produktów przy limicie Google 50 000.

## ODŁOŻONE: publiczny katalog sklepów (próg 20 sklepów)

Mapa działa sama, ale **nie zastępuje pierwszego wskazania** — Google musi się najpierw dowiedzieć, że subdomena istnieje. Centrala nigdzie publicznie nie linkuje do sklepów (sprawdzone 03.08: brak katalogu, landing nie wymienia sklepów, mapa centrali zawiera tylko strony platformy). Świeży sklep bez linku z zewnątrz jest dla Google niewidzialny — robot nie ma po czym przyjść po `robots.txt`. W praktyce odkrycie idzie przez to, co sprzedawca robi sam: Facebook, Instagram, wizytówka Google.

Jedna publiczna strona linkująca do wszystkich aktywnych sklepów rozwiązałaby to automatycznie i byłaby argumentem sprzedażowym. **Rafał 03.08: „przy 20 sklepach warto, przy jednym demo nie"** — pusty katalog ogłasza, że platforma jest pusta. **Wracać po przekroczeniu ~20 aktywnych sklepów**, nie wcześniej. Do omówienia wtedy: czy sprzedawca może się z listy wypisać (nie każdy chce być na wspólnej).

## Uwaga metodyczna, która się sprawdziła

Mapa centrali powstała w TYM SAMYM kroku co sklepowa. Wcześniej trzy razy z rzędu (grafika OG, Analytics, ciasteczka) funkcja powstawała najpierw dla sklepów, a strony platformy wypadały z zakresu → [[handoff-2026-07-31-analytics]].

Powiązane: [[plan-seo-audit]], [[multitenant-subdomain-architecture]], [[plan-packages]], [[brand-logo-assets]].
