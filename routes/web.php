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
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\ProductImageController;
use App\Http\Controllers\Seller\ShopProfileController;
use App\Http\Controllers\Seller\ShopSettingsController;
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

        // Auto-uzupełnienie danych firmy po NIP (Biała lista MF). Zwraca JSON.
        Route::post('/firma/z-nip', CompanyLookupController::class)
            ->middleware('throttle:20,1')
            ->name('company.lookup');

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
| STOREFRONT — subdomena sklepu {shop}.{central_domain}  [SZKIELET, WYŁĄCZONY]
|==============================================================================
| Publiczny sklep jednego sprzedawcy. {shop} = slug = etykieta subdomeny;
| middleware rozwiązuje model Shop i scope'uje wszystko do tego sklepu
| (jedna baza + shop_id). Inne kontrolery niż centrala — to jest sedno
| podziału „inne domeny → inne kontrolery".
|
| Włączymy razem z budową storefrontu i wildcard-subdomen na serwerze:
|
| Route::domain('{shop}.'.config('tenancy.central_domain'))
|     ->middleware('tenant')               // App\Http\Middleware\ResolveShop
|     ->group(function () {
|         Route::get('/', [Storefront\HomeController::class, 'show'])->name('storefront.home');
|         Route::get('/produkt/{product}', [Storefront\ProductController::class, 'show'])->name('storefront.product');
|         // /koszyk, /kategoria/{...}, ...
|     });
*/
