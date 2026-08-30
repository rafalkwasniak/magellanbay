---
name: input-normalization-conventions
description: "Wzorzec normalizacji danych wejściowych (telefon, NIP, rej., boole, blank) — robić w prepareForValidation Form Requestu, przed walidacją i zapisem."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 96f32cca-ed28-4afe-a864-1ce4ae544d7d
---

Konwencje normalizacji inputu (od Rafała, ze sprawdzonego projektu). **Zasada główna:** dane usera sprowadzamy do jednej kanonicznej postaci **w `prepareForValidation()` Form Requestu** — ZANIM zobaczą je reguły walidacji i kontroler/baza. Nie w modelu (bez mutatorów/castów do tego), nie w kontrolerze. Jedno źródło prawdy → walidacja i porównania (np. unikalność) działają niezależnie od zapisu usera.

**Architektura:** powtarzalne reguły = małe **traity w `App\Http\Requests`**, wpinane przez `use`; jednorazowe/proste = inline w `prepareForValidation()`. Merge: `$this->merge([...])` nadpisuje; usuwanie klucza: `$this->getInputSource()->remove($key)`.

**Reguły:**
1. **Telefon** → serwis `PhoneService::normalize(string): string`. trim → usuń spacje i `+` → jeśli nie zaczyna się od `48`, doklej `48` z przodu. Wynik: same cyfry z prefiksem kraju (`48123456789`). Prefiks PL hardcoded (projekt jednokrajowy). ✅ **W shop.kwasniak.org format kanoniczny = `48` + 9 cyfr** (ustalone 2026-06-26). Zaimplementowane: `App\Services\PhoneService::normalize()` (woła się `app(PhoneService::class)->normalize(...)` w `prepareForValidation`), walidacja `regex:/^48[0-9]{9}$/`.
2. **NIP** (opcjonalny) → trait `ValidatesNip`: `normalizeNip()` (strip do cyfr: `preg_replace('/\D/','',...)`, tylko gdy klucz istnieje i ≠ null) ODDZIELONE od `nipRule(): Closure` (dokładnie 10 cyfr `/^\d{10}$/` + suma kontrolna mod-11, wagi `[6,5,7,2,3,4,5,6,7]` dla pierwszych 9 cyfr, OK gdy `sum % 11 === (int) cyfra_10`; jeden komunikat na złą długość i złą sumę). **To bierzemy, gdy dojdą pola firmowe.**
3. **Nr rejestracyjny** → inline gdy `filled()`: usuń spacje + `strtoupper()`; walidacja `regex:/^[A-Z0-9]{4,10}$/`.
4. **Boole z query/form** → trait `NormalizesBooleanInput::normalizeBooleans(array $keys)`: `filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)`. Parsuje „true/false/yes/on…"; nieparsowalne (`null`) ZOSTAW, by `boolean` odrzuciło. (Laravelowy `boolean` nie łyka stringa `"true"`.)
5. **Blank-drop przy edycji częściowej** → trait `NormalizesBlankInput::dropBlank(array $keys)`: jeśli klucz istnieje, ale `blank()` → USUŃ klucz (`sometimes` go pominie). ⚠️ NIGDY dla pól `nullable`, gdzie pusty = świadome „wyczyść" i `null` musi dojść do walidacji. Caller jawnie wymienia tylko „nietknięte" klucze.
6. **E-mail** — NIE normalizujemy do zapisu. Jedyne miejsce: klucz throttle przy logowaniu `strtolower($email)` (Jan@X.pl i jan@x.pl dzielą limit). Nie dotyka wartości zapisywanej.

Poboczne: sklejanie whitespace w treściach `preg_replace('/\s+/',' ',$t)`+`trim()`; import CSV — nagłówki `trim()+strtolower()`, klucz wiersza `trim()`+`/^[a-zA-Z0-9_.-]+$/`.

**Checklista:** normalizuj w warstwie walidacji; powtarzalne→traity, jednorazowe→inline; telefon=serwis z hardcoded prefiksem; NIP=oddziel strip od mod-11; boole z query parsuj ale nieparsowalne zostaw; blank-drop tylko dla „nietkniętych", nie `nullable`; wszystko działa zanim reguły/kontroler zobaczą wartość.

**Stan w shop.kwasniak.org (2026-06-26):** ujednolicone z wzorcem — `App\Services\PhoneService` (telefon 48+9) + trait `App\Http\Requests\ValidatesPassword` (`passwordRules()`/`passwordMessages()`), wpięte w `ActivationRequest`. Do dodania gdy będą pola firmowe/edycja profilu: trait `ValidatesNip` (mod-11), `NormalizesBooleanInput`, `NormalizesBlankInput`.
