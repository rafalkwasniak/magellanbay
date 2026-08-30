---
name: pricing-packages
description: "Ceny 3 pakietów (roczne): Kram 0 / Stragan 75 / Pawilon 150 zł/mc brutto, rok=10× (2 mc gratis), value-based"
metadata: 
  node_type: memory
  type: project
  originSessionId: 2e8fa019-7e9e-4cb3-ab6b-a93b0b13411b
  modified: 2026-08-12T17:57:44.244Z
---

USTALONE 2026-07-19 (rozmowa strategiczna, nie kod). Cennik pakietów [[plan-packages]] — na nim opieramy egzekwowanie.

**Ceny (BRUTTO, VAT 23%), rozliczenie WYŁĄCZNIE roczne, rok = 10× miesiąc (2 miesiące gratis):**
- **Kram — 0 zł** (darmowy na zawsze, ≤24 produkty; robi akwizycję i segmentację).

**Limity produktów ROZCIĄGNIĘTE 2026-08-09: 24 / 72 / 240** (było 24/48/96). Powód (Rafał): „nawet najdroższa opcja może kogoś nie pomieścić i odpadnie; 1500 zł/rok warto, czy ma 50 czy 200 produktów". Limit NIE jest motorem konwersji (na płatny wypychają płatności/kurier/faktury, na Pawilon edycja zamówień i kody) — pracuje dopiero w GÓRNYM OGONIE, gdzie odsiewa leada przed rejestracją. Dlatego sufit rozciągnięty NIERÓWNOMIERNIE: Stragan ostrożnie (96 zabierałoby powód, dla którego ktoś z 60 pozycjami bierze Pawilon), Pawilon mocno. Kram bez zmian — to jest bramka. Koszt bliski zeru: 8 zdjęć × ~250 KB WebP = ~2 MB/produkt.
- **Stragan — 75 zł/mc → 750 zł/rok** (bez rabatu 12×=900). Netto ~610/rok, ~48,78/mc.
- **Pawilon — 150 zł/mc → 1500 zł/rok** (bez rabatu 1800). Netto ~1220/rok, ~97,56/mc.

Drabinka czyste 2,0× (75→150). Reguła cennika: roczna kwota round-50 brutto, miesięczna = rok÷10. Realny przepływ /mc = rok÷12 (Stragan 62,50, Pawilon 125), bo płacą rocznie z 2 mc gratis.

**Dlaczego wysoko, nie „po taniości" (value-based, decyzja Rafała):** darmowy Kram bierze na siebie segmentację i rolę trialu, więc płatne tiery nie muszą łapać ceną — kto potrzebuje FV/wysyłek/płatności/mailingu, ten zapłaci; za tanio = „łatka gorszego" (price-quality signaling). Podnoszenie ceny później jest trudne (wymaga grandfatheringu), obniżka łatwa — „zawsze można obniżyć". Wciąż grubo pod rynkiem: Shoper Standard 299/mc, Pawilon 150 to jego połowa; RedCart Platinum ~125.

**Bezpiecznik:** gdyby dane pokazały odbijanie się od Straganu na CENIE (nie potrzebie), zejście na 60/120 albo 45/90 jest łatwe (obie rozważane, round-50 zachowane).

**Realizm liczb:** ~22 płacących w splicie 50/50 = ~2 tys. zł/mc realnego przychodu brutto (28 dla 2 tys. netto). 30 płacących to cel niski/osiągalny — trudność w dystrybucji, nie w popycie. Realny rozkład raczej ~70% Stragan / 30% Pawilon, nie 50/50.

## Dlaczego NIE ma planu miesięcznego (Rafał, 2026-08-12)

Nie chodzi o kod ani o bramkę płatności, tylko o **koszt księgowości**: miesiąc to mała kwota, a każda faktura kosztuje osobno. Przy 50 klientach rozliczenie miesięczne to 600 dokumentów rocznie, które księgowa policzy pojedynczo — zysk topnieje, a Rafał nie chce tego przerzucać na klientów.

**Rozważany kompromis: PÓŁROCZE 450 / 900 zł** (6 × stawka miesięczna, bez rabatu). Dwie zalety, obie warte zapamiętania:
1. **Domyka sprawę przekreślonej ceny** ([[open-monthly-plan-vs-struck-price]]): rok kupiony na dwa razy daje dokładnie 900 / 1800 zł, więc przekreślona kwota przestaje być liczbą wyliczoną, a staje się ceną realnie dostępną w cenniku.
2. **Sześciokrotnie mniej dokumentów niż miesięcznie** — 100 faktur rocznie przy 50 klientach zamiast 600.

Wtedy drabinka czyta się sama: 75 zł/mies. jako stawka odniesienia, 450 za pół roku bez rabatu, 750 za rok z dwoma miesiącami gratis.

**Rama wartości (argument Rafała):** sklep na WordPressie z WooCommerce to ~5 tys. zł netto dla agencji ZA SAMO POSTAWIENIE, plus coroczny serwer, plus aktualizacje wtyczek i telefon do kogoś przy każdej awarii. Na tym tle 1500 zł/rok z utrzymaniem w cenie nie jest drogie.

Cennik = placeholder do egzekwowania; realny billing (kto/jak pobiera pieniądze) to osobny temat. Per-sklep nadpisanie ceny/funkcji: [[plan-per-shop-custom-pricing]].
