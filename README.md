# Kramio

Platforma sklepowa SaaS (`kramio.pl`). Centrala pod domeną główną — tam sprzedawca
zakłada konto i zarządza sklepem. Sam sklep żyje na subdomenie (`{sklep}.kramio.pl`)
i to ją widzi klient. Jedna baza, wszystko scope'owane po `shop_id`.

Repozytorium prywatne. Dokumentacja i komentarze po polsku, nazwy w kodzie po
angielsku — szczegóły w [`FOUNDATION.md`](FOUNDATION.md) sek. 1.

## Uwaga: `php` w shellu to NIE jest PHP serwisu

Najczęstsza pułapka tego repo. Serwis chodzi na **PHP 8.5**, a domyślne `php`
w powłoce to **8.3**:

```
php                        → 8.3.31   ← NIE tego używamy
/opt/alt/php85/usr/bin/php → 8.5.7    ← runtime docelowy
```

Dlatego `artisan`, `composer` i skrypty PHP wołamy zawsze jawnie:

```bash
/opt/alt/php85/usr/bin/php artisan test
/opt/alt/php85/usr/bin/php artisan migrate --force
/opt/alt/php85/usr/bin/php $(which composer) install
```

Odpalenie przez samo `php` może przejść, a potem wywalić się na produkcji na
składni albo zachowaniu, którego 8.3 nie zna. Parytet z webem trzymamy zawsze.

## Stos

| | |
|---|---|
| Laravel | 13.17 |
| PHP | 8.5.7 (web), 8.3.31 (domyślny w shellu — patrz wyżej) |
| Composer | 2.9.7 |
| Node / npm | 20.20 / 10.8 |
| Baza | MySQL, połączenie `mysql` |
| Sesje / kolejka / cache | sterownik `database` |
| Front | Blade + Livewire. Panele w pełni na Livewire, storefront Blade-first z Livewire punktowo (koszyk). Bez publicznego JSON API. |

## Codzienna praca

```bash
# testy (pełny przebieg to ~10 s)
/opt/alt/php85/usr/bin/php artisan test
/opt/alt/php85/usr/bin/php artisan test --filter=NazwaTestu

# migracje
/opt/alt/php85/usr/bin/php artisan migrate --force

# build front-endu — RAYON_NUM_THREADS=1 jest OBOWIĄZKOWE
RAYON_NUM_THREADS=1 npm run build
```

Bez `RAYON_NUM_THREADS=1` build wywraca się na puli wątków Rolldown i potrafi
zostawić wiszące procesy, które zjadają `fork()` na całym hostingu.

**Nowa klasa Tailwinda działa tylko wtedy, gdy jest w zbudowanym CSS.** Klasa
spoza buildu nie krzyczy — po prostu po cichu nic nie robi. Sprawdzaj przed
wypuszczeniem (uwaga na escapowany dwukropek w CSS):

```bash
grep -F ".sm\:text-2xl" public/build/assets/*.css
```

## Baza produkcyjna

Pracujemy **na żywej bazie**. Komendy niszczące (`migrate:fresh`, `db:wipe`) są
zablokowane na poziomie aplikacji i testów — zasady i uzasadnienie w
[`DB_SECURITY.md`](DB_SECURITY.md). Nie obchodź tych bramek.

## Dokumenty

| Plik | Co zawiera |
|---|---|
| [`FOUNDATION.md`](FOUNDATION.md) | Uniwersalne zasady współpracy: komunikacja, tryb pracy, Git, standardy implementacji. Te same w każdym projekcie. |
| [`CLAUDE.md`](CLAUDE.md) | Specyfika Kramio: domena, stos, decyzje produktowe i techniczne, stan środowiska. W sprawach projektowych ma pierwszeństwo. |
| [`DB_SECURITY.md`](DB_SECURITY.md) | Ochrona produkcyjnej bazy przed przypadkowym wyczyszczeniem. |
| [`docs/specyfikacja.md`](docs/specyfikacja.md) | Punkt wyjścia projektu. Uwaga: żywe ustalenia z `CLAUDE.md` są nadrzędne — spec nie jest wyrocznią. |
