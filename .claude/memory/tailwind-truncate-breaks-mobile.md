---
name: tailwind-truncate-breaks-mobile
description: "Tailwind `truncate` zawiera white-space:nowrap i rozpycha uklad na komorce; w koszyku/zamowieniach nazwa produktu ma sie ZAWIJAC (break-words), nie byc ucinana."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: d742d9f9-2a68-4405-a0fd-fa8cdd4f1c1d
---

Tailwindowy `truncate` to skrot na `overflow:hidden` + `text-overflow:ellipsis` + **`white-space:nowrap`**. To trzecie daje elementowi ogromna szerokosc minimalna i na waskich ekranach rozpycha karte/tabele — na desktopie tego nie widac. Lek: `break-words` (`overflow-wrap:break-word`) — dzieli po spacjach, wewnatrz slowa tylko gdy pojedyncze slowo samo sie nie miesci.

Zastosowane 2026-07-17 (`1ba180d`) w [[handoff-2026-07-17-cart-wrap]]: `resources/views/livewire/cart.blade.php` i `resources/views/livewire/seller/order-editor.blade.php`.

**Why:** Rafał: w koszyku nazwa NIE MOZE byc ucinana — ucina sie wtedy kolor/rozmiar, a klient ma wiedziec co kupuje, nie domyslac sie. To decyzja produktowa, nie kosmetyka.

**How to apply:** Domyslnie NIE uzywaj `truncate` na nazwach produktow w koszyku, kasie, podsumowaniu i pozycjach zamowienia — tam tekst ma sie zawijac. `truncate` zostaje dopuszczalny tylko tam, gdzie utrata konca tekstu nic nie kosztuje (etykiety pomocnicze). Pamietaj, ze `break-words` i pokrewne klasy zawijania moga nie byc w zbudowanym CSS — sprawdz i przebuduj, patrz [[tailwind-classes-must-exist-in-build]].
