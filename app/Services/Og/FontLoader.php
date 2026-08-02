<?php

namespace App\Services\Og;

use RuntimeException;

/**
 * Kroje pisma do grafik składanych po stronie serwera.
 *
 * Pliki leżą w repozytorium (Figtree, licencja OFL — `resources/fonts/OFL.txt`).
 * ŚWIADOMIE nie sięgamy po czcionki systemowe: na shared hoście są tylko odmiany
 * mono, a i te potrafią zniknąć przy migracji — wtedy napisy na grafikach
 * przestałyby się rysować bez żadnego ostrzeżenia.
 */
class FontLoader
{
    public function bold(): string
    {
        return $this->path('Figtree-Bold.ttf');
    }

    public function semiBold(): string
    {
        return $this->path('Figtree-SemiBold.ttf');
    }

    private function path(string $file): string
    {
        $path = resource_path('fonts/'.$file);

        if (! is_file($path)) {
            throw new RuntimeException('Brak kroju pisma do grafiki: '.$path);
        }

        return $path;
    }
}
