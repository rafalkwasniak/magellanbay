<?php

namespace Tests\Unit;

use App\Support\Prose;
use PHPUnit\Framework\TestCase;

/**
 * Normalizacja treści z edytora (Trix) na wyjściu: <div>/<br> → czyste akapity,
 * spójny układ niezależnie od tego, jak ktoś naklikał w edytorze.
 */
class ProseTest extends TestCase
{
    public function test_trix_divs_and_double_breaks_become_clean_paragraphs(): void
    {
        // Realny kształt z edytora: akapit zamknięty <br><br>, nagłówek, a po nim
        // <div> zaczynający się od <br> i z podwójnym <br> w środku.
        $html = '<div>Pierwszy akapit.<br><br></div>'
            .'<h2>Nasz nagłówek</h2>'
            .'<div><br>Drugi akapit.<br><br>Trzeci akapit.</div>';

        $this->assertSame(
            "<p>Pierwszy akapit.</p>\n"
            ."<h2>Nasz nagłówek</h2>\n"
            ."<p>Drugi akapit.</p>\n"
            .'<p>Trzeci akapit.</p>',
            Prose::render($html),
        );
    }

    public function test_single_break_inside_paragraph_is_kept(): void
    {
        // Pojedynczy <br> to miękkie łamanie linii — zostaje w akapicie.
        $this->assertSame(
            '<p>Linia jeden<br>Linia dwa</p>',
            Prose::render('<div>Linia jeden<br>Linia dwa</div>'),
        );
    }

    public function test_empty_lines_are_dropped(): void
    {
        // Puste linie (sam <br> lub &nbsp;) nie tworzą pustych akapitów.
        $this->assertSame(
            "<p>A</p>\n<p>B</p>",
            Prose::render('<div>A</div><div><br></div><div>&nbsp;</div><div>B</div>'),
        );
    }

    public function test_clean_semantic_html_passes_through(): void
    {
        // Nasz czysty szablon (Polityka) — idempotentny, bez szkody.
        $html = '<p>Wstęp.</p><h2>Sekcja</h2><p>Treść sekcji.</p>';

        $this->assertSame(
            "<p>Wstęp.</p>\n<h2>Sekcja</h2>\n<p>Treść sekcji.</p>",
            Prose::render($html),
        );
    }

    public function test_lists_and_inline_formatting_are_preserved(): void
    {
        $html = '<div>Tekst z <strong>pogrubieniem</strong>.</div><ul><li>Raz</li><li>Dwa</li></ul>';

        $this->assertSame(
            "<p>Tekst z <strong>pogrubieniem</strong>.</p>\n<ul><li>Raz</li><li>Dwa</li></ul>",
            Prose::render($html),
        );
    }

    public function test_heading_one_becomes_h2(): void
    {
        // H1 zarezerwowane dla tytułu strony — nagłówek treści schodzi do H2.
        $this->assertSame('<h2>Tytuł</h2>', Prose::render('<h1>Tytuł</h1>'));
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', Prose::render(''));
        $this->assertSame('', Prose::render('   '));
        $this->assertSame('', Prose::render('<div><br></div>'));
    }
}
