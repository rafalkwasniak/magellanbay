---
name: gotcha-dedicated-mode-platform-leftovers
description: "WZORZEC BŁĘDU w trybie dedykowanym: rzeczy, które w Kramio należą do PLATFORMY, w sklepie jednego klienta są ciche i nieprawdziwe. Trafione 3× w jednej sesji (05.09)."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 43b6e9cc-a378-4a18-8cf7-b97d8a63be16
  modified: 2026-09-05T14:38:09.401Z
---

Przełącznik `SHOP_MODE=dedicated` wygasza **ekrany** platformy (rejestracja, pakiety, konsola admina). Nie rusza natomiast wszystkiego, co w kodzie **zakłada istnienie platformy** — a tego jest więcej i jest cichsze.

**Wspólny kształt błędu: kod nie wywala się, tylko mówi nieprawdę.** Żaden z trzech przypadków nie dał błędu ani pustego ekranu.

## Trafione 05.09.2026 — trzy w jednej sesji

| Co | Objaw w trybie dedykowanym |
|---|---|
| `Shop::host()` | doklejał slug do domeny → `magellan.magellan.kwasniak.org`. Leciało do **maili o zamówieniu, linku zwrotu, linku płatności, mapy strony, webhooka Paynow, grafiki OG**. Widoczne dopiero przy pierwszym prawdziwym zamówieniu |
| `/informacje/polityka-prywatnosci` | renderowało dokument **PLATFORMY** (`LegalDocument`), czyli politykę cudzego podmiotu jako politykę sklepu klienta. Odnośnik `informationMenu()` dokleja ZAWSZE |
| §11 wzoru regulaminu | bezwarunkowe „sklep działa na platformie Kramio, której operator przetwarza dane jako podmiot przetwarzający" — nieprawda, gdy nie ma platformy |

## Reguła na przyszłość

**Przy każdym nowym ekranie i dokumencie zadać jedno pytanie: czy to zdanie/adres/link jest prawdziwe, gdy NIE MA platformy?**

Trzy miejsca, gdzie ten błąd się gnieździ:
1. **Adresy budowane z sluga** — w dedykowanym sklep stoi na domenie głównej.
2. **Treści należące do operatora** — polityka prywatności, dokumenty zgód, stopki. W dedykowanym administratorem danych i gospodarzem serwera jest jedna osoba: właściciel.
3. **Zdania o „platformie" i „powierzeniu"** w dokumentach prawnych.

**Jak szukać:** `grep -rn "Kramio" resources/views/ app/` oraz przejrzeć wywołania `->host()` i wszystko, co czyta `LegalDocument`.

## Dlaczego to boli akurat tutaj

Klient dostaje sklep na własny serwer i **nie wolno mu zmieniać kodu** (ingerencja = utrata gwarancji — patrz [[plan-magellan-bay-separate-project]]). Nie poprawi więc sobie ani adresu w mailu, ani polityki prywatności. Co wyjedzie, to zostaje.

Powiązane: [[plan-magellan-legal-documents]], [[multitenant-subdomain-architecture]].
