# 03 — Jedna domena zamiast subdomen

Cel: sklep odpowiada na `magellanbay.pl`, panel na `magellanbay.pl/sprzedawca`. Żadnych subdomen, żadnego wildcard DNS.

To najbardziej „kodowa" część przeróbki i jedyna, która dotyka routingu. Warto ją zrobić raz porządnie, bo będzie służyć każdemu kolejnemu klientowi.

---

## 3.1. Jak jest dziś

Storefront żyje w grupie domenowej [`routes/web.php:423`](../routes/web.php#L423):

```php
Route::domain('{shop}.'.config('tenancy.central_domain'))
```

Parametr `{shop}` z etykiety subdomeny trafia do middleware [`ResolveShop`](../app/Http/Middleware/ResolveShop.php#L28), który znajduje sklep po `slug` i scope'uje wszystko do niego. Centrala (landing, logowanie, panele) siedzi na gołej domenie, **na końcu pliku tras** — kolejność jest istotna i pilnuje jej test (patrz pamięć: mapa strony + Search Console).

Adresy budujemy przez [`Central::url()`](../app/Support/Central.php), bo `route()` i `url()` na subdomenie celują w tę subdomenę.

---

## 3.2. Docelowo w trybie dedykowanym

**Storefront przenosi się na domenę główną**, centrala znika jako pojęcie.

Do rozstrzygnięcia przy pierwszym wdrożeniu — rekomendacja:

```php
if (Mode::dedicated()) {
    // Bez Route::domain — trasy storefrontu na domenie głównej.
    Route::middleware('tenant')->group(function () {
        // ...te same trasy co dziś w grupie subdomeny
    });
} else {
    Route::domain('{shop}.'.config('tenancy.central_domain'))
        ->middleware('tenant')
        ->group(/* ... */);
}
```

Ważne, żeby **treść grupy była jedna** — wspólny plik tras dołączany w obu wariantach albo domknięcie w zmiennej. Zduplikowanie 60 tras storefrontu w dwóch gałęziach `if` to gwarancja, że za pół roku będą się różnić.

### `ResolveShop` bez parametru z adresu

Middleware musi umieć rozwiązać sklep, gdy nie ma `{shop}` w trasie. Najprostsze i wystarczające:

```php
$shop = Mode::dedicated()
    ? Shop::query()->first()          // jedyny sklep w instalacji
    : Shop::where('slug', $slug)->first();
```

Warto to zapamiętać w kontenerze na czas żądania, żeby nie odpytywać bazy wielokrotnie.

Cała reszta middleware — karencja przed usunięciem, sklep niepublikowany, `www` — w trybie dedykowanym jest bezprzedmiotowa albo działa bez zmian.

### Kolizja adresów: `/rejestracja`

Znany gotcha (pamięć: `route()/url()` na subdomenie): na storefroncie `/rejestracja` to **rejestracja klienta sklepu** ([`:485`](../routes/web.php#L485)), a na centrali — rejestracja sprzedawcy ([`:113`](../routes/web.php#L113)). Po zejściu na jedną domenę byłyby to dwie trasy o tym samym adresie.

Rozwiązuje to dokument 02: **rejestracja sprzedawcy jest wyłączona**, więc zostaje tylko rejestracja klienta. Ale to jest dokładnie ten rodzaj kolizji, którego trzeba szukać przy scalaniu — przejrzeć wszystkie adresy centrali i storefrontu pod kątem powtórzeń, zanim ruszy praca.

Kandydaci do sprawdzenia: `/logowanie`, `/moje-konto`, `/regulamin`, `/polityka-prywatnosci`, `/koszyk`.

### `Central::url()`

Przy jednej domenie centrala i storefront to ten sam host, więc pomocnik może po prostu zwracać zwykły `url()`. Nie usuwać go z kodu — zmienić zachowanie w trybie dedykowanym. Wywołań jest sporo i nie ma sensu ich tykać.

---

## 3.3. Konfiguracja

| Klucz | Kramio | Sklep dedykowany |
|---|---|---|
| `APP_URL` | `https://kramio.pl` | `https://magellanbay.pl` |
| `APP_DOMAIN` → [`tenancy.central_domain`](../config/tenancy.php) | `kramio.pl` | domena klienta |
| `SHOP_MODE` | `saas` | `dedicated` |

[`config/tenancy.php`](../config/tenancy.php) zawiera też `subdomain.min/max` i listę `reserved_subdomains` (39 pozycji) — w trybie dedykowanym nieużywane, ale zostają. Walidacja sluga sklepu nadal działa, po prostu nikt jej nie uruchamia.

**Wildcard DNS i wildcard SSL nie są potrzebne.** To istotne przy rozmowie z hostingiem klienta — wystarczy zwykły certyfikat na jedną domenę. Odpada najczęstszy powód, dla którego tani hosting nie nadaje się pod Kramio.

---

## 3.4. Co po drodze warto sprawdzić

- **Mapa witryny XML** — generowana per host; sprawdzić, że wskazuje domenę główną, nie subdomenę
- **`public/robots.txt` MUSI nie istnieć** (znany gotcha) — plik fizyczny przesłania trasę generującą treść
- **Karta na Facebooka (OG)** — adresy grafik i `og:url` budowane per host
- **Ciasteczka zgody** — dziś przypinane do hosta, z którego przyszło żądanie; przy jednej domenie upraszcza się samo
- **Linki w mailach** — sprawdzić, czy prowadzą do właściwego hosta po zmianie
- **Canonical i `og:url`** na kartach produktów

---

## 3.5. Alternatywa, gdyby routing okazał się kłopotliwy

Gdyby scalanie tras zajęło więcej, niż warto, jest wyjście awaryjne: **zostawić strukturę subdomenową i przekierować domenę główną na subdomenę sklepu** na poziomie serwera WWW. Działa od ręki, ale:

- klient widzi w pasku `sklep.magellanbay.pl` zamiast `magellanbay.pl`
- potrzebny jest wildcard albo drugi certyfikat
- pierwsze wrażenie jest gorsze, a to sklep sprzedażowy

Traktować to jako plan B na wypadek problemu z terminem, nie jako rozwiązanie docelowe. Baza wielokrotnego użytku powinna umieć jedną domenę.

---

## Sprawdzian etapu

- [ ] `https://domena-klienta.pl/` pokazuje stronę główną sklepu
- [ ] `https://domena-klienta.pl/sprzedawca/panel` prowadzi do panelu po zalogowaniu
- [ ] Karta produktu, koszyk i kasa działają na domenie głównej
- [ ] Żaden adres w aplikacji nie zawiera subdomeny sklepu
- [ ] Mapa witryny XML wskazuje domenę główną
- [ ] `/robots.txt` odpowiada treścią z aplikacji, nie plikiem
- [ ] Mail potwierdzający zamówienie zawiera poprawne odnośniki
