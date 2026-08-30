---
name: storefront-draft-preview
description: "Przy budowie storefrontu — właściciel/admin mają podgląd sklepu-szkicu (przed publikacją); publiczny gość widzi „już wkrótce\"."
metadata: 
  node_type: memory
  type: project
  originSessionId: ad2d01af-7396-446a-bc53-3263cd9693a7
---

Ustalenie (2026-06-28, pomysł Rafała) do realizacji RAZEM z modułem storefront (dziś storefront to wyłączony szkielet w routes/web.php — nie istnieje).

Sklep w stanie szkic / niespełniający warunków publikacji (brak aktywnego produktu) NIE jest publiczny — gość widzi stronę „Już wkrótce udostępnimy naszą ofertę. Zapraszamy ponownie." ALE **właściciel (i administrator) zachowują dostęp i mogą podejrzeć storefront szkicu** — widzą dokładnie to, co skonfigurowali (logo, opis, dane, produkty), zanim sklep stanie się publiczny. Zgodne ze specyfikacją (sekcja „Widoczność sklepu" + „Administrator oraz właściciel sklepu zachowują dostęp").

Konsekwencja dla UI: na pulpicie/edycji link do `demo.kramio.pl` docelowo prowadzi do tego podglądu dla właściciela. Do czasu zbudowania storefrontu opis statusu na pulpicie celowo NIE obiecuje podglądu („Nie jest jeszcze publiczny. Opublikujemy go automatycznie po dodaniu pierwszego produktu."). Publikacja jest automatyczna po dodaniu pierwszego aktywnego produktu. Powiązane: [[multitenant-subdomain-architecture]], [[storefront-theme-system]].
