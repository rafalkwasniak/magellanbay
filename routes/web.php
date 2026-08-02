<?php

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Administrator\DashboardController as AdministratorDashboard;
use App\Http\Controllers\Administrator\MailPreviewController;
use App\Http\Controllers\Administrator\ShopController as AdministratorShopController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResendActivationController;
use App\Http\Controllers\Consent\ConsentController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PackagePaymentWebhookController;
use App\Http\Controllers\PaynowWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Seller\AnalyticsController;
use App\Http\Controllers\Seller\AppearanceController;
use App\Http\Controllers\Seller\BulkMailingController;
use App\Http\Controllers\Seller\CompanyLookupController;
use App\Http\Controllers\Seller\CustomerController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboard;
use App\Http\Controllers\Seller\DiscountCodeController;
use App\Http\Controllers\Seller\IntegrationController;
use App\Http\Controllers\Seller\OrderController;
use App\Http\Controllers\Seller\PackageController;
use App\Http\Controllers\Seller\PageController;
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\ProductImageController;
use App\Http\Controllers\Seller\ShopProfileController;
use App\Http\Controllers\Seller\ShopSettingsController;
use App\Http\Controllers\Storefront\AccountController as StorefrontAccount;
use App\Http\Controllers\Storefront\ActivationController as StorefrontActivation;
use App\Http\Controllers\Storefront\AuthController as StorefrontAuth;
use App\Http\Controllers\Storefront\CartController as StorefrontCart;
use App\Http\Controllers\Storefront\CheckoutController as StorefrontCheckout;
use App\Http\Controllers\Storefront\HomeController as StorefrontHome;
use App\Http\Controllers\Storefront\OrderReturnController as StorefrontOrderReturn;
use App\Http\Controllers\Storefront\PageController as StorefrontPage;
use App\Http\Controllers\Storefront\PasswordResetController as StorefrontPasswordReset;
use App\Http\Controllers\Storefront\PaymentController as StorefrontPayment;
use App\Http\Controllers\Storefront\ProductController as StorefrontProduct;
use App\Http\Controllers\Storefront\RegisterController as StorefrontRegister;
use App\Http\Controllers\Storefront\UnsubscribeController as StorefrontUnsubscribe;
use Illuminate\Support\Facades\Route;

