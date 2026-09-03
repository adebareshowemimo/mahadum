<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\UpdateLearnerAvatarRequest;
use App\Http\Resources\LearnerProfileResource;
use App\Models\LearnerProfile;
use App\Models\MediaAsset;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class LearnerAvatarController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function update(UpdateLearnerAvatarRequest $request, LearnerProfile $learner): JsonResponse
    {
        $oldPhoto = $learner->profilePhoto;
        $before = [
            'avatar_id' => $learner->avatar_id,
            'profile_photo_asset_id' => $learner->profile_photo_asset_id,
        ];

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $path = $file->store('profile-photos', 'public');
            $asset = MediaAsset::create([
                'type' => 'image',
                'url' => $path,
                'original_name' => $file->getClientOriginalName(),
                'uploaded_by' => $request->user()->id,
            ]);
            $learner->update(['avatar_id' => null, 'profile_photo_asset_id' => $asset->id]);
        } else {
            $learner->update([
                'avatar_id' => $request->integer('avatar_id'),
                'profile_photo_asset_id' => null,
            ]);
        }

        if ($oldPhoto && (int) $oldPhoto->id !== (int) $learner->profile_photo_asset_id) {
            if (! str_starts_with((string) $oldPhoto->url, 'http')) {
                Storage::disk('public')->delete($oldPhoto->url);
            }
            $oldPhoto->delete();
        }

        $this->audit->record('learner.avatar_updated', $learner, $before, [
            'avatar_id' => $learner->avatar_id,
            'profile_photo_asset_id' => $learner->profile_photo_asset_id,
        ]);

        return (new LearnerProfileResource($learner->fresh(['targetLanguage', 'profilePhoto'])))->response();
    }
}
