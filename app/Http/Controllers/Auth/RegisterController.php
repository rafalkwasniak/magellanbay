<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LegalDocumentType;
use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\ActivationMailer;
use App\Services\ConsentRecorder;
use App\Services\SlugService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    /**
     * Formularz rejestracji sprzedawcy.
     *
     * `?adres=` przynosi etykietę subdomeny ze strony wolnego adresu
     * ({slug}.{central_domain} bez sklepu). Formularz nie ma pola adresu —
     * slug liczy serwer z nazwy sklepu — więc podpowiadamy NAZWĘ odtworzoną
     * z adresu tak, by wróciła dokładnie ta sama etykieta.
     */
    public function create(Request $request, SlugService $slugs): Renderable|RedirectResponse
    {
        if ($user = Auth::user()) {
            return redirect()->route($user->role->homeRoute());
        }

        return view('auth.register', [
            'suggestedName' => $this->nameFromSlug($request->query('adres'), $slugs),
        ]);
    }

    /**
     * Nazwa sklepu podpowiedziana na podstawie adresu z parametru. Cokolwiek
     * przyszło w URL-u, sprawdzamy dwa razy: musi być czystym slugiem, a nazwa
     * z niego zrobiona musi wracać do tego samego sluga. Inaczej sprzedawca
     * zająłby adres inny niż ten, po który kliknął — a to gorsze niż puste pole.
     */
    private function nameFromSlug(mixed $slug, SlugService $slugs): ?string
    {
        if (! is_string($slug) || $slugs->make($slug) !== $slug) {
            return null;
        }

        $name = Str::headline(str_replace('-', ' ', $slug));

        return $slugs->make($name) === $slug ? $name : $slug;
    }

    /**
     * Rejestracja konta sprzedawcy. Konto powstaje BEZ użytecznego hasła
     * (losowe, niemożliwe do odgadnięcia) — sprzedawca dostaje mailem link do
     * ustawienia hasła. Razem z kontem zakładamy szkic sklepu z zarezerwowaną
     * subdomeną (slug). Zaznaczone zgody zapisujemy na aktualne wersje
     * dokumentów. Nie logujemy automatycznie (nie ma jeszcze hasła).
     */
    public function store(RegisterRequest $request, ConsentRecorder $consents, ActivationMailer $activation): RedirectResponse
    {
        $documents = collect(config('legal.required_types'))
            ->map(fn (LegalDocumentType $type) => LegalDocument::current($type))
            ->filter();

        $user = DB::transaction(function () use ($request, $consents, $documents) {
            $user = new User;
            $user->fill($request->safe()->only('name', 'surname', 'email'));
            $user->password = Str::password(32); // placeholder; właściwe hasło ustawi sprzedawca z linku
            $user->role = UserRole::Seller; // role nie jest mass-assignable — ustawiamy jawnie
            $user->save();

            // Szkic sklepu — subdomena zaklepana, dane sklepu i publikacja przyjdą przy aktywacji.
            $shop = $user->shop()->create([
                'name' => $request->string('shop_name'),
                'slug' => $request->string('slug'),
                'status' => ShopStatus::Draft,
            ]);

            // Snapshot pakietu domyślnego (darmowy „Kram") — sklep od startu ma pełny
            // zestaw uprawnień zapisany u siebie (model snapshot, patrz config/shop.php).
            $shop->assignPackage(config('shop.default_package'));

            $consents->record($user, $documents, $request->ip());

            // Zgody marketingowej TU NIE zbieramy — pyta o nią ekran AKTYWACJI,
            // gdzie adres jest już potwierdzony linkiem z własnej skrzynki
            // (ten sam wzorzec co u klientów sklepu).
            return $user;
        });

        // Mail aktywacyjny (link do formularza aktywacji, 24 h) ląduje w kolejce — wyśle go cron.
        $activation->send($user);

        // Zapamiętany adres pozwala wysłać link ponownie z ekranu potwierdzenia (bez logowania).
        $request->session()->put('registered_email', $user->email);

        return redirect()->route('register.confirmation');
    }
}
