<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Zajawka treści z edytora Trix — jedno źródło prawdy dla kafelków na stronie
 * głównej: wirtualnego „O sklepie" (`shop.description`) i promowanych stron
 * (`page.content`). Oba źródła to ten sam edytor i ten sam sanitizer, więc i
 * zajawka musi działać dla nich identycznie.
 *
 * `hasMore` mówi jedną rzecz: czy tekst nie zmieścił się w limicie. To ono
 * rozstrzyga, w którym z dwóch stanów jest kafelek:
 *
 *  - MIEŚCI SIĘ → kafelek pokazuje CAŁĄ treść, z formatowaniem, bez odnośnika.
 *    Nie ma dokąd wchodzić, bo cel miałby dokładnie to samo. Linki w treści są
 *    wtedy klikalne wprost w kafelku, więc nic nie ginie.
 *  - NIE MIEŚCI SIĘ → kafelek pokazuje `text` (czysty tekst, bo skracanie HTML-a
 *    rozjeżdża tagi) plus odnośnik „Czytaj więcej" po resztę.
 *
 * Stąd asymetria: zajawka jest czystym tekstem, a pełna treść nie. To nie są dwa
 * warianty tego samego — to dwa różne stany kafelka.
 */
final class Excerpt
{
    private function __construct(
        public readonly string $text,
        public readonly bool $hasMore,
    ) {}

    public static function fromHtml(?string $html, int $length): self
    {
        $plain = self::plainText($html);

        return new self(
            text: Str::limit($plain, $length, preserveWords: true),
            hasMore: mb_strlen($plain) > $length,
        );
    }

    /**
     * Treść jako czysty tekst: bez tagów, bez encji, ze zwiniętymi białymi znakami.
     * Tagi i `<br>` nie mogą zawyżać długości, bo od niej zależą progi.
     */
    public static function plainText(?string $html): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** Czy zajawka jest pusta — brak treści to brak kafelka (patrz Page::hasContent). */
    public function isEmpty(): bool
    {
        return $this->text === '';
    }
}
