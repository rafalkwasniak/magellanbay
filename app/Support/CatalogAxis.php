<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Oś podziału katalogu — rodzaj, tematyka, geografia.
 *
 * Osie są KONFIGURACJĄ (`config/catalog.php`), nie kodem, ale konfiguracja
 * czytana surowo rozłazi się po projekcie jako `config('catalog.axes.geo.multiple')`
 * w piętnastu miejscach. Ta klasa daje jej kształt: `$axis->multiple()` zamiast
 * ciągu znaków, o który łatwo się potknąć literówką.
 *
 * Nie jest to enum, bo osie mają być wymienialne bez zmiany kodu — kolejny
 * sklep dostaje „Kolor" i „Okazję", a enum kazałby to skompilować.
 */
final class CatalogAxis
{
    private function __construct(
        private readonly string $key,
        private readonly array $config,
    ) {}

    /**
     * Wszystkie osie sklepu, w kolejności z configu.
     *
     * @return Collection<string, self>
     */
    public static function all(): Collection
    {
        return collect(config('catalog.axes', []))
            ->map(fn (array $config, string $key): self => new self($key, $config));
    }

    /**
     * Oś po kluczu albo null — nieznany klucz to nie wyjątek, tylko brak
     * dopasowania (trafia tu wartość z adresu i z formularza).
     */
    public static function find(?string $key): ?self
    {
        $config = config('catalog.axes.'.$key);

        return is_array($config) ? new self((string) $key, $config) : null;
    }

    /**
     * Oś po segmencie adresu (`rodzaj`, `tematyka`, `geografia`).
     */
    public static function bySegment(?string $segment): ?self
    {
        return self::all()->first(fn (self $axis): bool => $axis->segment() === $segment);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(config('catalog.axes', []));
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return (string) ($this->config['label'] ?? $this->key);
    }

    public function labelPlural(): string
    {
        return (string) ($this->config['label_plural'] ?? $this->label());
    }

    public function segment(): string
    {
        return (string) ($this->config['segment'] ?? $this->key);
    }

    public function hint(): string
    {
        return (string) ($this->config['hint'] ?? '');
    }

    /**
     * Czy produkt może należeć do wielu węzłów tej osi.
     */
    public function multiple(): bool
    {
        return (bool) ($this->config['multiple'] ?? true);
    }

    /**
     * Czy węzły tej osi mogą mieć rodziców.
     */
    public function hierarchical(): bool
    {
        return (bool) ($this->config['hierarchical'] ?? false);
    }

    /**
     * Czy da się wstrzymać sprzedaż całego węzła tej osi.
     *
     * Wielokrotność wyklucza to z definicji, niezależnie od configu: produkt
     * stojący w dwóch węzłach naraz byłby jednocześnie wstrzymany i dostępny.
     */
    public function suspendable(): bool
    {
        return ! $this->multiple() && (bool) ($this->config['suspendable'] ?? false);
    }

    /**
     * Ile poziomów wolno zagnieździć. Oś płaska ma zawsze jeden.
     */
    public function maxDepth(): int
    {
        return $this->hierarchical() ? max(1, (int) config('catalog.max_depth', 3)) : 1;
    }
}
