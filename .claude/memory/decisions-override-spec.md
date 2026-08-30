---
name: decisions-override-spec
description: "Nasze żywe ustalenia (CLAUDE.md / pamięć) są NADRZĘDNE nad docs/specyfikacja.md — spec to punkt wyjścia, nie wyrocznia."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 496eb5d0-33a2-486c-9a6d-89ca05143142
---

Hierarchia źródeł prawdy w projekcie: **nasze bieżące ustalenia (zapisane w CLAUDE.md lub w pamięci) > `docs/specyfikacja.md`.**

**Why:** Rafał jest właścicielem, programistą i handlowcem w jednym — gdy on i asystent coś ustalą podczas rozmowy, to bije wcześniejszą specyfikację. Spec (`docs/specyfikacja.md`, ~34 KB, pisana 2026-06-24) to wartościowy punkt wyjścia i materiał referencyjny, ale NIE jest dokumentem nadrzędnym ani niezmiennym kontraktem.

**How to apply:** Gdy spec przeczy naszym ustaleniom — wygrywają ustalenia, nie zgłaszaj tego jako „błąd do naprawy w kodzie". Specem można się posiłkować i cytować, ale przy konflikcie domyślnie idziemy za rozmową/pamięcią. Przykłady nadpisań (2026-06-30): limit Free 24 (spec mówi 25); model abonamentowy [[plan-packages]] rozbudowany ponad to, co jest w §1.6.
