---
name: front-blocked-on-subdomain-ssl
description: "ODBLOKOWANE 2026-07-01: subdomeny + wildcard SSL `*.kramio.pl` działają; front sklepu (storefront, kolory/szablony) można już robić."
metadata: 
  node_type: memory
  type: project
  originSessionId: 777df83f-73fe-4104-ad17-c20175e935a7
  modified: 2026-07-25T20:45:30.530Z
---

**ODBLOKOWANE 2026-07-01.** Subdomeny + wildcard SSL `*.kramio.pl` **działają** (Rafał ogarnął u serwerowca). Blokada zdjęta — storefront i „Wygląd" (kolory/szablony) można robić, gdy przyjdzie ich kolej. Historyczny kontekst blokady poniżej.

**SPROSTOWANIE 2026-07-24 (Michał drążył, miałem błąd):** routing storefrontu na subdomenach jest w aplikacji **WŁĄCZONY i działa** — NIE „szkielet wyłączony". Dowód w kodzie: `routes/web.php` `Route::domain('{shop}.'.config('tenancy.central_domain'))->middleware('tenant')` + `App\Http\Middleware\ResolveShop` (slug→Shop, 404 gdy brak, share z widokami). Cała grupa tras storefrontu (home/produkty/koszyk/kasa/płatność/rejestracja/moje-konto) żyje w tej grupie. **Subdomeny już kierują i aplikacja obsługuje je end-to-end.** MYLĄCE komentarze **POSPRZĄTANE 2026-07-25** (commit `33a1946`): `config/tenancy.php`, nagłówek `routes/web.php` i sekcja w `CLAUDE.md` opisują teraz stan faktyczny; w CLAUDE.md został ślad, że to zdanie było nieprawdą (żeby mit nie odrósł). Zostaje tylko smoke-test na żywej subdomenie realnego sklepu — nie budowa. LEKCJA: streszczając zaległości, czytać kod, nie przestarzałe komentarze/pamięć.

**(Historia) Blokada (2026-06-30).** Wszystko, co dotyczy **frontu sklepu (storefront na subdomenie)**, czekało na **wildcard SSL dla subdomen `*.kramio.pl`**. Wildcard DNS działał; brakowało vhost+SSL — działka Rafała, ogarnięta 2026-07-01.

Konsekwencje kolejności prac:
- **Kolory / szablony** (strona „Wygląd”) — siadamy do tego **dopiero gdy front sklepu istnieje**, żeby widzieć, co zmieniamy. Box-placeholder już stoi.
- **Storefront** ([[storefront-draft-preview]], [[storefront-theme-system]]) — czeka na SSL.
- Do tego czasu robimy rzeczy **niezależne od frontu**: logika w panelu, auto-publikacja, pakiety (po uzgodnieniu — [[plan-packages]]).

Powiązane: [[multitenant-subdomain-architecture]], [[handoff-2026-06-29]].
