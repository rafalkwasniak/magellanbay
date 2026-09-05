# Specyfikacja klienta — Magellan Bay

**Tekst źródłowy od klienta**, przepisany z dokumentu przekazanego przez Rafała
05.09.2026. Do tej pory jedynym śladem po nim było moje streszczenie w pamięci
asystenta — i to za mało: przy pierwszym pytaniu „czy klient o tym pisał"
okazało się, że nie ma czego sprawdzić.

Pominięta jest wyłącznie końcowa część dokumentu z listą gotowych komponentów
Kramio (analiza RK) — poza sekcją „Do zrobienia", bo to ona wyznacza zakres
Etapu 2.

> **Uwaga:** `docs_mod/` wycinamy z archiwum wdrożeniowego (patrz `SETUP.md`).
> Ten plik zostaje po naszej stronie.

---

## Tekst klienta

Strona ma obsługiwać sklep internetowy sprzedający magnesy o różnej tematyce.
Koncepcja opiera się o dwa elementy: indywidualną 2-poziomową personalizację
pojedynczych produktów (magnesów), oraz system określania ceny końcowej produktu
uwzględniający koszt licencji za użyte na magnesie logotypy firm współpracujących
ze sklepem.

**Przykład:** Klient kupuje kamienny magnes, na którego awersie znajduje się
oficjalny licencjonowany logotyp 7 Wałbrzych Maratonu, a na rewersie
wygrawerowane zostaje jego imię, nazwisko, uzyskany czas oraz sentencja „Do mety
albo śmierć". Na cenę końcową magnesu składa się koszt magnesu plus koszt
wykonania grawerki, plus koszt licencji określony przez właściciela logotypu —
ta ostatnia część ceny trafi do organizatora jako jego pasywny zysk.

W sklepie znajdują się zarówno produkty **nie personalizowane (mniejszość)**, jak
i **personalizowane (większość)**. Produkty występują w kilku kategoriach:
w liniach rodzajów produktów (metalowe, plastikowe, 3D), w grupach tematycznych
(Flagi Państw, Wielkie Maratony Europy, Zabytki Polski, Obiekty UNESCO) oraz
w grupach geograficznych (Afryka, Włochy, Warszawa).

Jeden produkt może mieć zero, jeden lub kilka elementów personalizacji.

Personalizacje składają się z dwóch poziomów: **napisów drukowanych na awersie**
(pola tekstowe na „formatce" przypisanej do produktu) oraz **grawerki na
rewersie** (plik graficzny z bazy grawerek albo tekst wpisany przez klienta —
**nie przewiduję możliwości łączenia obu opcji**).

Każdy produkt i każda grafika grawerki mogą mieć przypisane kwoty licencji.
**Jeżeli na jednym produkcie wystąpią dwa logotypy tego samego klienta (na
awersie jako napis, i na rewersie jako grafika) to cena nie jest sumowana, tylko
przyjmuje się wyższą z nich.**

### Cena końcowa — cztery składowe

1. koszt produktu (**zawiera koszt personalizacji awersu, który nie jest
   wykazywany osobno**),
2. ewentualny koszt licencji za logotyp zastosowany na awersie,
3. ewentualny koszt zamówienia grawerki,
4. ewentualny koszt licencji na logotyp zastosowany w grawerce.

Każda z nich **musi być widoczna podczas procesu składania zamówienia**.

### Potrzebne tablice

- produktów,
- formatek personalizujących (zestaw pól tekstowych),
- grafik do grawerki — nazwa, plik graficzny, cena licencji,
- licencji, czyli firm udzielających praw do logotypów,
- kategoria 1 — **rodzaje** produktów (Metalowy Token, Wklejka z ramką, Kamień),
- kategoria 2 — **tematyka** (Biegi, Szczyty Górskie, UNESCO, Flagi),
- kategoria 3 — **geografia** (Pcim, Warszawa, Włochy, Afryka) — „tu chyba
  wskazane wielopoziomowe zagłębienie".

Sklep musi obsługiwać koszyk, płatności i dostawę InPostem. Potrzebna jest też
możliwość **zablokowania sprzedaży całej serii** (rodzaju produktów) jednym
przyciskiem, z komunikatem o terminie wznowienia.

**Musi być także ekran prezentujący wszystkie produkty tylko wybranej firmy**,
np. Stowarzyszenia Tychy Razem.

### Cechy produktu

nazwa skrócona · nazwa pełna · opis · grafiki · cena · kategoria 1 (jednokrotna)
· kategoria 2 (wielokrotna) · kategoria 3 (wielokrotna) · nr formatki
personalizacji (zero = brak personalizacji) · czy może być grawerowany ·
dostępność · **nr firmy, do której dowiązana jest licencja na logotyp** ·
**koszt licencji** · ile sprzedano (opcja) · ile oglądano (opcja).

### Formatki

Do jednego produktu może być przypisana **tylko jedna formatka**, ale formatka
może składać się z kilku pól tekstowych, każde o określonej maksymalnej długości.
Przykłady: rok (4 znaki) · nazwa + rok (25 i 4) · opis (30) · rok, miasto,
dystans, opis, wynik.

### Grawerka

Klient **nie musi** wybierać grawerki, domyślnie jest pusta. Może wybrać grafikę
z predefiniowanych rysunków z logotypami albo podać napis. Wybranie grawerki
dolicza koszt grawerki i ewentualny koszt licencji.

### Przykładowe zakupy

| # | Produkt | Rozbicie | Razem |
|---|---|---|---|
| 1 | Metalowy 49 zł, logotyp 7 Maraton Wałbrzych 25 zł, grawer z **bezpłatnym** logiem | 49 + 25 + 10 + 0 | **84 zł** |
| 2 | Kamienny 30 zł, bez personalizacji, grawer **tekstowy** | 30 + 0 + 10 + 0 | **40 zł** |
| 3 | Ramka 3D, formatka 3 pola, bez grawerki i licencji | 20 + 0 + 0 + 0 | **20 zł** |
| 4 | Metalowy 49 zł, licencja logotypu 25 zł **oraz** grafiki graweru 40 zł — **ten sam** organizator | 49 + **40** + 10 | **99 zł** |

> **SPRZECZNOŚĆ W PRZYKŁADZIE 3:** treść mówi „kosztuje w sklepie 15 pln",
> wyliczenie „+ 20 pln za produkt" i suma 20 zł. Przyjęliśmy 20 (kwota użyta
> w wyliczeniu). **Do potwierdzenia z klientem.**

