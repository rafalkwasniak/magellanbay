---
name: gotcha-route-helpers-on-subdomain
description: "route() i url() wywolane na subdomenie sklepu buduja link do TEJ subdomeny, nie do centrali - /rejestracja trafia w rejestracje klienta"
metadata: 
  node_type: memory
  type: reference
  originSessionId: 9b42a7e8-65de-49c5-a571-38f05836de8b
  modified: 2026-08-12T16:36:34.542Z
---

Trasy centrali NIE mają przypiętej domeny (odpowiadają na każdym hoście, który nie jest subdomeną sklepu). Skutek: **`route('register')` i `url('/')` renderowane NA SUBDOMENIE zbudują adres tej subdomeny**, nie centrali.

To nie jest tylko kosmetyka. Storefront ma własne `/rejestracja` (konto KLIENTA sklepu, `storefront.register` w `routes/web.php`), więc link „załóż sklep" wskazałby zupełnie inny formularz. Ta sama kolizja dotyczy `/logowanie`, `/nowe-haslo`, `/polityka-prywatnosci`.

**Jak robić:** każdy link, który ma prowadzić na platformę niezależnie od miejsca renderowania, buduj przez `App\Support\Central::url('/sciezka')` (zwraca adres bez końcowego ukośnika dla korzenia, żeby był zgodny z `url()`). Dotyczy to zwłaszcza layoutów wspólnych dla obu światów — `components/layouts/guest.blade.php` używa go dla logo i polityki prywatności, bo tego samego layoutu używa strona wolnej subdomeny ([[plan-unclaimed-subdomain-landing]]).

Pokrewna pułapka z tej samej rodziny: w teście `url()` wywołane na subdomenie zwraca tę subdomenę ([[handoff-2026-08-03]]).
