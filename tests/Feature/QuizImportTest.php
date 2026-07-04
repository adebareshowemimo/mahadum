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
}
