<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use Illuminate\Http\Request;

class LibrarySetController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['required', 'in:proverb,trivia'],
            'language_id' => ['required', 'integer', 'exists:languages,id'],
            'standalone_level' => ['required', 'integer', 'between:1,10'],
            'summary' => ['nullable', 'string', 'max:1000'],
        ]);

        $lesson = Lesson::create($data + [
            'course_level_id' => null,
            'position' => 1,
            'est_minutes' => 5,
            'is_locked_by_default' => false,
        ]);

        return (new LessonResource($lesson))->response()->setStatusCode(201);
    }
}
