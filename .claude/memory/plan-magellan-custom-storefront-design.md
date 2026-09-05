---
name: plan-magellan-custom-storefront-design
description: "PRZYPUSZCZENIE Rafala (05.09), NIE ZLECENIE: Magellan moze dostac projekt storefrontu OD ZERA — wlasny uklad, nie tylko paleta. NA RAZIE NIC NIE ROBIMY."
metadata: 
  node_type: memory
  type: project
  originSessionId: 43b6e9cc-a378-4a18-8cf7-b97d8a63be16
  modified: 2026-09-05T15:39:50.151Z
---

> **STAN: nic nie robimy.** Rafał powiedział wprost: *„na razie tylko tak przypuszczam, ale kto wie. do tematu wrócimy na pewno w przyszłości"*. Nie proponować prac, nie planować kodu, nie wyceniać. Jeśli Rafał wróci do tematu — najpierw zapytać, czy klient to zamówił.

## Przypuszczenie

Dla Magellana może być potrzebny **projekt graficzny od zera**, a nie wybór palety w istniejącym szablonie. Rafał ujął to tak: *„trzeba będzie wyciąć to co nazywamy szablonami i włożyć dedykowany szablon, może nawet z innym układem"*.

To znaczy: **inny UKŁAD strony**, nie inne kolory. Kolory zostały już zrobione — szablon `white_harbour` („Biały port"), patrz [[storefront-theme-system]].

## Czego NIE trzeba będzie wycinać — mechanizm już jest

Ważne, żeby przy powrocie do tematu nie zacząć od demontażu. `ResolveShop` **dokłada katalog widoków sklepu PRZED wspólny**:

```
resources/views/storefronts/{slug}/home.blade.php   ← jeśli istnieje, wygrywa
resources/views/storefront/home.blade.php            ← fallback
```

Konsekwencje, które przesądzają o kosztach:
- nadpisuje się **4–6 plików zamiast 24** — reszta dziedziczy późniejsze poprawki bezpieczeństwa i zmiany API dostawców;
- **inny układ strony mieści się w tym bez wycinania czegokolwiek** — nadpisany widok nie musi mieć nic wspólnego ze wspólnym;
- `shops.template` już trzyma slug per sklep.

**Komponenty w `resources/views/components/storefront/` mają zostać wspólne** (`product-card`, `order-totals`, `delivery-summary`, `breadcrumbs`, `tag-cloud`, `account-shell`, `information-shell`) — wtedy poprawki dziedziczą się do obu wdrożeń.

## Pułapka w wycenie

Obietnica „4–5 h przy kolejnym kliencie" dotyczy **hydrauliki**. Projekt graficzny nowego frontu od zera to nadal **2–4 dni**. Tanio jest wyłącznie przy wariancie istniejącego wyglądu. Nie mylić tych dwóch liczb w rozmowie z pośrednikiem.

## CZERWONA LAMPKA

Zmiana układu **kasy albo logiki koszyka** to osobna sprawa i osobna wycena — tędy idą pieniądze. Reguła „gdzie trafia każda prośba klienta" jest w `CLAUDE.md` sek. 1 oraz [[plan-magellan-bay-separate-project]].

Powiązane: [[plan-storefront-theming]], [[ui-design-direction]].
