<?php

namespace Tests\Feature;

use App\Models\ComponentProgress;
use App\Models\Course;
use App\Models\XapiStatement;
use App\Services\Learning\XapiRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class VideoTrackingTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_video_events_accumulate_metrics_and_emit_video_profile_statements(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $courseId = Course::first()->id;
        $video = $lesson->components->firstWhere('type', 'video');

        $this->postJson('/api/v1/enrollments', ['learner_id' => $learner->id, 'course_id' => $courseId])->assertCreated();

        $post = fn (array $body) => $this->postJson("/api/v1/lessons/{$lesson->id}/progress", array_merge([
            'learner_id' => $learner->id,
            'component_id' => $video->id,
        ], $body))->assertOk();

        // A full watch: start, two heartbeats, a seek, then completion.
        $post(['event' => 'played', 'play_delta' => 1, 'position_seconds' => 0, 'duration_seconds' => 60]);
        $post(['event' => 'heartbeat', 'watched_delta' => 10, 'position_seconds' => 10]);
        $post(['event' => 'seeked', 'position_seconds' => 30]);
        $post(['event' => 'heartbeat', 'watched_delta' => 15, 'position_seconds' => 45]);
        $post(['event' => 'completed', 'watched_delta' => 15, 'position_seconds' => 60, 'completed' => true]);

        // ---- Aggregates persisted on component_progress.data ----
        $cp = ComponentProgress::where('lesson_component_id', $video->id)->firstOrFail();
        $this->assertSame('complete', $cp->status);
        $this->assertSame(40, $cp->data['watched_seconds']); // 10 + 15 + 15
        $this->assertSame(1, $cp->data['play_count']);
        $this->assertEquals(60, $cp->data['position_seconds']);
        $this->assertEquals(60, $cp->data['duration_seconds']);
        $this->assertSame('completed', $cp->data['last_event']);

        // ---- Statements: discrete events only (heartbeats are silent) ----
        $videoIri = app(XapiRecorder::class)->iri('components', $video->id);
        $statements = XapiStatement::where('object_iri', $videoIri)->get();
        $this->assertCount(3, $statements, 'played + seeked + completed, no heartbeat statements');

        foreach ([XapiRecorder::VERB_PLAYED, XapiRecorder::VERB_SEEKED, XapiRecorder::VERB_COMPLETED] as $verb) {
            $this->assertDatabaseHas('xapi_statements', ['learner_profile_id' => $learner->id, 'verb' => $verb]);
        }

        // ---- The completed statement carries Video Profile result extensions ----
        $completed = $statements->firstWhere('verb', XapiRecorder::VERB_COMPLETED);
        $ext = $completed->raw['result']['extensions'];
        $this->assertEquals(60, $ext[XapiRecorder::EXT_TIME]);
        $this->assertEquals(60, $ext[XapiRecorder::EXT_LENGTH]);
        $this->assertEquals(0.667, $ext[XapiRecorder::EXT_PROGRESS]); // 40/60
        $this->assertEquals(1, $ext[XapiRecorder::EXT_PLAY_COUNT]);
        $this->assertSame('PT40S', $completed->raw['result']['duration']);
        $this->assertTrue($completed->raw['result']['completion']);
    }
}
