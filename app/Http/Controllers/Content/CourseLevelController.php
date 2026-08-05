<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ReorderLevelsRequest;
use App\Http\Requests\Content\StoreCourseLevelRequest;
use App\Http\Requests\Content\UpdateCourseLevelRequest;
use App\Http\Resources\CourseLevelResource;
use App\Models\Course;
use App\Models\CourseLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

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

    public function update(UpdateCourseLevelRequest $request, CourseLevel $level): CourseLevelResource
    {
        $level->update($request->validated());

        return new CourseLevelResource($level);
    }

    public function destroy(CourseLevel $level): JsonResponse
    {
        $level->delete();

        return response()->json(null, 204);
    }

    /**
     * Persist a new level order for the course. `order` is the full list of
     * level ids in their desired sequence; positions are reassigned 1..n.
     */
    public function reorder(ReorderLevelsRequest $request, Course $course): AnonymousResourceCollection
    {
        DB::transaction(function () use ($request, $course) {
            foreach ($request->input('order') as $index => $levelId) {
                $course->levels()->whereKey($levelId)->update(['position' => $index + 1]);
            }
        });

        return CourseLevelResource::collection($course->levels()->orderBy('position')->get());
    }
}
