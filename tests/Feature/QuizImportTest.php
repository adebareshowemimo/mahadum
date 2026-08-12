<?php

namespace Tests\Feature;

use App\Services\Content\QuizImportParser;
use App\Services\Content\SpreadsheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class QuizImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_maps_every_question_type_and_reports_bad_rows(): void
    {
        $rows = [
            ['type', 'prompt', 'options', 'correct', 'explanation', 'points'],
            ['mcq_single', 'Capital of Nigeria?', 'Abuja|Lagos|Kano', 'Abuja', 'It is Abuja', '2'],
            ['mcq_multi', 'Pick the greetings', 'Ututu oma|Daalu|Mba', '1|2', '', ''],
            ['true_false', 'Nna means father', '', 'True', '', ''],
            ['match_pairs', 'Match', 'Mama=Mother|Nna=Father', '', '', ''],
            ['word_bank', 'Arrange', 'Ututu|oma', '', '', ''],
            ['type_what_you_hear', 'Type it', '', 'Ututu oma', '', ''],
            ['mcq_single', 'No correct match', 'A|B', 'Z', '', ''],
        ];

        $result = app(QuizImportParser::class)->parse($rows);

        $this->assertCount(6, $result['questions']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame(8, $result['errors'][0]['row']);

        [$single, $multi, $tf, $match, $word, $text] = $result['questions'];

        $this->assertSame('mcq_single', $single['type']);
        $this->assertSame(2, $single['points']);
        $this->assertSame('It is Abuja', $single['explanation']);
        $this->assertTrue($single['options'][0]['is_correct']); // "Abuja"
        $this->assertFalse($single['options'][1]['is_correct']);

        // Multi correctness resolved by 1-based index (1|2).
        $this->assertTrue($multi['options'][0]['is_correct']);
        $this->assertTrue($multi['options'][1]['is_correct']);
        $this->assertFalse($multi['options'][2]['is_correct']);

        // true_false defaults its options when the column is blank.
        $this->assertSame('True', $tf['options'][0]['label']);
        $this->assertTrue($tf['options'][0]['is_correct']);

        $this->assertSame('Mother', $match['options'][0]['match_target']);
        $this->assertArrayNotHasKey('is_correct', $word['options'][0]); // order-graded
        $this->assertSame('Ututu oma', $text['target_text']);
    }

    public function test_parser_carries_prompt_audio_asset_id_and_rejects_non_numeric(): void
    {
        $rows = [
            ['type', 'prompt', 'options', 'correct', 'prompt_audio_asset_id'],
            ['listen_and_respond', 'Choose the reply', 'Ututu oma|Ka chi fo', 'Ututu oma', '42'],
            ['listen_and_respond', 'Bad audio ref', 'Ututu oma|Ka chi fo', 'Ututu oma', 'abc'],
        ];

        $result = app(QuizImportParser::class)->parse($rows);

        $this->assertCount(1, $result['questions']);
        $this->assertSame(42, $result['questions'][0]['prompt_audio_asset_id']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame(3, $result['errors'][0]['row']);
    }

    public function test_parse_endpoint_accepts_a_csv_upload(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));

        $csv = "type,prompt,options,correct\nmcq_single,\"Capital?\",Abuja|Lagos,Abuja\n";
        $file = UploadedFile::fake()->createWithContent('questions.csv', $csv);

        $this->postJson('/api/v1/quiz-imports/parse', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.questions.0.type', 'mcq_single')
            ->assertJsonPath('data.questions.0.options.0.label', 'Abuja')
            ->assertJsonPath('data.questions.0.options.0.is_correct', true);
    }

    public function test_import_drops_audio_ids_that_are_not_real_assets(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));

        // 999999 is not a real Media asset — audio is optional on import, so it is
        // dropped rather than failing the "exists:media_assets,id" rule on save.
        $csv = "type,prompt,options,correct,prompt_audio_asset_id\n"
            ."listen_and_respond,Pick the reply,Ututu oma|Ka chi fo,Ututu oma,999999\n";
        $file = UploadedFile::fake()->createWithContent('questions.csv', $csv);

        $this->postJson('/api/v1/quiz-imports/parse', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.questions.0.type', 'listen_and_respond')
            ->assertJsonMissingPath('data.questions.0.prompt_audio_asset_id');
    }

    public function test_reader_parses_a_native_xlsx_grid(): void
    {
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $strings = ['type', 'prompt', 'options', 'correct', 'mcq_single', 'Capital?', 'Abuja|Lagos', 'Abuja'];
        $si = implode('', array_map(fn ($s) => "<si><t>$s</t></si>", $strings));

        $cell = fn (string $ref, int $i) => "<c r=\"$ref\" t=\"s\"><v>$i</v></c>";
        $sheet = "<?xml version=\"1.0\"?><worksheet xmlns=\"$ns\"><sheetData>"
            .'<row>'.$cell('A1', 0).$cell('B1', 1).$cell('C1', 2).$cell('D1', 3).'</row>'
            .'<row>'.$cell('A2', 4).$cell('B2', 5).$cell('C2', 6).$cell('D2', 7).'</row>'
            .'</sheetData></worksheet>';

        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml', "<?xml version=\"1.0\"?><sst xmlns=\"$ns\">$si</sst>");
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        $rows = app(SpreadsheetReader::class)->rows($path, 'xlsx');
        @unlink($path);

        $this->assertSame(['type', 'prompt', 'options', 'correct'], $rows[0]);
        $this->assertSame(['mcq_single', 'Capital?', 'Abuja|Lagos', 'Abuja'], $rows[1]);

        // And it flows through the parser.
        $result = app(QuizImportParser::class)->parse($rows);
        $this->assertSame('mcq_single', $result['questions'][0]['type']);
    }

    public function test_reader_parses_the_first_table_of_a_native_docx(): void
    {
        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

        $cell = fn (string $text) => "<w:tc><w:p><w:r><w:t>$text</w:t></w:r></w:p></w:tc>";
        $row = fn (array $cells) => '<w:tr>'.implode('', array_map($cell, $cells)).'</w:tr>';
        $table = '<w:tbl>'
            .$row(['type', 'prompt', 'options', 'correct'])
            .$row(['mcq_single', 'Capital?', 'Abuja|Lagos', 'Abuja'])
            .'</w:tbl>';

        $document = "<?xml version=\"1.0\"?><w:document xmlns:w=\"$ns\"><w:body>$table</w:body></w:document>";

        $path = tempnam(sys_get_temp_dir(), 'docx').'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', $document);
        $zip->close();

        $rows = app(SpreadsheetReader::class)->rows($path, 'docx');
        @unlink($path);

        $this->assertSame(['type', 'prompt', 'options', 'correct'], $rows[0]);
        $this->assertSame(['mcq_single', 'Capital?', 'Abuja|Lagos', 'Abuja'], $rows[1]);

        // And it flows through the parser.
        $result = app(QuizImportParser::class)->parse($rows);
        $this->assertSame('mcq_single', $result['questions'][0]['type']);
    }

    public function test_reader_parses_aiken_prose_from_a_docx(): void
    {
        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $lines = [
            'Quiz for the morning lesson', // a title before the questions — must be ignored
            '',
            'What is the correct answer to this question?',
            'A. Is it this one?',
            'B. Maybe this answer?',
            'C. Possibly this one?',
            'D. Must be this one!',
            'ANSWER: D',
            '',
            'Which of these are greetings?',
            'A) Ututu oma',
            'B) Random word',
            'C) Daalu',
            'ANSWER: A, C',
        ];
        $body = '';
        foreach ($lines as $line) {
            $run = $line === '' ? '' : '<w:r><w:t xml:space="preserve">'.htmlspecialchars($line, ENT_QUOTES | ENT_XML1).'</w:t></w:r>';
            $body .= "<w:p>$run</w:p>";
        }
        $document = "<?xml version=\"1.0\"?><w:document xmlns:w=\"$ns\"><w:body>$body</w:body></w:document>";

        $path = tempnam(sys_get_temp_dir(), 'docx').'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', $document);
        $zip->close();

        $rows = app(SpreadsheetReader::class)->rows($path, 'docx');
        @unlink($path);

        // Synthesized header + two question rows; the title paragraph is dropped.
        $this->assertSame(['type', 'prompt', 'options', 'correct', 'explanation', 'points', 'prompt_audio_asset_id'], $rows[0]);
        $this->assertCount(3, $rows);

        $result = app(QuizImportParser::class)->parse($rows);
        $this->assertCount(2, $result['questions']);
        $this->assertCount(0, $result['errors']);

        [$q1, $q2] = $result['questions'];

        // ANSWER: D → the 4th option is the correct one.
        $this->assertSame('mcq_single', $q1['type']);
        $this->assertSame('What is the correct answer to this question?', $q1['prompt']);
        $this->assertSame('Must be this one!', $q1['options'][3]['label']);
        $this->assertTrue($q1['options'][3]['is_correct']);
        $this->assertFalse($q1['options'][0]['is_correct']);

        // ANSWER: A, C → two correct options ⇒ mcq_multi.
        $this->assertSame('mcq_multi', $q2['type']);
        $this->assertTrue($q2['options'][0]['is_correct']);  // Ututu oma
        $this->assertFalse($q2['options'][1]['is_correct']); // Random word
        $this->assertTrue($q2['options'][2]['is_correct']);  // Daalu
    }

    public function test_reader_parses_every_question_type_from_docx_prose(): void
    {
        $lines = [
            'Quiz for the morning lesson', '', // a title — must be ignored
            'Capital of Nigeria?', 'A. Abuja', 'B. Lagos', 'C. Kano', 'ANSWER: A', '',
            'Which are greetings? (choose all)', 'A. Ututu oma', 'B. Random', 'C. Daalu', 'ANSWER: A, C', '',
            'TYPE: true_false', 'Nna means father.', 'ANSWER: True', '',
            'TYPE: fill_blank', 'Good ___ (morning)', 'A. morning', 'B. evening', 'ANSWER: A', '',
            'TYPE: complete_the_chat', 'Reply to Ututu oma', 'A. Ututu oma', 'B. Ka chi fo', 'ANSWER: A', '',
            'TYPE: listen_and_respond', 'Pick the reply', 'AUDIO: 42', 'A. Ututu oma', 'B. Ka chi fo', 'ANSWER: A', '',
            'TYPE: type_what_you_hear', 'Type what you hear', 'AUDIO: 42', 'ANSWER: Ututu oma', '',
            'Match word to meaning', 'A. Mama = Mother', 'B. Nna = Father', '',
            'TYPE: word_bank', 'Arrange the greeting', '- Ututu', '- oma',
        ];

        $rows = app(SpreadsheetReader::class)->rows($this->docxFromLines($lines), 'docx');
        $this->assertSame(['type', 'prompt', 'options', 'correct', 'explanation', 'points', 'prompt_audio_asset_id'], $rows[0]);

        $result = app(QuizImportParser::class)->parse($rows);
        $this->assertCount(0, $result['errors']);

        $types = array_column($result['questions'], 'type');
        $this->assertSame([
            'mcq_single', 'mcq_multi', 'true_false', 'fill_blank', 'complete_the_chat',
            'listen_and_respond', 'type_what_you_hear', 'match_pairs', 'word_bank',
        ], $types);

        $byType = array_combine($types, $result['questions']);

        // mcq_multi resolves both correct answers by letter.
        $this->assertTrue($byType['mcq_multi']['options'][0]['is_correct']);   // Ututu oma
        $this->assertFalse($byType['mcq_multi']['options'][1]['is_correct']);  // Random
        $this->assertTrue($byType['mcq_multi']['options'][2]['is_correct']);   // Daalu

        // true_false with no options → defaulted True/False, True marked correct.
        $this->assertSame('True', $byType['true_false']['options'][0]['label']);
        $this->assertTrue($byType['true_false']['options'][0]['is_correct']);

        // Audio id carried through for the listen/type variants.
        $this->assertSame(42, $byType['listen_and_respond']['prompt_audio_asset_id']);
        $this->assertSame('Ututu oma', $byType['type_what_you_hear']['target_text']);

        // Pairs and word-bank order.
        $this->assertSame('Mother', $byType['match_pairs']['options'][0]['match_target']);
        $this->assertSame('Ututu', $byType['word_bank']['options'][0]['label']);
        $this->assertArrayNotHasKey('is_correct', $byType['word_bank']['options'][0]);
    }

    public function test_reader_recovers_options_word_autoformatted_into_a_list(): void
    {
        // Word's "AutoFormat As You Type" turns a typed "- " or "A. " prefix into
        // a real bulleted/numbered list and strips the typed prefix from the run
        // text — so a re-saved copy of our template can lose the very markers our
        // regex-based option parsing looks for. The reader should still recover
        // the options (as a <w:numPr> list item) and letter them A, B, C… so
        // "ANSWER: A" keeps resolving.
        $lines = [
            ['text' => 'Capital of Nigeria?', 'list' => false],
            ['text' => 'Abuja', 'list' => true],   // Word stripped "A. "
            ['text' => 'Lagos', 'list' => true],   // Word stripped "B. "
            ['text' => 'Kano', 'list' => true],    // Word stripped "C. "
            ['text' => 'ANSWER: A', 'list' => false],
        ];

        $rows = app(SpreadsheetReader::class)->rows($this->docxFromEntries($lines), 'docx');
        $result = app(QuizImportParser::class)->parse($rows);

        $this->assertCount(0, $result['errors']);
        $this->assertCount(1, $result['questions']);
        $this->assertSame('mcq_single', $result['questions'][0]['type']);
        $this->assertSame('Abuja', $result['questions'][0]['options'][0]['label']);
        $this->assertTrue($result['questions'][0]['options'][0]['is_correct']);
        $this->assertFalse($result['questions'][0]['options'][1]['is_correct']);
    }

    public function test_reader_does_not_mistake_a_numbered_question_for_an_option(): void
    {
        // The question stem itself may also be a Word auto-numbered list item
        // (e.g. a numbered list of questions) — that must still become the
        // prompt, not a stray option.
        $lines = [
            ['text' => 'Capital of Nigeria?', 'list' => true], // numbered question, no options text stripped
            ['text' => 'A. Abuja', 'list' => false],
            ['text' => 'B. Lagos', 'list' => false],
            ['text' => 'ANSWER: A', 'list' => false],
        ];

        $rows = app(SpreadsheetReader::class)->rows($this->docxFromEntries($lines), 'docx');
        $result = app(QuizImportParser::class)->parse($rows);

        $this->assertCount(0, $result['errors']);
        $this->assertCount(1, $result['questions']);
        $this->assertSame('Capital of Nigeria?', $result['questions'][0]['prompt']);
        $this->assertCount(2, $result['questions'][0]['options']);
    }

    public function test_reader_rejects_a_docx_with_no_table(): void
    {
        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $document = "<?xml version=\"1.0\"?><w:document xmlns:w=\"$ns\"><w:body>"
            .'<w:p><w:r><w:t>Just a paragraph, no table.</w:t></w:r></w:p>'
            .'</w:body></w:document>';

        $path = tempnam(sys_get_temp_dir(), 'docx').'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', $document);
        $zip->close();

        $this->expectException(\RuntimeException::class);
        try {
            app(SpreadsheetReader::class)->rows($path, 'docx');
        } finally {
            @unlink($path);
        }
    }

    /**
     * Write a throwaway .docx whose body is one paragraph per line (blank lines
     * kept), and return its path.
     *
     * @param  array<int, string>  $lines
     */
    private function docxFromLines(array $lines): string
    {
        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $body = '';
        foreach ($lines as $line) {
            $run = $line === '' ? '' : '<w:r><w:t xml:space="preserve">'.htmlspecialchars($line, ENT_QUOTES | ENT_XML1).'</w:t></w:r>';
            $body .= "<w:p>$run</w:p>";
        }
        $document = "<?xml version=\"1.0\"?><w:document xmlns:w=\"$ns\"><w:body>$body</w:body></w:document>";

        $path = tempnam(sys_get_temp_dir(), 'docx').'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', $document);
        $zip->close();

        return $path;
    }

    /**
     * Like {@see docxFromLines()}, but each entry can also be marked as a
     * Word-auto-list paragraph (`<w:numPr>` in its `<w:pPr>`, no literal
     * "- "/"A. " prefix in the text — Word strips it on AutoFormat).
     *
     * @param  array<int, array{text: string, list: bool}>  $entries
     */
    private function docxFromEntries(array $entries): string
    {
        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $body = '';
        foreach ($entries as $entry) {
            $pPr = $entry['list'] ? '<w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>' : '';
            $run = $entry['text'] === '' ? '' : '<w:r><w:t xml:space="preserve">'.htmlspecialchars($entry['text'], ENT_QUOTES | ENT_XML1).'</w:t></w:r>';
            $body .= "<w:p>$pPr$run</w:p>";
        }
        $document = "<?xml version=\"1.0\"?><w:document xmlns:w=\"$ns\"><w:body>$body</w:body></w:document>";

        $path = tempnam(sys_get_temp_dir(), 'docx').'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', $document);
        $zip->close();

        return $path;
    }
}
