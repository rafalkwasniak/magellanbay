<?php

namespace App\Models;

use App\Enums\ContentReportCategory;
use App\Enums\ContentReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jedno zgłoszenie treści bezprawnej (art. 16 DSA).
 *
 * `shop_id` nie jest mass-assignable — sklep rozwiązujemy z adresu po stronie
 * serwera (`shopFromUrl()`), żeby zgłaszający nie mógł wskazać cudzego sklepu
 * przez podrzucenie pola w formularzu.
 */
#[Fillable(['url', 'category', 'justification', 'reporter_name', 'reporter_email', 'good_faith', 'ip_address'])]
class ContentReport extends Model
{
    protected function casts(): array
    {
        return [
            'category' => ContentReportCategory::class,
            'status' => ContentReportStatus::class,
            'good_faith' => 'boolean',
            'decided_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * Numer sprawy — jedyny sposób, żeby dogadać się o KTÓRE zgłoszenie chodzi,
     * gdy spłynie ich kilka podobnych z tego samego sklepu.
     *
     * Wyprowadzony z `id`, nie z osobnego licznika: identyfikator jest już
     * unikalny i nadaje go baza, więc dwa zgłoszenia wysłane w tej samej
     * sekundzie nie mogą dostać tego samego numeru. Osobna sekwencja (np.
     * resetowana co rok) wymagałaby blokady przy zapisie i potrafiłaby się
     * zdublować pod obciążeniem — za tę cenę kupowalibyśmy wyłącznie ładniejszy
     * wygląd numeru.
     *
     * Świadomie BEZ roku w numerze: rok sugerowałby licznik zerowany co styczeń,
     * a ten nim nie jest — „ZG-000042" w 2027 byłoby po prostu nieprawdą.
     */
    public function reference(): string
    {
        return 'ZG-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Zamienia wpisany numer sprawy na `id`. Przyjmuje to, co człowiek naprawdę
     * wkleja: „ZG-000042", „zg-42", samo „42", z wiodącymi zerami lub bez.
     * Zwraca null, gdy tekst nie wygląda na numer — wtedy szukamy zwyczajnie.
     */
    public static function idFromReference(string $text): ?int
    {
        if (preg_match('/^\s*(?:zg-?)?0*(\d{1,9})\s*$/i', $text, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @param  Builder<ContentReport>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ContentReportStatus::New);
    }

    /**
     * Sklep, którego dotyczy zgłoszony adres — albo null, gdy adres nie wskazuje
     * na żaden storefront (centrala, obcy serwis, literówka w domenie).
     *
     * Rozwiązujemy z ETYKIETY SUBDOMENY, tak samo jak robi to `ResolveShop` przy
     * zwykłym ruchu — jedno źródło prawdy o tym, czym jest adres sklepu. `www`
     * jest drugą nazwą centrali, nie sklepem, więc odpada razem z resztą.
     */
    public static function shopFromUrl(string $url): ?Shop
    {
        $host = parse_url(trim($url), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $suffix = '.'.config('tenancy.central_domain');

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $slug = substr($host, 0, -strlen($suffix));

        // Tylko JEDNA etykieta przed domeną centrali jest adresem sklepu —
        // `cos.bukiety.kramio.pl` nie jest sklepem `cos.bukiety`.
        if ($slug === '' || $slug === 'www' || str_contains($slug, '.')) {
            return null;
        }

        return Shop::where('slug', $slug)->first();
    }
}