/*
|==============================================================================
| CENTRALA — domena platformy (config('tenancy.central_domain'))
|==============================================================================
| Zarządzanie: strona główna platformy, logowanie/rejestracja, panel
| administratora i sprzedawcy. Sprzedawca zarządza sklepem TUTAJ, nie na
| swojej subdomenie.
|
| Architektura wielonajemcza: storefronty sprzedawców są serwowane z subdomen
| {shop}.{central_domain} (np. ilikemybike.kramio.pl) — sekcja STOREFRONT na
| dole pliku, DZIAŁAJĄCA (wildcard DNS + SSL na serwerze, Route::domain +
| middleware ResolveShop). Trasy w tej sekcji nie są wiązane z domeną, więc
| centrala odpowiada na każdym hoście, który nie jest subdomeną sklepu.
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Uwierzytelnianie (wspólne dla admina i sprzedawcy)
|--------------------------------------------------------------------------
| Jedno wejście logowania; po zalogowaniu przekierowanie zależne od roli
| (UserRole::homeRoute()).
*/
Route::get('/logowanie', [AuthController::class, 'create'])->name('login');
Route::post('/logowanie', [AuthController::class, 'store'])->name('login.attempt');
Route::post('/wyloguj', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

// Odzyskiwanie hasła. Prośba o link jest dławiona per IP: ten formularz wysyła
// maila na podany adres, więc bez limitu byłby drugą — obok rejestracji —
// maszynką do zalewania cudzej skrzynki.
Route::get('/odzyskiwanie-hasla', [PasswordResetController::class, 'create'])->name('password.request');
Route::post('/odzyskiwanie-hasla', [PasswordResetController::class, 'store'])
    ->middleware('throttle:password_reset')
    ->name('password.email');
Route::get('/nowe-haslo/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
Route::post('/nowe-haslo', [PasswordResetController::class, 'update'])
    ->middleware('throttle:password_reset')
    ->name('password.update');

// Rejestracja sprzedawcy (nowe konta otrzymują rolę 'seller'). Konto powstaje
// bez hasła — sprzedawca dostaje mailem link do jego ustawienia.
Route::get('/rejestracja', [RegisterController::class, 'create'])->name('register');
// Limit per IP: ten formularz wysyła maila aktywacyjnego na DOWOLNY podany
// adres, więc bez dławika jest gotowym narzędziem do zalewania cudzej skrzynki
// naszym kosztem — reputacyjnym. Progi: config/security.php.
Route::post('/rejestracja', [RegisterController::class, 'store'])
    ->middleware('throttle:register')
    ->name('register.store');
Route::view('/rejestracja/potwierdzenie', 'auth.registered')->name('register.confirmation');
Route::post('/rejestracja/wyslij-ponownie', [ResendActivationController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('register.resend');

// Aktywacja konta (ustawienie pierwszego hasła + danych) — token brokera 'activation', 24 h.
Route::get('/aktywacja/{token}', [ActivationController::class, 'create'])->name('activation.show');
Route::post('/aktywacja', [ActivationController::class, 'store'])
    ->middleware('throttle:activation')
    ->name('activation.store');

// Webhook Paynow: powiadomienie o zmianie statusu płatności (źródło prawdy o
// zapłacie). Publiczny — Paynow nie ma sesji ani CSRF; broni go podpis (patrz
// kontroler). Na domenie centrali; adres wpisujemy w panelu Paynow sprzedawcy.
Route::post('/platnosci/paynow/webhook', PaynowWebhookController::class)->name('payments.paynow.webhook');

// Webhooki opłat za PAKIETY Kramio (konto platformy) — osobna trasa, bo podpis
// weryfikuje klucz platformy z `.env`, nie klucz sklepu.
Route::post('/platnosci/paynow/pakiety/webhook', PackagePaymentWebhookController::class)
    ->name('payments.paynow.packages.webhook');

/*
|--------------------------------------------------------------------------
| Dokumenty prawne (publiczne, bez logowania)
|--------------------------------------------------------------------------
| URL po polsku, nazwa trasy po angielsku. Typ wstrzykiwany przez domyślny
| parametr trasy (implicit enum binding).
*/
Route::get('/'.LegalDocumentType::Terms->slug(), [LegalController::class, 'show'])
    ->defaults('type', LegalDocumentType::Terms->value)
    ->name(LegalDocumentType::Terms->routeName());
Route::get('/'.LegalDocumentType::Privacy->slug(), [LegalController::class, 'show'])
    ->defaults('type', LegalDocumentType::Privacy->value)
    ->name(LegalDocumentType::Privacy->routeName());

/*
|--------------------------------------------------------------------------
| Panel administratora (rola: admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])
    ->prefix('administrator')
    ->name('administrator.')
    ->group(function () {
        Route::get('/panel', AdministratorDashboard::class)->name('dashboard');

        // Sklepy — lista + zarządzanie pakietem/uprawnieniami/ceną per sklep.
        Route::get('/sklepy', [AdministratorShopController::class, 'index'])->name('shops.index');
        Route::get('/sklepy/{shop}', [AdministratorShopController::class, 'edit'])->name('shops.edit');

        // Podgląd szablonów maili (na froncie, dla nas) — np. /administrator/podglad-maila/aktywacja
        Route::get('/podglad-maila/{template}', [MailPreviewController::class, 'show'])->name('mail.preview');
    });

/*
|--------------------------------------------------------------------------
| Panel sprzedawcy (rola: seller)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:seller', 'ensure.consents'])
    ->prefix('sprzedawca')          // URL po polsku; nazwa trasy 'seller.' (kod) po angielsku
    ->name('seller.')
    ->group(function () {
        Route::get('/panel', SellerDashboard::class)->name('dashboard');

        // Analityka (Poziom 1: z danych, które już mamy; dla wszystkich pakietów).
        Route::get('/analityka', [AnalyticsController::class, 'index'])->name('analytics.index');

        // Profil sklepu (nazwa, opis, adres). Edycja przez POST (FOUNDATION sek. 5).
        Route::get('/sklep', [ShopProfileController::class, 'edit'])->name('shop.edit');
        Route::post('/sklep', [ShopProfileController::class, 'update'])->name('shop.update');

        // Wygląd sklepu (logo, kolor przewodni, szablon motywu). Edycja przez POST.
        Route::get('/wyglad', [AppearanceController::class, 'edit'])->name('appearance.edit');
        Route::post('/wyglad', [AppearanceController::class, 'update'])->name('appearance.update');

        // Ustawienia sklepu (sprzedaż/VAT, dostawa, płatności, włączniki
        // integracji). Edycja przez POST.
        Route::get('/ustawienia', [ShopSettingsController::class, 'edit'])->name('settings.edit');
        Route::post('/ustawienia', [ShopSettingsController::class, 'update'])->name('settings.update');

        // Integracje (klucze usług: Paynow, Fakturownia, Google Analytics).
        // Edycja przez POST.
        Route::get('/integracje', [IntegrationController::class, 'edit'])->name('integrations.edit');
        Route::post('/integracje', [IntegrationController::class, 'update'])->name('integrations.update');

        // Auto-uzupełnienie danych firmy po NIP (Biała lista MF). Zwraca JSON.
        Route::post('/firma/z-nip', CompanyLookupController::class)
            ->middleware('throttle:20,1')
            ->name('company.lookup');

        // Zamówienia (podgląd + zmiana statusu). Lista i szczegół; zmiana statusu przez POST.
        Route::get('/zamowienia', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/zamowienia/{order}', [OrderController::class, 'show'])->name('orders.show');

        // „Mój pakiet" — co sprzedawca ma wykupione i do kiedy. Zakup online
        // dojdzie osobno (wymaga konta płatniczego platformy); na razie ekran
        // informuje i kieruje do kontaktu.
        Route::get('/pakiet', [PackageController::class, 'show'])->name('package.show');
        Route::post('/pakiet/kup/{package}', [PackageController::class, 'purchase'])
            ->middleware('throttle:10,1')->name('package.purchase');

        // Kartoteka klientów — we wszystkich pakietach. Identyfikatorem jest
        // ADRES E-MAIL, bo klient bez konta (gość) nie ma `id`; `where` na
        // wzorcu przepuszcza kropki i małpę w segmencie ścieżki.
        Route::get('/klienci', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/klienci/{email}', [CustomerController::class, 'show'])
            ->where('email', '.*')->name('customers.show');

        // Kody rabatowe (funkcja płatna — uprawnienie `discount_codes`, Pawilon).
        // Bez uprawnienia strona pokazuje zachętę zamiast narzędzia.
        Route::get('/kody-rabatowe', [DiscountCodeController::class, 'index'])->name('discounts.index');
        Route::get('/kody-rabatowe/nowy', [DiscountCodeController::class, 'create'])->name('discounts.create');
        Route::get('/kody-rabatowe/{discountCode}/edycja', [DiscountCodeController::class, 'edit'])->name('discounts.edit');
        Route::post('/kody-rabatowe/{discountCode}/przelacz', [DiscountCodeController::class, 'toggle'])->name('discounts.toggle');
        Route::post('/kody-rabatowe/{discountCode}/usun', [DiscountCodeController::class, 'destroy'])->name('discounts.destroy');

        // Wiadomości do klientów (korespondencja seryjna — uprawnienie `bulk_mail`,
        // Pawilon). Kontroler prowadzi wyłącznie szkic; wysyłkę (próbka do siebie
        // i wysyłka do klientów) obsługuje komponent Livewire na stronie edycji.
        Route::get('/wiadomosci', [BulkMailingController::class, 'index'])->name('mailings.index');
        Route::get('/wiadomosci/nowa', [BulkMailingController::class, 'create'])->name('mailings.create');
        Route::post('/wiadomosci', [BulkMailingController::class, 'store'])->name('mailings.store');
        Route::get('/wiadomosci/{bulkMailing}/edycja', [BulkMailingController::class, 'edit'])->name('mailings.edit');
        Route::post('/wiadomosci/{bulkMailing}', [BulkMailingController::class, 'update'])->name('mailings.update');
        Route::post('/wiadomosci/{bulkMailing}/usun', [BulkMailingController::class, 'destroy'])->name('mailings.destroy');

        // Informacje (strony tekstowe storefrontu). Edycja/usuwanie przez POST;
        // kolejność (drag & drop) zapisywana AJAX-em przez POST.
        Route::get('/informacje', [PageController::class, 'index'])->name('pages.index');
        Route::get('/informacje/nowa', [PageController::class, 'create'])->name('pages.create');
        Route::post('/informacje', [PageController::class, 'store'])->name('pages.store');
        Route::post('/informacje/kolejnosc', [PageController::class, 'reorder'])->name('pages.reorder');
        Route::get('/informacje/{page}/edycja', [PageController::class, 'edit'])->name('pages.edit');
        Route::post('/informacje/{page}', [PageController::class, 'update'])->name('pages.update');
        Route::post('/informacje/{page}/usun', [PageController::class, 'destroy'])->name('pages.destroy');

        // Produkty (edycja/usuwanie przez POST — FOUNDATION sek. 5).
        Route::get('/produkty', [ProductController::class, 'index'])->name('products.index');
        Route::get('/produkty/nowy', [ProductController::class, 'create'])->name('products.create');
        Route::post('/produkty', [ProductController::class, 'store'])->name('products.store');
        Route::get('/produkty/{product}/edycja', [ProductController::class, 'edit'])->name('products.edit');
        Route::post('/produkty/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::post('/produkty/{product}/usun', [ProductController::class, 'destroy'])->name('products.destroy');

        // Zdjęcia produktu.
        Route::post('/produkty/{product}/zdjecia', [ProductImageController::class, 'store'])->name('products.images.store');
        Route::post('/produkty/{product}/zdjecia/kolejnosc', [ProductImageController::class, 'reorder'])->name('products.images.reorder');
        Route::post('/produkty/{product}/zdjecia/{image}/usun', [ProductImageController::class, 'destroy'])->name('products.images.destroy');
    });

/*
|--------------------------------------------------------------------------
| Ponowna akceptacja dokumentów prawnych (po zmianie ich wersji)
|--------------------------------------------------------------------------
| Dostępne dla zalogowanego użytkownika; brama spod której nie wpuszczamy do
| panelu, dopóki zaległe zgody nie zostaną złożone (EnsureConsentsAreCurrent).
*/
Route::middleware('auth')->group(function () {
    Route::get('/zgody', [ConsentController::class, 'show'])->name('consents.show');
    Route::post('/zgody', [ConsentController::class, 'store'])->name('consents.store');

    // Redakcja treści przez AI („Popraw przez AI") — zwraca poprawiony tekst, nie zapisuje.
    // Limit liczy FRAGMENTY, nie kliknięcia: jedna korekta długiej strony to
    // kilkanaście żądań (dzielenie w resources/js/ai.js), więc dawne 30/min
    // kończyłoby się błędem limitu w połowie roboty. Ochrona przed nadużyciem
    // zostaje — 120 wywołań modelu na minutę to i tak dużo powyżej normalnej pracy.
    // Opis SEO pisany na żądanie z boksu „SEO" (zwraca tekst, nie zapisuje).
    Route::post('/ai/opis-seo', [AiController::class, 'seoDescription'])
        ->middleware('throttle:30,1')
        ->name('ai.seo-description');

    Route::post('/ai/popraw', [AiController::class, 'improve'])
        ->middleware('throttle:120,1')
        ->name('ai.improve');

    // Edycja własnego profilu (dane z users, awatar, hasło). Edycja przez POST.
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profil', [ProfileController::class, 'update'])->name('profile.update');
});

/*
|==============================================================================
| STOREFRONT — subdomena sklepu {shop}.{central_domain}
|==============================================================================
| Publiczny sklep jednego sprzedawcy. {shop} = slug = etykieta subdomeny;
| middleware `tenant` (ResolveShop) rozwiązuje model Shop z subdomeny i dzieli
| go z widokami (404 gdy slug bez sklepu). Inne kontrolery niż centrala — to
| sedno podziału „inne domeny → inne kontrolery". Grupa łapie tylko hosty
| {label}.{central_domain}, więc centrala (bez subdomeny) działa jak dotąd.
*/
Route::domain('{shop}.'.config('tenancy.central_domain'))
    ->middleware(['tenant', 'record.traffic'])
    ->group(function () {
        Route::get('/', [StorefrontHome::class, 'show'])->name('storefront.home');
        Route::get('/produkty', [StorefrontProduct::class, 'index'])->name('storefront.products');
        Route::get('/produkt/{product}', [StorefrontProduct::class, 'show'])->name('storefront.product');
        // Landing działu „Informacje" → 302 na pierwszą podstronę (lewe menu
        // przejmuje dalszą nawigację). PRZED wildcardem, by nie wpadł w {page}.
        Route::get('/informacje', [StorefrontPage::class, 'index'])->name('storefront.information');
        // Wirtualna „O sklepie" (treść z shop.description) — PRZED wildcardem, by
        // stały slug nie wpadł w /informacje/{page} (który szuka strony po id).
        Route::get('/informacje/'.config('pages.about.slug'), [StorefrontPage::class, 'about'])->name('storefront.about');
        // Nasza Polityka prywatności renderowana w motywie sklepu, jako ostatnia
        // pozycja działu „Informacje". Stały slug PRZED wildcardem {page}.
        Route::get('/informacje/'.config('pages.privacy.slug'), [StorefrontPage::class, 'privacy'])->name('storefront.privacy');
        Route::get('/informacje/{page}', [StorefrontPage::class, 'show'])->name('storefront.page');
        // Stary adres → 301 na nowy kanoniczny (przeniesienie na stałe).
        Route::redirect('/polityka-prywatnosci', '/informacje/'.config('pages.privacy.slug'), 301);
        Route::get('/koszyk', [StorefrontCart::class, 'show'])->name('storefront.cart');
        Route::get('/kasa', [StorefrontCheckout::class, 'show'])->name('storefront.checkout');
        Route::get('/kasa/dziekujemy', [StorefrontCheckout::class, 'confirmation'])->name('storefront.checkout.confirmation');

        // Strona płatności zamówienia (token = zaszyfrowany id zamówienia). Jedno
        // miejsce dla wszystkich linków „dokończ płatność": mail, „Moje konto",
        // powrót z Paynow, ekran podziękowania. Działa bez logowania.
        Route::get('/platnosc/{token}', [StorefrontPayment::class, 'show'])->name('storefront.payment.show');
        Route::post('/platnosc/{token}', [StorefrontPayment::class, 'pay'])->name('storefront.payment.pay');

        // Odstąpienie od umowy (14 dni) — publiczny formularz pod tym samym
        // tokenem zamówienia co płatność. Bez logowania, bo ustawa wymaga, by
        // złożenie oświadczenia było łatwe. Throttle chroni przed wysypem
        // zgłoszeń z jednego linku, nie przed klientem (limit jest hojny).
        // Wypis z korespondencji seryjnej — podpisany link ze stopki mailingu,
        // bez logowania. Samo wejście wypisuje (zgoda ma być odwoływalna równie
        // łatwo, jak udzielona); POST obok przywraca zgodę klikniętą omyłkowo.
        // Link NIE wygasa: mail sprzed roku musi dać się odsubskrybować.
        Route::get('/wypisz-sie/{customer}', [StorefrontUnsubscribe::class, 'show'])
            ->middleware('signed')->name('storefront.unsubscribe');
        Route::post('/wypisz-sie/{customer}/przywroc', [StorefrontUnsubscribe::class, 'restore'])
            ->middleware('signed')->name('storefront.unsubscribe.restore');

        Route::get('/zwrot/{token}', [StorefrontOrderReturn::class, 'show'])->name('storefront.return.show');
        Route::post('/zwrot/{token}', [StorefrontOrderReturn::class, 'store'])
            ->middleware('throttle:10,1')->name('storefront.return.store');

        /*
        |----------------------------------------------------------------------
        | Konta klientów (guard `customer`, w obrębie sklepu)
        |----------------------------------------------------------------------
        | Rejestracja bez hasła → podpisany link aktywacyjny mailem → ustawienie
        | hasła + przypięcie wcześniejszych zamówień + auto-login. Logowanie i
        | wylogowanie scope'owane do sklepu. Aktywacja pod `signed` (link z maila).
        */
        Route::get('/rejestracja', [StorefrontRegister::class, 'create'])->name('storefront.register');
        Route::post('/rejestracja', [StorefrontRegister::class, 'store'])
            ->middleware('throttle:10,1')->name('storefront.register.store');
        Route::get('/rejestracja/potwierdzenie', [StorefrontRegister::class, 'registered'])
            ->name('storefront.register.confirmation');

        Route::get('/aktywacja/{customer}', [StorefrontActivation::class, 'create'])
            ->middleware('signed')->name('storefront.activation');
        Route::post('/aktywacja/{customer}', [StorefrontActivation::class, 'store'])
            ->middleware('signed')->name('storefront.activation.store');

        // Odzyskiwanie hasła klienta. Link jest PODPISANY i niesie identyfikator
        // konta, bo konta są per sklep — ten sam e-mail bywa kontem u wielu
        // sprzedawców i token szukany po samym adresie trafiłby w cudze.
        Route::get('/nie-pamietam-hasla', [StorefrontPasswordReset::class, 'create'])
            ->name('storefront.password.request');
        Route::post('/nie-pamietam-hasla', [StorefrontPasswordReset::class, 'store'])
            ->middleware('throttle:password_reset')->name('storefront.password.email');
        Route::get('/nowe-haslo/{customer}', [StorefrontPasswordReset::class, 'edit'])
            ->middleware('signed')->name('storefront.password.reset');
        Route::post('/nowe-haslo/{customer}', [StorefrontPasswordReset::class, 'update'])
            ->middleware('signed')->name('storefront.password.update');

        Route::get('/logowanie', [StorefrontAuth::class, 'create'])->name('storefront.login');
        Route::post('/logowanie', [StorefrontAuth::class, 'store'])
            ->middleware('throttle:10,1')->name('storefront.login.attempt');
        Route::post('/wyloguj', [StorefrontAuth::class, 'destroy'])->name('storefront.logout');

        // Moje konto — tylko zalogowany klient (guard `customer`): historia
        // zamówień, dane profilu, zmiana hasła, usunięcie konta (RODO).
        Route::middleware('auth.customer')->prefix('moje-konto')->name('storefront.account.')->group(function () {
            Route::get('/', [StorefrontAccount::class, 'index'])->name('index');
            Route::get('/zamowienia', [StorefrontAccount::class, 'orders'])->name('orders');
            Route::get('/zamowienia/{order}', [StorefrontAccount::class, 'order'])->name('order');
            Route::get('/dane', [StorefrontAccount::class, 'edit'])->name('edit');
            Route::post('/dane', [StorefrontAccount::class, 'update'])->name('update');
            Route::post('/zgody', [StorefrontAccount::class, 'consents'])->name('consents');
            Route::post('/haslo', [StorefrontAccount::class, 'password'])->name('password');
            Route::post('/usun', [StorefrontAccount::class, 'destroy'])->name('destroy');
        });
    });
