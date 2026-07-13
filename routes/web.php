<?php

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Administrator\DashboardController as AdministratorDashboard;
use App\Http\Controllers\Administrator\MailPreviewController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\Auth\ResendActivationController;
use App\Http\Controllers\Consent\ConsentController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Seller\AppearanceController;
use App\Http\Controllers\Seller\CompanyLookupController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboard;
use App\Http\Controllers\Seller\IntegrationController;
use App\Http\Controllers\Seller\OrderController;
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
use App\Http\Controllers\Storefront\RegisterController as StorefrontRegister;
use App\Http\Controllers\Storefront\PageController as StorefrontPage;
use App\Http\Controllers\Storefront\ProductController as StorefrontProduct;
use Illuminate\Support\Facades\Route;

/*
|==============================================================================
| CENTRALA — domena platformy (config('tenancy.central_domain'))
|==============================================================================
| Zarządzanie: strona główna platformy, logowanie/rejestracja, panel
| administratora i sprzedawcy. Sprzedawca zarządza sklepem TUTAJ, nie na
| swojej subdomenie.
|
| Architektura wielonajemcza: storefronty sprzedawców będą serwowane z
| subdomen {shop}.{central_domain} (np. bukiety.shop.kwasniak.org) — patrz
| wyłączony szkielet STOREFRONT na dole pliku. Dopóki subdomeny nie są
| włączone na serwerze, wszystko działa na domenie centrali bez wiązania
| Route::domain (uniknięcie kruchości na localhost/www/testach).
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

// Rejestracja sprzedawcy (nowe konta otrzymują rolę 'seller'). Konto powstaje
// bez hasła — sprzedawca dostaje mailem link do jego ustawienia.
Route::get('/rejestracja', [RegisterController::class, 'create'])->name('register');
Route::post('/rejestracja', [RegisterController::class, 'store'])->name('register.store');
Route::view('/rejestracja/potwierdzenie', 'auth.registered')->name('register.confirmation');
Route::post('/rejestracja/wyslij-ponownie', [ResendActivationController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('register.resend');

// Aktywacja konta (ustawienie pierwszego hasła + danych) — token brokera 'activation', 24 h.
Route::get('/aktywacja/{token}', [ActivationController::class, 'create'])->name('activation.show');
Route::post('/aktywacja', [ActivationController::class, 'store'])->name('activation.store');

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

        // Profil sklepu (nazwa, opis, adres). Edycja przez POST (FOUNDATION sek. 5).
        Route::get('/sklep', [ShopProfileController::class, 'edit'])->name('shop.edit');
        Route::post('/sklep', [ShopProfileController::class, 'update'])->name('shop.update');

        // Wygląd sklepu (logo; docelowo kolory/szablony). Edycja przez POST.
        Route::get('/wyglad', [AppearanceController::class, 'edit'])->name('appearance.edit');
        Route::post('/wyglad', [AppearanceController::class, 'update'])->name('appearance.update');

        // Ustawienia sklepu (na razie domyślny VAT; docelowo więcej). Edycja przez POST.
        Route::get('/ustawienia', [ShopSettingsController::class, 'edit'])->name('settings.edit');
        Route::post('/ustawienia', [ShopSettingsController::class, 'update'])->name('settings.update');

        // Integracje (na razie Google Analytics — konfiguracja usług). Edycja przez POST.
        Route::get('/integracje', [IntegrationController::class, 'edit'])->name('integrations.edit');
        Route::post('/integracje', [IntegrationController::class, 'update'])->name('integrations.update');

        // Auto-uzupełnienie danych firmy po NIP (Biała lista MF). Zwraca JSON.
        Route::post('/firma/z-nip', CompanyLookupController::class)
            ->middleware('throttle:20,1')
            ->name('company.lookup');

        // Zamówienia (podgląd + zmiana statusu). Lista i szczegół; zmiana statusu przez POST.
        Route::get('/zamowienia', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/zamowienia/{order}', [OrderController::class, 'show'])->name('orders.show');

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
    Route::post('/ai/popraw', [AiController::class, 'improve'])
        ->middleware('throttle:30,1')
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
|
| Kolejno dojdą: /produkt/{product}, /kategoria/{...}, /koszyk.
*/
Route::domain('{shop}.'.config('tenancy.central_domain'))
    ->middleware('tenant')
    ->group(function () {
        Route::get('/', [StorefrontHome::class, 'show'])->name('storefront.home');
        Route::get('/produkty', [StorefrontProduct::class, 'index'])->name('storefront.products');
        Route::get('/produkt/{product}', [StorefrontProduct::class, 'show'])->name('storefront.product');
        // Wirtualna „O sklepie" (treść z shop.description) — PRZED wildcardem, by
        // stały slug nie wpadł w /informacje/{page} (który szuka strony po id).
        Route::get('/informacje/'.config('pages.about.slug'), [StorefrontPage::class, 'about'])->name('storefront.about');
        Route::get('/informacje/{page}', [StorefrontPage::class, 'show'])->name('storefront.page');
        // Nasza Polityka prywatności renderowana w motywie sklepu (footer linkuje tu).
        Route::get('/polityka-prywatnosci', [StorefrontPage::class, 'privacy'])->name('storefront.privacy');
        Route::get('/koszyk', [StorefrontCart::class, 'show'])->name('storefront.cart');
        Route::get('/kasa', [StorefrontCheckout::class, 'show'])->name('storefront.checkout');
        Route::get('/kasa/dziekujemy', [StorefrontCheckout::class, 'confirmation'])->name('storefront.checkout.confirmation');

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
            Route::post('/haslo', [StorefrontAccount::class, 'password'])->name('password');
            Route::post('/usun', [StorefrontAccount::class, 'destroy'])->name('destroy');
        });
    });