---

## Zakres Etapu 2 (ustalony z klientem)

Personalizacja nadruku · grawerka rewersu · cena z czterech składników · koszyk
rozpoznający konfigurację · kartoteka licencjodawców · **ekran produktów jednej
firmy** · rozliczenia z licencjodawcami *(do ustalenia — nie ma tego w opisie
klienta, ale bez tego model się nie domyka)* · **arkusz produkcyjny** *(również
nieobecny w opisie, a niezbędny do realizacji zamówień)* · trzy niezależne
podziały katalogu · wstrzymanie sprzedaży serii · polityka prywatności ·
liczniki przy produkcie *(opcjonalne)*.

---

## Jak się to ma do tego, co zbudowano

Stan na 05.09.2026, po Etapie 2 krok 1–4 i ekranach A–B.

### Zgodne

| Wymaganie | Gdzie |
|---|---|
| Formatka: pola tekstowe z limitami | grupa `text` + `option_fields` |
| Grawerka: grafika **albo** tekst | wykluczanie się grup (`excludes_group_id`) |
| Grawerka domyślnie pusta | grupa nieobowiązkowa |
| Cztery składowe ceny, widoczne przy zamawianiu | `ProductConfiguration::breakdown()` + rozbicie na karcie |
| Dwie licencje tej samej firmy → wyższa | `LicenceFees::reduce()` |
| Dwa magnesy z różnym grawerem = dwie pozycje | klucz pozycji koszyka |
| Wyłączenie z prawa odstąpienia per produkt | `products.withdrawal_excluded` |

**Cztery przykłady zakupu klienta są przepisane na testy co do złotówki** —
`tests/Feature/Product/SpecificationExamplesTest.php`.

### Poprawione po lekturze specyfikacji

**Licencja logotypu awersu należy do PRODUKTU, nie do wyboru kupującego.**
Zbudowałem ją najpierw jako grupę opcji, w której klient wybiera logotyp. To
opisywało inny sklep — taki, w którym kupujący dobiera sobie cudze znaki
towarowe do dowolnego produktu. Poza rozminięciem się z zamówieniem jest to
model, w którym łatwo o użycie logotypu bez pokrycia w umowie licencyjnej.
Przeniesione na `products.licensor_id` + `products.licence_fee_gross`.

Bez tej poprawki **przykładu 4 nie dało się nawet wyrazić**.

### Do rozstrzygnięcia

1. **Sprzeczność 15/20 zł** w przykładzie 3.
2. **Koszt nadruku awersu = 0.** Specyfikacja mówi wprost, że jest wliczony
   w cenę produktu i nie wykazywany osobno. Silnik to obsługuje (dopłata grupy
   zero → brak wiersza w rozbiciu), ale dane pokazowe miały 10 zł — poprawione.
3. **Jedna formatka na produkt.** Specyfikacja: „do jednego produktu może być
   przypisana tylko jedna formatka". Nasz model dopuszcza kilka grup — to
   nadzbiór, więc nic nie blokuje, ale panel powinien prowadzić za rękę
   w stronę jednej.

### Czego brakuje w planie prac

Dwie pozycje z zakresu Etapu 2, których nie ująłem w kolejności A–E:

- **Ekran produktów jednej firmy** — w specyfikacji wprost („musi być także
  ekran…"). Partner ma dostać link do wszystkich magnesów ze swoim logotypem.
- **Arkusz produkcyjny** — dane do nadruku i plik do graweru per sztuka. Bez
  niego zamówienia są niewykonalne, co Rafał zauważył już przy wycenie.
