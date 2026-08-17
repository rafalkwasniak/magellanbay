<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

/**
 * Nocna kopia zapasowa: baza + zdjęcia + `.env` do katalogu poza domeną.
 *
 * `mysqldump` i `tar` są tu ZAŚLEPIONE — testowa baza to SQLite w pamięci, więc
 * prawdziwy zrzut nie miałby czego zrzucić, a odpalanie narzędzi systemowych
 * w suicie to proszenie się o kłopoty na współdzielonym hoście. Sprawdzamy
 * to, co naprawdę może się zepsuć: kształt poleceń, prawa do plików,
 * sprzątanie i retencję.
 */
class BackupRunTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/backups-'.uniqid());

        config([
            'backup.enabled' => true,
            'backup.path' => $this->path,
            'backup.retention_days' => 14,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->path.'/*') ?: [] as $file) {
            is_dir($file) ? array_map('unlink', glob($file.'/*') ?: []) && rmdir($file) : unlink($file);
        }

        if (is_dir($this->path)) {
            rmdir($this->path);
        }

        parent::tearDown();
    }

    /**
     * Zaślepka udająca `tar`: tworzy plik archiwum, żeby dalsza część komendy
     * (prawa, rozmiar, retencja) miała na czym pracować.
     */
    private function fakeTooling(string $archive): void
    {
        Process::fake(function () use ($archive) {
            if (! is_file($archive)) {
                file_put_contents($archive, 'archiwum testowe');
            }

            return Process::result('');
        });
    }

    public function test_creates_archive_with_private_permissions(): void
    {
        Carbon::setTestNow('2026-08-12 03:00:00');
        $archive = $this->path.'/kramio-2026-08-12_030000.tar.gz';
        $this->fakeTooling($archive);

        $this->artisan('backup:run')->assertSuccessful();

        $this->assertFileExists($archive);

        // Archiwum niesie `.env` z kluczami Paynow i hasłem do bazy, więc prawa
        // są częścią kopii, nie ozdobą: katalog 700, plik 600.
        $this->assertSame('0700', substr(sprintf('%o', fileperms($this->path)), -4));
        $this->assertSame('0600', substr(sprintf('%o', fileperms($archive)), -4));
    }

    public function test_database_password_never_appears_in_process_arguments(): void
    {
        // Linię poleceń procesu widzi w `ps` KAŻDY użytkownik współdzielonego
        // serwera. Hasło może iść wyłącznie plikiem --defaults-extra-file.
        config(['database.connections.'.config('database.default').'.password' => 'tajne-haslo-bazy']);

        Carbon::setTestNow('2026-08-12 03:00:00');
        $this->fakeTooling($this->path.'/kramio-2026-08-12_030000.tar.gz');

        $this->artisan('backup:run')->assertSuccessful();

        Process::assertRan(function ($process) {
            $command = implode(' ', (array) $process->command);

            return str_contains($command, 'mysqldump')
                && str_contains($command, '--defaults-extra-file=')
                && ! str_contains($command, 'tajne-haslo-bazy');
        });
    }

    public function test_workspace_with_dump_and_credentials_is_removed(): void
    {
        Carbon::setTestNow('2026-08-12 03:00:00');
        $this->fakeTooling($this->path.'/kramio-2026-08-12_030000.tar.gz');

        $this->artisan('backup:run')->assertSuccessful();

        // Ani zrzutu, ani pliku z hasłem nie wolno zostawić obok archiwum.
        $this->assertSame([], glob($this->path.'/.tmp-*') ?: []);
    }

    public function test_successful_run_records_the_moment_for_the_watchdog(): void
    {
        Carbon::setTestNow('2026-08-12 03:00:00');
        $this->fakeTooling($this->path.'/kramio-2026-08-12_030000.tar.gz');

        $this->artisan('backup:run')->assertSuccessful();

        $this->assertNotNull(PlatformSetting::lastBackupAt());
        $this->assertTrue(PlatformSetting::lastBackupAt()->equalTo(Carbon::now()));
    }

    public function test_failed_run_leaves_the_previous_timestamp_untouched(): void
    {
        // Data ostatniej kopii jest DOWODEM, nie deklaracją: gdyby nieudany
        // przebieg ją odświeżał, strażnik milczałby przy zepsutym backupie.
        Carbon::setTestNow('2026-08-10 03:00:00');
        PlatformSetting::put(PlatformSetting::LAST_BACKUP_AT, Carbon::now()->toIso8601String());

        Carbon::setTestNow('2026-08-12 03:00:00');
        Process::fake(['*' => Process::result(errorOutput: 'Access denied', exitCode: 1)]);

        try {
            $this->artisan('backup:run');
        } catch (RuntimeException) {
            // oczekiwane
        }

        $this->assertSame('2026-08-10', PlatformSetting::lastBackupAt()->toDateString());
    }

    public function test_workspace_is_removed_even_when_dump_fails(): void
    {
        Carbon::setTestNow('2026-08-12 03:00:00');
        Process::fake(['*' => Process::result(errorOutput: 'Access denied', exitCode: 1)]);

        try {
            $this->artisan('backup:run');
            $this->fail('Nieudany zrzut powinien przerwać komendę.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('mysqldump', $e->getMessage());
        }

        $this->assertSame([], glob($this->path.'/.tmp-*') ?: []);
    }

    public function test_archives_older_than_retention_window_are_removed(): void
    {
        Carbon::setTestNow('2026-08-12 03:00:00');
        mkdir($this->path, 0700, true);

        $stale = $this->path.'/kramio-2026-07-20_030000.tar.gz';   // 23 dni
        $fresh = $this->path.'/kramio-2026-08-05_030000.tar.gz';   // 7 dni
        $alien = $this->path.'/nie-nasze-2026-01-01.tar.gz';
        foreach ([$stale, $fresh, $alien] as $file) {
            file_put_contents($file, 'x');
        }

        $this->fakeTooling($this->path.'/kramio-2026-08-12_030000.tar.gz');

        $this->artisan('backup:run')->assertSuccessful();

        $this->assertFileDoesNotExist($stale);
        $this->assertFileExists($fresh);
        // Cudzych plików w tym katalogu nie ruszamy — kasujemy po NASZYM wzorcu nazwy.
        $this->assertFileExists($alien);
    }

    public function test_disabled_backup_does_nothing(): void
    {
        config(['backup.enabled' => false]);
        Process::fake();

        $this->artisan('backup:run')->assertSuccessful();

        Process::assertNothingRan();
        $this->assertDirectoryDoesNotExist($this->path);
    }

    public function test_enabled_backup_without_path_fails_loudly(): void
    {
        // Cicha porażka usypia czujność — dlatego brak ścieżki wywraca komendę,
        // zamiast po prostu nic nie robić.
        config(['backup.path' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BACKUP_PATH');

        $this->artisan('backup:run');
    }

    public function test_backup_is_scheduled_twice_a_day(): void
    {
        // Dwa przebiegi na dobę skracają okno utraty danych z ~24 h do ~12 h.
        // Godziny NIE są przypadkowe: 04:00 trzyma się z dala od
        // `subscriptions:check` (06:10) i `shops:purge` (06:20), a 16:00 dzieli
        // dobę równo. Test pilnuje jednego i drugiego, bo cicha zmiana na
        // pojedynczy przebieg wróciłaby do 24-godzinnego okna niezauważona.
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'backup:run'));

        $this->assertCount(2, $events, 'Kopia zapasowa musi lecieć dwa razy na dobę.');
        // Kolejność jak w configu — sortowanie po stringu ustawiłoby „0 16" przed „0 4".
        $this->assertSame(['0 4 * * *', '0 16 * * *'], $events->pluck('expression')->values()->all());
    }

    public function test_backup_times_come_from_a_comma_separated_list(): void
    {
        // `.env` niesie godziny jednym stringiem — rozjazd w parsowaniu cicho
        // zabrałby drugi przebieg, a harmonogram dalej wyglądałby poprawnie.
        $this->assertSame(['04:00', '16:00'], config('backup.daily_at'));
    }

    public function test_watchdog_threshold_matches_the_backup_cadence(): void
    {
        // Próg strażnika MUSI iść za częstotliwością. Przy kopii co 12 h alarm
        // o 09:00 ma milczeć po JEDNYM pominiętym przebiegu (17 h od ostatniej
        // udanej), a odezwać się po dwóch pod rząd (29 h).
        $hours = (int) config('backup.stale_after_hours');

        $this->assertGreaterThan(17, $hours, 'Pojedyncza czkawka nie ma budzić nikogo.');
        $this->assertLessThan(29, $hours, 'Dwa pominięte przebiegi muszą zapalić alarm.');
    }
}
