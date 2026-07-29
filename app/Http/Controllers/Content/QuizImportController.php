<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ParseQuizImportRequest;
use App\Services\Content\QuizImportParser;
use App\Services\Content\SpreadsheetReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QuizImportController extends Controller
{
    /**
     * Parse an uploaded CSV/XLSX/DOCX into structured quiz questions for review in
     * the builder. Read-only — nothing is persisted; the author saves the reviewed
     * questions through the normal component-create endpoint.
     */
    public function parse(ParseQuizImportRequest $request, SpreadsheetReader $reader, QuizImportParser $parser): JsonResponse
    {
        $file = $request->file('file');

        try {
            $rows = $reader->rows($file->getRealPath(), $file->getClientOriginalExtension());
        } catch (RuntimeException $e) {
            return response()->json(['error' => ['code' => 'unreadable_file', 'message' => $e->getMessage()]], 422);
        }

        $result = $parser->parse($rows);
        $questions = $this->dropUnknownAudio($result['questions']);

        return response()->json(['data' => [
            'questions' => $questions,
            'errors' => $result['errors'],
            'imported' => count($questions),
        ]]);
    }

    /**
     * Audio is optional on import: drop any prompt_audio_asset_id that doesn't
     * resolve to a real Media library asset (e.g. an AI placeholder like
     * "AUDIO: 1"). The question still imports, and saving won't fail the
     * "exists:media_assets,id" rule — the author attaches audio in the builder.
     *
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<int, array<string, mixed>>
     */
    private function dropUnknownAudio(array $questions): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn ($q) => $q['prompt_audio_asset_id'] ?? null,
            $questions,
        ))));

        if ($ids === []) {
            return $questions;
        }

        $known = DB::table('media_assets')->whereIn('id', $ids)->pluck('id')
            ->map(fn ($id) => (int) $id)->all();

        return array_map(function (array $question) use ($known) {
            $audioId = $question['prompt_audio_asset_id'] ?? null;
            if ($audioId !== null && ! in_array((int) $audioId, $known, true)) {
                unset($question['prompt_audio_asset_id']);
            }

            return $question;
        }, $questions);
    }
}
