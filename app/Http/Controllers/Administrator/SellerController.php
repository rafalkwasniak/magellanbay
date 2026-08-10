<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivationMailer;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Konsola admina — sprzedawcy. Lista kont z rolą `seller` i karta pojedynczego
 * konta: dane kontaktowe, jego sklep, stan aktywacji i komplet zgód.
 *
 * Świadomie NIE pokazuje kont administratorów — dział nazywa się „Sprzedawcy",
 * a wrzucenie tu własnych kont zafałszowałoby liczniki, z których czyta się
 * m.in. „do ilu osób wolno mi napisać".
 *
 * Ten ekran to podgląd plus jedna akcja pomocowa (ponowna wysyłka linku
 * aktywacyjnego). Edycji cudzych danych osobowych tu nie ma i nie ma być:
 * sprzedawca zmienia je sam w profilu, a admin poprawiający komuś nazwisko
 * zostawia zmianę bez śladu, kto ją zrobił.
 */
class SellerController extends Controller
{
    /**
     * Sposoby sortowania: klucz w URL (po polsku) → etykieta zakładki.
     */
    private const SORTS = [
        'najnowsi' => 'Najnowsi',
        'nazwisko' => 'Nazwisko',
        'logowanie' => 'Ostatnie logowanie',
    ];

    private const PER_PAGE = 20;

    public function index(Request $request): Renderable
    {
        $sort = $request->query('sortuj');
        $sort = is_string($sort) && array_key_exists($sort, self::SORTS) ? $sort : 'najnowsi';

        $search = trim((string) $request->query('szukaj', ''));

        $filters = [
            'aktywacja' => $this->tristate($request->query('aktywacja')),
            'zgoda' => $this->tristate($request->query('zgoda')),
        ];

        $base = User::query()
            ->where('role', UserRole::Seller)
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.$search.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('surname', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhereHas('shop', fn ($s) => $s
                            ->where('name', 'like', $term)
                            ->orWhere('slug', 'like', $term));
                });
            })
            ->when($filters['aktywacja'] !== null, fn ($q) => $filters['aktywacja']
                ? $q->whereNotNull('email_verified_at')
                : $q->whereNull('email_verified_at'))
            ->when($filters['zgoda'] !== null, fn ($q) => $filters['zgoda']
                ? $q->whereHas('marketingConsents', User::activeMarketingConsent())
                : $q->whereDoesntHave('marketingConsents', User::activeMarketingConsent()));

        // Kafelki liczone z TEGO SAMEGO zapytania co lista, czyli po filtrach —
        // przy pustych filtrach dają obraz całej platformy, a przy zawężonych
        // opisują dokładnie to, co widać niżej. Trzy zliczenia na tabeli kont
        // są tanie; klonujemy, bo `count()` domknąłby budowniczego.
        $summary = [
            'sellers' => (clone $base)->count(),
            'activated' => (clone $base)->whereNotNull('email_verified_at')->count(),
            'consented' => (clone $base)->whereHas('marketingConsents', User::activeMarketingConsent())->count(),
        ];

        $sellers = $base
            ->with(['shop', 'marketingConsents'])
            ->when($sort === 'najnowsi', fn ($q) => $q->orderByDesc('created_at'))
            ->when($sort === 'nazwisko', fn ($q) => $q->orderBy('surname')->orderBy('name'))
            // Konta bez śladu logowania na koniec — inaczej puste pola przykryłyby
            // odpowiedź na pytanie, kto był tu ostatnio.
            ->when($sort === 'logowanie', fn ($q) => $q->orderByRaw('last_login_at is null')->orderByDesc('last_login_at'))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('administrator.sellers.index', [
            'sellers' => $sellers,
            'summary' => $summary,
            'sort' => $sort,
            'sorts' => self::SORTS,
            'search' => $search,
            'filters' => $filters,
            'filtered' => $search !== '' || $filters['aktywacja'] !== null || $filters['zgoda'] !== null,
        ]);
    }

    public function show(User $user): Renderable
    {
        // Adres z `id` administratora otwierałby kartę „sprzedawcy”, którym on
        // nie jest — w tym dziale takie konto po prostu nie istnieje.
        abort_unless($user->isSeller(), 404);

        return view('administrator.sellers.show', [
            'seller' => $user->load([
                'shop',
                'marketingConsents',
                // Akceptacje dokumentów z samym dokumentem — dowód jest wart tyle,
                // ile wiedza, KTÓRĄ wersję regulaminu ktoś widział.
                'consents.document',
            ]),
        ]);
    }

    /**
     * Ponowna wysyłka linku aktywacyjnego. Sprzedawca ma na to własny przycisk na
     * ekranie „Sprawdź skrzynkę”, ale ten ginie razem z zamkniętą kartą — a mail
     * potrafi utknąć w spamie. Bez tego jedynym ratunkiem jest grzebanie w bazie.
     */
    public function resendActivation(User $user, ActivationMailer $activation): RedirectResponse
    {
        abort_unless($user->isSeller(), 404);

        // Konto z potwierdzonym adresem ma już swoje hasło. Wysłanie mu linku
        // aktywacyjnego nie jest groźne, ale jest mylące — dostałby zaproszenie
        // do zakładania konta, które od dawna ma.
        if ($user->isActivated()) {
            return back()->with('error', 'To konto jest już aktywne — link aktywacyjny nie ma tu zastosowania.');
        }

        $activation->send($user);

        return back()->with('success', 'Link aktywacyjny poleciał ponownie na '.$user->email.'.');
    }

    /**
     * Filtr trójstanowy z URL: „1” → tak, „0” → nie, brak → bez znaczenia. Ten
     * sam wzorzec co w kartotece klientów — bez niego nie dałoby się wskazać
     * kont BEZ aktywacji, bo „0” i „brak parametru” znaczyłyby to samo.
     */
    private function tristate(mixed $value): ?bool
    {
        return match ((string) $value) {
            '1' => true,
            '0' => false,
            default => null,
        };
    }
}
