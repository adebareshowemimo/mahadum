<?php

namespace App\Console\Commands;

use App\Services\Content\MediaStorageSynchronizer;
use Illuminate\Console\Command;

class SyncMediaStorage extends Command
{
    protected $signature = 'media:sync-storage
                            {--dry-run : Report changes without writing media asset rows}';

    protected $description = 'Import files already present under storage/app/public/media into the media library';

    public function handle(MediaStorageSynchronizer $synchronizer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $synchronizer->sync(persist: ! $dryRun);

        $this->components->info(sprintf(
            '%s: %d created, %d updated, %d unchanged, %d unsupported.',
            $dryRun ? 'Media storage dry run' : 'Media storage synchronized',
            $result['created'],
            $result['updated'],
            $result['unchanged'],
            $result['unsupported'],
        ));

        return self::SUCCESS;
    }
}
