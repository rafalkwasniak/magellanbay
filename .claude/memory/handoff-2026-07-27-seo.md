---
name: handoff-2026-07-27-seo
description: "Handoff 2026-07-27 WIECZÓR: SEO 3 z 4 punktów zamknięte (nagłówki bezpieczeństwa, meta+canonical+OG, dostępność, grafika OG 1200×630). ZOSTAŁ box SEO + opisy od AI. 930 testów."
metadata: 
  node_type: memory
  type: project
  originSessionId: 37f72f3b-963d-48a4-ba3e-06ca036f5d60
  modified: 2026-07-27T21:38:42.322Z
---

**Sesja wieczorna 2026-07-27 (z Michałem). Dwa commity na `main`, wypchnięte, 930 testów zielonych.**
`9194d89` nagłówki bezpieczeństwa + meta/canonical/OG · `1dbc3b8` dostępność + generowana grafika OG.

## Zrobione (wszystko potwierdzone NA PRODUKCJI, nie tylko testami)
1. **Nagłówki bezpieczeństwa** — `SecurityHeaders` + sekcja w `config/security.php`. To była przyczyna oceny E (55/100 identycznie na 100 podstronach). `geolocation=(self)` ZOSTAJE dozwolone przez mapę paczkomatów; HSTS bez `preload` (nieodwracalne); CSP świadomie osobno, bo inline `<style>`/`<script>` wymagają nonce'ów.
2. **Meta description + canonical + Open Graph + twitter:card** — `App\Support\Seo`, layout storefrontu. Fallbacki FAKTOGRAFICZNE (nazwa, cena, sklep), bez obietnic o dostawie — jeden sklep wysyła, inny ma tylko odbiór. `noindex` na 7 widokach transakcyjnych.
3. **Dostępność** — skip link (własny CSS, `focus:not-sr-only` NIE MA w buildzie), `aria-label` na miniaturach galerii, `for`/`id` przy polu e-mail w koncie klienta. Dwa z trzech zarzutów audytu wskazywały to samo miejsce (miniatury).
4. **Grafika OG 1200×630** — `OgImageGenerator` + job + `ShopObserver` + `artisan og:generate`. Kolumna `shops.og_image_path`. Figtree (OFL) w `resources/fonts/`. **Decyzja Rafała po obejrzeniu renderu: BEZ podpisu z nazwą przy wariancie z logo** — logo samo ją niesie. Nazwa wraca tylko gdy logo nie ma (nazwa na kolorze marki). Logo skalowane też W GÓRĘ (sufit 2,4×), bo typowy upload to 200×200.

**Cały łańcuch przetestowany na żywo:** Rafał wgrał nowe logo w panelu → observer → job w kolejce → cron → nowa grafika + sprzątnięty stary plik + zmieniony `og:image` na stronie. Bez udziału asystenta.

## ZOSTAŁO z SEO (jutro, ostatni punkt)
**Box „SEO" w formularzu + opisy pisane przez AI.** Wszystkie ustalenia wykonawcze są już zapisane w [[plan-seo-audit]] (sekcja „USTALENIA WYKONAWCZE") — nie ustalać od nowa. Skrót:
- kolumna na opis + znacznik „ręczny"; **ręczna edycja wygrywa na zawsze**;
- generowanie w KOLEJCE (padnięty DeepSeek nie może blokować zapisu), tylko gdy skrót tekstu źródłowego się zmienił, próg wejścia ~120 znaków treści;
- twardy limit ~155 znaków + czyszczenie odpowiedzi modelu;
- box nazwany wprost „SEO" (będzie rósł), z licznikiem znaków i przyciskiem **„Wygeneruj z AI"**; wygenerowany tekst ląduje w polu, ale zapisuje się dopiero przy „Zapisz";
- dotyczy strony głównej sklepu i produktów; strony informacyjne i wykaz — deterministycznie.
**Uwaga:** to zahacza o [[plan-ai-usage-limits]] (limity użyć AI per pakiet, okno dzienne LUB tygodniowe) — limitów NIE wdrażamy przy okazji, ale warto pisać kod tak, by dały się dołożyć.

## Potem
Wracamy do [[priorities-launch-first]]: Zwroty Fazy B/C → korespondencja seryjna (+ link wypisu) → płatności za pakiety i zakup z głównej (z dopłatą przy zmianie pakietu) → wysyłki → panel admina → wygaśnięcie pakietu.

## Drobiazgi techniczne z tej sesji
- `docs/ursalogic-kramio.pdf` jest w `.gitignore` (`/docs/*.pdf`), plik zostaje lokalnie. Czytanie: `pdftotext -layout` (jest), `pdftoppm` NIE MA.
- Logo `ilikemybike` daje `libpng warning: iCCP` — ostrzeżenie o profilu kolorów w pliku źródłowym, nie błąd.
- Kratka na grafice OG sklepu `cocojambo` to szachownica przezroczystości WYPALONA w logo sprzedawcy, nie usterka generatora.
