---
name: open-landing-fabricated-stats
description: "ROZWIĄZANE 2026-07-20 — zmyślone „1 200+ sklepów\" i błędy pakietów wycięte z welcome.blade.php. Historia dla kontekstu."
metadata: 
  node_type: memory
  type: project
  originSessionId: d742d9f9-2a68-4405-a0fd-fa8cdd4f1c1d
  modified: 2026-07-20T19:44:09.089Z
---

**ROZWIĄZANE 2026-07-20** (Michał robił landing „do puszczenia znajomym"). Wszystkie fałsze wycięte z `resources/views/welcome.blade.php`:
- „1 200+ aktywnych sklepów" / „5 min średni czas startu" / „25 produktów" — **cały fejkowy pasek statystyk usunięty**, zastąpiony sekcją **Pakiety** czytającą realne dane z `config('shop.packages')` (ceny Kram 0 / Stragan 75 / Pawilon 150 zł/mc = price_yearly/10, oraz limity i funkcje z entitlements). Nie może się rozjechać ze źródłem prawdy.
- „pakiet Free" → **Kram** (badge hero + wszędzie).
- „Sklepy, które mogłyby powstać" (zmyślone nazwy + subdomeny) → **„Dla kogo jest Kramio"** = branże/przypadki użycia, bez udawanych sklepów.
- Makieta „Bukiety Anny" ZOSTAJE jako uczciwa ilustracja — dostała plakietkę **„Przykładowy sklep"**; stopka mówi „widok w nagłówku to wizualizacja".

Zweryfikowane: render OK (PHP 8.5 tinker), nowa klasa `ring-amber-400/50` dodana do buildu (`npm run build`, RAYON_NUM_THREADS=1).

**Historia (dlaczego to było groźne):** placeholder wyglądający na gotowy landing (209 linii) stał na produkcji z „dowodem społecznym", którego nikt nie pilnował — [[feedback-dont-stack-caveats]] tej kategorii (fakt nieprawdziwy) NIE dotyczyła, więc poszło od razu.

**Otwarte dalej:** pełny redesign/dopracowanie treści landing = wciąż szerszy temat (patrz [[plan-admin-panel-and-landing]]); kafelek „Płatności i dostawy" w sekcji Funkcje wciąż opisuje możliwości platformy ogólnie — OK, bo płatności online realnie istnieją (Paynow wdrożony), ale to per-sklep płatne (Stragan+).
