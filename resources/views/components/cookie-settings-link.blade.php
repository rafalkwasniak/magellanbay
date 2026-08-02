@props([
    // Czy w tym miejscu było o co pytać. Bez pomiaru nie ma czego cofać.
    'needed' => true,
])

{{-- Wycofanie zgody musi być równie łatwe jak jej udzielenie — bez tego link
     baner byłby jednorazową bramką, a decyzja nieodwracalna do czasu, aż ktoś
     sam wyczyści przeglądarkę.

     Formularz, nie odnośnik: zmiana stanu po stronie serwera należy do POST,
     a przy okazji działa bez JavaScriptu, którego storefront nie ładuje. --}}

@if ($needed)
    <form method="POST" action="{{ route('cookies.store') }}" {{ $attributes->merge(['class' => 'inline']) }}>
        @csrf
        <button type="submit" name="decision" value="reset" class="cursor-pointer underline-offset-2 transition hover:underline">
            Ciasteczka
        </button>
    </form>
@endif
