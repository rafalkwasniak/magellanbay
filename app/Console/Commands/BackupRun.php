<?php

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Kopia zapasowa Kramio: jedno archiwum na dobę, w nim dokładnie to, czego NIE
 * MA w gicie i czego nikt nie odtworzy z kodu:
 *
 *   baza.sql  — zrzut bazy (sklepy, produkty, zamówienia, klienci, zgody)
 *   public/   — `storage/app/public`, czyli zdjęcia produktów
 *   .env      — klucze Paynow i Fakturowni, hasło do bazy
 *
 * Baza i pliki lecą w JEDNYM przebiegu, więc przy dzisiejszej skali są spójne
 * co do sekundy. Odtworzenie: rozpakuj archiwum, `mysql < baza.sql`, katalog
 * `public` skopiuj do `storage/app/`, `.env` na miejsce.
 *
 * Próg skali: `tar` całości co noc jest właściwy do ~1 GB zdjęć (dziś 2,6 MB,
 * czyli ~380× zapasu). Powyżej trzeba przejść na pełny raz w tygodniu plus
 * dobowe dosypywanie różnicy.
 *
 * Idempotentna w tym sensie, że nazwa archiwum niesie sekundę — powtórzony bieg
 * robi drugi plik, niczego nie nadpisuje.
 */
class BackupRun extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Kopia zapasowa: baza + zdjęcia + .env do katalogu poza katalogiem domeny';

    public function handle(): int
    {
        if (! config('backup.enabled')) {
            $this->comment('Kopie zapasowe są wyłączone (BACKUP_ENABLED=false).');

            return self::SUCCESS;
        }

        $path = rtrim((string) config('backup.path'), '/');

        if ($path === '') {
            // Głośno, nie po cichu: backup, który udaje, że działa, jest gorszy
            // od żadnego, bo usypia czujność.
            throw new RuntimeException('Kopie zapasowe są włączone, ale BACKUP_PATH jest pusty — nie ma gdzie zapisać archiwum.');
        }

        $stamp = Carbon::now()->format('Y-m-d_His');
        $workspace = $path.'/.tmp-'.$stamp;
        $archive = $path.'/kramio-'.$stamp.'.tar.gz';

        $this->prepareDirectory($path);
        $this->prepareDirectory($workspace);

        try {
            $this->dumpDatabase($workspace.'/baza.sql');
            $this->packArchive($archive, $workspace);
        } finally {
            // Zrzut bazy i plik z hasłem znikają NIEZALEŻNIE od wyniku — nie
            // mogą przeleżeć nocy obok archiwum jako drugi, luźny egzemplarz.
            $this->removeDirectory($workspace);
        }

        chmod($archive, 0600);

        // Dopiero TU, po spakowaniu — data ostatniej kopii ma być dowodem, a nie
        // deklaracją zamiaru. Z niej żyje strażnik `backup:check` i ekran Ustawień.
        PlatformSetting::put(PlatformSetting::LAST_BACKUP_AT, Carbon::now()->toIso8601String());

        $removed = $this->prune($path);

        $this->info(sprintf(
            'Kopia zapasowa: %s (%s). Usunięte przeterminowane: %d.',
            $archive,
            $this->humanSize((int) filesize($archive)),
            $removed
        ));

        return self::SUCCESS;
    }

    /**
     * Katalog o prawach 700 — na współdzielonym hoście archiwum z `.env`
     * w środku nie może być czytelne dla innych kont.
     */
    private function prepareDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("Nie udało się utworzyć katalogu kopii: {$directory}");
        }

        chmod($directory, 0700);
    }

    /**
     * Zrzut bazy. Hasło idzie przez plik `--defaults-extra-file`, NIGDY
     * w argumentach: linię poleceń procesu widzi w `ps` każdy użytkownik
     * serwera, więc `--password=` na shared-hoście to jawny wyciek.
     */
    private function dumpDatabase(string $target): void
    {
        $connection = config('database.default');
        $db = config("database.connections.{$connection}");

        $credentials = dirname($target).'/mysql.cnf';

        file_put_contents($credentials, sprintf(
            "[client]\nhost=%s\nport=%s\nuser=%s\npassword=\"%s\"\n",
            $db['host'] ?? '127.0.0.1',
            $db['port'] ?? 3306,
            $db['username'] ?? '',
            str_replace('"', '\"', (string) ($db['password'] ?? '')),
        ));
        chmod($credentials, 0600);

        $result = Process::run([
            config('backup.mysqldump_binary'),
            '--defaults-extra-file='.$credentials,
            '--single-transaction',   // spójny zrzut bez blokowania sklepów na czas dumpu
            '--quick',
            '--no-tablespaces',       // konto hostingowe nie ma uprawnienia PROCESS
            '--default-character-set=utf8mb4',
            '--result-file='.$target,
            $db['database'] ?? '',
        ]);

        if ($result->failed()) {
            throw new RuntimeException('mysqldump zakończył się błędem: '.trim($result->errorOutput()));
        }
    }

    /**
     * Jedno archiwum z trzech źródeł leżących w różnych miejscach — stąd kilka
     * przełączników `-C` zamiast kopiowania zdjęć do katalogu roboczego.
     */
    private function packArchive(string $archive, string $workspace): void
    {
        $photos = storage_path('app/public');

        $arguments = ['tar', '-czf', $archive, '-C', $workspace, 'baza.sql'];

        if (is_file(base_path('.env'))) {
            array_push($arguments, '-C', base_path(), '.env');
        }

        // Świeża instalacja bywa bez katalogu zdjęć — brak jest normalny,
        // a `tar` na nieistniejącej ścieżce wywróciłby cały backup.
        if (is_dir($photos)) {
            array_push($arguments, '-C', storage_path('app'), 'public');
        }

        $result = Process::run($arguments);

        if ($result->failed()) {
            throw new RuntimeException('Pakowanie archiwum nie powiodło się: '.trim($result->errorOutput()));
        }
    }

    /**
     * Kasuje archiwa starsze niż okno retencji. Patrzy na NAZWĘ pliku, nie na
     * czas modyfikacji — data w nazwie mówi, z kiedy są dane, a `mtime` łatwo
     * przestawić kopiowaniem katalogu.
     */
    private function prune(string $path): int
    {
        $days = (int) config('backup.retention_days');

        if ($days < 1) {
            return 0;
        }

        $oldest = Carbon::now()->subDays($days);
        $removed = 0;

        foreach (glob($path.'/kramio-*.tar.gz') ?: [] as $file) {
            if (! preg_match('/kramio-(\d{4}-\d{2}-\d{2})_\d{6}\.tar\.gz$/', $file, $matches)) {
                continue;
            }

            if (Carbon::parse($matches[1])->startOfDay()->lt($oldest->copy()->startOfDay())) {
                unlink($file);
                $removed++;
            }
        }

        return $removed;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }

    private function humanSize(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 1).' MB'
            : round($bytes / 1024).' KB';
    }
}
