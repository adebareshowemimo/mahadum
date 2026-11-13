<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Services\Content\VideoTranscoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VideoMediaController extends Controller
{
    public function caption(Request $request, Video $video): JsonResponse
    {
        abort_unless($video->lesson_component_id !== null, 404);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:1024', 'extensions:vtt'],
            'language_code' => ['required', 'regex:/^[a-z]{2,3}(-[A-Za-z]{2,8})?$/'],
        ]);
        $contents = file_get_contents($data['file']->getRealPath());
        abort_unless($contents !== false && str_starts_with(ltrim($contents, "\xEF\xBB\xBF\r\n\t "), 'WEBVTT'), 422,
            'Upload a valid WebVTT caption file.');

        $path = $data['file']->store("media/captions/{$video->id}", 'public');
        foreach ($video->captions()->where('language_code', $data['language_code'])->get() as $oldCaption) {
            $prefix = Storage::disk('public')->url('');
            if (str_starts_with($oldCaption->url, $prefix)) {
                Storage::disk('public')->delete(ltrim(substr($oldCaption->url, strlen($prefix)), '/'));
            }
            $oldCaption->delete();
        }
        $caption = $video->captions()->create([
            'language_code' => $data['language_code'], 'format' => 'vtt',
            'url' => Storage::disk('public')->url($path),
            'is_default' => ! $video->captions()->exists(),
        ]);

        return response()->json(['data' => [
            'id' => $caption->id, 'language_code' => $caption->language_code, 'url' => $caption->url,
        ]], 201);
    }

    public function transcode(Video $video, VideoTranscoder $transcoder): JsonResponse
    {
        abort_unless($video->lesson_component_id !== null, 404);
        abort_unless($video->source_type === 'upload' && $video->sourceAsset, 422,
            'Attach an uploaded video before processing it.');
        $transcoder->process($video);

        return response()->json(['data' => [
            'status' => $video->fresh()->status,
            'renditions' => $video->renditions()->where('ready', true)->pluck('quality'),
        ]]);
    }
}
