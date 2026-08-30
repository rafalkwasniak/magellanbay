---
name: incremental-checkpoints-per-element
description: Nawet po zatwierdzeniu planu — realizuj element po elemencie z przystankiem na froncie i potwierdzeniem Rafała przed kolejnym krokiem.
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 96f32cca-ed28-4afe-a864-1ce4ae544d7d
---

Zatwierdzenie planu (ExitPlanMode) NIE jest zgodą na wykonanie całej listy jednym ciągiem. Po każdym pojedynczym elemencie należy: zatrzymać się, pokazać efekt na froncie, poczekać na potwierdzenie/uzgodnienie Rafała, dopiero potem ruszać dalej. Brak pytań i checkpointów w trakcie = błąd, nawet jeśli kod jest poprawny i testy zielone.

**Why:** 2026-06-26 przy rejestracji + zgodach prawnych zbudowałem całą zatwierdzoną listę naraz, bez ani jednego pytania, uzgodnienia czy podglądu na froncie po drodze. Rafałowi bardzo się to nie podobało — po niedawnym fakapie chce mieć kontrolę krok po kroku, nie hurtowy zrzut zmian do recenzji na końcu.

**How to apply:** Rozbijaj pracę na najmniejsze sensowne elementy. Po każdym: krótko pokaż co zrobione + jak to wygląda na froncie, i zapytaj zanim przejdziesz dalej. Domyślnie pojedynczy krok na turę, nie cała lista. Spina się z [[references-are-suggestions]] i ogólną zasadą „najpierw omawiamy, potem robimy, małe kroki".
