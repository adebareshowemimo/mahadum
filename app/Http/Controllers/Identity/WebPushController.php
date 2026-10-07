<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Models\WebPushSubscription;
use App\Services\Messaging\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebPushController extends Controller
{
    public function show(Request $request, WebPushService $service): JsonResponse
    {
        $enabled = $service->configured() && $service->eligible($request->user());

        return response()->json(['data' => ['enabled' => $enabled,
            'public_key' => $enabled ? config('services.messaging.web_push.public_key') : null]]);
    }

    public function store(Request $request, WebPushService $service): JsonResponse
    {
        abort_unless($service->configured(), 409, 'Browser notifications are not configured yet.');
        abort_unless($service->eligible($request->user()), 403);
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{87}=?$/'],
            'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{22}={0,2}$/']]);
        abort_unless($service->validEndpoint($data['endpoint']), 422, 'Unsupported browser push service.');
        $decoded = base64_decode(strtr($data['keys']['p256dh'], '-_', '+/'), true);
        abort_unless($decoded !== false && strlen($decoded) === 65 && ord($decoded[0]) === 4, 422, 'Invalid browser public key.');
        DB::transaction(function () use ($request, $data) {
            $hash = hash('sha256', $data['endpoint']);
            // The endpoint is a bearer capability: never silently transfer it to another account.
            $values = [
                'user_id' => $request->user()->id, 'personal_access_token_id' => $request->user()->currentAccessToken()->exists ? $request->user()->currentAccessToken()->id : null,
                'endpoint' => $data['endpoint'], 'public_key' => $data['keys']['p256dh'], 'auth_key' => $data['keys']['auth'],
            ];
            // firstOrCreate resolves a concurrent unique insert before ownership is checked.
            $created = WebPushSubscription::firstOrCreate(['endpoint_hash' => $hash], $values);
            $subscription = WebPushSubscription::whereKey($created->id)->lockForUpdate()->firstOrFail();
            abort_if((int) $subscription->user_id !== (int) $request->user()->id, 409, 'Disable browser notifications for the previous account first.');
            $subscription->update($values);
        }, 3);

        return response()->json(['data' => ['status' => 'subscribed']], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        WebPushSubscription::where('user_id', $request->user()->id)->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        return response()->json(null, 204);
    }
}
