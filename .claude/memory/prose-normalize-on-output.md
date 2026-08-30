---
name: prose-normalize-on-output
description: "Treść z edytora (Trix) normalizowana NA WYJŚCIU (App\\Support\\Prose), nie na zapisie — baza trzyma natywny Trix, spójny układ składany przy renderze. Decyzja + gdzie wpięte."
metadata: 
  node_type: memory
  type: project
  originSessionId: 8b701b2c-27a6-4c0c-a3bd-d0669cf2bb18
---

**Ustalone i wdrożone 2026-07-16 (commit 91bbc82).** Treść bogata z edytora Trix (strony CMS, „O sklepie"=`shop.description`, opis produktu, kafle home, Polityka) jest **normalizowana na WYJŚCIU**, tuż przed wyświetleniem — nie na zapisie.

**Dlaczego na wyjściu, nie na zapisie (decyzja Rafała):**
- **Round-trip edytora:** Trix ma własny model (`div`/`br`); gdybyśmy zapisali przerobione `<p>`, przy ponownym wczytaniu Trix by to „poprawiał" i układ dryfował. Baza trzyma natywny Trix → edytor wczytuje swój format bez niespodzianek.
- **Wstecznie + odczepialnie:** reguły w jednym miejscu działają na WSZYSTKIE strony, też dodane dawno; zmiana reguł nie wymaga ruszania bazy ani ponownego zapisu.

**Podział ról (nie mylić):**
- `App\Services\HtmlSanitizer` — na ZAPIS (Form Requesty), BEZPIECZEŃSTWO: wąska whitelista tagów, `{!! !!}` bezpieczny. Zostaje bez zmian. Renames h1→h2 na zapisie.
- `App\Support\Prose::render(string): string` — na WYJŚCIU, UKŁAD: `<div>`/`<p>` „linie" i podwójne `<br>` → osobne `<p>`; pojedynczy `<br>` (miękkie łamanie) zostaje; nagłówki/listy jako własne bloki; puste linie (`<br>`/`&nbsp;`) znikają; nagłówki treści → `<h2>`. Idempotentne (czysty `<p>`/`<h2>` przechodzi bez szkody). DOMDocument, bez dodatkowych bibliotek (shared-host).

**Wpięcie (wszystkie punkty `.st-prose` na storefroncie):** `storefront/page.blade`, `about.blade`, `product.blade`, `home.blade` (kafel gdy treść się mieści), `privacy.blade` — każdy przez `{!! \App\Support\Prose::render($expr ?? '') !!}`. Nowy punkt renderu treści z edytora → owinąć tak samo.

**Problem, który to rozwiązało:** strona „zespół" miała ogromne przerwy nad/pod `h2`, bo edytor wstawił `<br><br>` wokół nagłówka, a to nakładało się na marginesy `.st-prose h2`. Polityka (nasz czysty szablon `<p>`/`<h2>`) układała się równo — stąd rozbieżność. Testy: `tests/Unit/ProseTest.php`. Powiązane: [[plan-nip-autofill-and-editor]], [[plan-storefront-editorial-and-pages]].
