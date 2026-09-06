# Pytania do klienta — Magellan Bay

Stan na 06.09.2026, po domknięciu Etapu 2 (kroki A–H).

Wszystko poniżej **działa już w sklepie** — pytamy nie dlatego, że czegoś
brakuje, tylko dlatego, że w kilku miejscach przyjęliśmy założenie tam, gdzie
opis był niejednoznaczny albo milczał. Każde z tych założeń siedzi w jednym
miejscu w kodzie i da się zmienić bez przebudowy.

Dokument ma dwie części: **A** — rzeczy, które musimy wiedzieć, bo dotyczą
pieniędzy albo treści widocznej dla kupujących. **B** — rzeczy, które
poprawimy, gdy będą materiały.

---

## A. Pytania, na które czekamy

### A1. Sprzeczność w przykładzie 3 specyfikacji

W opisie przykładowego zakupu nr 3 (ramka 3D z formatką, bez grawerki
i licencji) treść mówi, że produkt **kosztuje w sklepie 15 zł**, wyliczenie
podaje **+ 20 zł za produkt**, a suma końcowa to **20 zł**.

**Przyjęliśmy 20 zł** — kwotę użytą w wyliczeniu i w sumie.

> **Pytanie:** która kwota jest właściwa? To wpływa wyłącznie na zrozumienie
> przykładu, nie na działanie sklepu — ale wolimy mieć pewność, że dobrze
> czytamy model cenowy.

### A2. Czy logotyp na awersie ma być obowiązkowy?

Dziś **licencja logotypu awersu jest cechą produktu**: właściciel wpisuje przy
magnesie, czyj znak na nim jest i ile kosztuje licencja. Kupujący tego nie
wybiera — magnes po prostu ten znak ma.

