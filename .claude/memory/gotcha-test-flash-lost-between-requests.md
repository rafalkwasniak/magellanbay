---
name: gotcha-test-flash-lost-between-requests
description: "W teście `post()` a potem osobne `get()` GUBI flash — błędy walidacji i old() nie dożywają. Trzeba from() + followingRedirects()."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 4d8bfafa-24ef-47c8-8b0f-9bb8f207d0e5
  modified: 2026-08-15T15:00:29.568Z
---

Sprawdzając w teście, **jak wygląda strona po odbiciu walidacji**, nie da się zrobić `post()` a potem osobnego `get()` — worek błędów i stare dane wejściowe nie dożywają do drugiego żądania. `session()->getOldInput()` zwraca pustą tablicę, `@error` nie renderuje się wcale, a test cicho mierzy zero zamiast jedynki.

Zmierzone 2026-08-15 przy zawężaniu komunikatu zgody na ekranie „Mój pakiet".

**Nie działa:**
```php
$this->actingAs($user)->post($url, $dane)->assertSessionHasErrors('pole');
$content = $this->get($ekran)->getContent();   // ← flash już zjedzony
```

**Działa** — odtwarza to, co robi przeglądarka (odbicie wraca NA TEN SAM ekran, błąd żyje w tym jednym przeładowaniu):
```php
$content = $this->actingAs($user)
    ->from($ekran)
    ->followingRedirects()
    ->post($url, $dane)
    ->getContent();
```

Uwaga: `followRedirects()` **nie jest** metodą na `TestResponse` (leci „Call to undefined method Illuminate\Http\RedirectResponse::followRedirects()"). Poprawnie: `followingRedirects()` PRZED `post()`.

Bez `from()` odbicie `back()` leci na `/`, więc renderuje się landing zamiast badanego ekranu — assercja przechodzi lub nie z zupełnie innego powodu.

Powiązane: [[form-client-validation-convention]].
