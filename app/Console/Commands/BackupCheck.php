<?php

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use App\Services\DiscordErrorReporter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Strażnik kopii zapasowych: dobowe pytanie „czy backup w ogóle jeszcze działa?".
 *
 * Powód istnienia: kopia psuje się PO CICHU — zmienione hasło do bazy, brak
 * miejsca, przeniesiony katalog, wyłączony cron. Sam `backup:run` zgłosi awarię
 * tylko wtedy, gdy się URUCHOMI; najgorszy przypadek to ten, w którym nie
 * uruchamia się wcale i nikt tego nie widzi aż do dnia, w którym kopia jest
 * potrzebna. Dlatego pilnujemy nie przebiegu, lecz JEGO ŚLADU.
 *
 * Próg 36 h, nie 24 h: nocny przebieg, który raz się spóźni albo zostanie
 * ręcznie przesunięty, nie ma budzić alarmu. Dwie pominięte doby już tak.
 */
class BackupCheck extends Command
{
    protected $signature = 'backup:check';

    protected $description = 'Sprawdza, czy ostatnia udana kopia zapasowa nie jest przeterminowana';

    public function handle(DiscordErrorReporter $discord): int
    {
        if (! config('backup.enabled')) {
            // Świadome wyłączenie nie może zamienić się w codzienny alarm —
            // inaczej po tygodniu nikt nie czyta kanału.
            $this->comment('Kopie zapasowe są wyłączone — strażnik milczy.');

            return self::SUCCESS;
        }

        $hours = (int) config('backup.stale_after_hours');
        $last = PlatformSetting::lastBackupAt();

        if ($last !== null && $last->gt(Carbon::now()->subHours($hours))) {
            $this->info('Ostatnia kopia: '.$last->diffForHumans().'.');

            return self::SUCCESS;
        }

        $when = $last?->toDateTimeString() ?? 'nigdy';

        Log::error('Kopia zapasowa jest przeterminowana.', ['ostatnia' => $when, 'prog_godzin' => $hours]);

        $discord->alert(
            'Kopia zapasowa nie powstaje',
            "Od ponad {$hours} h nie zapisano udanej kopii. Sprawdź `backup:run` i miejsce na dysku.",
            [
                'Ostatnia udana kopia' => $when,
                'Katalog' => (string) config('backup.path'),
            ]
        );

        $this->error("Ostatnia udana kopia: {$when}. Alarm wysłany.");

        return self::FAILURE;
    }
}
