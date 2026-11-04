<?php

namespace App\Services\Content;

use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class VideoTranscoder
{
    public function process(Video $video): void
    {
        $disk = Storage::disk('public');
        $source = $video->sourceAsset?->url;
        abort_unless($source && $disk->exists($source), 422, 'The source video is missing.');
        $video->update(['status' => 'processing']);
        $video->renditions()->update(['ready' => false]);

        try {
            foreach ([240, 360, 720] as $height) {
                $path = "media/renditions/{$video->id}/{$height}p.mp4";
                $disk->makeDirectory("media/renditions/{$video->id}");
                $process = new Process([
                    (string) config('services.video.ffmpeg', 'ffmpeg'),
                    '-y', '-i', $disk->path($source),
                    '-vf', "scale=-2:min({$height}\\,ih)",
                    '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '28',
                    '-c:a', 'aac', '-movflags', '+faststart', $disk->path($path),
                ]);
                $process->setTimeout(600);
                $process->mustRun();
                $video->renditions()->updateOrCreate(['quality' => "{$height}p", 'protocol' => 'mp4'], [
                    'manifest_url' => $disk->url($path), 'size_bytes' => $disk->size($path),
                    'bitrate_kbps' => null, 'ready' => true,
                ]);
            }
            $measured = app(StoryPreviewInspector::class)->durationSeconds($disk->path($source));
            $video->update([
                'status' => 'ready',
                'duration_seconds' => $measured === null ? null : (int) ceil($measured),
            ]);
        } catch (\Throwable $error) {
            $video->update(['status' => 'failed']);
            throw $error;
        }
    }
}
