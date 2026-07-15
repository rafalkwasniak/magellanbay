<?php

namespace App\Models;

use App\Enums\ConsentChannel;
use Database\Factories\CustomerFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Klient storefrontu — logowanie w obrębie JEDNEGO sklepu (guard `customer`).
 * Całkowicie odseparowany od `User` (sprzedawca/admin): inna tabela, inny guard,
 * `shop_id` na każdym wierszu. Ten sam e-mail może być klientem wielu sklepów.
 * Konto powstaje nieaktywne (bez hasła); aktywacja = ustawienie hasła z linku
 * mailowego i `email_verified_at`. `shop_id` nie jest mass-assignable — konto
 * tworzymy przez relację sklepu (`$shop->customers()->create(...)`).
 */
#[Fillable(['name', 'surname', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Customer extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Sklep, którego klientem jest to konto.
     *
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Zamówienia przypięte do konta (po e-mailu, w obrębie sklepu). Zamówienia
     * gościa mają `customer_id = null` i tu się nie pojawiają.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Czy konto jest aktywowane — hasło ustawione z linku aktywacyjnego i e-mail
     * potwierdzony. Nieaktywne konto istnieje (rejestracja/kasa), ale nie może
     * się zalogować, dopóki klient nie kliknie w mail.
     */
    public function isActivated(): bool
    {
        return $this->email_verified_at !== null && $this->password !== null;
    }

    /**
     * @return HasMany<CustomerConsent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(CustomerConsent::class);
    }

    /**
     * Czy klient zgodził się na korespondencję w tym kanale (i nie wycofał zgody).
     * TO jest jedyne pytanie, które wolno zadać przed wysłaniem mailingu — sam
     * fakt rejestracji zgodą NIE jest (art. 10 uśude).
     */
    public function hasConsent(ConsentChannel $channel): bool
    {
        return (bool) $this->consents
            ->firstWhere('channel', $channel)
            ?->isActive();
    }

    /**
     * Włącza albo wycofuje zgodę na kanał. Idempotentne — jeden wiersz na parę
     * (klient, kanał), bez dziennika zmian.
     *
     * Przy udzielaniu zapisujemy DOWÓD: kiedy, z jakiego IP i na jaką wersję
     * treści (`config('legal.marketing_consent.version')`). Przy wycofaniu
     * zostawiamy wiersz z `revoked_at` — żeby odróżnić „wypisał się" od „nigdy
     * się nie zgodził"; zgoda musi być odwoływalna tak łatwo, jak udzielona.
     */
    public function setConsent(ConsentChannel $channel, bool $granted, ?string $ip = null): void
    {
        $this->consents()->updateOrCreate(
            ['channel' => $channel],
            $granted
                ? [
                    'granted_at' => now(),
                    'revoked_at' => null,
                    'version' => config('legal.marketing_consent.version'),
                    'ip_address' => $ip,
                ]
                : ['revoked_at' => now()],
        );

        $this->unsetRelation('consents');
    }

    /**
     * Przypina do tego konta wszystkie zamówienia gościa złożone na jego e-mail w
     * jego sklepie (specyfikacja: „Powiązanie wcześniejszych zamówień" — działa
     * wyłącznie w obrębie danego sklepu). Bierze tylko zamówienia bez właściciela
     * (`customer_id` = null), dopasowanie e-maila bez względu na wielkość liter.
     * Zwraca liczbę przypiętych zamówień. Wołane przy aktywacji i w kasie.
     */
    public function claimGuestOrders(): int
    {
        return Order::query()
            ->where('shop_id', $this->shop_id)
            ->whereNull('customer_id')
            ->whereRaw('LOWER(buyer_email) = ?', [mb_strtolower($this->email)])
            ->update(['customer_id' => $this->id]);
    }
}
