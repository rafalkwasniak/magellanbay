{{-- WZÓR POLITYKI PRYWATNOŚCI SKLEPU — szablon deterministyczny.

     Bliźniak `regulamin.blade.php` i obowiązują tu DOKŁADNIE te same zasady:
     - ZERO AI w runtime. Te same dane zawsze dają ten sam tekst, więc prawnik
       przegląda wynik raz i wie, że u nikogo nie wyjdzie coś innego.
     - Opisujemy TYLKO to, co sklep faktycznie robi. Lista odbiorców danych
       powstaje z WŁĄCZONYCH integracji, nie z katalogu możliwości. Polityka
       wymieniająca operatora płatności w sklepie bez płatności online jest
       gorsza niż jej brak — mówi klientowi nieprawdę o jego danych.
     - AKAPITY W <div>, NIE W <p>: `HtmlSanitizer`, przez który przechodzi zapis
       podstrony, nie ma `p` na liście dozwolonych tagów i zlepiłby tekst w jedno.

     RÓŻNICA WOBEC REGULAMINU — LUKI DO UZUPEŁNIENIA:
     regulamin trzyma zasadę „zero pól «uzupełnij» w wyniku", bo jego kreator
     wymusza komplet danych PRZED wstawieniem. Tutaj brakujące wartości
     wypisujemy jako [WIELKIE_LITERY_W_NAWIASACH]. To nie jest złamanie tamtej
     zasady, tylko inny tryb użycia: przy wypełnionym kreatorze żaden nawias się
     nie pojawi, a przy szkicu przygotowywanym ZA klienta (wdrożenie dedykowane,
     zanim poda dane firmowe) luka musi być widoczna gołym okiem w 20 tys. znaków.
     Nawiasy KWADRATOWE, nie klamry — klamra to składnia Blade i potrafi zniknąć.

     Zmienne: $shop (App\Models\Shop), $dane (odpowiedzi kreatora). --}}
@php
    use App\Support\Mode;

    /*
     * Wartość z kreatora albo widoczna luka. Jedno miejsce, żeby nie rozjechały
     * się konwencje nawiasów w kilkunastu zdaniach.
     */
    $pole = function (string $klucz, string $etykieta) use ($dane): string {
        $wartosc = trim((string) ($dane[$klucz] ?? ''));

        return $wartosc !== '' ? $wartosc : '['.$etykieta.']';
    };

    $sprzedawca = $pole('seller_name', 'NAZWA_SPRZEDAWCY');
    $adres = $pole('address', 'ADRES_SIEDZIBY');
    $email = $pole('email', 'ADRES_EMAIL');

    // NIP-u NIE zastępujemy luką: działalność nierejestrowana go nie ma i pustka
    // jest tu poprawną odpowiedzią, nie brakiem (ta sama reguła co w regulaminie).
    $nip = trim((string) ($dane['nip'] ?? ''));
    $telefon = trim((string) ($dane['phone'] ?? ''));

    /*
     * ODBIORCY DANYCH — wyłącznie realnie włączone integracje.
     * `*Enabled()` (nie `*Configured()`) to stan, który widzi klient: klucze
     * wpisane, ale integracja wyłączona, nie przetwarza niczyich danych.
     */
    $platnosciOnline = $shop->onlinePaymentsEnabled();
    $przesylki = $shop->shipxEnabled();
    $faktury = $shop->invoicingEnabled();
    $analityka = $shop->googleAnalyticsId() !== null;

    // Przewoźnik dostaje dane także wtedy, gdy sklep wysyła bez integracji ShipX
    // — liczy się to, czy jakakolwiek metoda wysyłkowa jest dostępna w kasie.
    $wysylka = collect($shop->availableDeliveryMethods())
        ->contains(fn ($m) => ! in_array($m->value, ['pickup'], true));

    $konta = true; // konta klientów są w sklepie zawsze dostępne
