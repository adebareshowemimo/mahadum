<?php

namespace App\Services\Content;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Dependency-free tabular reader for CSV, XLSX and DOCX uploads. Returns a plain
 * grid of trimmed string cells (first row = header). XLSX is parsed straight from
 * its zip (sharedStrings + first worksheet); DOCX reads the first table out of
 * word/document.xml — so no spreadsheet or Word library is required.
 *
 * @phpstan-type Grid array<int, array<int, string>>
 */
class SpreadsheetReader
{
    /** WordprocessingML namespace (docx table/row/cell/paragraph/text elements). */
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Synthetic header for prose-parsed docx rows (matches the CSV/XLSX columns). */
    private const PROSE_COLUMNS = ['type', 'prompt', 'options', 'correct', 'explanation', 'points', 'prompt_audio_asset_id'];

    /** @return Grid */
    public function rows(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx' => $this->readXlsx($path),
            'docx' => $this->readDocx($path),
            default => throw new RuntimeException('Unsupported file type — upload a CSV, Excel (.xlsx) or Word (.docx) file.'),
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
     * Read the FIRST table in a .docx into a grid (header row + one question per
     * row), so the same columns as the CSV/XLSX template work in Word. Parses
     * word/document.xml directly from the zip — no Word library required.
     *
     * @return Grid
     */
    private function readDocx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('This server can’t read .docx files — please upload a CSV instead.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not open the Word file.');
        }
        $document = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($document === false) {
            throw new RuntimeException('The Word file has no readable document.');
        }

        $xml = @simplexml_load_string($document);
        if ($xml === false) {
            throw new RuntimeException('The Word document couldn’t be parsed.');
        }
        $xml->registerXPathNamespace('w', self::WORD_NS);

        // Preferred when present: a table whose columns match the CSV/XLSX template.
        $tables = $xml->xpath('//w:tbl');
        if (! empty($tables)) {
            return $this->docxTableRows($tables[0]);
        }

        // Otherwise: prose questions (an "Aiken"-style superset that covers every
        // question type). Each blank-line-separated block becomes one grid row,
        // so the normal per-row validation applies downstream.
        $rows = $this->parseProse($this->docxParagraphs($xml));
        if (empty($rows)) {
            throw new RuntimeException('No questions found in the Word file. Follow the Word template — one question per block with its answers and an "ANSWER:" line.');
        }
        array_unshift($rows, self::PROSE_COLUMNS);

