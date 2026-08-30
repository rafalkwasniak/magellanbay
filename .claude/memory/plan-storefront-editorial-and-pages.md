---
name: plan-storefront-editorial-and-pages
description: "USTALONE 2026-07-09 (budowa 2026-07-10+): redesign frontu storefrontu w kierunku EDITORIAL (foto-first) + nowy moduł stron tekstowych (CMS). Pełna mapa treści nagłówka/głównej/stopki, karta produktu z aplą hover, wirtualna podstrona O sklepie, model stron, decyzja RODO (PP=nasza)."
metadata: 
  node_type: memory
  type: project
  originSessionId: 4a61b28a-7811-4a8b-8949-92c46da890c2
---

**Sesja planistyczna 2026-07-09 (Rafał + asystent). Dziś TYLKO ustalenia, budowa od 2026-07-10.** To jest solidna podstawa do dalszej pracy nad wyglądem storefrontu — Rafał uznał obecny układ za „fatalny" (surowy Tailwind, pusty nagłówek, brak rytmu/skali, generyczny `max-w-6xl`). Powiązane: [[plan-storefront-theming]], [[storefront-theme-system]], [[ui-design-direction]], [[storefront-image-treatment]], [[nip-autofill-and-editor]], [[deepseek-ai-improve]], [[naming-and-locale-convention]], [[incremental-checkpoints-per-element]].

