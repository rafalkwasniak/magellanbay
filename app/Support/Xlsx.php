<?php

namespace App\Support;

use ZipArchive;

/**
 * Minimalny zapis arkusza .xlsx — bez bibliotek zewnętrznych.
 *
 * ---------------------------------------------------------------------------
 * DLACZEGO WŁASNY, A NIE PhpSpreadsheet
 *
 * Ten projekt ma DOKŁADNIE trzy zależności produkcyjne: framework, tinker
 * i Livewire. Każda kolejna to coś, co trzeba aktualizować, łatać i tłumaczyć
 * klientowi, który dostaje kod i nie wolno mu go zmieniać. PhpSpreadsheet
 * potrafi wszystko — my potrzebujemy tabeli z nagłówkiem i liczbami.
 *
 * DLACZEGO NIE CSV. Bo trafia do polskiego Excela, a tam CSV jest loterią:
 * separator zależy od ustawień regionalnych, a UTF-8 bez BOM-u zamienia „ł"
 * w krzaki. Rozliczenie z partnerem, w którym rozsypią się kwoty, jest gorsze
 * niż brak rozliczenia.
 *
 * ---------------------------------------------------------------------------
 * CO TO POTRAFI
 *
 * Kilka arkuszy, nagłówek pogrubiony, teksty, liczby z dwoma miejscami i daty.
 * Nic więcej — bo nic więcej nie jest tu potrzebne, a każda dodana możliwość
 * to kod, który trzeba utrzymać.
 *
 * Teksty zapisujemy jako `inlineStr`, nie przez tablicę wspólnych ciągów:
 * plik jest odrobinę większy, ale generowanie mieści się w jednym przebiegu
 * i nie trzeba trzymać w pamięci słownika całego raportu.
 */
final class Xlsx
{
    /** @var list<array{name: string, rows: list<list<mixed>>}> */
    private array $sheets = [];

    /**
     * @param  list<list<mixed>>  $rows  pierwszy wiersz jest nagłówkiem
     */
    public function sheet(string $name, array $rows): self
    {
        $this->sheets[] = ['name' => $this->safeName($name), 'rows' => $rows];

        return $this;
    }

    /**
     * Składa plik i zwraca jego zawartość.
     *
     * Idzie przez plik tymczasowy, bo `ZipArchive` nie umie inaczej — i tak
     * musi zamknąć archiwum, zanim da się je odczytać.
     */
    public function contents(): string
    {
        if ($this->sheets === []) {
            $this->sheet('Arkusz', []);
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml', $this->worksheet($sheet['rows']));
        }

        $zip->close();

        $binary = (string) file_get_contents($path);
        @unlink($path);

        return $binary;
    }

    /**
     * Nazwa arkusza wg reguł Excela: bez `\ / ? * [ ]`, do 31 znaków.
     * Przekroczenie któregokolwiek z tych warunków sprawia, że Excel odmawia
     * otwarcia CAŁEGO pliku — bez wskazania, co było nie tak.
     */
    private function safeName(string $name): string
    {
        $clean = str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', trim($name));

        return mb_substr($clean, 0, 31) ?: 'Arkusz';
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function worksheet(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $r => $row) {
            $xml .= '<row r="'.($r + 1).'">';

            foreach (array_values($row) as $c => $value) {
                $ref = $this->column($c).($r + 1);
                $xml .= $this->cell($ref, $value, $r === 0);
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function cell(string $ref, mixed $value, bool $header): string
    {
        // Nagłówek zawsze jako tekst, nawet gdy brzmi jak liczba („2026").
        if (! $header && (is_int($value) || is_float($value))) {
            $style = is_float($value) ? ' s="2"' : '';

            return '<c r="'.$ref.'"'.$style.'><v>'.$this->number($value).'</v></c>';
        }

        $text = (string) ($value ?? '');

        if ($text === '') {
            return '<c r="'.$ref.'"/>';
        }

        return '<c r="'.$ref.'" t="inlineStr"'.($header ? ' s="1"' : '')
            .'><is><t xml:space="preserve">'.$this->escape($text).'</t></is></c>';
    }

    private function number(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        // Cztery miejsca wystarczą na kwoty i na ilości ułamkowe (waga),
        // a obcięcie zer trzyma plik czytelnym przy podglądzie w edytorze.
        $text = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');

        return $text === '' || $text === '-' ? '0' : $text;
    }

    /**
     * Excel odrzuca plik ze znakiem sterującym w treści — a te wpadają do
     * danych same, przez wklejenie z Worda albo z bazy po starym imporcie.
     */
    private function escape(string $text): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? $text;

        return htmlspecialchars($clean, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function column(int $index): string
    {
        $name = '';

        do {
            $name = chr(65 + $index % 26).$name;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $name;
    }

    private function contentTypes(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

        foreach (array_keys($this->sheets) as $i) {
            $xml .= '<Override PartName="/xl/worksheets/sheet'.($i + 1).'.xml"'
                .' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return $xml.'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';

        foreach ($this->sheets as $i => $sheet) {
            $xml .= '<sheet name="'.$this->escape($sheet['name']).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
        }

        return $xml.'</sheets></workbook>';
    }

    private function workbookRels(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach (array_keys($this->sheets) as $i) {
            $xml .= '<Relationship Id="rId'.($i + 1).'"'
                .' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                .' Target="worksheets/sheet'.($i + 1).'.xml"/>';
        }

        // Style dostają identyfikator PO arkuszach — inaczej kolidują z nimi
        // i Excel zgłasza uszkodzony plik.
        $xml .= '<Relationship Id="rId'.(count($this->sheets) + 1).'"'
            .' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
            .' Target="styles.xml"/>';

        return $xml.'</Relationships>';
    }

    /**
     * Trzy style: 0 zwykły, 1 pogrubiony (nagłówek), 2 liczba z dwoma
     * miejscami po przecinku (kwoty).
     */
    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
