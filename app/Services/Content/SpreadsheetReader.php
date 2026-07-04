<?php

namespace App\Services\Content;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Dependency-free tabular reader for CSV and XLSX uploads. Returns a plain grid
 * of trimmed string cells (first row = header). XLSX is parsed straight from its
 * zip (sharedStrings + first worksheet) so no spreadsheet library is required.
 *
 * @phpstan-type Grid array<int, array<int, string>>
 */
class SpreadsheetReader
{
    /** @return Grid */
    public function rows(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx' => $this->readXlsx($path),
            default => throw new RuntimeException('Unsupported file type — upload a CSV or Excel (.xlsx) file.'),
        };
    }

    /** @return Grid */
    private function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Could not read the file.');
        }

        while (($cols = fgetcsv($handle)) !== false) {
            $line = array_map(fn ($c) => trim((string) $c), $cols);
            if ($this->isBlank($line)) {
                continue;
            }
            $rows[] = $line;
        }
        fclose($handle);

        return $rows;
    }

    /** @return Grid */
    private function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('This server can’t read .xlsx files — please upload a CSV instead.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open the Excel file.');
        }

        $shared = $this->sharedStrings($zip->getFromName('xl/sharedStrings.xml'));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheet === false) {
            throw new RuntimeException('The Excel file has no readable sheet.');
        }

        $xml = @simplexml_load_string($sheet);
        if ($xml === false || ! isset($xml->sheetData)) {
            throw new RuntimeException('The Excel sheet couldn’t be parsed.');
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            $maxIndex = -1;
            foreach ($row->c as $c) {
                $index = $this->columnIndex((string) ($c['r'] ?? ''));
                $cells[$index] = $this->cellValue($c, $shared);
                $maxIndex = max($maxIndex, $index);
            }
            if ($maxIndex < 0) {
                continue;
            }

            $line = [];
            for ($i = 0; $i <= $maxIndex; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            if ($this->isBlank($line)) {
                continue;
            }
            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * @param  string|false  $xmlString
     * @return array<int, string>
     */
    private function sharedStrings($xmlString): array
    {
        if ($xmlString === false) {
            return [];
        }
        $xml = @simplexml_load_string($xmlString);
        if ($xml === false) {
            return [];
        }

        $strings = [];
        foreach ($xml->si as $si) {
            $strings[] = $this->stringItemText($si);
        }

        return $strings;
    }

    /** @param  array<int, string>  $shared */
    private function cellValue(SimpleXMLElement $cell, array $shared): string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 's') {
            return trim($shared[(int) $cell->v] ?? '');
        }
        if ($type === 'inlineStr' && isset($cell->is)) {
            return trim($this->stringItemText($cell->is));
        }

        return trim((string) $cell->v);
    }

    /** Text of a <si>/<is> node — plain <t> or concatenated rich-text runs. */
    private function stringItemText(SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $text = '';
        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    /** "B12" → 1 (zero-based column index). */
    private function columnIndex(string $ref): int
    {
        preg_match('/^([A-Z]+)/', strtoupper($ref), $m);
        $letters = $m[1] ?? 'A';
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    /** @param  array<int, string>  $line */
    private function isBlank(array $line): bool
    {
        return count(array_filter($line, fn ($v) => $v !== '')) === 0;
    }
}
