<?php

namespace App\Console\Commands;

use App\Services\GateSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gate:sync')]
#[Description('Synchronize local offline gate verification events and pass snapshots')]
class SyncGateTerminal extends Command
{
    public function handle(GateSyncService $gateSyncService): int
    {
        $status = $gateSyncService->synchronize();

        if (! $status['connected']) {
            $this->components->warn($status['last_error'] ?? $status['message'] ?? 'Central server is unavailable.');

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Synchronized %d pass snapshots and %d verification events.',
            $status['snapshots_downloaded'],
            $status['events_synchronized'],
        ));

        return self::SUCCESS;
    }
}
