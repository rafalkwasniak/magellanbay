<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Support\PlatformHealth;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Strażnik kopii zapasowych i sygnał na ekranie Ustawień.
 *
 * Sens tej warstwy: sam `backup:run` zgłasza tylko własne awarie. Przypadek
 * najgorszy — kopia nie uruchamia się WCALE (wyłączony cron, przeniesiony
 * katalog) — nie zgłosi się sam, bo nie ma komu. Dlatego pilnujemy śladu.
 */
class BackupWatchdogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'backup.enabled' => true,
            'backup.stale_after_hours' => 36,
            'services.discord.webhook' => 'https://discord.test/webhook',
        ]);

        Carbon::setTestNow('2026-08-12 09:00:00');
    }

    public function test_fresh_backup_raises_no_alarm(): void
    {
        Http::fake();
        PlatformSetting::put(PlatformSetting::LAST_BACKUP_AT, Carbon::now()->subHours(6)->toIso8601String());

        $this->artisan('backup:check')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_stale_backup_alerts_on_discord(): void
    {
        Http::fake();
        PlatformSetting::put(PlatformSetting::LAST_BACKUP_AT, Carbon::now()->subHours(40)->toIso8601String());

        $this->artisan('backup:check')->assertFailed();

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'Kopia zapasowa nie powstaje'));
    }

    public function test_backup_that_never_succeeded_alerts_too(): void
    {
        // Świeża instalacja z włączonymi kopiami, w której nic nigdy nie zadziałało,
        // jest dokładnie tak samo niezabezpieczona jak ta z zepsutym cronem.
        Http::fake();

        $this->artisan('backup:check')->assertFailed();

        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'nigdy'));
    }

    public function test_disabled_backup_does_not_nag_every_day(): void
    {
        config(['backup.enabled' => false]);
        Http::fake();

        $this->artisan('backup:check')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_watchdog_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'backup:check'));

        $this->assertCount(1, $events);
        $this->assertSame('0 9 * * *', $events->first()->expression);
    }

    public function test_settings_screen_reports_backups_green_only_when_fresh(): void
    {
        PlatformSetting::put(PlatformSetting::LAST_BACKUP_AT, Carbon::now()->subHours(6)->toIso8601String());

        $entry = collect(PlatformHealth::integrations())->firstWhere('name', 'Kopie zapasowe');

        $this->assertTrue($entry['ok']);
        $this->assertStringContainsString('12 sierpnia 2026', $entry['detail']);
    }

    public function test_settings_screen_turns_red_when_backup_is_stale(): void
    {
        PlatformSetting::put(PlatformSetting::LAST_BACKUP_AT, Carbon::now()->subDays(3)->toIso8601String());

        $entry = collect(PlatformHealth::integrations())->firstWhere('name', 'Kopie zapasowe');

        $this->assertFalse($entry['ok']);
        $this->assertStringContainsString('PRZETERMINOWANE', $entry['detail']);
    }

    public function test_settings_screen_is_red_before_first_successful_backup(): void
    {
        $entry = collect(PlatformHealth::integrations())->firstWhere('name', 'Kopie zapasowe');

        $this->assertFalse($entry['ok']);
        $this->assertStringContainsString('żadna kopia', $entry['detail']);
    }
}
