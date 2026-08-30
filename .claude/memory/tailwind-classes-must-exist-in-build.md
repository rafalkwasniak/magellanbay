---
name: tailwind-classes-must-exist-in-build
description: Nowa klasa Tailwinda działa TYLKO jeśli jest w zbudowanym CSS — sprawdzaj każdą nową klasę (zwłaszcza warianty sm:/lg:) grepem po public/build/assets/*.css przed pokazaniem czegokolwiek.
metadata: 
  node_type: memory
  type: feedback
  originSessionId: d597683e-ad06-4dba-a499-2ecb401092fa
  modified: 2026-08-04T15:41:17.739Z
---

Każdą **nową** klasę Tailwinda sprawdź w zbudowanym CSS, zanim uznasz widok za gotowy:

```bash
grep -qF ".sm\:text-2xl" public/build/assets/*.css && echo ok || echo BRAK
```

**Uwaga na escapowanie — to kłamie w obie strony.** W CSS escapowany jest dwukropek (`.sm\:text-2xl`) ORAZ nawiasy wartości arbitralnych (`.max-w-\[14rem\]`). Grep bez escapowania powie „BRAK" o klasie, która jest:

```bash
grep -qF '.sm\:text-2xl'      public/build/assets/*.css   # dwukropek
grep -qF '.max-w-\[14rem\]'   public/build/assets/*.css   # nawiasy
```

Fałszywe „BRAK" jest groźniejsze niż fałszywe „jest": wysyła cię naprawiać problem, którego nie ma (2026-07-15 dwa razy: raz przy `sm:text-3xl`, raz przy `max-w-[10rem]`; 2026-08-04 znów, przy `hover:bg-rose-50` — pułapka wraca za każdym razem, gdy sprawdzasz klasę z dwukropkiem).

Przy sprawdzaniu partii klas naraz pisz je **od razu w postaci escapowanej**, inaczej połowa wyniku to fałszywe alarmy:

```bash
for c in 'hover\:bg-rose-50' 'focus\:ring-rose-400' 'bg-rose-600'; do
    printf "%-24s " "$c"; grep -qF ".$c" public/build/assets/*.css && echo OK || echo BRAK
done
```

Szybsze od zgadywania: `grep -o "\.text-rose-[0-9]*" public/build/assets/*.css | sort -u` wypisuje, które stopnie danego koloru w ogóle są w buildzie — wybierasz z listy zamiast trafiać.

**Why:** klasa spoza buildu nie znika ani nie krzyczy — po prostu cicho nic nie robi, a widok wygląda „prawie dobrze". 2026-07-15 wypuściłem na produkcję `text-xl sm:text-2xl` na nagłówkach kafelków głównej; `sm:text-2xl` nie było w buildzie (nikt go wcześniej nie użył), więc nagłówki utknęły na 20 px zamiast 24. Rafał zauważył gołym okiem, że tytuły są „za małe i mało czytelne". Testy tego nie łapią — asertują HTML, nie CSS.

**How to apply:** przy każdym nowym widoku zbierz nowe klasy i przelec je grepem przez `public/build/assets/*.css`. Klasy skopiowane z istniejącego widoku są bezpieczne; ryzyko to warianty responsywne i rzadkie stopnie (`sm:text-2xl`, `xl:`, nietypowe `space-y-*`). Jeśli klasy brakuje — najpierw pytanie, czy naprawdę jej potrzeba (zwykle da się użyć tej, która już żyje w projekcie i pasuje do rytmu strony); przebudowa to ostateczność, bo `npm run build` na tym hostingu wymaga `RAYON_NUM_THREADS=1` i potrafi zostawić sieroty — patrz [[vite-build-rayon-threads]] i [[orphaned-build-processes-incident]].

**Wariant responsywny bez pary działa najgorzej z możliwych** (2026-07-30): `w-full sm:w-auto` na przycisku zakupu pakietu — `sm:w-auto` nie było w buildzie, więc przycisk został paskiem na całą szerokość także na desktopie, czyli dokładnie tam, gdzie miał być zgrabny. Rafał wyłapał to dwa razy z rzędu na dwóch ekranach. Bezpieczniejszy wzorzec: `inline-flex` (jest w buildzie od dawna) zamiast pary `w-full` + wariant, jeśli nie masz pewności co do wariantu.

Dobór stopnia bierz z **rodzeństwa na tej samej stronie**, nie z podobnie wyglądającego komponentu gdzie indziej: boxy `st-card` na głównej mają `text-2xl sm:text-3xl`, a kafle siatki produktów `text-xl` — bo tam tytuł jest podpisem pod zdjęciem. Patrz [[ui-design-direction]].
