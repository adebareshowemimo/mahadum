<?php

namespace App\Services\Learning;

use App\Models\XapiStatement;

/**
 * Builds and persists xAPI statements for learning events. Statements are stored
 * in `xapi_statements` (with `lrs_synced_at` left null); a future job can push
 * unsynced rows to an external LRS — emission here is intentionally LRS-agnostic.
 */
class XapiRecorder
{
    public const VERB_REGISTERED = 'http://adlnet.gov/expapi/verbs/registered';

    public const VERB_EXPERIENCED = 'http://adlnet.gov/expapi/verbs/experienced';

    public const VERB_COMPLETED = 'http://adlnet.gov/expapi/verbs/completed';

    public const VERB_ANSWERED = 'http://adlnet.gov/expapi/verbs/answered';

    public const VERB_RESPONDED = 'http://adlnet.gov/expapi/verbs/responded';

    // xAPI Video Profile (https://w3id.org/xapi/video) verbs + result extensions.
    public const VERB_PLAYED = 'https://w3id.org/xapi/video/verbs/played';

    public const VERB_PAUSED = 'https://w3id.org/xapi/video/verbs/paused';

    public const VERB_SEEKED = 'https://w3id.org/xapi/video/verbs/seeked';

    public const EXT_TIME = 'https://w3id.org/xapi/video/extensions/time';

    public const EXT_LENGTH = 'https://w3id.org/xapi/video/extensions/length';

    public const EXT_PROGRESS = 'https://w3id.org/xapi/video/extensions/progress';

    public const EXT_PLAY_COUNT = 'https://w3id.org/xapi/video/extensions/play-count';

    public const ACTIVITY_COURSE = 'http://adlnet.gov/expapi/activities/course';

    public const ACTIVITY_LESSON = 'http://adlnet.gov/expapi/activities/lesson';

    public const ACTIVITY_ASSESSMENT = 'http://adlnet.gov/expapi/activities/assessment';

    public const ACTIVITY_INTERACTION = 'http://adlnet.gov/expapi/activities/cmi.interaction';

    public const ACTIVITY_MEDIA = 'http://adlnet.gov/expapi/activities/media';

    private const DISPLAY = [
        self::VERB_REGISTERED => 'registered',
        self::VERB_EXPERIENCED => 'experienced',
        self::VERB_COMPLETED => 'completed',
        self::VERB_ANSWERED => 'answered',
        self::VERB_RESPONDED => 'responded',
        self::VERB_PLAYED => 'played',
        self::VERB_PAUSED => 'paused',
        self::VERB_SEEKED => 'seeked',
    ];

    /**
     * @param  array<string, mixed>  $result  optional xAPI result (success, score, completion, …)
     */
    public function record(
        int $learnerId,
        string $verb,
        string $objectType,
        int|string $objectId,
        ?string $objectName = null,
        ?string $activityType = null,
        array $result = [],
    ): XapiStatement {
        $objectIri = $this->iri($objectType, $objectId);

        $object = ['objectType' => 'Activity', 'id' => $objectIri];
        $definition = [];
        if ($objectName !== null) {
            $definition['name'] = ['en-US' => $objectName];
        }
        if ($activityType !== null) {
            $definition['type'] = $activityType;
        }
        if ($definition !== []) {
            $object['definition'] = $definition;
        }

        $statement = [
            'actor' => [
                'objectType' => 'Agent',
                'account' => ['homePage' => (string) config('xapi.actor_homepage'), 'name' => 'learner:'.$learnerId],
            ],
            'verb' => ['id' => $verb, 'display' => ['en-US' => self::DISPLAY[$verb] ?? $verb]],
            'object' => $object,
            'timestamp' => now()->toIso8601String(),
        ];
        if ($result !== []) {
            $statement['result'] = $result;
        }

        return XapiStatement::create([
            'learner_profile_id' => $learnerId,
            'verb' => $verb,
            'object_iri' => $objectIri,
            'raw' => $statement,
        ]);
    }

    public function iri(string $type, int|string $id): string
    {
        return rtrim((string) config('xapi.base_iri'), '/')."/{$type}/{$id}";
    }
}
