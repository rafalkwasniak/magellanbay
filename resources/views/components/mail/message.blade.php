@props([
    'brand' => null,
    'preheader' => null,
    'heading' => null,
    'greeting' => null,
    'lines' => [],          // bloki przed przyciskiem: string = akapit, tablica = akapit z linii sklejonych <br>
    'bodyHtml' => null,     // treść z edytora sprzedawcy (zsanityzowana na zapisie); zamiast `lines`
    'productCard' => null,  // migawka promowanego produktu (mailing): name, price, previous_price, excerpt, image_url, url
    'actionText' => null,   // tekst przycisku CTA (opcjonalny)
    'actionUrl' => null,
    'outroLines' => [],     // akapity po przycisku
    'unsubscribeUrl' => null,   // TYLKO korespondencja seryjna; maile transakcyjne zostawiają puste
])

@php
    $brand ??= \App\Support\MailBranding::system();
@endphp

<x-mail.layout :brand="$brand" :preheader="$preheader">
    @isset($heading)
        <h1 style="margin:0 0 16px 0; font-size:24px; line-height:1.25; font-weight:700; letter-spacing:-0.02em; color:{{ $brand['heading'] }};">{{ $heading }}</h1>
    @endisset

    @isset($greeting)
        <p style="margin:0 0 16px 0; font-size:15px; line-height:1.65; color:{{ $brand['ink_card'] }};">{{ $greeting }}</p>
    @endisset

    @foreach ($lines as $block)
        @php
            // String = pojedynczy akapit; tablica = jeden akapit, linie sklejone <br>.
            // Odstęp akapitu (16px) daje „pustą linię" między blokami, a <br> trzyma
            // linie jednego bloku ciasno razem.
            $html = implode('<br>', array_map([\App\Support\MailMarkup::class, 'inline'], (array) $block));
        @endphp
        <p style="margin:0 0 16px 0; font-size:15px; line-height:1.65; color:{{ $brand['muted'] }};">{!! $html !!}</p>
    @endforeach

    @isset($bodyHtml)
        {{-- Treść napisana w edytorze przez sprzedawcę. HTML przeszedł przez
             HtmlSanitizer na zapisie (biała lista znaczników), a Prose układa go
             w równe akapity — te same dwa filtry co treść stron sklepu.
             Kolory i typografia inline, bo klienty pocztowe ignorują arkusze. --}}
        <div style="font-size:15px; line-height:1.65; color:{{ $brand['muted'] }};">
            {!! \App\Support\Prose::render($bodyHtml) !!}
        </div>
    @endisset

    @isset($productCard)
        {{-- Karta promowanego produktu — migawka z chwili wysyłki.

             Układ tabelkowy i szerokość 100%: klienty pocztowe (zwłaszcza
             Outlook) nie znają flexa ani grida, a tabela zachowuje się
             przewidywalnie także na telefonie.

             Karta musi mieć sens BEZ obrazka — skrzynki domyślnie blokują
             grafikę, więc nazwa, cena i przycisk niosą treść same z siebie,
             a zdjęcie jest dodatkiem. Stąd też `alt` z nazwą produktu. --}}
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
               style="margin:28px 0 8px 0; border:1px solid {{ $brand['border'] ?? '#e7e5e4' }}; border-radius:16px; overflow:hidden;">
            @if (($productCard['image_url'] ?? null) !== null)
                <tr>
                    <td style="padding:0; line-height:0;">
                        <a href="{{ $productCard['url'] }}" target="_blank" rel="noopener">
                            <img src="{{ $productCard['image_url'] }}" alt="{{ $productCard['name'] }}" width="100%"
                                 style="display:block; width:100%; max-width:100%; height:auto; border:0;">
                        </a>
                    </td>
                </tr>
            @endif
            <tr>
                <td style="padding:20px;">
                    <p style="margin:0 0 6px 0; font-size:17px; font-weight:700; line-height:1.35; color:{{ $brand['heading'] }};">
                        <a href="{{ $productCard['url'] }}" target="_blank" rel="noopener" style="color:{{ $brand['heading'] }}; text-decoration:none;">{{ $productCard['name'] }}</a>
                    </p>

                    <p style="margin:0 0 10px 0; font-size:20px; font-weight:700; line-height:1.3; color:{{ $brand['accent'] }};">
                        {{ $productCard['price'] }}
                        @if (($productCard['previous_price'] ?? null) !== null)
                            <span style="margin-left:8px; font-size:14px; font-weight:400; text-decoration:line-through; color:{{ $brand['muted'] }};">{{ $productCard['previous_price'] }}</span>
                        @endif
                    </p>

                    @if (($productCard['previous_price'] ?? null) !== null)
                        {{-- Obowiązek z dyrektywy Omnibus: przy obniżce podajemy
                             najniższą cenę z 30 dni przed nią. --}}
                        <p style="margin:0 0 10px 0; font-size:12px; line-height:1.5; color:{{ $brand['muted'] }};">
                            Najniższa cena z 30 dni przed obniżką: {{ $productCard['previous_price'] }}
                        </p>
                    @endif

                    @if (($productCard['excerpt'] ?? null) !== null)
                        <p style="margin:0 0 16px 0; font-size:14px; line-height:1.6; color:{{ $brand['muted'] }};">{{ $productCard['excerpt'] }}</p>
                    @endif

                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td style="border-radius:12px; background-color:{{ $brand['brand'] }};">
                                <a href="{{ $productCard['url'] }}" target="_blank" rel="noopener"
                                   style="display:inline-block; padding:12px 24px; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:14px; font-weight:600; color:{{ $brand['brand_ink'] }}; text-decoration:none;">Zobacz w sklepie</a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    @endisset

    @if ($actionText && $actionUrl)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;">
            <tr>
                <td style="border-radius:14px; background-color:{{ $brand['brand'] }};">
                    <a href="{{ $actionUrl }}" target="_blank" rel="noopener"
                       style="display:inline-block; padding:14px 28px; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; font-weight:600; color:{{ $brand['brand_ink'] }}; text-decoration:none;">{{ $actionText }}</a>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 8px 0; font-size:15px; line-height:1.65; color:{{ $brand['muted'] }};">
            Gdyby przycisk nie działał, skopiuj ten adres do przeglądarki:<br>
            <a href="{{ $actionUrl }}" style="color:{{ $brand['accent'] }}; word-break:break-all;">{{ $actionUrl }}</a>
        </p>
    @endif

    @foreach ($outroLines as $line)
        <p style="margin:16px 0 0 0; font-size:15px; line-height:1.65; color:{{ $brand['muted'] }};">{!! \App\Support\MailMarkup::inline($line) !!}</p>
    @endforeach

    @isset($unsubscribeUrl)
        {{-- Stopka wypisu — obowiązkowa w każdej wiadomości marketingowej.
             Zgoda ma być odwoływalna równie łatwo, jak została udzielona, więc
             link działa bez logowania i wypisuje natychmiast. --}}
        <p style="margin:28px 0 0 0; padding-top:16px; border-top:1px solid {{ $brand['border'] ?? '#e7e5e4' }}; font-size:13px; line-height:1.6; color:{{ $brand['muted'] }};">
            Dostajesz tę wiadomość, bo zgodziłeś się na wiadomości od tego sklepu.
            <a href="{{ $unsubscribeUrl }}" style="color:{{ $brand['accent'] }};">Wypisz się jednym kliknięciem</a>.
        </p>
    @endisset

    {{ $slot ?? '' }}
</x-mail.layout>
