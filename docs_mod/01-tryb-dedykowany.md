# 01 — Tryb dedykowany: zdjęcie limitów i bram

Cel: sklep bez limitów produktów, zdjęć i AI, ze wszystkimi funkcjami odblokowanymi, bez możliwości wygaśnięcia czegokolwiek.

---

## 1.1. Nowy pakiet `dedicated` w `config/shop.php`

Wszystkie bramy czytają przez [`Shop::entitlement()`](../app/Models/Shop.php#L1565), więc wystarczy pakiet, którego uprawnienia są otwarte. Dopisać do tablicy `packages` (obecnie [`config/shop.php:195`](../config/shop.php#L195)):

```php
'dedicated' => [
    'name' => 'Sklep dedykowany',
    'order' => 99,
    'price_yearly' => 0,      // 0 = nie ma jak wygasnąć (patrz subscriptionActive)
    'available' => false,     // NIE pokazujemy go w cenniku ani przy zmianie pakietu
    'entitlements' => [
        'max_products'     => 1000000,
        'ai_weekly_limit'  => 100000,
        'online_payments'  => true,
        'courier_shipping' => true,
        'invoices'         => true,
        'ga_analytics'     => true,
        'order_editing'    => true,
        'discount_codes'   => true,
        'bulk_mail'        => true,
    ],
],
```

Komplet kluczy uprawnień jest w [`PackageFeatures.php:234`](../app/Support/PackageFeatures.php#L234) — przy dokładaniu nowego uprawnienia w przyszłości trzeba je dopisać także tutaj.

### GOTCHA: nigdy `null` jako limit

Każdy odbiorca rzutuje wartość na `int`:

```php
$limit = (int) $shop->entitlement('max_products');   // ProductLimitLock.php:40 i :77
```

`(int) null` daje **0**, a warunek brzmi `count() >= $limit` — czyli sklep zablokowałby się przy zerowym produkcie. **Do „bez limitu" używamy dużej liczby, nie `null` i nie `-1`.** Milion produktów i sto tysięcy zadań AI tygodniowo to w praktyce brak limitu, a arytmetyka pozostaje bezpieczna.

Miejsca, które to czytają:
- [`ProductLimitLock.php:40,77`](../app/Services/ProductLimitLock.php) — blokada dodawania
- [`ProductController.php:253`](../app/Http/Controllers/Seller/ProductController.php#L253) — sprawdzenie przed formularzem
- [`AiQuota.php:92`](../app/Services/AiQuota.php#L92) — pula zadań AI
- [`PackageController.php:36`](../app/Http/Controllers/Seller/PackageController.php#L36) — ekran „Mój pakiet" (i tak wyłączany, patrz dok. 02)
- [`administrator/shops/index.blade.php:64`](../resources/views/administrator/shops/index.blade.php) — kolumna „produkty / limit"

---

## 1.2. Rekord sklepu: `comped = true`

Sam pakiet nie wystarczy — trzeba jeszcze wyłączyć cykl abonamentowy. Robi to flaga `comped`, obsłużona w [`subscriptionActive()`](../app/Models/Shop.php#L1464):

```php
if ($this->comped) {
    return true;      // dostęp gratisowy nie wygasa nigdy
}
```

Ustawienie dla jedynego sklepu (przez seeder wdrożeniowy albo ręcznie w konsoli):

```php
$shop->update(['comped' => true]);
$shop->assignPackage('dedicated');   // robi SNAPSHOT uprawnień — patrz niżej
```

**Kolejność ma znaczenie.** [`assignPackage()`](../app/Models/Shop.php#L1422) kopiuje `entitlements` z configu do kolumny `entitlements` jako snapshot. Bez tego wywołania sklep czytałby uprawnienia z definicji pakietu przez fallback — działa, ale konsola admina pokazywałaby puste pola.

Dwa zabezpieczenia w jednym: `comped` wyłącza wygasanie, a `price_yearly => 0` w pakiecie robi to samo drugą drogą ([`Shop.php:1471`](../app/Models/Shop.php#L1471)). Celowo pas i szelki — gdyby ktoś kiedyś zdjął `comped`, sklep i tak nie zgaśnie.

---

## 1.3. Limity poza systemem pakietów

Nie wszystko siedzi w uprawnieniach. Te wartości są twarde i trzeba je podnieść osobno.

### Zdjęcia produktu — dziś maksimum 8

[`ProductRequest.php:74`](../app/Http/Requests/Seller/ProductRequest.php#L74):

```php
'images' => ['nullable', 'array', 'max:8'],
```

oraz komunikat w [`:137`](../app/Http/Requests/Seller/ProductRequest.php#L137): *„Możesz dodać maksymalnie 8 zdjęć."*

**Do zrobienia:** wyprowadzić liczbę do configu (np. `shop.product_images.max_per_product`, domyślnie 8) i czytać ją w regule oraz w komunikacie. Dla sklepu dedykowanego ustawić 100.

Nie warto robić z tego „bez limitu" — walidacja tablicy bez górnej granicy to otwarte drzwi na przypadkowe wgranie kilkuset plików naraz i zjedzenie pamięci przez GD. Sto zdjęć na produkt to w praktyce brak ograniczenia.

### Pozostałe progi w `config/shop.php`

| Klucz | Dziś | Uwaga |
|---|---|---|
| `description_max` ([:46](../config/shop.php#L46)) | 4000 | opis sklepu; podnieść w razie potrzeby |
| `product_description_max` ([:58](../config/shop.php#L58)) | 5000 | opis produktu |
| `ai.max_uses_per_field` ([:71](../config/shop.php#L71)) | 3 | ile razy AI poprawia to samo pole; podnieść lub zdjąć |
| `product_images.max_side` ([:92](../config/shop.php#L92)) | 1600 px | rozdzielczość po przeskalowaniu |
| `product_images.max_upload_kb` ([:94](../config/shop.php#L94)) | 20 MB | górny limit oryginału |
| `homepage_promoted_limit` ([:110](../config/shop.php#L110)) | 6 | kafelki na stronie głównej |

Żaden z nich nie blokuje sprzedaży — to kwestia wygody. Warto je przejrzeć z klientem po pierwszym wgraniu katalogu, a nie zgadywać teraz.

---

## 1.4. Czego NIE ruszamy

- **Systemu pakietów jako takiego.** Zostaje w kodzie nietknięty. Zmienia się tylko to, który pakiet ma przypisany jedyny sklep.
- **Kolumn `package`, `entitlements`, `price_yearly`, `subscription_ends_at`, `comped`.** Zostają w tabeli. Nic nie kosztują, a przy następnym kliencie są gotowe.
- **Snapshotów uprawnień.** Mechanizm „uprawnienia lepkie" działa dalej i jest tu przydatny: gdyby klient kiedyś dokupił moduł spoza standardu, wystarczy dopisać klucz do snapshotu jego sklepu.

---

## Sprawdzian etapu

- [ ] Dodanie 300 produktów pod rząd przechodzi bez komunikatu o limicie
- [ ] Produkt przyjmuje więcej niż 8 zdjęć
- [ ] Kody rabatowe, edycja zamówienia, faktury, newsletter i kurier działają bez komunikatu o pakiecie
- [ ] `Shop::first()->subscriptionActive()` zwraca `true`
- [ ] `Shop::first()->entitlement('max_products')` zwraca liczbę, nie `null`
- [ ] Pakiet `dedicated` nie pojawia się w cenniku ani na ekranie zmiany pakietu
