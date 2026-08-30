---
name: gotcha-package-key-stall-is-kram
description: "PUŁAPKA NAZW: klucz `stall` to Kram (0 zł), a Stragan to `booth`. Brzmi odwrotnie niż jest."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 4d8bfafa-24ef-47c8-8b0f-9bb8f207d0e5
  modified: 2026-08-15T15:00:41.665Z
---

Klucze pakietów w `config/shop.php` **nie odpowiadają intuicji podpowiadanej przez angielskie słowa**:

| klucz | nazwa | cena |
|---|---|---|
| `stall` | **Kram** | 0 zł |
| `booth` | **Stragan** | 750 zł |
| `pavilion` | Pawilon | 1500 zł |

`stall` znaczy „stragan", więc odruchowo czyta się go jako Stragan — a to darmowy Kram. Kosztowało mnie to błędne założenie 2026-08-15: uznałem, że wszystkie trzy skloby są na pakiecie płatnym, i zdziwiłem się, czemu nie renderuje się przedłużenie ani zejście niżej. Powód był prozaiczny: **darmowy pakiet nie ma czego przedłużać ani na co zejść**, więc `PackageUpgrade::renewal()` zwraca `unavailable`, a `downsizeQuotes()` pustkę.

Przy czytaniu kodu i przy zapytaniach do bazy sprawdzać cenę, nie nazwę klucza.

Powiązane: [[pricing-packages]], [[plan-package-payments]].
