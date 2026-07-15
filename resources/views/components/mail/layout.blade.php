@props([
    'brand' => null,
    'preheader' => null,
])

@php
    $brand ??= \App\Support\MailBranding::system();
@endphp

<!DOCTYPE html>
<html lang="pl" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $brand['name'] }}</title>
</head>
<body style="margin:0; padding:0; background-color:{{ $brand['page_bg'] }}; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    @isset($preheader)
        <span style="display:none !important; visibility:hidden; opacity:0; color:transparent; height:0; width:0; overflow:hidden; mso-hide:all;">{{ $preheader }}</span>
    @endisset

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $brand['page_bg'] }};">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" width="700" cellpadding="0" cellspacing="0" border="0" style="width:700px; max-width:700px;">

                    {{-- Nagłówek z brandingiem --}}
                    <tr>
                        <td align="center" style="padding:8px 0 24px 0;">
                            @if (!empty($brand['logo_url']))
                                {{-- `height` i jako atrybut, i w `style` — klienci pocztowi
                                     honorują raz jedno, raz drugie. `width:auto` jest tu
                                     konieczne: logo sklepu ma zmienną proporcję, a samo
                                     `height` potrafi je rozciągnąć w Outlooku. --}}
                                <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['name'] }}" height="64" style="display:block; border:0; height:64px; width:auto; max-width:100%;">
                            @elseif (!empty($brand['glyph']))
                                {{-- Kramio: znak marki w kółku + nazwa. --}}
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="padding-right:14px;">
                                            <div style="width:64px; height:64px; border-radius:9999px; background-color:{{ $brand['brand'] }}; color:{{ $brand['brand_ink'] }}; font-size:32px; line-height:64px; text-align:center;">{{ $brand['glyph'] }}</div>
                                        </td>
                                        <td style="font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:28px; font-weight:700; letter-spacing:-0.02em; color:{{ $brand['text'] }};">{{ $brand['name'] }}</td>
                                    </tr>
                                </table>
                            @else
                                {{-- Sklep bez logo: sama nazwa sklepu w miejscu logo. Skala
                                     idzie w parze z logo (64px), żeby nagłówek nie skakał
                                     między sklepem z logo a sklepem bez logo. --}}
                                <div style="font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:28px; font-weight:700; letter-spacing:-0.02em; color:{{ $brand['text'] }};">{{ $brand['name'] }}</div>
                            @endif
                        </td>
                    </tr>

                    {{-- Karta z treścią („wsad”) --}}
                    <tr>
                        <td style="background-color:#ffffff; border-radius:20px; border:1px solid #f0eeec; padding:36px 36px 32px 36px; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- Stopka: dane firmowe NADAWCY (sklepu albo Kramio przy mailach
                         platformy). Świadomie NIE wraca tu dawna formułka „wysłana
                         automatycznie / możesz zignorować" — nigdzie nie mamy adresu
                         noreply, maile sklepu niosą Reply-To na jego kontakt, a maile
                         platformy wracają na From (sklep@kramio.pl). Na każdy da się
                         odpowiedzieć i ktoś to przeczyta, więc tamta stopka zniechęcała
                         wbrew prawdzie. Ta niesie treść, która coś daje: wiarygodność,
                         kontakt, dane informacyjne.

                         Dane firmowe sklepu są OPCJONALNE (wymagany jest tylko kontakt),
                         a `config/company.php` może być niewypełniony — dlatego stopka
                         składa się z tego, co jest, i chowa w całości, gdy nie ma nic.
                         Żadnych pustych linii.

                         BEZ NIP-u nadawcy — świadomie: klient nie ma co z nim zrobić,
                         a mylił się w tym samym mailu z NIP-em KUPUJĄCEGO, który
                         OrderMailer pokazuje w danych do faktury (tam ma sens — klient
                         weryfikuje własne dane). Ta stopka mówi tyle samo, co stopka
                         storefrontu: firma, adres, kontakt.

                         E-mail i telefon są linkami mimo stonowanego wyglądu: klienci
                         pocztowi i tak autolinkują gołe adresy własnym niebieskim, więc
                         lepiej to kontrolować niż z tym walczyć. --}}
                    @php
                        $footerIdentity = array_values(array_filter([
                            $brand['company_name'] ?? null,
                            $brand['company_address'] ?? null,
                        ]));
                        $footerLinkStyle = 'color:'.$brand['muted'].'; text-decoration:none;';
                    @endphp

                    @if ($footerIdentity !== [] || filled($brand['contact_email'] ?? null) || filled($brand['contact_phone'] ?? null))
                        <tr>
                            <td align="center" style="padding:28px 16px 8px 16px; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:20px; color:{{ $brand['muted'] }};">
                                @foreach ($footerIdentity as $line)
                                    <div>{{ $line }}</div>
                                @endforeach

                                @if (filled($brand['contact_email'] ?? null) || filled($brand['contact_phone'] ?? null))
                                    <div style="padding-top:{{ $footerIdentity === [] ? '0' : '10px' }};">
                                        @if (filled($brand['contact_email'] ?? null))
                                            <a href="mailto:{{ $brand['contact_email'] }}" style="{{ $footerLinkStyle }}">{{ $brand['contact_email'] }}</a>
                                        @endif
                                        @if (filled($brand['contact_email'] ?? null) && filled($brand['contact_phone'] ?? null))
                                            <span style="padding:0 6px;">&middot;</span>
                                        @endif
                                        @if (filled($brand['contact_phone'] ?? null))
                                            <a href="tel:{{ preg_replace('/\s+/', '', $brand['contact_phone']) }}" style="{{ $footerLinkStyle }}">{{ $brand['contact_phone'] }}</a>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endif

                </table>

            </td>
        </tr>
    </table>
</body>
</html>
