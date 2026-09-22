<?php

namespace App\Services\Content;

use App\Models\MediaAsset;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class MediaStorageSynchronizer
{
    /** @var array<string, string> */
    private const TYPES = [
        'mp4' => 'video',
        'm4v' => 'video',
        'webm' => 'video',
        'ogv' => 'video',
        'mov' => 'video',
        'mp3' => 'audio',
        'm4a' => 'audio',
        'aac' => 'audio',
        'wav' => 'audio',
        'ogg' => 'audio',
        'oga' => 'audio',
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'webp' => 'image',
    ];

    /**
     * Import files already present on the public media disk.
     *
     * The full storage path is the identity. This intentionally allows the same
     * filename to exist once in Yoruba, Igbo, Hausa, or any other folder.
     *
     * @return array{created: int, updated: int, unchanged: int, unsupported: int}
     */
    public function sync(?FilesystemAdapter $disk = null, bool $persist = true, array $folders = []): array
    {
        $disk ??= Storage::disk('public');
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'unsupported' => 0];
        $roots = collect($folders)
            ->map(fn (string $folder) => trim(str_replace('\\', '/', $folder), '/'))
            ->filter()
            ->map(fn (string $folder) => "media/{$folder}")
            ->values();
        $files = $roots->isEmpty()
            ? collect($disk->allFiles('media'))
            : $roots->flatMap(fn (string $root) => $disk->allFiles($root));

        foreach ($files->unique()->sort()->values() as $storedPath) {
            $path = str_replace('\\', '/', $storedPath);
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $type = self::TYPES[$extension] ?? null;
            if ($type === null) {
                $result['unsupported']++;

                continue;
            }

            $relative = str_starts_with($path, 'media/') ? substr($path, 6) : $path;
            $directory = dirname($relative);
            $folder = $directory === '.' ? null : trim(str_replace('\\', '/', $directory), '/');
            $originalName = basename($path);

            $asset = MediaAsset::firstOrNew(['url' => $path]);
            $created = ! $asset->exists;
            $asset->fill([
                'type' => $type,
                'original_name' => $asset->original_name ?: $originalName,
                'folder' => $folder,
                'title' => $asset->title ?: pathinfo($originalName, PATHINFO_FILENAME),
            ]);

            if ($created) {
                if ($persist) {
                    $asset->save();
                }
                $result['created']++;
            } elseif ($asset->isDirty()) {
                if ($persist) {
                    $asset->save();
                }
                $result['updated']++;
            } else {
                $result['unchanged']++;
            }
        }

        return $result;
    }
}
