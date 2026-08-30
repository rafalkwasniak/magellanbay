---
name: ""
metadata: 
  node_type: memory
  originSessionId: b4df2d45-73c2-45d4-81b0-2be1634ea6d5
---

**ROZSTRZYGNIĘTE 2026-07-15 — stopka maili usunięta w całości.**

`resources/views/components/mail/layout.blade.php` miał zaszytą na sztywno stopkę dla WSZYSTKICH maili:

> „{nazwa sklepu} · ta wiadomość została wysłana automatycznie / Jeśli nie spodziewałeś się tej wiadomości, możesz ją zignorować."

Wyszło przy module „Napisz do klienta" ([[handoff-2026-07-15-messenger]]): pod ręczną wiadomością sprzedawcy proszącą o decyzję stopka mówiła „to automat, możesz zignorować". Zaproponowałem 3 warianty (kolumna `automated` / przeredagowanie / zostawić) — **Rafał odrzucił wszystkie i trafnie postawił mocniejszą tezę:** problem nie dotyczy tylko nowego maila, bo **nigdzie nie ma adresu noreply**, więc stopka kłamie w KAŻDYM mailu.

Zweryfikowane w kodzie przed cięciem:
- `OrderMailer` + `CustomerActivationMailer` → `reply_to` = `contact_email` sklepu.
- `ActivationMailer` (mail platformy do sprzedawcy) → bez `reply_to`, więc odpowiedź wraca na `From` = `sklep@kramio.pl`.

Czyli na każdy mail da się odpowiedzieć i ktoś to przeczyta. Cały blok `<tr>` stopki wycięty (nie przeredagowany) — brand niesie nagłówek, a zewnętrzna komórka ma własny padding 32px, więc nic nie zginęło. Żaden test nie zależał od stopki.

**Why:** to jest wzorzec myślenia Rafała, nie jednorazowa decyzja — nie akceptuje komunikatu, który zniechęca klienta do kontaktu tylko dlatego, że „tak się robi w mailach transakcyjnych". Jak nie ma noreply, to nie udajemy, że jest. Przy podobnych „standardowych" formułkach pytać, czy w NASZYM systemie są w ogóle prawdziwe.

**Wołacz — ZROBIONE 2026-07-15**, patrz [[plan-vocative-dictionary]].

Problem „spodziewał**eś**" (rodzaj męski) zniknął sam razem ze stopką.

**How to apply:** te rzeczy nie wychodzą z testów — asercje sprawdzają `intro_lines`/`outro_lines`, nie skorupę. Wyszły dopiero z RENDERU maila do HTML. Przy dotykaniu maili renderuj i czytaj całość. Powiązane: [[email-outbox-cron-pattern]], [[per-shop-email-identity-branding]].
