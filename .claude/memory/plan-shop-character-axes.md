---
name: plan-shop-character-axes
description: "Charakter sklepu (WDROŻONE 2026-08-08): czcionka nagłówków dekoracyjna/prosta + zaokrąglenia małe/średnie/duże, niezależne od szablonu. Technika: nadpisywanie zmiennych Tailwinda v4 zamiast edycji widoków."
metadata: 
  node_type: memory
  type: project
  originSessionId: e03d2a67-a20c-47fc-82f6-de09ee8ca64e
  modified: 2026-08-08T09:58:02.114Z
---

**Wdrożone 2026-08-08, commit `7e86e5d`. Testy 1400 → 1416.** Pomysł Rafała po rozmowach ze znajomymi: „znajomy robi wędlinę na zamówienie, ale sprzedaż szynki to nie sprzedaż biżuterii" — ten sam szablon musi umieć ściszyć ozdobność.

## Co to jest
Box **„Charakter"** na `/sprzedawca/wyglad`, pod *Kolorem przewodnim*. Dwie osie:
- **Czcionka nagłówków:** `decorative` (Instrument Serif) / `plain` (ten sam sans co treść)
- **Zaokrąglenia:** `small` / `medium` / `large`

Obie **niezależne od szablonu** — leżą w JSON `shops.theme` pod `font` i `radius`, OBOK `palette` (która została per szablon). Zmiana szablonu ich nie kasuje. Definicje w `config/themes.php`, sekcja „Charakter sklepu".

## Technika warta powtórzenia — nadpisywanie zmiennych Tailwinda v4
Tailwind v4 generuje utilities czytające zmienne CSS:
`.rounded-xl{border-radius:var(--radius-xl)}`, `.font-serif{font-family:var(--font-serif)}`, `.text-4xl{font-size:var(--text-4xl)}`.

Dlatego **kilkanaście linii w `:root` layoutu storefrontu przestawia ~200 użyć klas w widokach** — bez edycji choćby jednego widoku, bez kompilacji CSS per sklep (wymóg shared-hostingu) i **bez przebudowy Vite** (żadna nowa klasa nie powstaje). Inline `<style>` jest po arkuszu Tailwinda, więc przy równej wadze selektora wygrywa.

**Kluczowy niuans zakresu:** zmienną można ustawić NA elemencie, nie tylko w `:root` — wtedy działa tylko na niego i jego potomków. Wykorzystane dwa razy:
- korekta stopni nagłówków siedzi w regule `.font-serif { --text-4xl: … }`, więc kurczą się WYŁĄCZNIE nagłówki, a treść bierze rozmiary z `:root`;
- podgląd w panelu ma zmienne na kontenerze kafla, więc panel dookoła zostaje nietknięty.

## Decyzje, które wyszły z oglądania na żywo
1. **Skala `małe/średnie/duże`, nie `brak/małe/duże`** (poprawka Rafała). To zdjęło cały problem: `rounded-full` ma w CSS literał (`3.4e38px`), nie zmienną, więc przy wyzerowanych `--radius-*` pigułki zostałyby owalne obok idealnie ostrych kafli. Nie schodzić do zera bez rozdzielenia pigułek na osobną klasę.
2. **Rozmiary nagłówków w widokach są dobrane POD SERIF**, który czyta się optycznie mniejszy niż sans. Sam podmieniony krój dawał za duże i za ciężkie nagłówki. Krój prosty ma własną drabinkę `sizes` (−15% przy `text-xl` do −31% przy `text-7xl`); **pierwsza runda korekty (−10…−17%) była za łagodna, Rafał kazał zejść niżej**. Wysokości wierszy w Tailwindzie są bezjednostkowe, więc schodzą same.
3. Nazwa produktu w mini-podglądzie dostała `font-serif`, bo na prawdziwej karcie produktu tak jest — bez tego przełącznik kroju nie miał czego pokazać w podglądzie.

## Gotcha przy zapisie motywu
`AppearanceController` składał wcześniej `theme` OD ZERA wewnątrz `if ($request->filled('template'))`. Teraz wychodzi od `$shop->theme ?? []` i dokłada — inaczej charakter ginąłby przy każdej zmianie szablonu. Klucze ustawiane jawnie na `null` + `array_filter` na końcu, żeby wyczyszczenie koloru dalej usuwało wpis.

Powiązane: [[storefront-theme-system]], [[tailwind-classes-must-exist-in-build]], [[ui-design-direction]], [[incremental-checkpoints-per-element]].
