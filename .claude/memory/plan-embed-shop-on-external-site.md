---
name: plan-embed-shop-on-external-site
description: "ROZEZNANE 2026-08-15, ZERO kodu — osadzanie sklepu Kramio na cudzej stronie (klient z biurem podróży). iframe odpada, rekomendacja = wsad serwerowy katalogu."
metadata: 
  node_type: memory
  type: project
  originSessionId: f62169b8-be88-42dc-8ef3-ab0357fa1bd2
  modified: 2026-08-15T10:14:02.987Z
---

Zapotrzebowanie od klienta (biuro podróży, ~2000 podstron, własne menu/skrypty/rezerwacje): chce **jedną podstronę „Sklep"** na swojej domenie, która pokazuje sklep Kramio. Sklepem zarządza przez `kramio.pl`, sprzedaje przez swoją podstronę. To NIE jest nałożenie własnej domeny na cały storefront — to „wsad" na cudzą stronę.

**Status: tylko rozmowa, 15.08 temat ODŁOŻONY przez Rafała. Zero kodu, zero wyceny.**

**Dlaczego iframe odpada** (nazwane raz, nie wracać do rozważań):
- Sesja = ciasteczko third-party → Safari/iOS gubią koszyk między krokami. To projekt przeglądarki, nie usterka do obejścia.
- Bramki płatności (Paynow/P24) zabraniają ramkowania → i tak wyjście z ramki w najgorszym momencie.
- SEO zero (treść liczy się na adres ramki), brak linkowalnych adresów produktów, modale/toasty/zgoda cookies przycięte do prostokąta.
- Auto-wysokość akurat NIE jest problemem (postMessage) — nie to blokuje.

**Warianty, od najlepszego:**
1. **Proxy pod ścieżką** `klient.pl/sklep/*` → nasz storefront. Jedna domena = sesja first-party, płatności, SEO na jego domenie. Najlepiej gdy jego aplikacja pobiera nasz HTML serwerowo i wkleja we własny szablon — chrome (menu, stopka) dostajemy za darmo i zawsze aktualny.
2. **Wsad serwerowy tylko katalogu + zakup na `{shop}.kramio.pl`** ← REKOMENDACJA na start. Osadzona część bezstanowa, więc sesja/CSRF/ciasteczka/powroty z bramki po prostu nie występują. Działa nawet przy zabetonowanej stronie klienta.
3. iframe — ostatnia deska ratunku, i wtedy uczciwie: koszyk i tak wychodzi na nasz adres.

**Czego brakuje w kodzie dla wariantu 1/2** (dotyka fundamentów, dlatego to nie jest robota „na jednego klienta"):
- Świadomość prefiksu ścieżki na obcym hoście — dziś `route()` buduje od korzenia bieżącego hosta (ta sama rodzina pułapek co [[gotcha-route-helpers-on-subdomain]]).
- Tryb osadzony: chrome ścięty, ale dane sprzedawcy/regulamin/polityka/dostawa/odstąpienie MUSZĄ zostać widoczne.
- **Dwa adresy tego samego sklepu = duplikat treści.** Decyzja produktowa: kanoniczny jego adres + `noindex` na subdomenie (= oddajemy mu SEO) czy odwrotnie. Patrz [[plan-sitemap-search-console]].
- Powroty z płatności, linki w mailach, mapa strony celujące w jego host.
- Kolizje CSS/JS — Tailwind preflight kontra jego nagłówek, jego jQuery kontra Livewire. Scope'owanie stylów do zaplanowania.

**Pytania bez odpowiedzi** (zadać, gdy temat wróci): na czym stoi strona klienta i czy da się z jej szablonu wykonać żądanie HTTP (to rozdziela wariant 1/2 od 3); kto ma dostęp do konfiguracji jego serwera; czy to funkcja Kramio czy jednorazówka.
