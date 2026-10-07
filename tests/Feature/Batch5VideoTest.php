<?php

namespace Tests\Feature;

use App\Models\ComponentProgress;
use App\Models\Course;
use App\Models\VideoRendition;
use App\Models\XapiStatement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class Batch5VideoTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    private function fixture(): array
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $lesson = $this->publishedLesson();
        $component = $lesson->components->firstWhere('type', 'video');
        $component->update(['settings' => ['require_watch' => true]]);
        $component->video->update(['duration_seconds' => 60]);
        $this->postJson('/api/v1/enrollments', ['learner_id' => $learner->id, 'course_id' => Course::first()->id])->assertCreated();

        return [$lesson, $component, $learner];
    }

    public function test_seek_to_end_and_repeated_partial_watch_cannot_complete_a_required_video(): void
    {
        [$lesson, $component, $learner] = $this->fixture();
        $body = ['learner_id' => $learner->id, 'component_id' => $component->id, 'position_seconds' => 60];
        $url = "/api/v1/lessons/{$lesson->id}/progress";
        $this->postJson($url, $body + ['event' => 'seeked'])->assertOk();
        $this->postJson($url, $body + ['event' => 'completed', 'completed' => true])->assertUnprocessable();
        for ($i = 0; $i < 4; $i++) {
            $this->postJson($url, $body + ['event' => 'heartbeat', 'watched_delta' => 20, 'watched_ranges' => [[0, 20]]])->assertOk();
        }
        $this->postJson($url, $body + ['completed' => true])->assertUnprocessable();
        $this->assertSame('in_progress', ComponentProgress::where('lesson_component_id', $component->id)->sole()->status);
        $this->assertSame(0, XapiStatement::where('verb', 'http://adlnet.gov/expapi/verbs/completed')->count());
    }

    public function test_contiguous_coverage_resumes_and_completes_with_server_duration(): void
    {
        [$lesson, $component, $learner] = $this->fixture();
        $body = ['learner_id' => $learner->id, 'component_id' => $component->id];
        $url = "/api/v1/lessons/{$lesson->id}/progress";
        $this->postJson($url, $body + ['event' => 'paused', 'watched_ranges' => [[0, 30]], 'position_seconds' => 30])->assertOk();
        $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertOk()
            ->assertJsonPath('data.components.0.watched_ranges.0.1', 30);
        $this->postJson($url, $body + ['completed' => true, 'duration_seconds' => 10, 'watched_ranges' => [[30, 40]]])->assertUnprocessable();
        $this->postJson($url, $body + ['completed' => true, 'event' => 'completed', 'watched_ranges' => [[30, 60]]])->assertOk()->assertJsonPath('data.status', 'complete');
        // Continue and an already-completed revisit do not require rewatching.
        $this->postJson($url, $body + ['completed' => true])->assertOk();
    }

    public function test_skipped_middle_is_not_hidden_by_total_time_and_invalid_ranges_are_rejected(): void
    {
        [$lesson, $component, $learner] = $this->fixture();
        $body = ['learner_id' => $learner->id, 'component_id' => $component->id, 'completed' => true];
        $url = "/api/v1/lessons/{$lesson->id}/progress";
        $this->postJson($url, $body + ['watched_delta' => 100, 'watched_ranges' => [[0, 20], [40, 60]]])->assertUnprocessable();
        $this->postJson($url, $body + ['watched_ranges' => [[-1, 60]]])->assertUnprocessable();
        $this->postJson($url, $body + ['watched_ranges' => [[0, 60, 70]]])->assertUnprocessable();
    }

    public function test_play_payload_only_exposes_ready_mp4_renditions_and_vtt_captions(): void
    {
        [$lesson, $component, $learner] = $this->fixture();
        foreach ([['240p', 'mp4', true], ['360p', 'mp4', true], ['720p', 'mp4', false], ['720p', 'hls', true]] as [$quality, $protocol, $ready]) {
            VideoRendition::create(['video_id' => $component->video->id, 'quality' => $quality, 'protocol' => $protocol, 'ready' => $ready, 'manifest_url' => "/{$quality}.{$protocol}"]);
        }
        $component->video->captions()->create(['language_code' => 'yo', 'format' => 'vtt', 'url' => '/yo.vtt', 'is_default' => true]);
        $this->getJson("/api/v1/lessons/{$lesson->id}/play?learner_id={$learner->id}")->assertOk()
            ->assertJsonCount(2, 'data.components.0.video.renditions')
            ->assertJsonPath('data.components.0.video.captions.0.language', 'yo');
    }
}
