<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreCourseLevelRequest;
use App\Http\Resources\CourseLevelResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;

class CourseLevelController extends Controller
{
    public function store(StoreCourseLevelRequest $request, Course $course): JsonResponse
    {
        $position = $request->input('position')
            ?? (($course->levels()->max('position') ?? 0) + 1);

        $level = $course->levels()->create([
            'title' => $request->string('title'),
            'position' => $position,
            'has_assessment' => $request->boolean('has_assessment'),
        ]);

        return (new CourseLevelResource($level))->response()->setStatusCode(201);
    }
}
