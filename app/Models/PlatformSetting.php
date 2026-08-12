<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Przełączniki operacyjne platformy (klucz–wartość).
 *
 * Czytane na KAŻDYM żądaniu — rejestracja sprawdza, czy jest otwarta, a layout,
 * czy pokazać komunikat o przerwie. Dlatego cały zestaw idzie przez cache w
 * jednym wpisie: dwa dodatkowe zapytania na request byłyby cichym podatkiem
 * nałożonym na wszystko, żeby obsłużyć dwa pola zmieniane raz na kwartał.
 *
 * Cache jest kasowany przy zapisie, więc przestawienie przełącznika działa
 * natychmiast — a to cały sens tej tabeli.
 */
#[Fillable(['key', 'value'])]
class PlatformSetting extends Model
{
    /** Rejestracja sprzedawców otwarta (bool jako '1'/'0'). */
    public const REGISTRATION_OPEN = 'registration_open';

    /** Komunikat o przerwie technicznej; pusty = brak baneru. */
    public const MAINTENANCE_NOTICE = 'maintenance_notice';

    /** Czas ostatniej UDANEJ kopii zapasowej (ISO 8601). */
    public const LAST_BACKUP_AT = 'last_backup_at';

    private const CACHE_KEY = 'platform_settings';

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    /**
     * Wszystkie ustawienia jako tablica — jeden odczyt na request.
     *
     * @return array<string, string|null>
     */
    public static function values(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => self::query()->pluck('value', 'key')->all()
        );
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::values()[$key] ?? $default;
    }

    /**
     * Rejestracja jest otwarta DOMYŚLNIE. Brak wiersza w tabeli (świeża
     * instalacja, wyczyszczona baza) nie może zamykać drzwi przed sprzedawcami —
     * awaria magazynu ustawień ma być niewidoczna, a nie wyłączać sprzedaż.
     */
    public static function registrationOpen(): bool
    {
        return self::get(self::REGISTRATION_OPEN, '1') !== '0';
    }

    /**
     * Treść baneru o przerwie technicznej albo null. Pokazywany wyłącznie w
     * CENTRALI — komunikat Kramio na storefroncie sprzedawcy przejmowałby jego
     * markę przed jego własnym klientem.
     */
    public static function maintenanceNotice(): ?string
    {
        $notice = trim((string) self::get(self::MAINTENANCE_NOTICE));

        return $notice !== '' ? $notice : null;
    }

    /**
     * Czas ostatniej udanej kopii zapasowej albo null, gdy jeszcze żadna się nie
     * powiodła. Zapisuje komenda `backup:run` — dopiero PO spakowaniu archiwum,
     * więc ta data nie kłamie: nieudany przebieg jej nie rusza i strażnik
     * `backup:check` zdąży zauważyć ciszę.
     */
    public static function lastBackupAt(): ?Carbon
    {
        $value = trim((string) self::get(self::LAST_BACKUP_AT));

        return $value !== '' ? Carbon::parse($value) : null;
    }

    public static function put(string $key, ?string $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }
}
