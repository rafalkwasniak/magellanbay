---
name: feedback-ask-dont-probe-for-user-state
description: "Stanu, który zna tylko Rafał (czy coś już zrobił, czy założył bazę, jaką podjął decyzję) — PYTAĆ, nie sprawdzać komendą. Fakty w kodzie i na serwerze — sprawdzać, nie zgadywać."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 85301e1d-9f9e-4502-a6f9-d1381d0aa86c
  modified: 2026-09-05T09:45:47.901Z
---

**Powiedziane wprost 2026-09-05:** *„DB nie ma, zapytaj zamiast sprawdzać :)"*

Odpaliłem `ls`/`grep`, żeby zobaczyć, czy Rafał założył już bazę dla nowej instalacji. Odpowiedź znał tylko on i podałby ją w sekundę — komenda była marnowaniem tury i spowalniała go.

## Rozróżnienie

**PYTAĆ** — gdy odpowiedź zależy od Rafała albo od świata poza serwerem:
- czy już coś założył, ustawił, wysłał, kupił
- jaką podjął decyzję, jaki ma budżet, co powiedział klient
- co jest w panelu hostingu, w DirectAdmin, u zewnętrznego dostawcy
- czy zdążył z czymś, na co czekamy

**SPRAWDZAĆ** — gdy odpowiedź jest w kodzie albo w plikach i mogę się pomylić z pamięci:
- jak działa metoda, co jest w konfiguracji, jakie są limity
- czy funkcja istnieje, zanim ją zarekomenduję
- historia gita, rozmiary katalogów, wersje PHP
- **weryfikacja przed twierdzeniem** — to Rafał docenia i o to prosił wielokrotnie

Krótko: **fakty techniczne weryfikować zawsze, stan pracy Rafała — pytać.**

## Kontekst haseł

Przy tej okazji: dla instalacji roboczej Magellana Rafał **nie ma obaw o hasła w czacie** — *„magellan jest wersją, która i tak pójdzie na serwer klienta, więc jakby jest ona do pisania przez nas"*. Nie proponować mu obchodzenia tego przez wpisywanie danych do pliku, jeśli sam poda je wprost. Dotyczy tej instalacji, nie produkcji Kramio.

Powiązane: [[plan-magellan-bay-separate-project]], [[feedback-dont-stack-caveats]].
