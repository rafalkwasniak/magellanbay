---
name: handoff-2026-07-11-product
description: "3. sesja 2026-07-11 — redesign wykazu i karty produktu (nagłówki, boxy Filtruj/Sortuj, układ 3/2, Cena/Dostępne/koszyk, Podobne produkty)."
metadata: 
  node_type: memory
  type: project
  originSessionId: 441e61d2-3b99-4410-a609-180450006054
---

3. sesja 2026-07-11 (storefront, sklep testowy `ilikemybike.kramio.pl`). Wszystko wypchnięte na `main`.

**Wykaz `/produkty`:**
- Jednolity nagłówek jak inne podstrony: breadcrumb → tytuł → pozioma linia (`st-border border-t`). Tytuł = „Produkty" (konwencja), nie „Wszystkie produkty".
- „Filtruj" (chmura tagów) i „Sortuj" jako kafle `st-card st-border rounded-3xl` OBOK siebie (`flex sm:flex-row`); Filtruj `flex-1`, Sortuj `sm:w-64`. Sortuj = natywny `<select>` (onchange → `window.location`), nie pigułki. Równa wysokość kafli przez domyślny flex `stretch` (NIE dawać `items-start`).

**Karta produktu:**
- Ten sam nagłówek: breadcrumb → nazwa (serif `text-4xl`) → linia; nazwa przeniesiona NA GÓRĘ (nie w kolumnie szczegółów).
- Siatka `md:grid-cols-5`: zdjęcie `md:col-span-3` (szersze), zakup `md:col-span-2` po prawej.
- Prawa kolumna: „**Cena:**" (etykieta lżejsza) + kwota, „najniższa cena z 30 dni" (Omnibus), „**Dostępne: X szt**" (z `product->stock`, gdy `track_stock`), na końcu tylko przycisk „Do koszyka" wyrównany do prawej (`flex justify-end`, `<livewire:add-to-cart :compact="true">` — compact, żeby nie dublował wewnętrznej linii dostępności).
- Kafel tagów przemianowany na „**Podobne produkty**" (nadal same pigułki tagów linkujące do wykazu; realne miniatury produktów = osobny większy temat na później).
- „**O produkcie**" = opis na całą szerokość POD siatką (`st-prose`).

**Uwagi:**
- Znów potknięcie o [[vite-build-rayon-threads]]: `md:grid-cols-5`/`md:col-span-*` nie było w buildzie → trzeba było `RAYON_NUM_THREADS=1 npm run build`. `public/build` jest gitignorowany, serwowany lokalnie z katalogu domeny (rebuild od razu żyje na produkcji).
- Omnibus [[omnibus-lowest-price-30d]] potwierdzony: linia „najniższa z 30 dni" NIE pokazuje się przy podwyżce ceny (tylko przy realnej obniżce, gdy bieżąca < wcześniejsza) — to poprawne.
- Testów nie dotykaliśmy (same zmiany Blade + 1 flaga compact w wywołaniu Livewire).
