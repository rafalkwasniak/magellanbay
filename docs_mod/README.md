# Sklep dedykowany — przeróbka Kramio pod jednego klienta

Dokumenty w tym katalogu opisują, co zmienić w Kramio, żeby powstał **sklep dedykowany**: jeden klient, jeden panel, jedna domena, bez pakietów i abonamentu.

Pierwszym odbiorcą jest Magellan Bay, ale **to nie jest robota pod jednego klienta**. Celem jest **baza wielokrotnego użytku** — punkt wyjścia dla każdego kolejnego zlecenia „sklep na własnym serwerze". Dlatego wszystko, co poniżej, ma być przełącznikiem, a nie cięciem.

---

## Zasada naczelna: konfiguracja, nie wycinanie

**Niczego nie usuwamy z kodu.** Warstwa SaaS — rejestracja, pakiety, abonament, landing, panel platformy — zostaje w repozytorium i jest **wyłączana konfiguracją**.

Trzy powody:

1. **Drugi klient.** Jeśli wytniemy, przy kolejnym zleceniu robimy tę samą pracę od zera. Jeśli wyłączymy — nowy klient to nowe wdrożenie tej samej bazy.
2. **Poprawki płyną w obie strony.** Wspólny kod znaczy, że łatka bezpieczeństwa albo zmiana API InPostu robi się raz.
3. **Ryzyko.** Wycinanie wielonajemczości (`shop_id` w każdej tabeli, scope'y, relacje) to tygodnie roboty w ścieżce pieniędzy — tam, gdzie błąd kosztuje najwięcej. Zysk byłby czysto kosmetyczny, bo klient i tak tego nie widzi.

**Wielonajemczość zostaje uśpiona.** Jeden rekord `shops`, `shop_id` wszędzie jak dziś. Klient nigdy się o niej nie dowie.

---

## Kluczowe znalezisko: limity zdejmuje się danymi, nie kodem

Wszystkie bramy pakietów schodzą się w jednym miejscu — [`Shop::entitlement()`](../app/Models/Shop.php#L1565). Każdy limit i każda blokada czyta przez tę metodę.

A ona ma dwa wyjścia, z których oba działają na naszą korzyść:

```php
public function entitlement(string $key): mixed
{
    if (! $this->subscriptionActive()) {          // ← wygasły abonament = pakiet darmowy
        return config('shop.packages.'.config('shop.default_package').".entitlements.{$key}");
    }
    return $this->rawEntitlement($key);           // ← snapshot uprawnień sklepu
}
```

Do tego [`subscriptionActive()`](../app/Models/Shop.php#L1464) zaczyna od:

```php
if ($this->comped) {
    return true;      // dostęp gratisowy — NIE WYGASA NIGDY
}
```

**Wniosek: nowy pakiet w configu + `comped = true` na rekordzie sklepu otwiera wszystkie bramy i nic nigdy nie wygasa — bez jednej linii zmiany w logice.** Szczegóły w [01-tryb-dedykowany.md](01-tryb-dedykowany.md).

---

## Kolejność prac

| # | Etap | Dokument | Charakter |
|---|---|---|---|
| 1 | Zdjęcie limitów i bram | [01-tryb-dedykowany.md](01-tryb-dedykowany.md) | konfiguracja + migracja danych |
| 2 | Wyłączenie warstwy SaaS z ekranów i crona | [02-wylaczenie-warstwy-saas.md](02-wylaczenie-warstwy-saas.md) | konfiguracja + trasy |
| 3 | Jedna domena zamiast subdomen | [03-adresy-i-domena.md](03-adresy-i-domena.md) | trasy + middleware |
| 4 | Marka klienta zamiast Kramio | [04-branding-i-dokumenty.md](04-branding-i-dokumenty.md) | treści i pliki |
| 5 | Wdrożenie na serwerze klienta | [05-wdrozenie.md](05-wdrozenie.md) | operacje |
| 6 | Dane startowe — migracje i seeder | [06-dane-startowe.md](06-dane-startowe.md) | decyzja + seedery |

Etapy 1–4 da się robić równolegle, ale **1 przed 2**: dopóki limity nie są zdjęte, część ekranów pokazuje bramy pakietów i nie wiadomo, czy coś jest schowane celowo, czy przez brak uprawnienia.

---

## Czego ten katalog NIE opisuje

**Funkcji zamówionych przez klienta** — personalizacji nadruku, grawerki, ceny z czterech składników, partnerów licencyjnych. To Etap 2 oferty i osobny temat. Tutaj chodzi wyłącznie o przerobienie Kramio na sklep dedykowany, czyli Etap 1.

---

## Sprawdzian, że baza jest gotowa

Zanim ruszy praca nad funkcjami klienta, wszystkie punkty muszą być prawdziwe:

- [ ] `php artisan test` — pełna suita zielona
- [ ] Panel logowania prowadzi wprost do panelu sklepu; nie ma śladu wyboru pakietu ani rejestracji
- [ ] Dodanie 300 produktów pod rząd nie trafia w żaden limit
- [ ] Produkt przyjmuje więcej niż 8 zdjęć
- [ ] Storefront odpowiada na domenie głównej, bez subdomeny
- [ ] Wszystkie funkcje płatne (kody rabatowe, edycja zamówienia, faktury, newsletter, kurier) są dostępne bez komunikatu o pakiecie
- [ ] W panelu nie ma pozycji „Mój pakiet" ani żadnej wzmianki o abonamencie
- [ ] Cron nie wykonuje `subscriptions:check` ani `shops:purge`
- [ ] Żaden e-mail wychodzący nie mówi „Kramio"
- [ ] Wejście na `/administrator/panel` nie ujawnia konsoli platformy
