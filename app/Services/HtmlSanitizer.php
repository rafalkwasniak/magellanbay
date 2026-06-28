<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Sanityzacja HTML z edytora Trix przez WŁASNĄ, wąską whitelistę (bez dodatkowej
 * biblioteki — w duchu „lekko" na shared-hoście). Przepuszcza tylko proste
 * formatowanie produkowane przez Trix; wszystko inne (skrypty, style, obce tagi,
 * atrybuty) jest usuwane. Sanityzujemy na ZAPIS (w Form Requeście), więc render
 * `{!! $html !!}` na storefroncie jest bezpieczny.
 */
class HtmlSanitizer
{
    /** Dozwolone tagi → dozwolone atrybuty. Bez cytatu i kodu (świadomie usunięte). */
    private const ALLOWED = [
        'div' => [], 'br' => [], 'strong' => [], 'em' => [], 'del' => [],
        'h1' => [], 'h2' => [], 'ul' => [], 'ol' => [], 'li' => [],
        'a' => ['href'],
    ];

    /** Zamiana tagów na wyjściu: nagłówek z edytora ma być H2 (H1 = tytuł strony). */
    private const RENAME = ['h1' => 'h2'];

    /** Tagi usuwane WRAZ z zawartością. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'template'];

    public function clean(string $html): string
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
        $clean = $root ? $this->render($root, true) : '';

        return trim($clean);
    }

    private function render(DOMNode $node, bool $isRoot = false): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES, 'UTF-8');
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->nodeName);

        if (in_array($tag, self::DROP, true)) {
            return '';
        }

        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $this->render($child);
        }

        // Korzeń-opakowanie oraz tagi spoza whitelisty: zachowaj treść, usuń tag.
        if ($isRoot || ! array_key_exists($tag, self::ALLOWED)) {
            return $inner;
        }

        if ($tag === 'br') {
            return '<br>';
        }

        if ($tag === 'a') {
            $href = $node->getAttribute('href');

            return $this->isSafeHref($href)
                ? '<a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'" rel="nofollow noopener" target="_blank">'.$inner.'</a>'
                : $inner; // niebezpieczny/pusty link → zostaw sam tekst
        }

        $outputTag = self::RENAME[$tag] ?? $tag;

        return '<'.$outputTag.'>'.$inner.'</'.$outputTag.'>';
    }

    private function isSafeHref(string $href): bool
    {
        $scheme = strtolower((string) parse_url(trim($href), PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }
}
