---
name: open-monthly-plan-vs-struck-price
description: "ZAMKNIETE 2026-08-12: przekreslona cena na landingu (900/1800) jest OK, bo strona pokazuje rachunek 12 x 75 / 12 x 150, placisz za 10. Nie podnosic tematu ponownie."
metadata: 
  node_type: memory
  type: project
  originSessionId: 9b42a7e8-65de-49c5-a571-38f05836de8b
  modified: 2026-08-12T17:52:22.370Z
---

**ZAMKNIĘTE 2026-08-12. Nie wracać do tematu — ani z własnej inicjatywy, ani przy okazji przeglądu landingu.**

Cennik pokazuje przekreśloną cenę roczną obok promocyjnej: **900 → 750 zł** (Stragan) i **1800 → 1500 zł** (Pawilon), a **pod spodem jawny rachunek**: „2 miesiące za darmo — 75 zł/mies., płacisz za 10" (i analogicznie 150 zł). Liczone z `config('shop.billing')` (`months_paid` 10, `months_total` 12), nie wpisane w widok.

**Dlaczego to jest w porządku:** strona nie twierdzi, że ktoś kiedyś zapłacił 900 zł — pokazuje DZIAŁANIE, z którego ta liczba wynika. Czytelnik widzi każdy składnik. To zupełnie inna sytuacja niż przekreślona kwota bez wyjaśnienia.

**Jedyna teoretyczna rysa** (nazwana raz, wystarczy): stawki 75 zł/mies. nie da się dziś kupić, bo billing jest wyłącznie roczny — więc „2 miesiące za darmo" opisuje rabat wobec opcji nieobecnej w sprzedaży. Domknięcie na 100% wymagałoby wystawienia realnego planu miesięcznego, czyli dołożenia PRODUKTU, nie poprawienia tekstu.

**Skala ryzyka — korekta wcześniejszej, zbyt ostrej oceny:** poprzednia wersja tej notatki straszyła UOKiK-iem i Omnibusem. To były złe narzędzia: oba chronią KONSUMENTA, a klientem Kramio jest przedsiębiorca. Właściwą miarą jest reklama wprowadzająca w błąd z ustawy o zwalczaniu nieuczciwej konkurencji — poprzeczka znacznie wyżej, egzekwowana pozwem konkurenta, nie sprawdzeniem urzędu.

**Lekcja dla asystenta:** zanim ocenisz zgodność z prawem konsumenckim, sprawdź, KTO jest klientem. I przeczytaj cały blok cennika — przy weryfikacji 12.08 `grep` na słowie „miesięcznie" nie trafił skrótu „75 zł/mies." i przez chwilę twierdziłem, że wyjaśnienia na stronie nie ma. Rafał sprostował.

Powiązane: [[pricing-packages]], [[plan-package-payments]], [[landing-promises-drift-audit]]
