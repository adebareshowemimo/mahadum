<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /**
     * Register/refresh a device for push + fraud fingerprinting.
     * Idempotent on (user_id, device_fingerprint).
     */
    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $now = now();

        $device = Device::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'device_fingerprint' => $request->string('device_id'),
            ],
            [
                'platform' => $request->string('platform'),
                'push_token' => $request->input('push_token'),
                'ip_last_seen' => $request->ip(),
                'last_seen_at' => $now,
            ]
        );

        if (! $device->first_seen_at) {
            $device->forceFill(['first_seen_at' => $now])->save();
        }

        return response()->json(['data' => ['id' => $device->id]], 201);
    }
}
