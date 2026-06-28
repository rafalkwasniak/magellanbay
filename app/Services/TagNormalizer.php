<?php

namespace App\Services;

use Collator;
use Illuminate\Support\Collection;

/**
 * Normalizacja tagów — jedno źródło prawdy dla jakości chmury tagów:
 * - małe litery, polskie znaki ZOSTAJĄ (ślub, nie slub),
 * - spacje wokół myślnika znikają (czarno - niebieskie → czarno-niebieskie),
 * - przycinane śmieci/interpunkcja z brzegów,
 * - z pola wyciągamy tylko wyrazy, deduplikujemy i sortujemy po polsku.
 */
class TagNormalizer
{
    private const MAX_LENGTH = 50;

    /**
     * Pojedynczy tag → postać kanoniczna albo null.
     */
    public function normalize(?string $raw): ?string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        $value = (string) preg_replace('/\s*-\s*/u', '-', $value);   // spacje wokół myślnika
        $value = (string) preg_replace('/\s+/u', ' ', $value);       // wielokrotne spacje
        $value = mb_strtolower($value, 'UTF-8');
        $value = (string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $value);
        $value = (string) preg_replace('/[^\p{L}\p{N}]+$/u', '', $value);
        $value = mb_substr($value, 0, self::MAX_LENGTH, 'UTF-8');

        return $value === '' ? null : $value;
    }

    /**
     * Pole (po przecinku) → posortowana, unikalna lista kanonicznych tagów.
     *
     * @return array<int, string>
     */
    public function parse(?string $input): array
    {
        /** @var Collection<int, string> $tags */
        $tags = collect(explode(',', (string) $input))
            ->map(fn (string $tag): ?string => $this->normalize($tag))
            ->filter()
            ->unique()
            ->values();

        $names = $tags->all();
        $this->sortPolish($names);

        return $names;
    }

    /**
     * @param  array<int, string>  $names
     */
    private function sortPolish(array &$names): void
    {
        if (class_exists(Collator::class)) {
            (new Collator('pl_PL'))->sort($names);

            return;
        }

        usort($names, 'strcoll');
    }
}
