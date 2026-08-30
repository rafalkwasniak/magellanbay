---
name: build-fails-tell-rafal-immediately
description: "Build nie przechodzi = NATYCHMIAST powiedz Rafałowi, żeby zrobił go z terminala. Nie walcz sam, nie ponawiaj w kółko."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 3555802d-d4fb-4f61-936d-2ec6d0355b27
  modified: 2026-07-26T21:21:46.692Z
---

Rafał, 2026-07-26 (po wieczorze, w którym zeszła prawie godzina na walkę z buildem): „jeśli kiedyś build nie przejdzie, od razu dajesz znać i robię to w terminalu — nie chcę powtórki z tego, że godzinę tracimy na małą zmianę, to serio lepsze wyjście i szybsze".

**Zasada: pierwsza-druga nieudana próba builda → od razu mów.** Nie uruchamiaj wielominutowych pętli ponawiających, nie kombinuj z obejściami (skrypty czekające na spadek load, budowanie bez Tailwinda, szukanie alternatywnych bundlerów) — to wszystko zostało tego wieczoru sprawdzone i NIE zadziałało, a zjadło czas Rafała, który miał godzinę na zupełnie inną robotę.

**Why:** asystent działa WEWNĄTRZ VS Code Server, a to właśnie on zjada wątki konta (zmierzone: **195 z 212**, patrz [[vite-build-rayon-threads]]). Rolldown nie dostaje puli i pada z EAGAIN. Asystent fizycznie nie może zwolnić tych wątków — to jedyna rzecz, której nie zrobi za użytkownika. Rafał zamyka VS Code, robi build z czystego SSH i przechodzi za pierwszym–drugim strzałem (zmierzone: 613 ms i 306 ms).

**How to apply:**
1. Zmianę w JS/CSS przygotuj i zweryfikuj (testy, symulacja algorytmu na danych z bazy) — to rób sam, do końca.
2. Spróbuj builda **raz, najwyżej dwa razy**: `RAYON_NUM_THREADS=1 timeout -k 10 45 node node_modules/vite/bin/vite.js build` + `pkill -9 -f "bin/vite.js"` po sobie.
3. Nie przechodzi → **powiedz od razu** i podaj gotową komendę do wklejenia:
   ```
   cd /home/host473413/domains/kramio.pl && RAYON_NUM_THREADS=1 node node_modules/vite/bin/vite.js build
   ```
   z informacją, że trzeba wcześniej zamknąć VS Code.
4. Weryfikację buildu podaj po LITERAŁACH, nie po nazwach funkcji — minifikator je przemianowuje (dałem raz `grep splitBlock`, wyglądało na nieudany build, choć był udany).

**DECYZJA RAFAŁA (2026-07-26, koniec dnia): NIE przebudowujemy narzędzi.** Padły dwie propozycje — zdjęcie JS z bundlera (9 własnych plików bez zależności, Trix ma gotowy `dist`, bundler potrzebny tylko do CSS) oraz podmiana dostawcy AI na szybszego (Groq/Cerebras, bo architektura zadań już na to pozwala jedną linią w configu). Rafał: „na razie nie będziemy kombinować z rozwiązaniami, jeśli buildy mogę robić ja, to na razie nie chcę się na tym skupiać".

**Nie wracaj do tych tematów z własnej inicjatywy.** Obecny układ jest wystarczający: asystent przygotowuje i weryfikuje zmianę, Rafał odpala build z terminala. Wróć do tego tylko, jeśli Rafał sam zapyta albo jeśli buildy zaczną blokować pracę na tyle, że sam to zgłosi.