        return $rows;
    }

    /**
     * Turn a docx table into the grid (skipping blank rows).
     *
     * @return Grid
     */
    private function docxTableRows(SimpleXMLElement $table): array
    {
        $table->registerXPathNamespace('w', self::WORD_NS);

        $rows = [];
        foreach ($table->xpath('.//w:tr') ?: [] as $tr) {
            $tr->registerXPathNamespace('w', self::WORD_NS);
            $line = [];
            foreach ($tr->xpath('./w:tc') ?: [] as $tc) {
                $line[] = trim($this->docxCellText($tc));
            }
            if ($this->isBlank($line)) {
                continue;
            }
            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * Body paragraphs in document order (blank ones kept — they separate questions).
     *
     * @return array<int, string>
     */
    private function docxParagraphs(SimpleXMLElement $xml): array
    {
        $xml->registerXPathNamespace('w', self::WORD_NS);

        $out = [];
        foreach ($xml->xpath('//w:p') ?: [] as $paragraph) {
            $paragraph->registerXPathNamespace('w', self::WORD_NS);
            $text = '';
            foreach ($paragraph->xpath('.//w:t') ?: [] as $node) {
                $text .= (string) $node;
            }
            $out[] = $text;
        }

        return $out;
    }

    /**
     * Parse prose questions into grid rows (one row per blank-line-separated
     * block). This is an "Aiken"-style superset covering every question type:
     *
     *   [TYPE: <type>]          optional; inferred for mcq/true_false/match/word_bank
     *   <question text>
     *   A. option               lettered answers (A. / A)) — or "- item" bullets
     *   B. option               for word_bank; "left = right" items for match_pairs
     *   [AUDIO: <asset id>]     optional (listen_and_respond, type_what_you_hear)
     *   [POINTS: <n>] [EXPLANATION: <text>]   optional
     *   ANSWER: <letters | True/False | text>  required except word_bank/match_pairs
     *
     * @param  array<int, string>  $paragraphs
     * @return array<int, array<int, string>>
     */
    private function parseProse(array $paragraphs): array
    {
        $rows = [];
        foreach ($this->splitBlocks($paragraphs) as $block) {
            $row = $this->proseBlock($block);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Group paragraphs into per-question blocks. A blank line ends a block; an
     * "ANSWER:" line also closes one (so back-to-back questions work); a "TYPE:"
     * line starts a fresh block even without a blank separator.
     *
     * @param  array<int, string>  $paragraphs
     * @return array<int, array<int, string>>
     */
    private function splitBlocks(array $paragraphs): array
    {
        $blocks = [];
        $current = [];

        foreach ($paragraphs as $raw) {
            $line = trim((string) preg_replace('/\s+/', ' ', $raw));
            if ($line === '') {
                if ($current !== []) {
                    $blocks[] = $current;
                    $current = [];
                }

                continue;
            }
            if ($current !== [] && preg_match('/^type\s*[:\-]/i', $line)) {
                $blocks[] = $current;
                $current = [];
            }
            $current[] = $line;
            if (preg_match('/^(?:answer|ans)\s*[:\-]/i', $line)) {
                $blocks[] = $current;
                $current = [];
            }
        }
        if ($current !== []) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    /**
     * Parse one prose block into a grid row, or null if it isn't a question.
     *
     * @param  array<int, string>  $lines
     * @return array<int, string>|null
     */
    private function proseBlock(array $lines): ?array
    {
        $type = null;
        $audio = $points = $explanation = '';
        $answerRaw = null;
        $promptParts = [];
        /** @var array<int, array{letter: ?string, text: string}> $items */
        $items = [];

        foreach ($lines as $line) {
            if (preg_match('/^type\s*[:\-]\s*(.+)$/i', $line, $m)) {
                $type = strtolower(str_replace([' ', '-'], '_', trim($m[1])));
            } elseif (preg_match('/^audio\s*[:\-]\s*(.+)$/i', $line, $m)) {
                $audio = trim($m[1]);
            } elseif (preg_match('/^points?\s*[:\-]\s*(.+)$/i', $line, $m)) {
                $points = trim($m[1]);
            } elseif (preg_match('/^(?:explanation|feedback)\s*[:\-]\s*(.+)$/i', $line, $m)) {
                $explanation = trim($m[1]);
            } elseif (preg_match('/^(?:answer|ans)\s*[:\-]\s*(.*)$/i', $line, $m)) {
                $answerRaw = trim($m[1]);
            } elseif (preg_match('/^([A-Za-z])\s*[.)\-]\s+(.+)$/', $line, $m)) {
                $items[] = ['letter' => strtoupper($m[1]), 'text' => trim($m[2])];
            } elseif (preg_match('/^[-*•]\s+(.+)$/u', $line, $m)) {
                $items[] = ['letter' => null, 'text' => trim($m[1])];
            } elseif ($items === []) {
                $promptParts[] = $line; // still building the question text
            }
        }

        $prompt = trim((string) preg_replace('/^\s*\d+[.)]\s*/', '', implode(' ', $promptParts)));
        $type = $this->resolveProseType($type, $prompt, $items, $answerRaw);
        if ($prompt === '' || $type === null) {
            return null;
        }

        return $this->buildProseRow($type, $prompt, $items, $answerRaw, $explanation, $points, $audio);
    }

    /**
     * Decide the question type — an explicit TYPE wins; otherwise infer from the
     * shape (pairs, true/false, order-only, or plain choice).
     *
     * @param  array<int, array{letter: ?string, text: string}>  $items
     */
    private function resolveProseType(?string $type, string $prompt, array $items, ?string $answerRaw): ?string
    {
        if ($type !== null && $type !== '') {
            return $type; // validated downstream by QuizImportParser
        }
        if ($items !== [] && $this->allPairs($items)) {
            return 'match_pairs';
        }
        if ($items === [] && $answerRaw !== null && preg_match('/^(?:true|false|t|f|yes|no)$/i', $answerRaw)) {
            return 'true_false';
        }
        if ($items !== [] && ($answerRaw === null || $answerRaw === '')) {
            return 'word_bank'; // order defines correctness, no ANSWER line
        }
        if ($items !== []) {
            $count = count($this->answerPositions((string) $answerRaw, $items));
            if (str_contains($prompt, '___') && $count <= 1) {
                return 'fill_blank';
            }

            return $count > 1 ? 'mcq_multi' : 'mcq_single';
        }

        return null;
    }

    /**
     * Build the grid row for a resolved type, or null if the block is incomplete.
     *
     * @param  array<int, array{letter: ?string, text: string}>  $items
     * @return array<int, string>|null
     */
    private function buildProseRow(string $type, string $prompt, array $items, ?string $answerRaw, string $explanation, string $points, string $audio): ?array
    {
        $options = '';
        $correct = '';

        switch ($type) {
            case 'true_false':
                $correct = $this->trueFalse($answerRaw);
                if ($correct === '') {
                    return null;
                }
                break;

            case 'type_what_you_hear':
                if ($answerRaw === null || $answerRaw === '') {
                    return null;
                }
                $correct = $answerRaw; // the expected text
                break;

            case 'word_bank':
                if (count($items) < 2) {
                    return null;
                }
                $options = implode('|', array_map(fn ($i) => $i['text'], $items)); // authored order
                break;

            case 'match_pairs':
                if (count($items) < 2) {
                    return null;
                }
                $options = implode('|', array_map(fn ($i) => $i['text'], $items)); // "left = right"
                break;

            default: // mcq_single, mcq_multi, fill_blank, listen_and_respond, complete_the_chat
                if ($items === []) {
                    return null;
                }
                $options = implode('|', array_map(fn ($i) => $i['text'], $items));
                $correct = implode('|', $this->answerPositions((string) $answerRaw, $items));
                break;
        }

        return [$type, $prompt, $options, $correct, $explanation, $points, $audio];
    }

    /**
     * Map each answer letter to its 1-based position among lettered options.
     *
     * @param  array<int, array{letter: ?string, text: string}>  $items
     * @return array<int, string>
     */
    private function answerPositions(string $answerRaw, array $items): array
    {
        preg_match_all('/[A-Za-z]/', strtoupper($answerRaw), $letters);
        $positions = [];
        foreach ($letters[0] as $letter) {
            foreach ($items as $i => $item) {
                if (($item['letter'] ?? null) === $letter) {
                    $positions[] = (string) ($i + 1);
                    break;
                }
            }
        }

        return $positions;
    }

    /**
     * True when every item reads as a "left = right" pair (⇒ match_pairs).
     *
     * @param  array<int, array{letter: ?string, text: string}>  $items
     */
    private function allPairs(array $items): bool
    {
        foreach ($items as $item) {
            if (! str_contains($item['text'], '=')) {
                return false;
            }
        }

        return $items !== [];
    }

    /** Normalise a true/false answer word to "True"/"False" (or '' if neither). */
    private function trueFalse(?string $answer): string
    {
        $a = strtolower(trim((string) $answer));
        if (in_array($a, ['true', 't', 'yes'], true)) {
            return 'True';
        }
        if (in_array($a, ['false', 'f', 'no'], true)) {
            return 'False';
        }

        return '';
    }

    /** Text of one table cell — runs joined within a paragraph, paragraphs by newline. */
    private function docxCellText(SimpleXMLElement $cell): string
    {
        $cell->registerXPathNamespace('w', self::WORD_NS);

        $paragraphs = [];
        foreach ($cell->xpath('./w:p') ?: [] as $paragraph) {
            $paragraph->registerXPathNamespace('w', self::WORD_NS);
            $text = '';
            foreach ($paragraph->xpath('.//w:t') ?: [] as $node) {
                $text .= (string) $node;
            }
            $paragraphs[] = $text;
        }

        return implode("\n", $paragraphs);
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