@endphp
<h2>1 / Kto odpowiada za Twoje dane</h2>
<div>Administratorem Twoich danych osobowych jest {{ $sprzedawca }}@if ($nip !== ''), NIP {{ $nip }}@endif, z adresem: {{ $adres }} — prowadzący sklep internetowy „{{ $shop->name }}" pod adresem {{ $shop->host() }}.</div>
<div>W sprawach dotyczących danych osobowych napisz na {{ $email }}@if ($telefon !== '') lub zadzwoń: {{ $telefon }}@endif. Odpowiadamy w dni robocze.</div>
<div>Nie wyznaczyliśmy inspektora ochrony danych — nie mamy takiego obowiązku. Wszystkimi sprawami zajmujemy się pod adresem podanym wyżej.</div>

<h2>2 / Skąd mamy Twoje dane</h2>
<div>Wyłącznie od Ciebie. Podajesz je, gdy składasz zamówienie, zakładasz konto, zapisujesz się na newsletter, składasz reklamację albo po prostu do nas piszesz. Nie kupujemy baz danych i nie pozyskujemy Twoich danych z innych źródeł.</div>
<div>Podanie danych jest dobrowolne, ale bez części z nich nie da się zrealizować zamówienia — nie wyślemy paczki bez adresu ani nie wystawimy faktury bez danych do faktury.</div>

<h2>3 / Po co przetwarzamy dane, na jakiej podstawie i jak długo</h2>
<ol>
    <li><strong>Realizacja zamówienia</strong> — imię i nazwisko, adres dostawy, e-mail, telefon, treść zamówienia. Podstawa: wykonanie umowy (art. 6 ust. 1 lit. b RODO). Przechowujemy przez czas realizacji, a potem do upływu terminu przedawnienia roszczeń z umowy.</li>
    <li><strong>Rozliczenia i księgowość</strong> — dane z dokumentu sprzedaży, w tym dane firmy i NIP, jeśli prosisz o fakturę. Podstawa: obowiązek prawny (art. 6 ust. 1 lit. c RODO). Przechowujemy <strong>5 lat licząc od końca roku kalendarzowego</strong>, w którym upłynął termin płatności podatku.</li>
    <li><strong>Reklamacje, zwroty i odstąpienie od umowy</strong> — dane z zamówienia oraz treść zgłoszenia, a przy zwrocie pieniędzy numer rachunku. Podstawa: obowiązek prawny i wykonanie umowy (art. 6 ust. 1 lit. b i c RODO). Przechowujemy do zakończenia sprawy, a potem do przedawnienia roszczeń.</li>
    @if ($konta)
        <li><strong>Konto klienta</strong> — adres e-mail, hasło w postaci zaszyfrowanej, historia zamówień. Podstawa: wykonanie umowy o prowadzenie konta (art. 6 ust. 1 lit. b RODO). Przechowujemy do czasu usunięcia konta przez Ciebie.</li>
    @endif
    <li><strong>Newsletter i informacje handlowe</strong> — adres e-mail oraz imię, jeśli je podasz. Podstawa: <strong>Twoja zgoda</strong> (art. 6 ust. 1 lit. a RODO). Przetwarzamy do czasu cofnięcia zgody; cofnięcie nie wpływa na to, co zrobiliśmy wcześniej.</li>
    <li><strong>Obrona przed roszczeniami i dochodzenie własnych</strong> — dane z zamówień i korespondencji. Podstawa: nasz prawnie uzasadniony interes (art. 6 ust. 1 lit. f RODO). Przechowujemy do przedawnienia roszczeń.</li>
    <li><strong>Bezpieczeństwo sklepu</strong> — adres IP i zapisy techniczne o wejściach, potrzebne do wykrywania nadużyć i awarii. Podstawa: nasz prawnie uzasadniony interes (art. 6 ust. 1 lit. f RODO). Przechowujemy nie dłużej niż <strong>14 dni</strong>, chyba że zapis dotyczy incydentu, który wyjaśniamy.</li>