Wynika to wprost z opisu („nr firmy, do której dowiązana jest **ewentualna**
licencja na logotyp"), ale słowo „ewentualna" da się czytać dwojako.

> **Pytanie:** czy zdarza się magnes, na którym kupujący **wybiera**, czyj
> logotyp ma być na awersie? Jeśli tak, to inny mechanizm i trzeba go dołożyć.

### A3. Arkusz produkcyjny — jak wygląda praca w pracowni?

To jedyna funkcja, której opis nie zawierał wcale. Dopisaliśmy ją, bo bez niej
zamówienia na personalizowany towar nie da się wykonać.

**Zrobiliśmy świadomie najmniejszą wersję:** wydruk jednego zamówienia — co,
ile sztuk, jaki nadruk, jaka grawerka. Bez cen (arkusz może iść do
podwykonawcy). Tekst do wykonania powiększony i monospace, żeby nie pomylić
„l" z „1".

> **Pytania:**
> 1. Czy grawerkę i nadruk wykonuje Pan sam, czy zleca na zewnątrz?
> 2. Czy potrzebne jest **zbiorcze** zestawienie — np. „wszystkie grawerki do
>    zrobienia dziś, pogrupowane po grafice" — czy kartka przy zamówieniu
>    wystarcza?
> 3. Czy arkusz ma zawierać dane kupującego (imię, adres), czy ma zostać
>    anonimowy?
>
> Nie zgadywaliśmy kształtu, bo zależy on w całości od organizacji pracy,
> a zbudowanie niewłaściwego zestawienia to strata czasu po obu stronach.

### A4. Rozliczenia z partnerami — pięć przyjętych zasad

Moduł liczy, komu i ile należy się za dany miesiąc. Przyjęliśmy:

| Zasada | Jak jest dziś |
|---|---|
| Co wchodzi | wszystkie zamówienia **poza anulowanymi** |
| Zamówienia nieopłacone | wchodzą do kwoty, ale są **wykazane osobno** |
| Zwrot towaru | **odejmuje** — oddany magnes nie generuje opłaty |
| Okres | po **dacie złożenia** zamówienia |
| Kwoty | **brutto** |

> **Pytania:**
> 1. Czy partnerowi płaci Pan za zamówienia **zapłacone**, czy za **złożone**?
>    Dziś pokazujemy obie liczby i decyzja należy do Pana — ale jeśli zasada
>    jest stała, możemy ją wpisać na sztywno.
> 2. Czy rozliczenie ma iść **miesięcznie**, czy w innym rytmie (kwartał,
>    po zakończeniu wydarzenia)?
> 3. Czy w zestawieniu dla partnera ma być widoczna **nazwa produktu**, czy
>    tylko liczba sztuk i kwota?

### A5. Reguła „nie sumujemy, liczy się wyższa" — potwierdzenie

Zaimplementowaliśmy tak, jak w opisie: **dwie opłaty tej samej firmy** na
jednym produkcie nie sumują się (liczy się wyższa), a opłaty **różnych firm**
sumują się normalnie.

> **Pytanie:** czy przy trzech opłatach tej samej firmy również liczy się
> tylko najwyższa? (Tak to działa — chcemy potwierdzić, że o to chodziło.)

### A6. Limity długości tekstów

Formatki personalizacji mają limity znaków ustawiane per pole — to jest
w Pana rękach. Ale kilka limitów systemowych zostało na wartościach odziedziczonych
i wypada je przejrzeć na realnym katalogu:

- długość opisu produktu,
- długość opisu sklepu,
- ile produktów naraz można wyróżnić na stronie głównej.

> **Pytanie:** czy po wgraniu katalogu może Pan zasygnalizować, gdyby któryś
> z tych limitów okazał się za ciasny?

---

## B. Czekamy na materiały

### B1. Identyfikacja wizualna

Sklep działa dziś na **tymczasowej szacie** — jasnej, spokojnej, celowo
neutralnej. Logo jest robocze.

> **Potrzebujemy:** logo w pliku wektorowym (SVG/AI/PDF) albo w PNG minimum
> 1000 px szerokości, na przezroczystym tle. Jeśli są ustalone kolory marki —
> ich kody. Jeśli nie ma, zaproponujemy.

### B2. Dane firmy

W stopce, mailach, regulaminie i polityce prywatności stoją dziś **znaczniki
w nawiasach kwadratowych** — `[NAZWA FIRMY]`, `[NIP]`, `[ADRES]`. Są tak
napisane, żeby dało się je wyłapać wzrokiem.

> **Potrzebujemy:** pełna nazwa działalności, adres, NIP, REGON (jeśli jest),
> adres e-mail do kontaktu, telefon (jeśli ma być publiczny), numer konta do
> przelewów.

### B3. Karta sklepu na Facebooku

Grafika, która pokazuje się przy udostępnieniu linku, jest dziś **wyłączona** —
żeby nie wystawiać domyślnej, nie Pana.

> **Potrzebujemy:** decyzji, czy generujemy ją z logo, czy dostarczy Pan własną
> (zalecane 1200 × 630 px).

### B4. Katalog startowy

W sklepie stoją **przykładowe podziały** — 5 rodzajów, 6 tematyk, 10 pozycji
geografii z zagnieżdżeniem (Europa → Polska → Gdańsk). To materiał poglądowy,
żeby było widać, jak katalog działa.

> **Potrzebujemy:** rzeczywistych list. Zwłaszcza **geografii** — trzeba
> ustalić, jak głęboko schodzimy (kontynent → kraj → miasto? czy od razu
> miasta?) i czy porządkujemy je alfabetycznie, czy ręcznie.

### B5. Dokumenty prawne

Regulamin i polityka prywatności są napisane i czekają na weryfikację.

> **Uwaga merytoryczna:** produkty personalizowane są **wyłączone z prawa
> odstąpienia** (art. 38 pkt 3 ustawy o prawach konsumenta) — magnes z cudzym
> imieniem nie nadaje się do odsprzedaży. Jest to w regulaminie i przy każdym
> produkcie da się to zaznaczyć osobno.
>
> **Pytanie:** czy wszystkie personalizowane magnesy mają być wyłączone
> ze zwrotu, czy tylko część?

---

## Czego NIE pytamy, bo jest zrobione

Dla porządku — te rzeczy z opisu działają i nie wymagają decyzji:

formatki nadruku z limitami znaków · grawerka z biblioteki grafik albo własny
tekst klienta · wykluczanie się grawerki graficznej i tekstowej · cena składana
z czterech części, każda widoczna przy zamawianiu · koszyk rozpoznający
konfigurację · kartoteka partnerów · katalog w trzech niezależnych podziałach ·
wstrzymanie sprzedaży całej serii z komunikatem · strona z produktami jednej
firmy · koszyk, płatności, dostawa InPostem.
