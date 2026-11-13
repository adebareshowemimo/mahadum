<?php

namespace App\Services\Content;

use Symfony\Component\Process\Process;

class StoryPreviewInspector
{
    public function durationSeconds(string $path): ?float
    {
        $process = new Process([
            (string) config('services.video.ffprobe', 'ffprobe'),
            '-v', 'error', '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1', $path,
        ]);
        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        $duration = trim($process->getOutput());

        return $process->isSuccessful() && is_numeric($duration) ? (float) $duration : null;
    }
}