</ol>
<div>Gdy ten sam zestaw danych służy kilku celom naraz, usuwamy go dopiero po upływie najdłuższego z podanych okresów.</div>

<h2>4 / Komu przekazujemy dane</h2>
<div>Nie sprzedajemy Twoich danych i nie udostępniamy ich nikomu dla jego własnych celów. Korzystamy natomiast z usług firm, które przetwarzają dane <strong>w naszym imieniu i na nasze polecenie</strong>:</div>
<ul>
    <li><strong>Dostawca serwera i poczty elektronicznej</strong> — na jego infrastrukturze działa sklep i z niej wychodzą wiadomości do Ciebie.</li>
    @if ($wysylka)
        <li><strong>Przewoźnik i operator punktów odbioru</strong> — otrzymuje dane potrzebne do doręczenia przesyłki: imię i nazwisko, adres albo wskazany punkt, telefon i e-mail do powiadomień o statusie.@if ($przesylki) Etykiety nadania tworzymy w systemie InPost.@endif</li>
    @endif
    @if ($platnosciOnline)
        <li><strong>Operator płatności internetowych</strong> — obsługuje płatność za zamówienie. Otrzymuje kwotę, numer zamówienia i dane niezbędne do rozliczenia transakcji. <strong>Nie mamy dostępu do danych Twojej karty ani do danych logowania do bankowości</strong> — te wpisujesz bezpośrednio u operatora.</li>
    @endif
    @if ($faktury)
        <li><strong>System do wystawiania faktur</strong> — przechowuje dokumenty sprzedaży i dane, które muszą się na nich znaleźć.</li>
    @endif
    <li><strong>Biuro rachunkowe oraz doradcy</strong> — w zakresie, w jakim wymaga tego prowadzenie dokumentacji i obrona naszych praw.</li>
    @if ($analityka)
        <li><strong>Dostawca narzędzia analitycznego</strong> — otrzymuje dane o sposobie korzystania ze sklepu (odwiedzone strony, przybliżona lokalizacja, rodzaj urządzenia). Narzędzie uruchamiamy <strong>dopiero po wyrażeniu przez Ciebie zgody</strong> na pliki cookies analityczne.</li>
    @endif
</ul>
@if (Mode::saas())
    {{-- W sklepie dedykowanym tego zdania NIE MA, bo nie ma platformy ani
         podmiotu przetwarzającego: właściciel sklepu jest administratorem
         i jednocześnie gospodarzem infrastruktury. Zdanie o Kramio byłoby
         wtedy wprost nieprawdziwe. --}}
    <div>Sklep działa na platformie Kramio. Jej operator przetwarza dane w naszym imieniu jako podmiot przetwarzający, na podstawie zawartej z nami umowy powierzenia.</div>
@endif
<div>Dane mogą też trafić do organów państwowych, jeżeli zwrócą się o nie na podstawie przepisów prawa.</div>

<h2>5 / Czy dane trafiają poza Europejski Obszar Gospodarczy</h2>
@if ($analityka)
    <div>Co do zasady nie. Wyjątkiem jest narzędzie analityczne, którego dostawca może przetwarzać dane także poza EOG — odbywa się to na podstawie mechanizmów przewidzianych w RODO, w szczególności standardowych klauzul umownych lub decyzji Komisji Europejskiej stwierdzającej odpowiedni stopień ochrony.</div>
@else
    <div>Nie. Twoje dane przetwarzamy na terenie Europejskiego Obszaru Gospodarczego. Gdyby to się zmieniło, uprzedzimy o tym w tej polityce i wskażemy podstawę takiego przekazania.</div>
@endif

