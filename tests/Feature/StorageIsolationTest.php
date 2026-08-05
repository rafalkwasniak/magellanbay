<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Suita nie ma prawa dotknąć plików produkcji.
 *
 * Testy chodzą w katalogu produkcyjnym (shared hosting, jedna kopia kodu), więc
 * dysk „public" to te same zdjęcia produktów i awatary, które widzą klienci.
 * 2026-08-04 test usuwania sklepu skasował realny `users/1` — awatar
 * administratora — bo `ShopEraser` kasuje katalogi po ID, a sqlite `:memory:`
 * rozdaje ID od 1. Ten test pilnuje gardy z `Tests\TestCase::isolateDisks()`,
 * żeby przy następnym takim teście zniknął plik z piaskownicy, a nie klienta.
 */
class StorageIsolationTest extends TestCase
{
    public static function diskProvider(): array
    {
        return [
            'public (zdjęcia, awatary)' => ['public', 'app/public'],
            'local (dysk domyślny)' => ['local', 'app/private'],
        ];
    }

    #[DataProvider('diskProvider')]
    public function test_disk_writes_land_in_the_sandbox_not_in_production(string $disk, string $productionPath): void
    {
        Storage::disk($disk)->put('kanarek.txt', 'x');

        $this->assertStringStartsWith(
            storage_path('framework/testing/disks'),
            Storage::disk($disk)->path(''),
            "Dysk [{$disk}] wskazuje poza piaskownicę testów."
        );

        $this->assertFileDoesNotExist(
            storage_path($productionPath.'/kanarek.txt'),
            "Test zapisał plik w produkcyjnym storage/{$productionPath}."
        );
    }

    /**
     * Najgroźniejsza operacja w kodzie: kasowanie CAŁEGO katalogu po ID. Tu ma
     * trafiać wyłącznie w piaskownicę, nawet gdy ID pokrywa się z produkcyjnym.
     */
    #[DataProvider('diskProvider')]
    public function test_directory_deletion_cannot_reach_production_files(string $disk, string $productionPath): void
    {
        $production = storage_path($productionPath.'/users/1');
        $existedBefore = is_dir($production);

        Storage::disk($disk)->put('users/1/awatar.jpg', 'x');
        Storage::disk($disk)->deleteDirectory('users/1');

        Storage::disk($disk)->assertMissing('users/1/awatar.jpg');
        $this->assertSame(
            $existedBefore,
            is_dir($production),
            "deleteDirectory() ruszyło produkcyjny katalog {$production}."
        );
    }
}
