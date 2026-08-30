---
name: plan-shop-og-image-redesign
description: "DO PRZEMYŚLENIA (Rafał śpi z tematem 31.07): grafika promująca sklep jest za uboga — logo na bieli. Cztery warianty rozwiązania na stole, faworyt Rafała = 4 gotowe tła per szablon + napisy przez GD. Zawiera pułapki i konkret z CURVII."
metadata: 
  node_type: memory
  type: project
  originSessionId: 5c4f5743-5e78-4788-bcf6-2be5ad5638e4
  modified: 2026-08-02T14:51:30.074Z
---

## ✅✅ MODUŁ WDROŻONY 02.08.2026 (`4c08830`, `41c3f50`) — testy 1248

Karta sklepu DZIAŁA na produkcji (`lemoniady`, `ciuszki`). Zrealizowany wariant 5. Kod: `App\Services\OgImageGenerator` + `App\Services\Og\{SceneCutout, ScreenContent, FontLoader}`, scena w `public/images/og-example.png`, ścieżka w `config/seo.shop_card`.

**Jak działa:** gradient z palety motywu → zawartość ekranu (zdjęcia produktów) wpasowana PERSPEKTYWICZNIE w monitor → scena z wyciętą dziurą po zieleni na wierzchu → napisy przez Imagick. Wynik: JPG ~130 KB, ~0,9 s.

