<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Normalizacja treści z edytora (Trix) DO WYŚWIETLENIA na storefroncie.
 *
 * Trix zapisuje „linie" jako <div>, a odstępy dosłownymi <br> (często podwójnymi).
 * Renderowane wprost w .st-prose te <br> nakładają się na marginesy nagłówków i
 * akapitów, dając nierówne, za duże przerwy — zależnie od tego, jak ktoś naklikał
 * w edytorze. Nasz własny, czysty szablon (np. Polityka: <p>/<h2>) układa się
 * równo, bo nie ma w nim błąkających się <br>.
 *
 * Robimy to NA WYJŚCIU, nie na zapisie: baza trzyma natywny format Trixa (edytor
 * wczytuje go z powrotem bez dryfu), a spójny wygląd składamy tuż przed pokazaniem.
 * Dzięki temu reguły zmienia się w jednym miejscu i działają WSTECZ na wszystkie
 * strony — też dodane dawno temu. Idempotentne: czysty HTML przechodzi bez szkody.
 *
 * Zamiana: <div>/<p> „linie" oraz podwójne <br> → osobne <p>; pojedynczy <br> w
 * akapicie (miękkie łamanie) zostaje; nagłówki i listy stoją jako własne bloki;
 * puste akapity i wiodące/końcowe <br> znikają. Wejście jest już zsanityzowane
 * (HtmlSanitizer na zapisie), więc tutaj zajmujemy się WYŁĄCZNIE układem.
 */
class Prose
{
    public static function render(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        $root = $dom->documentElement;
        if ($root === null) {
            return '';
        }

        $blocks = [];
        $buffer = '';

        // Domknij bieżący akapit: podwójny (lub większy) <br> = granica akapitu;
        // wiodące/końcowe <br> i akapity „puste" (same spacje/nbsp/<br>) znikają.
        $flush = static function () use (&$blocks, &$buffer): void {
            $parts = preg_split('/(?:<br\s*\/?>\s*){2,}/i', $buffer) ?: [];
            foreach ($parts as $part) {
                $part = trim(preg_replace('/^(?:\s*<br\s*\/?>)+|(?:<br\s*\/?>\s*)+$/i', '', $part) ?? '');
                $visible = preg_replace('/(?:&nbsp;|\x{00A0}|\s|<br\s*\/?>)+/iu', '', $part) ?? '';
                if ($visible !== '') {
                    $blocks[] = '<p>'.$part.'</p>';
                }
            }
            $buffer = '';
        };

        $walk = static function (DOMNode $node) use (&$walk, &$flush, &$buffer, &$blocks, $dom): void {
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMText) {
                    $buffer .= htmlspecialchars($child->nodeValue ?? '', ENT_QUOTES, 'UTF-8');

                    continue;
                }

                if (! $child instanceof DOMElement) {
                    continue;
                }

                $tag = strtolower($child->nodeName);

                if (preg_match('/^h[1-6]$/', $tag) === 1) {
                    $flush();
                    $inner = trim(preg_replace(
                        '/^(?:\s*<br\s*\/?>)+|(?:<br\s*\/?>\s*)+$/i',
                        '',
                        Prose::inner($child, $dom),
                    ) ?? '');
                    if ($inner !== '') {
                        $level = $tag === 'h1' ? 'h2' : $tag;   // H1 = tytuł strony; treść od H2
                        $blocks[] = '<'.$level.'>'.$inner.'</'.$level.'>';
                    }

                    continue;
                }

                if ($tag === 'ul' || $tag === 'ol') {
                    $flush();
                    $blocks[] = trim($dom->saveHTML($child) ?: '');

                    continue;
                }

                if ($tag === 'div' || $tag === 'p') {
                    // „Linia"/akapit z edytora — domyka poprzedni i otwiera własny blok.
                    $flush();
                    $walk($child);
                    $flush();

                    continue;
                }

                if ($tag === 'br') {
                    $buffer .= '<br>';

                    continue;
                }

                // Inline (strong/em/del/a/…) — zachowaj jak jest (już zsanityzowane).
                $buffer .= $dom->saveHTML($child) ?: '';
            }
        };

        $walk($root);
        $flush();

        return implode("\n", $blocks);
    }

    /** Wewnętrzny HTML elementu (bez jego własnego taga). */
    private static function inner(DOMElement $el, DOMDocument $dom): string
    {
        $html = '';
        foreach ($el->childNodes as $child) {
            $html .= $dom->saveHTML($child) ?: '';
        }

        return $html;
    }
}
