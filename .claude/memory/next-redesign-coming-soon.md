---
name: next-redesign-coming-soon
description: "ZROBIONE 2026-07-12 — redesign ekranu „już wkrótce\" (coming-soon) storefrontu: bez nagłówka/stopki, wyśrodkowany box."
metadata: 
  node_type: memory
  type: project
  originSessionId: 4f430ab3-6e6c-4dbc-9cf3-74aae07a1070
---

**ZROBIONE (2026-07-12):** ekran **„już wkrótce"** (coming-soon) przeprojektowany wg życzenia Rafała.

**Jak wygląda teraz:** bez nagłówka i bez stopki — sam motyw (tło/kolory/fonty wybranego szablonu). W pionie i poziomie wyśrodkowany box `st-card` zgodny ze sklepem; w środku: (logo jeśli jest) → nazwa sklepu `font-serif` w rozmiarze jak nagłówek podstron (`text-4xl sm:text-5xl st-brand`) → cienka linia (jak na stronach tekstowych) → 4 zdania o sklepie w przygotowaniu → „Zapraszamy wkrótce" (serif, `st-brand`) + adres www sklepu (`Request::getHost()`) na dole.

**Jak zrobione technicznie:**
- Layout `resources/views/components/layouts/storefront.blade.php` dostał prop `bare` (`@unless($bare)` wokół `<header>` i `<footer>`). Coming-soon używa `:bare="true"`.
- Widok: `resources/views/storefront/coming-soon.blade.php` (przepisany).
- Uwaga Tailwind (pamięć: tylko klasy z buildu): `sm:px-12`, `sm:py-16`, `sm:text-lg` NIE były w buildzie → użyto `sm:p-14` i `text-base`. Jak chcesz inne klasy — najpierw sprawdź build albo przebuduj.
- Zdanie „Zajrzyj tu ponownie **już wkrótce**" celowo zawiera frazę, na którą asertują testy (`HomeTest`, `ProductListingTest`).

**Podgląd na żywo:** renderuje się pod normalnymi adresami sklepu, gdy sklep `draft` (brak aktywnych produktów) i oglądający to gość. Testowany na `wlodarczyk-i-syn-83638` — produkt id=3 zdezaktywowany w tej sesji, sklep w `draft`. PAMIĘTAĆ o przywróceniu `is_active=1` po podglądzie (żywa baza) → sklep sam wróci na `active`.

Powiązane: [[shop-visibility-auto-publish]], [[storefront-draft-preview]], [[plan-storefront-editorial-and-pages]].