**Decyzje domknięte przez Rafała:**
- Logo **takie, jakie wgrał sprzedawca** — bez podkładania płytki pod znak z własnym tłem („logo ma być takie jak dodał klient").
- **BEZ przycisku „Odśwież grafikę"** w panelu: „skoro tyle rzeczy generuje ją automatycznie, klient nie musi mieć do tego dostępu".
- `kramio.pl` zostaje przy swojej grafice — generator obsługuje WYŁĄCZNIE sklepy.

**Rozwiązania warte zapamiętania:**
- Zieleń ekranu wycinana **wypełnieniem obszaru od środka**, nie filtrem koloru: liście rośliny na biurku mają niemal identyczny kolor (121,168,88 vs 95,175,84). Dziura poszerzana o 3 px, bo antyaliasing zostawiał zielony rant. Wynik cache'owany po skrócie pliku sceny — podmiana renderu przelicza się sama.
- Zawartość ekranu wchodzi **POD** scenę (w dziurę), nie na wierzch — inaczej znika ramka monitora i odblaski.
- Perspektywa przez **Imagick** (`DISTORTION_PERSPECTIVE`); GD tego nie potrafi. Płótno musi mieć rozmiar docelowy PRZED przekształceniem.
- Generator **idempotentny**: istniejącą kartę pomija, więc obserwatory mogą zlecać odświeżenie przy każdej edycji. `--force` przerysowuje mimo to (skrót opisuje DANE, nie wersję kodu).
- Skrót niesie **ścieżki zdjęć trafiających na ekran**, nie próg układu — inaczej podmiana zdjęcia nie przerysowałaby karty, a bez przycisku nie dałoby się tego naprawić. Wybór stabilny (wyróżnione → najstarsze), więc dodanie towaru ponad sześć kafli nie unieważnia adresu.

**⚠️ GOTCHA WYDAJNOŚCIOWY:** `ShopObserver` zleca kartę przy KAŻDYM utworzeniu sklepu, a kolejka w testach jest synchroniczna → suita puchła z 44 s do **ponad 10 minut**. Zadanie jest teraz blokowane w `TestCase` (`Bus::fake([GenerateShopOgImage::class])`); pokrycia nie tracimy, bo `OgImageTest`/`ShopCardTest` wołają generator wprost. **Nie zdejmować tej blokady.**

## Historia decyzji (zapis rozumowania — poniżej już tylko kontekst)

## ✅ KIERUNEK ZATWIERDZONY (Rafał, 01.08.2026)

Po obejrzeniu makiety w 4 motywach: *„to jest bardzo dobry kierunek, przy tym zostajemy"*. **Wybrany WARIANT 5** (render z wyciętym tłem + gradient z motywu + napisy przez GD) — opis niżej. Reszta wariantów zostaje w pliku jako zapis rozumowania, nie jako opcje do ponownego rozważania.

**Co zostało po stronie Rafała:** dopracowanie renderu (pusty ekran widziany NA WPROST + wariant dla sklepów bez panelu sprzedawcy). Po stronie kodu: przepisanie `OgImageGenerator` na tę kompozycję.

Podgląd makiet (do skasowania po obejrzeniu): `public/images/podglad/siatka.jpg` oraz `v1`–`v4.jpg`, serwowane z `https://kramio.pl/images/podglad/`. **Nie są w gicie i nic ich nie używa.**

## Problem

Zestawienie dwóch grafik pokazuje dysproporcję:
- `kramio.pl` → pełna kompozycja (biurko, panel, teksty, ikony) — sprzedaje NARZĘDZIE.
- sklep (`og/7/…png`, „Domowe Lemoniady") → logo na białym tle + cienki pasek. **Nie mówi, CO sklep sprzedaje.**

Diagnoza: problem nie polega na tym, że karta sklepu jest zbyt prosta, tylko że jest PUSTA informacyjnie. Obecny `OgImageGenerator` używa WYŁĄCZNIE logo, choć sklep ma inne dane.

## Warianty na stole

1. **Kompozycja ze zdjęć produktów** — ❌ **ODRZUCONE przez Rafała.** Uzasadnienie (trafne): *„czasami jest to po prostu zbiór zdjęć… ktoś ma 90 zdjęć i ma myślo i powidło"*. Asystent zrobił makietę dla lemoniad i wyszła ładnie — ale to był NAJLEPSZY możliwy przypadek: 9 zdjęć w jednej stylistyce, to samo światło i tło. Sklep ze zdjęciami robionymi telefonem w różnym świetle dałby chaos. **Lekcja: nie generalizować z jednego udanego przykładu.**
2. **AI generuje kanwę per sklep** (z nazwy sklepu, nazw produktów, opisu) — pomysł Rafała; tło oddaje NASTRÓJ, nie promuje konkretnych produktów. Nie odrzucone, ale wypchnięte przez wariant 4.
3. **Jedna uniwersalna grafika dla całego systemu** — pomysł Rafała; kasuje wszystkie pułapki naraz, ale wszystkie sklepy wyglądają identycznie, co kłóci się z obietnicą „to TWÓJ sklep, nie nasz szablon".
4. **⭐ FAWORYT (ostatnia myśl Rafała): 4 gotowe tła — po jednym na SZABLON motywu — a napisy/logo/adres nanosi GD w kolorze wybranym przez sprzedawcę.** Zmiana koloru lub motywu = przerysowanie przez GD, **bez AI w runtime**.
   - **Pokrycie sprawdzone 31.07:** `config/themes.php` ma **4 szablony** (`velvet_cloud`, `green_nook`, `graphite_dusk`, `velour_mist`), **każdy po 5 palet** = 20 kombinacji + własny kolor przewodni. Czwórka Rafała odpowiada realnej osi systemu; paletę i kolor dokłada nakładka.
   - Propozycja asystenta obok: zamiast dzielić po MOTYWIE, dzielić po **kategorii sprzedaży** (jedzenie/moda/dom/rękodzieło/uroda/elektronika/dziecko/neutralne), klasyfikowanej RAZ tanim modelem tekstowym (DeepSeek, odpowiedź = jedno słowo z zamkniętej listy, więc zero halucynacji; „nie wiem" → neutralne). Oba podziały da się połączyć (kategoria = tło, motyw+kolor = nakładka), ale to zwielokrotnia liczbę plików.

## Pułapki wychwycone w dyskusji (obowiązują w KAŻDYM wariancie z AI)

- **Prompt musi celować w KATEGORIĘ I NASTRÓJ, nigdy w liczebność ani konkretne przedmioty.** Wtedy tło jest tak samo trafne przy 1 produkcie, co przy 30 — to rozwiązuje pułapkę „ktoś ma 1 produkt, potem 30, a grafika została z jednego" (zauważoną przez Rafała).
- Cena tej ogólności: im ogólniejsze tło, tym bardziej wszystkie sklepy wyglądają tak samo. Punktu równowagi **nie da się ustalić teoretycznie — trzeba zobaczyć wygenerowane przykłady.**
- **Modele obrazu wstawiają bełkotliwe napisy.** CURVIA ma na to twardy zakaz w promptcie (tekst, litery, cyfry, znaki wodne, cudze logo, godła, rozpoznawalne twarze) — przenieść wprost.
- **Czytelność napisów na nieprzewidywalnym tle** — CURVIA rozwiązuje przyciemnieniem (scrim) po lewej. Bez tego biały napis na jasnej kanwie znika.
- **Teksty marketingowe muszą być prawdziwe dla KAŻDEGO sklepu.** Ta sama pułapka co w `Seo::productDescription` (jest tam komentarz: celowo nie piszemy o dostawie, bo jeden sklep wysyła kurierem, a inny daje tylko odbiór osobisty). „Zrób zakupy w kilka minut" — OK. „Szybka wysyłka", „darmowa dostawa" — NIE.
- **Regeneracja nie automatyczna.** Nowy plik = nowy adres = Facebook trzyma stary w cache tygodniami. Lepiej podpowiedź w panelu („sklep sporo się zmienił — odświeżyć grafikę?") niż automat.
- **Nie wpalać tekstów na stałe w tło.** Rafał rozważał „resztę tekstów i ikonki już na grafice" — ryzyko: zmiana zdania wymaga przerysowania wszystkich plików. Napisy zależne od treści trzymać w nakładce GD.
- Materiał wejściowy: **kategorii produktów w Kramio NIE MA**. Zawsze jest nazwa sklepu + ≥1 nazwa produktu (sklep bez produktu jest niewidoczny — obserwacja Rafała). Opis sklepu bywa. Przypadek brzegowy: sklep „U Ani" z produktem „Zestaw nr 3" → nie da się wywnioskować branży.

## Konkret z CURVII (`~/domains/curvia.kwasniak.org`) — gdyby jednak AI

Działający wzorzec Rafała, do przeniesienia bez wymyślania od nowa:
- `ImagePromptBuilder` — DeepSeek zamienia polską treść na JEDEN angielski prompt (zwraca JSON `{"prompt": …}`).
- `ImageGenerator` + `ReplicateClient` — Replicate, model `black-forest-labs/flux-1.1-pro`, `aspect_ratio` 16:9, wyjście webp.
- `ImageComposer` — **GD**: scrim, logo PNG, tekst przez `imagettftext` z własnym TTF. W konfiguracji komentarz: *tekst renderujemy sami, NIGDY modelem obrazu, żeby polskie znaki zostały poprawne*.
- Koszty (z komentarza w `config/curvia.php`): flux-schnell ~$0.003, flux-dev ~$0.025, flux-1.1-pro ~$0.04 za obraz. Przy skali Kramio rachunek jest nieistotny; realnym ryzykiem jest sprzedawca klikający „generuj jeszcze raz" — na to są gotowe limity AI per pakiet ([[plan-ai-usage-limits]]).

## ⭐ WARIANT 5 (ostatni pomysł Rafała 31.07, technicznie NAJLEPSZY): render bez tła + gradient z GD

Pomysł: wziąć render `public/images/og-example.png` (monitor z panelem, biurko, roślina, kubek) **wycięty z tła**, podłożyć pod niego **gradient wyliczony z motywu/koloru sklepu**, a w pustym lewym polu dopisać przez GD logo, nazwę, adres i slogan w kolorach dekoru. Jeden zasób → nieskończenie wiele wariantów, zero AI w runtime.

**Ocena: technika WŁAŚCIWA.** Test asystenta (gradient + obiekt + tekst po lewej) potwierdził, że kompozycja się broni, a lewe pole jest dość duże na napisy. Dodatkowo znika pułapka czytelności: skoro sami rysujemy tło, znamy jego kolor i dobieramy kontrast tekstu.

**Proporcja pasuje idealnie:** źródło 1731×909 = 1,9043, OG 1200×630 = 1,9048 → przeskalowanie bez kadrowania.

**DWIE PRZESZKODY (obie do rozwiązania po stronie grafiki, nie kodu):**

1. **Plik NIE MA kanału alfa.** Szachownica jest WYRYSOWANA w pikselach jako bardzo jasna kratka (próbki narożne: 247–254) — błąd eksportu. Wymaga ponownego eksportu z prawdziwą przezroczystością.
   - **Automatyczne wycięcie po kolorze NIE ZADZIAŁA** — sprawdzone: `-fuzz … -transparent white` zżera biały interfejs na ekranie, klawiaturę, mysz i kubek, bo są tak samo jasne jak tło. Zrzut testowy pokazał dziury w środku obiektu.
   - Przy re-eksporcie zadbać, żeby **cień pod obiektem był PÓŁPRZEZROCZYSTY** (w alfie), a nie wypalony na biało — inaczej na ciemnym gradiencie zrobi się biała poświata.
2. **Ekran pokazuje PANEL SPRZEDAWCY** (menu: Kody rabatowe, Analityka, Integracje). To język i narzędzie Kramio → świetne dla `kramio.pl`, **bezużyteczne jako grafika SKLEPU**: kupujący lemoniady nie ma po co oglądać panelu administracyjnego. Dla sklepów potrzebny analogiczny render z innym motywem (zakupy/produkt/storefront na telefonie), ta sama technika.

### AKTUALIZACJA 01.08: Rafał podmienił render — technika POTWIERDZONA testem

Nowy `public/images/og-example.png`: **1536×1024, PRAWDZIWY kanał alfa** (srgba, 55% pikseli przezroczystych). Poprzedni problem (wypalona szachownica) rozwiązany.

- **Proporcja 1,5 vs 1,9048 NIE JEST problemem** — i to jest wniosek do zapamiętania. Skoro obiekt ma wycięte tło, nie skalujemy całości do 1200×630, tylko przycinamy puste marginesy (`-trim`), skalujemy obiekt do ~560 px wysokości i dosuwamy do prawej krawędzi płótna. Zostaje wtedy ok. 600 px na napisy po lewej — z zapasem.
- **Test złożenia wypadł dobrze:** gradient + obiekt + nazwa/slogan/przycisk/adres w Figtree. Cień pod biurkiem jest miękki, BEZ białej obwódki (obawa z 31.07 nie potwierdziła się). Polskie znaki poprawne, bo tekst rysuje GD.
- ⚠️ **NA EKRANIE JEST „Kranio" ZAMIAST „Kramio"** — model przekręcił nazwę marki; reszta napisów to bełkot. W kanale FB karta ma ~470 px, więc logo zrobi się plamką, ale przy większych podglądach wyjdzie. **Rozwiązanie: poprosić o render z PUSTYM/abstrakcyjnym ekranem** (docelowo można nakładać prawdziwy zrzut przez GD, ale monitor stoi pod kątem → potrzebne przekształcenie perspektywiczne, temat na później). To potwierdza regułę z CURVII: żadnego tekstu od modelu obrazu.
- Render pokazuje PANEL SPRZEDAWCY → **grafika dla `kramio.pl`, nie dla sklepu**. Dla sklepów potrzebny bliźniaczy render z motywem zakupowym, ta sama technika.

### KIERUNEK POTWIERDZONY 01.08 — makieta w 4 motywach

Asystent złożył podgląd w **prawdziwych paletach** z `config/themes.php` (po jednej na szablon): jeden render + 4 gradienty + napisy z GD = 4 wyraźnie różne karty. Rafał: *„mamy kierunek, to już dużo"*. Modyfikowalność DZIAŁA — to potwierdza wariant 5 jako docelowy.

Obserwacja z makiety: **Grafitowy wieczór jest jedynym CIEMNYM motywem** (`surface: #1F2124`) i jasne biurko odcina się na nim ostro. Do złagodzenia przyciemnieniem obiektu przy ciemnych paletach (jedna linijka) albo osobnym renderem w ciemnej tonacji. Nie blokuje.

### Plan Rafała: „ekran cały zielony, resztę wstawimy w GD"

Rafał chce zamówić render z pustym (zielonym) ekranem i wstawiać w niego treść samodzielnie — świadomie więcej pracy w zamian za pełną kontrolę. **Rozwiązuje przy okazji problem „Kranio", bo ekran będzie nasz.**

⚠️ **WARUNEK, który trzeba postawić PRZY ZAMAWIANIU RENDERU: ekran ma być widziany NA WPROST, nie pod kątem.** Monitor ustawiony perspektywicznie wymaga przekształcenia perspektywicznego przy wstawianiu zrzutu — **GD tego nie potrafi**, umie to ImageMagick (jest na serwerze), ale dokłada zależność do generatora, który dziś jest czysto GD-owy. Przy ujęciu frontalnym wstawienie treści to zwykłe skalowanie prostokąta i GD wystarczy.

**Wniosek:** wariant 5 jest najlepszą drogą dla `kramio.pl` (obecna grafika stałaby się modyfikowalna) i dobrą dla sklepów PO przygotowaniu drugiego renderu. Łączy się z wariantem 4: gradient może wynikać z szablonu motywu, więc „4 tła" sprowadzają się do 4 gradientów, a nie 4 osobnych obrazów.

## Otwarte pytania (do rozstrzygnięcia PO przespaniu)

1. Podział teł: po **motywie** (4, pomysł Rafała) czy po **kategorii sprzedaży** (~8, propozycja asystenta) — czy jedno i drugie?
2. Czy AI w ogóle wchodzi w runtime, czy tła powstają RAZ ręcznie (Rafał umie — zrobił grafikę landingu) i kod tylko je składa?
3. Czy AI jest funkcją dla wszystkich, czy wyróżnikiem płatnych pakietów? (Wariant Rafała „rozmyte zdjęcie ze sklepu + napisy" jako darmowy Kram, AI jako płatne — układa się w drabinkę.)
4. Czy grafika sprzedawcy nosi ślad Kramio? Każde udostępnienie = darmowa reklama platformy, ale to jego marka. **Uwaga: w wariancie 3/4 grafika jest systemowa, więc niesie rozpoznawalność sama z siebie — pytanie częściowo się rozwiązuje.**
5. Czy sprzedawca widzi podgląd i może odrzucić/wgrać własną? (Wgranie własnej było już planowane jako dodatek pakietowy — [[plan-seo-audit]].)

## Stan techniczny

`OgImageGenerator` + `GenerateShopOgImage` (job w kolejce) + komenda `og:generate` DZIAŁAJĄ, font Figtree jest w repo, GD z FreeType/WebP/PNG/JPEG na serwerze potwierdzone. **Zmienia się tylko to, CZYM wypełniamy płótno — nie architektura.**

Powiązane: [[plan-storefront-theming]], [[plan-custom-brand-color]], [[ai-task-profiles-architecture]], [[plan-ai-usage-limits]], [[references-are-suggestions]] (CURVIA = inspiracja do adaptacji, nie specyfikacja do kopiowania).
