---
name: plan-magellan-legal-documents
description: "W TRAKCIE (od 05.09): regulamin i polityka prywatnosci sklepu Magellan Bay. Podstawy istnieja — kreator regulaminu w kodzie + polityka Kramio w docs/prawne/. KLUCZOWE: produkty personalizowane WYLACZONE z prawa odstapienia."
metadata: 
  node_type: memory
  type: project
  originSessionId: 43b6e9cc-a378-4a18-8cf7-b97d8a63be16
  modified: 2026-09-05T12:07:16.407Z
---

Dokumenty dla **sklepu dedykowanego Magellan Bay** (katalog `magellan.kwasniak.org`). Nie mylić z dokumentami platformy Kramio — tu **znika pośrednik**, zostaje relacja sklep ↔ kupujący.

## Z czego budujemy (nie od zera)

| Dokument | Podstawa |
|---|---|
| Regulamin sklepu | **kreator w kodzie**: `resources/views/seller/legal/templates/regulamin.blade.php` (236 linii, realny szablon, wypełniany odpowiedziami — kolumny `terms_answers`, `terms_template_version` na `pages`) |
| Polityka prywatności sklepu | `docs/prawne/polityka-prywatnosci.html` — **nasza** polityka Kramio, do oczyszczenia z warstwy platformy |

Decyzja Rafała 05.09: „byle było coś fajnego na początek, a nie placeholder" — **klient i tak to zweryfikuje**, więc celujemy w porządny punkt wyjścia, nie w dokument gotowy do podpisu.

Co wypada w sklepie dedykowanym: regulamin platformy, polityka platformy, moduł DSA (art. 16/17). Klient nie hostuje cudzych treści — patrz [[legal-dsa-hosting-classification]].

## PRAWO ODSTĄPIENIA — najważniejsza rzecz merytoryczna

**Produkty personalizowane są wyłączone z prawa odstąpienia** (art. 38 pkt 3 u.p.k. — rzecz nieprefabrykowana, wykonana według specyfikacji konsumenta).

W Magellanie to nie jest przypis, tylko **oś katalogu**: magnes z czyimś imieniem albo z grawerem nie podlega zwrotowi w 14 dni, zwykły magnes z Gdańska — owszem. Ten sam sklep sprzedaje jedno i drugie.

W kodzie jest już wszystko, czego to wymaga:
- `Product::withdrawal_excluded` — flaga per produkt,
- kreator regulaminu **pyta** o towary wyłączone z odstąpienia,
- `DemoSeeder` ustawia flagę na produktach personalizowanych (i test tego pilnuje).

Nie mylić z [[legal-consumer-returns-withdrawal]] — tamto opisuje politykę zwrotów Kramio we wszystkich pakietach.

## Model sprzedaży, który dokumenty muszą opisać

Magnesy podróżnicze, personalizacja 2-poziomowa (nadruk awersu z formatek + grawer rewersu: grafika ALBO tekst, nigdy oba), cena składana z 4 części, **licencjodawcy** (organizatorzy biegów inkasujący opłatę za logotyp). Do tego produkty zwykłe. Szczegóły: [[plan-magellan-bay-separate-project]].

## Do przepisania przy okazji: mail aktywacyjny

`App\Services\ActivationMailer` mówi językiem platformy: „dziękujemy za rejestrację", „postawisz swój sklep w kilka minut". **W sklepie dedykowanym nikt się nie rejestrował.**

Dlatego `DeploymentSeeder` **wypisuje link aktywacyjny na konsolę zamiast wysyłać ten mail** — świadoma decyzja, opisana w jego docblocku. Po poprawieniu treści można to zmienić.

Powiązane: [[plan-seller-legal-templates]] (wzory dla sprzedawców Kramio — ta sama robota, inny odbiorca), [[legal-audit-2026-08-15]].