<h2>6 / Pliki cookies</h2>
<div>Cookies to małe pliki zapisywane w Twojej przeglądarce. Używamy ich w dwóch rolach:</div>
<ul>
    <li><strong>Niezbędne</strong> — bez nich sklep nie działa: pamiętają zawartość koszyka, utrzymują zalogowanie i chronią formularze przed nadużyciem. Zapisujemy je <strong>bez pytania o zgodę</strong>, bo wprost o nie prosisz, korzystając ze sklepu.</li>
    @if ($analityka)
        <li><strong>Analityczne</strong> — pokazują nam, które strony są odwiedzane i gdzie klienci się gubią. Zapisujemy je <strong>wyłącznie po Twojej zgodzie</strong> i możesz ją w każdej chwili cofnąć.</li>
    @endif
</ul>
<div>Zgodę wyrażasz lub odrzucasz w oknie, które pokazuje się przy pierwszej wizycie. Cookies możesz też w każdej chwili usunąć albo zablokować w ustawieniach przeglądarki — pamiętaj tylko, że zablokowanie niezbędnych plików uniemożliwi złożenie zamówienia.</div>

<h2>7 / Twoje prawa</h2>
<div>W związku z przetwarzaniem danych masz prawo:</div>
<ul>
    <li><strong>dostępu</strong> — dowiedzieć się, jakie dane o Tobie mamy, i otrzymać ich kopię;</li>
    <li><strong>sprostowania</strong> — poprawić dane nieprawidłowe i uzupełnić niepełne;</li>
    <li><strong>usunięcia</strong> — żądać skasowania danych, o ile nie musimy ich zachować, np. dla celów księgowych;</li>
    <li><strong>ograniczenia przetwarzania</strong> — żądać, byśmy wstrzymali operacje na danych na czas wyjaśnienia sprawy;</li>
    <li><strong>przenoszenia</strong> — otrzymać dane w formacie nadającym się do odczytu maszynowego i przekazać je innemu administratorowi;</li>
    <li><strong>sprzeciwu</strong> — sprzeciwić się przetwarzaniu opartemu na naszym prawnie uzasadnionym interesie;</li>
    <li><strong>cofnięcia zgody</strong> — w każdej chwili i bez podawania powodu, tam gdzie przetwarzamy dane na podstawie zgody.</li>
</ul>
<div>Żeby skorzystać z któregokolwiek z tych praw, napisz na {{ $email }}. Odpowiadamy niezwłocznie, najpóźniej w terminie miesiąca.</div>
<div>Z newslettera wypiszesz się od razu, bez pisania do nas — <strong>link do wypisania jest w stopce każdej wiadomości</strong>, którą od nas dostajesz.</div>
<div>Jeżeli uznasz, że przetwarzamy Twoje dane niezgodnie z prawem, możesz wnieść skargę do <strong>Prezesa Urzędu Ochrony Danych Osobowych</strong>, ul. Stawki 2, 00-193 Warszawa.</div>

<h2>8 / Automatyczne decyzje i profilowanie</h2>
<div>Nie podejmujemy wobec Ciebie decyzji opartych wyłącznie na automatycznym przetwarzaniu danych i nie profilujemy Cię w sposób, który wywoływałby wobec Ciebie skutki prawne lub w podobny sposób istotnie na Ciebie wpływał.</div>

<h2>9 / Bezpieczeństwo</h2>
<div>Połączenie ze sklepem jest szyfrowane. Hasła przechowujemy wyłącznie w postaci skrótów, których nie da się odwrócić — nie znamy Twojego hasła i nie możemy Ci go odczytać. Dostęp do danych zamówień mają tylko osoby, którym jest to potrzebne do obsługi sklepu.</div>

<h2>10 / Zmiany polityki</h2>
<div>Politykę możemy zmienić, gdy zmienią się przepisy, zakres naszej działalności albo lista firm, z których usług korzystamy. Nowa wersja obowiązuje od dnia opublikowania jej w sklepie. Zmiany istotne — a za takie uznajemy w szczególności nowego odbiorcę danych — zapowiadamy z wyprzedzeniem.</div>
<div>Data ostatniej aktualizacji: [DATA_AKTUALIZACJI].</div>
