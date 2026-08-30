---
name: ui-design-direction
description: "Kierunek wizualny paneli = \"warm boutique\" (C); emoji tylko jako ikony menu/funkcyjne, nigdy w tekście."
metadata: 
  node_type: memory
  type: feedback
  originSessionId: eb5e9cc9-94cf-4010-b386-8ca993d18870
---

Po obejrzeniu 3 demo (jasny split / ciemny tech / ciepły butik) Rafał wybrał **kierunek C — "warm boutique"** dla logowania i paneli:

- Tło kremowe/stone, miękkie rozmyte kształty (amber/rose/orange), karty `rounded-3xl` z `backdrop-blur`, ciepły akcent `gradient amber-500 → rose-500`. Ciepły, ludzki, premium — nie enterprise, nie tech.
- **Ciemny motyw odrzucony** świadomie (mimo że to panel dla klientów, decyzję podejmuje Rafał).

**Zasada o emoji (ważna):** emoji **tylko jako oznaczenia menu i ikony funkcyjne** (pozycje nawigacji, kafelki statystyk, awatary sklepów). **Nigdy w zdaniach ani nagłówkach** — tekst ma być nieco poważniejszy. Np. „Cześć 👋"/„Dzień dobry ☀️" → bez emoji.

**Why:** ten branding to marka „Shop" — panele mają jeden systemowy design (storefronty są osobno motywowane przez sprzedawców, patrz [[storefront-theme-system]]). Emoji w prozie psują powagę panelu z liczbami.

**How to apply:** każdy nowy ekran panelu/auth robimy w tym języku wizualnym; trzymaj emoji poza tekstem. Powiązane: [[frontend-stack-decision]] (Livewire/Blade).

**Układ ekranów panelu (ustalone 2026-06-28):** trzymamy koncepcję jak na `/panel` — siatka 12-kolumnowa, główna treść `lg:col-span-8`, kolumna pomocnicza `lg:col-span-4` (na treści opisowe/pomoc). Nie zwężaj formularzy `max-w-*`; pełna szerokość przez kolumnę 8/12. Wzorzec wdrożony na ekranie „Mój sklep" (seller/shop/edit).

**Stylowanie `<select>` (Safari):** natywny select w Safari jest brzydki (ignoruje padding, krótki). Globalna reguła w `resources/css/app.css` — POZA `@layer` (żeby wygrać z utilitami Tailwinda) — zdejmuje `appearance` i rysuje własny chevron (SVG w `background-image`), padding-right na chevron. Wzorzec przeniesiony z projektu kociaczek.com.pl (ten sam serwer). Nie trzeba klas per-select.
