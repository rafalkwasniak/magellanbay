---
name: gotcha-artifact-share-pinned-version
description: "PUŁAPKA: udostępniony artefakt pokazuje odbiorcom PRZYPIĘTĄ wersję, a ponowna publikacja jej NIE zmienia. Dokument handlowy sprawdzać w oknie prywatnym przed wysłaniem."
metadata: 
  node_type: memory
  type: project
  originSessionId: 85301e1d-9f9e-4502-a6f9-d1381d0aa86c
  modified: 2026-08-31T15:09:11.386Z
---

**Wykryte 2026-08-31 na ofercie dla Magellan Bay — o włos od wysłania klientowi cennika 3× za wysokiego.**

Artefakt udostępniony linkiem serwuje odbiorcom **wersję przypiętą w momencie udostępnienia**, nie bieżącą. Kolejne publikacje aktualizują wersję „live", ale **pinu nie ruszają**. W wyniku `action: "read"` widać to wprost:

> shared with anyone with the link (**viewers see a pinned earlier version, not this live version**)

Co się stało konkretnie: oferta była publikowana pięć razy (ceny zmieniły się z 6 000/3 000/150 zł na 2 000/2 400/100 zł, doszły opisy ekran po ekranie, sekcja Założeń i zabezpieczenie o wyglądzie). Link udostępniony był przy publikacji pierwszej — i to **pierwszą wersję** widział każdy, kto wszedł. Rafał wychwycił to, wklejając treść, którą sam zobaczył pod linkiem.

## Zasady na przyszłość

1. **Przed wysłaniem czegokolwiek do klienta: otworzyć link w oknie prywatnym i przeczytać kluczowe liczby.** Publikacja z mojej strony nie jest dowodem, że odbiorca to zobaczy.
2. **Po `action: "read"` czytać nagłówek wyniku** — informacja o przypiętej wersji jest tam podana wprost i jest jedynym sygnałem, jaki mam.
3. **Nadawać `label` przy każdej publikacji z istotną zmianą** (np. `ceny-2000-2400`) — bez tego w wyborze wersji nie da się rozpoznać właściwej.
4. **Przepięcie wersji to akcja w interfejsie, po stronie Rafała.** Narzędzie Artifact nie ma na to akcji — nie obiecywać, że „poprawione", po samej publikacji.
5. **ZASADA GŁÓWNA, potwierdzona dwukrotnie 31.08: dla dokumentu udostępnionego klientowi każda zmiana = NOWY artefakt.** Przepinanie wersji w interfejsie Rafał próbował i **nie zadziałało**; ponowna publikacja **za każdym razem** zostawia udostępniony link zamrożony na wersji z chwili udostępnienia. Nie proponować już drogi „przepnij wersję" — od razu nowy adres.

To zdarzyło się dwa razy tego samego dnia na tej samej ofercie: raz przy zmianie cen (6000 → 2000), raz przy doprecyzowaniu zakresu. Za drugim razem wykryte tylko dlatego, że po publikacji zrobiłem `action: "read"` i przeczytałem nagłówek.

Powiązane: [[plan-magellan-bay-separate-project]].
