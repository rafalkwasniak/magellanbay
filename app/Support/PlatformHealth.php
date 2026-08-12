<?php

namespace App\Support;

use App\Models\EmailMessage;
use App\Models\PlatformSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Stan platformy — odpowiedź na pytanie „czy coś się pali", bez logowania po SSH.
 *
 * Wszystko tutaj jest TYLKO DO ODCZYTU i liczone na żywo. Świadomie nie ma
 * własnej tabeli ani migawek: zdrowie systemu to stan chwili, a przechowywanie
 * go tworzyłoby drugie źródło prawdy, które potrafi się zdezaktualizować bez
 * niczyjej wiedzy.
 *
 * Sekrety NIE WYCHODZĄ na ekran. Integrację opisujemy wyłącznie zdaniem
 * „skonfigurowana / brak" plus środowisko — do diagnozy to wystarcza, a klucz
 * API na ekranie przeglądarki to klucz w historii przeglądarki.
 */
class PlatformHealth
{
    /**
     * Integracje platformy. Klucze per-sklep (Paynow i Fakturownia sprzedawców)
     * świadomie pomijamy — to konfiguracja sklepu, widoczna na jego karcie.
     *
     * @return list<array{name: string, ok: bool, detail: string}>
     */
    public static function integrations(): array
    {
        $paynowKey = config('services.paynow.platform.api_key');
        $paynowEnv = config('services.paynow.platform.environment');

        return [
            [
                'name' => 'Paynow (opłaty za pakiety)',
                'ok' => filled($paynowKey),
                'detail' => filled($paynowKey)
                    // Środowisko jest tu ważniejsze niż sam fakt konfiguracji:
                    // sandbox na produkcji znaczy, że nikt nie zapłaci naprawdę.
                    ? ($paynowEnv === 'production' ? 'Klucze produkcyjne' : 'UWAGA: tryb sandbox')
                    : 'Brak kluczy — zakup pakietu online nie zadziała',
            ],
            [
                'name' => 'Fakturownia (faktury za pakiety)',
                'ok' => filled(config('services.fakturownia.token')) && filled(config('services.fakturownia.url')),
                'detail' => filled(config('services.fakturownia.token'))
                    ? 'Konto platformy podłączone'
                    : 'Brak konta — faktury za pakiety nie powstaną',
            ],
            [
                'name' => 'Alerty na Discorda',
                'ok' => filled(config('services.discord.webhook')),
                'detail' => filled(config('services.discord.webhook'))
                    ? 'Błędy lecą na kanał'
                    : 'Brak webhooka — błędy zostają tylko w logu',
            ],
            self::backups(),
        ];
    }

    /**
     * Kopie zapasowe. Zielone światło daje wyłącznie ŚWIEŻY ślad po udanym
     * przebiegu — nie sama obecność konfiguracji. Wpis „skonfigurowane" przy
     * kopii, która ostatnio powstała trzy tygodnie temu, byłby gorszy niż
     * czerwony pasek: usypia dokładnie wtedy, gdy trzeba działać.
     *
     * @return array{name: string, ok: bool, detail: string}
     */
    private static function backups(): array
    {
        if (! config('backup.enabled')) {
            return [
                'name' => 'Kopie zapasowe',
                'ok' => false,
                'detail' => 'WYŁĄCZONE w konfiguracji (BACKUP_ENABLED=false)',
            ];
        }

        $last = PlatformSetting::lastBackupAt();

        if ($last === null) {
            return [
                'name' => 'Kopie zapasowe',
                'ok' => false,
                'detail' => 'Skonfigurowane, ale żadna kopia jeszcze się nie powiodła',
            ];
        }

        $stale = $last->lt(Carbon::now()->subHours((int) config('backup.stale_after_hours')));

        return [
            'name' => 'Kopie zapasowe',
            'ok' => ! $stale,
            'detail' => $stale
                ? 'PRZETERMINOWANE — ostatnia '.$last->translatedFormat('j F Y, H:i')
                : 'Ostatnia '.$last->translatedFormat('j F Y, H:i').', retencja '.config('backup.retention_days').' dni',
        ];
    }

    /**
     * Kolejka i poczta. Oba sygnały mówią o tym samym: czy zadania w tle są
     * mielone. Faktury i maile idą kolejką, więc stojący worker jest niewidoczny
     * dla użytkownika aż do chwili, gdy ktoś zapyta „gdzie moja faktura".
     *
     * @return array{queued: int, failed: int, mail_pending: int, mail_failed: int}
     */
    public static function queue(): array
    {
        return [
            'queued' => DB::table('jobs')->count(),
            'failed' => DB::table('failed_jobs')->count(),
            'mail_pending' => EmailMessage::query()->whereNull('sent_at')->whereNull('failed_at')->count(),
            'mail_failed' => EmailMessage::query()->whereNotNull('failed_at')->count(),
        ];
    }

    /**
     * Środowisko uruchomieniowe. `APP_DEBUG` jest tu najważniejszy: włączony na
     * produkcji pokazuje obcym ludziom ślady stosu i zawartość `.env`.
     *
     * @return array{env: string, debug: bool, php: string, laravel: string, timezone: string}
     */
    public static function runtime(): array
    {
        return [
            'env' => (string) config('app.env'),
            'debug' => (bool) config('app.debug'),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'timezone' => (string) config('app.timezone'),
        ];
    }

    /**
     * Dzienne logi błędów z ostatnich dni — ile linii ERROR/CRITICAL w każdym
     * pliku. Nie czytamy treści: ekran ma powiedzieć „zajrzyj do logu", a nie
     * zastąpić log. Wczytanie kilkumegabajtowego pliku do pamięci po to, żeby
     * pokazać jego fragment, byłoby złym interesem na współdzielonym hostingu.
     *
     * @return list<array{date: string, errors: int}>
     */
    public static function recentErrors(int $days = 5): array
    {
        $result = [];

        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($i);
            $path = storage_path('logs/laravel-'.$date->format('Y-m-d').'.log');

            if (! File::exists($path)) {
                continue;
            }

            $result[] = ['date' => $date->format('d.m.Y'), 'errors' => self::countErrorLines($path)];
        }

        return $result;
    }

    /**
     * Liczy linie z poziomem błędu, czytając plik STRUMIENIOWO.
     *
     * Nie `file_get_contents` (dzienny log potrafi mieć megabajty i wszedłby w
     * całości do pamięci PHP) i nie `grep` przez `shell_exec` — na współdzielonym
     * hostingu funkcje powłoki bywają wyłączone, a wtedy ekran diagnostyczny sam
     * stałby się awarią. Strumień działa wszędzie i zajmuje jedną linię pamięci.
     */
    private static function countErrorLines(string $path): int
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            return 0;
        }

        $count = 0;

        while (($line = fgets($handle)) !== false) {
            if (preg_match('/\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', $line) === 1) {
                $count++;
            }
        }

        fclose($handle);

        return $count;
    }
}
