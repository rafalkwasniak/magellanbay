---
name: plan-shop-self-deletion
description: "WDROŻONE 2026-08-04 (commit 61ea516): usunięcie sklepu przez sprzedawcę (karencja 7 dni) i przez admina (od ręki). Jeden silnik ShopEraser, kwarantanna adresu 90 dni. Zawiera wszystkie rozstrzygnięcia Rafała i pułapki kaskady FK."
metadata: 
  node_type: memory
  type: project
  originSessionId: e2788dc1-1abc-41e9-8ac5-80ba9d1d6a4a
  modified: 2026-08-04T15:41:48.542Z
---

**WDROŻONE 2026-08-04**, commit `61ea516`, suita 1293 → 1327 testów. Zgłoszone przez Rafała 31.07 („musimy dodać opcję usunięcia własnego sklepu") tuż po wpuszczeniu testerów; wcześniej kasowanie sklepu było ręczną robotą w bazie.

## Rozstrzygnięcia Rafała (2026-08-04) — NIE odgrzewać bez powodu

| Pytanie | Decyzja |
|---|---|
| Sklep z zamówieniami | Usuwamy wszystko. Dokumenty księgowe żyją w Fakturowni — osobny system, obowiązek po stronie sprzedawcy |
| Zakres | Sklep **i** konto razem (rejestracja tworzy sklep, konto bez sklepu jest martwe) |
| Bezpiecznik | Karencja 7 dni z możliwością cofnięcia |
| Adres subdomeny | Kwarantanna 90 dni |
| Opłacony pakiet | Przepada; ekran mówi to wprost PRZED kliknięciem |
| Admin | Kasuje natychmiast, z pominięciem karencji |
| Wejście u sprzedawcy | „Mój sklep", dół strony → **osobny ekran** `/sprzedawca/usun-sklep` (nie sekcja in-place) |

Wejście przez „Mój sklep", a nie `/profil`, bo profil jest **wspólny dla sprzedawcy i admina** — danger zone wymagałby tam warunku roli.

## Co powstało

- `App\Services\ShopEraser` — `schedule()` / `cancel()` / `erase()`. Jeden silnik dla obu dróg.
- `shops.deletion_scheduled_at` (NIE mass-assignable) + tabela `reserved_slugs`.
- Komenda `shops:purge` w schedulerze o 06:20 — kasuje po karencji i zwalnia wygasłe rezerwacje.
- Konsola admina: danger zone w karcie sklepu (pole na nazwę, walidacja SERWEROWA), plakietka „usunięcie 12.08" na liście, przycisk „Zatrzymaj usunięcie".
- Sprzedawca: ekran z rachunkiem strat, dwa bezpieczniki (nazwa sklepu + hasło), baner nad każdym ekranem panelu.

## Pułapki, na których to stoi

- **Kaskada FK nie odpala hooka `ProductImage::deleting`** — pliki trzeba zebrać PRZED usunięciem i skasować **po commicie** transakcji (rollback nie przywróci pliku). Katalogi: `products/{id}`, `shops/{id}`, `og/{id}` i **`users/{id}`** (awatar — o tym katalogu łatwo zapomnieć).
- **Mail pożegnalny MUSI mieć `shop_id = null`** — inaczej wpada we własną czystkę `email_messages` i nigdy nie wychodzi z outboxu.
- **Tabele bez FK do sprzątnięcia ręcznie**: `email_messages` (goły indeks `shop_id`), `sessions` (`user_id` należy tylko do guarda `web` — klienci storefrontu mają tam null, więc kasowanie po `user_id` jest bezpieczne), `password_reset_tokens` (po e-mailu).
- **Sklep bez właściciela nie istnieje** — `shops.owner_id` jest NOT NULL, więc gałąź „sierota" z czystki 31.07 jest nieosiągalna. W serwisie został null-guard, bez testu.
- **`ensure.consents` przekierowuje testy sprzedawcy na `/zgody`** — w testach `User::factory()->consented()`. Pierwsze uruchomienie: 10 z 13 testów na 302.
- Storefront gaśnie w chwili zlecenia (bramka w `ResolveShop`), **także dla właściciela**. To świadomie zamyka pytanie „co, jeśli w karencji wpłynie zamówienie".

Powiązane: [[plan-customer-accounts]] (usuwanie konta klienta — logout PRZED delete), [[plan-admin-panel-and-landing]], [[plan-package-payments]] (zasada „bez zwrotów gotówki"), [[handoff-2026-08-04]].