## Stan faktyczny na start (WERYFIKACJA kodu 2026-07-09, koryguje pamięć)
Pamięć mówiła o „3 osiach / blokach / kilku dynamicznych układach" — w kodzie realnie jest **JEDEN layout i jeden komplet widoków** dla wszystkich sklepów:
- **Szablon** (velvet_cloud / green_nook / graphite_dusk w `config/themes.php`) **NIE zmienia układu**, tylko domyślną paletę + etykietę. Struktura HTML identyczna.
- **Paleta** — 15 palet (5×3), zmienia 4 tokeny CSS (`--brand`, `--surface`, `--ink`, `--brand-ink`) wstrzykiwane w `:root` w `resources/views/components/layouts/storefront.blade.php`. Pochodne odcienie przez `color-mix()`. Kolor własny („custom") nadpisuje `brand`.
- Jedyna realna „dynamika układu" = strona główna przestawia się wg liczby wyróżnionych produktów (sztywny `@switch` 1/2/3/4-6 w `storefront/home.blade.php`), limit 6 (`config/shop.php` homepage_promoted_limit).
- **Kolory są stokenizowane, ale układ/spacing/typografia/promienie NIE** — rozsiane jako klasy Tailwinda po ~6 plikach Blade. To główny dług przy redesignie.
- Kluczowe pliki: `components/layouts/storefront.blade.php` (layout+tokeny+nagłówek slim+stopka), `storefront/{home,products,product}.blade.php`, `components/storefront/{product-card,tag-cloud,breadcrumbs}.blade.php`. Tailwind v4 (bez `tailwind.config.js`, konfiguracja w `resources/css/app.css`), font Instrument Sans (Bunny Fonts).
- **Przed dodaniem kolejnych szablonów: najpierw wyciągnąć SKALĘ układu** (spacing/typografia/promienie/szerokości jako tokeny). Decyzja architektoniczna: **JEDEN świetny elastyczny szkielet**, warianty przez palety + kilka przełączników — NIE 3 rozjeżdżające się codebase'y.

## KIERUNEK: „domowy butik" / EDITORIAL (foto-first)
- Napięcie z briefu Rafała: „standard sklepu" (klient wie, jak się poruszać) × „wyjątkowo, domowo, NIE sztampowo, mało produktów". To nie kompromis — to definicja estetyki **single-maker / editorial** (przeciwieństwo marketplace/Allegro/gęsta zimna siatka).
- **Rafał był przez kilka lat fotografem edytoriali i okładek — editorial/ekspozycja to jego natywny język.** To zakotwicza kierunek osobiście: duże zdjęcia, ekspozycja, powietrze, osobisty głos. Produkt = eksponat w galerii.
- Klucz do „mało produktów": NIE ukrywać, że ktoś ma 3 produkty (hero-kolosy) — **celebrować jako kolekcję**. 3 produkty jak galeria = dobrze; „katalog z 3 pozycjami" = biednie.
- **Serif display na nagłówki** (typu Fraunces / Instrument Serif) sparowany z Instrument Sans na treść — tani lewar „butik zamiast SaaS". Dziś wszystko jeden sans na bold = „SaaS". Rafał: dać od razu przy głównej, żeby poczuł różnicę.

## NAGŁÓWEK (globalny)
- Brand po lewej: logo, a jak brak → nazwa sklepu (jak w panelu). Link → główna. (Dziś logo brakuje w globalnym nagłówku — jest tylko w hero → stąd wrażenie „niedokończone".)
- **Etykieta linku do wykazu: TESTOWO nazwa sklepu zamiast „Sklep"** (jak w panelu). Uwaga: może dublować brand z lewej — ocenić na froncie.
- **`Informacje`** (nie „O nas") — rozwijane: wirtualna „O sklepie" (jeśli jest) + strony tekstowe wg kolejności.
- Koszyk po prawej (`<livewire:cart-counter>` — jest). Mobile: hamburger.
- Zasada: minimalnie, 3-4 pozycje max.

## STRONA GŁÓWNA (jeden kod, sekcje w pionie — kolejność do dopieszczenia na froncie)
1. **Hero / brand** — logo + nazwa sklepu (+ ew. króciutki tagline). Editorial — może sygnaturowe zdjęcie/duża typografia.
2. **Produkty** ⭐ NAJWAŻNIEJSZE — nad tym najwięcej pracy. Karta editorial z **aplą hover** (opis niżej).
3. **O sklepie** — tekst z mechanizmem wirtualnej podstrony (opis niżej).
4. **Tagi** — chmura tagów (Rafał: identyfikuje ofertę, funkcjonalne, wypełnia obszar). Zostaje.
5. **CTA** → „Zobacz wszystkie produkty".
6. Stopka.
- Metoda pracy: stawiam sekcję na froncie → Rafał patrzy → dopiero wtedy dłubiemy układ „na oko" (łatwiej mu ocenić widząc). [[incremental-checkpoints-per-element]].

## KARTA PRODUKTU NA GŁÓWNEJ — apla hover (pomysł Rafała, przyjęty)
- **DOPRECYZOWANE 2026-07-11 i ZBUDOWANE dla widoku z 1 produktem** (`home.blade.php` `@case(1)` + lokalny `<style>` w tym pliku, NIE w layoucie — layout jest współdzielony; NIE ruszać `product-card`, bo karmi wykaz). Decyzje Rafała, nadrzędne nad starszym opisem niżej:
  - **Zdjęcie w NATURALNYCH proporcjach, bez przycinania** (NIE kwadrat cover — cover obcina ważne elementy; koryguje [[storefront-image-treatment]]).
  - **Spoczynek: samo zdjęcie.** Nazwa produktu CELOWO nie nad zdjęciem (tam winieta niesie nazwę SKLEPU) — pojawia się dopiero w apli.
  - **Hover: CAŁE zdjęcie** przygasa **I** się powiększa (oba), a na nie wjeżdża półprzezroczysta apla (blur, `--surface` 50%): **nazwa produktu (normalny kolor tekstu, NIE `st-brand`) · część opisu · przycisk „Pokaż produkt"** → przejście do szczegółów produktu.
  - **DECYZJA (Rafał 2026-07-11): strona główna NIE sprzedaje.** BEZ ceny, BEZ omnibusa, BEZ „Do koszyka" w boxie głównej — ma tylko **zachęcić do wejścia w produkt** (sprzedaż na karcie produktu). Jedyny CTA = „Pokaż produkt".
  - **WIDOK 2+ PRODUKTÓW DOMKNIĘTY (2026-07-11, commit `8ebccd5`):** jednolita siatka kafli JEDNAKOWEGO wymiaru (lokalny kafel w `home.blade.php`, NIE `product-card` — ta karmi wykaz). Kadr zdjęcia stałe proporcje **4/5 + object-cover** (każde różne zdjęcie wypełnia kadr, przycięte ale możliwie oryginalne — świadoma zgoda Rafała na przycinanie), opis clamp 3 linie = równe wysokości. Pod zdjęciem dane jak przy 1 produkcie (nazwa akcent, wycinek, „Pokaż produkt"), BEZ ceny/koszyka. Kolumny: 2→2, 3→3, **4→2 (2×2)**, 5/6→3. Zoom hover przycięty do ramki. Dawne warianty 2/3/4-6 zwinięte w jedną siatkę. **Fallback bez wyróżnionych: 3 LOSOWE aktywne** (`config('shop.homepage_fallback_count')`), inne za każdym wejściem — „pusta główna woła o produkty". CTA „Zobacz wszystkie" usunięte (katalog przez menu „Produkty").
  - **WYBÓR KOŃCOWY (Rafał 2026-07-11): KAFELEK, nie apla.** Po zbudowaniu i porównaniu obu Rafał wybrał wariant **bez apli**: zdjęcie na górze + info POD zdjęciem (nazwa w `st-brand`, część opisu, „Pokaż produkt") w jednym kaflu `st-card` w stylu strony produktowej. Kafel hugguje szerokość zdjęcia (trik `.solo-info { width:0; min-width:100% }`, żeby tekst nie rozpychał boxu; zdjęcie `.solo-media` ograniczone szer. 100% I wys. 68vh). **Wersja z aplą (wyjazd z dołu) porzucona, zachowana w historii = commit `3408b87`** — do ewentualnego powrotu. To jest aktualny stan `home.blade.php` `@case(1)`.
  - **Mobile (brak hovera):** apla = statyczny panel POD zdjęciem (`@media (hover:none)`), dane zawsze widoczne, zero JS.
  - Zakres świadomie wąski: **tylko widok z 1 produktem**; inne warianty (2/3/4-6) osobno, później — po ocenie tego boxu na froncie.
- (Starszy opis z 2026-07-09, częściowo nieaktualny — patrz korekty wyżej:) Duże ~~kwadratowe~~ zdjęcie. Po najechaniu apla z ceną + add-to-cart + skróconym opisem; zdjęcie przygasa/powiększa. Mobile: „tap odsłania" albo dane zawsze widoczne.
- Pamiętać na karcie/aplę: jednostka sprzedaży kg/szt ([[plan-sale-unit-weight]]) i omnibus „najniższa cena 30 dni" ([[omnibus-lowest-price-30d]]) — gdzie je pokazać.
- Karta PRODUKTU (osobna strona) zostaje: zdjęcie w naturalnych proporcjach bez przycinania ([[storefront-image-treatment]]).

## „O SKLEPIE" — wirtualna, dynamiczna podstrona (pomysł Rafała, przyjęty)
- **Jedno pole** opisu sklepu (mamy `shop.description`, HTML z edytora Trix).
- **Próg** (~200 znaków — liczyć długość CZYSTEGO TEKSTU bez tagów HTML, żeby `<p>` nie fałszował):
  - **Krótki** → cała treść na stronie głównej w sekcji „O sklepie"; BRAK pozycji w menu `Informacje`.
  - **Długi** → na głównej tylko wycinek + „czytaj więcej →"; pełna treść na **WIRTUALNEJ** podstronie (NIE wiersz w tabeli `pages` — pochodna z `shop.description`), która automatycznie pojawia się jako **PIERWSZA** pozycja w `Informacje` (i stopce). Znika/pojawia się sama wg długości. To świadomy „efekt dynamiczności".
- URL wirtualnej strony w konwencji PL (np. `/strona/o-sklepie` lub `id-slug`) — dopiąć do konwencji [[naming-and-locale-convention]].

## STOPKA (globalna)
- Kolumna brand: logo/nazwa + jedno zdanie.
- Kolumna „Informacje": te SAME strony tekstowe co w menu (jedna lista, jedna kolejność — patrz niżej) + Regulamin + link do NASZEJ Polityki prywatności.
- Kolumna „Kontakt": `contact_email` + `contact_phone` (mamy te pola, [[per-shop-email-identity-branding]]).
- Dół: „Sklep na Kramio" (jest).

## MODUŁ STRON TEKSTOWYCH (CMS) — NOWY, do zbudowania
- **Tabela `pages`** (scope `shop_id`): `title`, `slug`, `content` (HTML z Trix), `position`, `published`, `is_system`.
- **BEZ rozdzielania menu/stopka** (decyzja Rafała z doświadczenia: ludzie nie lubią pisać tekstów; nikt w małym sklepie nie napisze 5 stron do menu i 5 do stopki). **Jedna lista, jedna kolejność** → te same strony pojawiają się i w menu `Informacje`, i w stopce, sortowane wg `position`. Sprzedawca ustala tylko kolejność. (Ew. cap ~5 w górnym menu, reszta w stopce — zdecydować przy budowie, domyślnie: wszystkie w obu.)
- **Strona systemowa = TYLKO Regulamin** — nieusuwalna, nie da się wyłączyć, można TYLKO przestawić pozycję. Zakładana automatycznie przy tworzeniu sklepu (rozważyć gotowy szablon do wypełnienia).
- **Panel sprzedawcy — nowa zakładka „Strony":** lista z sortowaniem (drag albo pole pozycji), dodaj/edytuj/usuń (poza Regulaminem). **Reużycie: ten sam edytor co produkt/sklep** (`<x-rich-editor>` / Trix, [[nip-autofill-and-editor]]) + **„Popraw przez AI"** (DeepSeek, [[deepseek-ai-improve]]). NIE budujemy edytora od zera — to samo co jest.
- **Storefront render:** URL PL `/informacje/{id}-slug` (jak produkt: szukaj po id, zły slug → 301 [[naming-and-locale-convention]]), ten sam szkielet, czytelna szerokość. **Nazewnictwo ujednolicone (Rafał 2026-07-11): jedno słowo „Informacje" na całej ścieżce** — segment URL `/informacje/...`, dział w panelu „Informacje", rozwijane menu na froncie „Informacje". Nie „Strony".

## RODO — Polityka prywatności jest NASZA (ważne)
- **PP należy do Kramio, nie do sklepu** — bo to MY trzymamy dane i jesteśmy administratorem danych (wiemy jakie i po co). Już istnieje po naszej stronie.
- Sklep NIE dostaje własnej PP. Sklep ma tylko **Regulamin**.
- W naszej PP **dopisać punkt**, że właściciel sklepu ma wgląd w dane zarejestrowanych osób (jego klientów).
- W stopce sklepu: Regulamin (sklepu) + link do NASZEJ PP.

## STAN BUDOWY (aktualizacja 2026-07-11, commit b2cd846)
**Punkt #1 (moduł stron) DOMKNIĘTY.** Zbudowane: tabela/model `pages` (scope shop_id), Regulamin systemowy auto-zakładany przez `ShopObserver` (nieusuwalny, tytuł stały, zawsze published; szkielet treści w `config/pages.php`), panel sprzedawcy „Informacje" (lista z **drag&drop** przez `resources/js/page-order.js`, dodaj/edytuj/usuń, reużycie `<x-rich-editor>` + AI `page_content`), render storefrontu `/informacje/{id}-slug` (301 przy złym slugu, bramka widoczności/podglądu), klasa `.st-prose` (typografia treści dziedzicząca kolor motywu, w inline `<style>` layoutu storefrontu). 433 testy zielone.
- **Nazewnictwo ujednolicone:** wszędzie „Informacje" (URL `/informacje/…`, dział panelu, przyszłe menu). Nie „Strony".
- **Wirtualna „O sklepie" ZROBIONA** (render): `/informacje/o-sklepie` (slug z `config('pages.about.slug')`, trasa PRZED wildcardem `{page}`). Decyzja Rafała (kluczowa): **istnienie ≠ obecność w menu**. Strona renderuje ZAWSZE gdy opis niepusty (`Shop::hasAbout()`), pusty → 404; próg (`config('pages.about.menu_threshold')`, teraz 200, CZYSTY tekst przez `Shop::aboutPlainText()`) rządzi TYLKO menu + przyszłym wycinkiem na głównej (`Shop::aboutInMenu()`). Helper adresu: `Shop::aboutPath()`.
- **Do zrobienia w #2/#3:** menu „Informacje" (pozycje: O-sklepie-jeśli-długie → strony wg position; w stopce te same + Regulamin + link do NASZEJ PP), wycinek + „czytaj więcej" na głównej.
- **Bug-nauczka:** pole `disabled` NIE leci w żądaniu — FormRequest wymagający takiego pola cicho odrzuca zapis. Rozwiązane wstrzyknięciem stałej wartości w `prepareForValidation` (Regulamin: tytuł). Test MUSI odtwarzać realny formularz (bez `title`), inaczej nie łapie.

## KOLEJNOŚĆ BUDOWY (ustalona)
Moduł stron jest zależnością nagłówka/stopki (linki do stron). **POTWIERDZONE 2026-07-09, przypomniane 2026-07-10: zaczynamy od CMS** (mało decyzji projektowych, łatwe wejście w kod, odblokowuje menu+stopkę). **To START DUŻEGO KROKU „sklep dla klientów" (storefront) — następna sesja rusza właśnie tu** (panel sprzedawcy uznany za domknięty, patrz [[handoff-2026-07-10]]):
1. Moduł stron (CMS): model+migracja+panel „Strony"+render+strona systemowa Regulamin. ← JUTRO STARTUJEMY TU.
2. Nagłówek + stopka globalne (z `Informacje` i stronami).
3. Strona główna: sekcje wg mapy, pole opisu + mechanizm wirtualnej „O sklepie", serif display.
4. Produkty: dopieszczenie prezentacji — karta z aplą hover, siatka, kg/szt, omnibus. Najgrubszy temat.

## DO DOMKNIĘCIA JUTRO (nie blokery)
- Apla hover na mobile (tap vs zawsze widoczne).
- Próg „O sklepie" — liczyć czysty tekst; 200 znaków = start.
- Dublowanie nazwy sklepu (brand z lewej vs link nawigacji) — ocenić na froncie.
- Cap liczby stron w górnym menu vs stopce.
- Gdzie na karcie/aplę omnibus + jednostka kg/szt.
