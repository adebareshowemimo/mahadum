<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ParseQuizImportRequest;
use App\Services\Content\QuizImportParser;
use App\Services\Content\SpreadsheetReader;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class QuizImportController extends Controller
{
    /**
     * Parse an uploaded CSV/XLSX into structured quiz questions for review in the
     * builder. Read-only — nothing is persisted; the author saves the reviewed
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

        return response()->json(['data' => [
            'questions' => $result['questions'],
            'errors' => $result['errors'],
            'imported' => count($result['questions']),
        ]]);
    }
}
