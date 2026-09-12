<?php

namespace GameNest\GameNestEcoEnhanced\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Backups\InitiateBackupService;
use App\Services\Backups\DeleteBackupService;
use RuntimeException;
use Throwable;

class EcoWorldWipeService
{
    public function pendingKey(Server $server): string
    {
        return 'gamenest:eco:pending-wipe:' . $server->id;
    }

    protected function pruneOldPreWipeBackups(Server $server): void
    {
        /*
         * Keep only the two newest successful GameNest Pre-Wipe backups
         * before creating another one.
         *
         * We ONLY touch backups whose names begin with "Pre-Wipe -".
         * Manually locked backups and ordinary backups are never touched.
         */
        $backups = $server
            ->backups()
            ->where('name', 'like', 'Pre-Wipe - %')
            ->where('is_successful', true)
            ->whereNotNull('completed_at')
            ->orderByDesc('created_at')
            ->get();

        /*
         * Before creating the next Pre-Wipe backup, keep at most one
         * existing backup. The new backup will become the second.
         */
        foreach ($backups->slice(1) as $backup) {
            /*
             * Pelican intentionally refuses to delete a successful
             * locked backup. GameNest owns these Pre-Wipe backups, so
             * explicitly unlock the old one before deleting it.
             */
            if ($backup->is_locked) {
                $backup->forceFill([
                    'is_locked' => false,
                ])->save();
            }

            app(DeleteBackupService::class)
                ->handle($backup);
        }
    }

    public function start(
        Server $server,
        string $trigger = 'manual',
        ?string $requestedBy = null
    ): string {
        $cacheKey = $this->pendingKey($server);

        if (cache()->has($cacheKey)) {
            throw new RuntimeException(
                'A protected Eco world wipe is already pending.'
            );
        }

        /*
         * Save the live world first when Eco is online.
         */
        try {
            app(EcoRconService::class)
                ->execute($server, 'manage save');
        } catch (Throwable) {
            // Offline Eco servers can still be backed up/wiped.
        }

        /*
         * Prune old GameNest Pre-Wipe backups first so locked safety
         * backups cannot eventually consume all server backup slots.
         */
        $this->pruneOldPreWipeBackups($server);

        /*
         * Always create a locked Pelican backup before a wipe.
         *
         * Pre-Wipe backups bypass Pelican's normal rate throttle because
         * they are a required safety backup for a destructive operation.
         * The server's actual backup-count limit is still respected by
         * InitiateBackupService::handle(..., override: true).
         *
         * Restore the normal throttle immediately afterward so ordinary
         * Pelican backups remain unchanged.
         */
        $originalThrottlePeriod = config('backups.throttles.period');

        try {
            config([
                'backups.throttles.period' => 0,
            ]);

            $backup = app(InitiateBackupService::class)
                ->setIsLocked(true)
                ->handle(
                    $server,
                    'Pre-Wipe - ' . now('America/Chicago')->format('Y-m-d H:i:s'),
                    true
                );
        } finally {
            config([
                'backups.throttles.period' => $originalThrottlePeriod,
            ]);
        }

        $trigger = in_array(
            $trigger,
            ['manual', 'scheduled'],
            true
        )
            ? $trigger
            : 'manual';

        cache()->put(
            $cacheKey,
            [
                'backup_uuid' => $backup->uuid,
                'trigger' => $trigger,
                'requested_at' => now()->toIso8601String(),
                'requested_by' => trim((string) $requestedBy),
            ],
            now()->addMinutes(30)
        );

        return $backup->uuid;
    }

    /**
     * Process a pending wipe.
     *
     * none      = no pending wipe
     * waiting   = backup still running
     * failed    = backup failed/missing
     * completed = world wiped and Eco restarted
     */
    public function process(Server $server): string
    {
        $cacheKey = $this->pendingKey($server);
        $pending = cache()->get($cacheKey);

        if (!$pending) {
            return 'none';
        }

        /*
         * Backward compatibility with wipes started before
         * wipe-history metadata was introduced.
         */
        if (is_string($pending)) {
            $backupUuid = $pending;
            $trigger = 'manual';
            $requestedBy = '';
        } else {
            $backupUuid = trim(
                (string) ($pending['backup_uuid'] ?? '')
            );

            $trigger = trim(
                (string) ($pending['trigger'] ?? 'manual')
            );

            $requestedBy = trim(
                (string) ($pending['requested_by'] ?? '')
            );
        }

        if ($backupUuid === '') {
            cache()->forget($cacheKey);

            return 'failed';
        }

        $backup = $server
            ->backups()
            ->where('uuid', $backupUuid)
            ->first();

        if (!$backup) {
            cache()->forget($cacheKey);

            app(EcoWipeHistoryService::class)
                ->record(
                    $server,
                    $trigger,
                    'failed',
                    false,
                    $backupUuid,
                    $requestedBy,
                    'The Pre-Wipe backup could not be found. The world was not touched.'
                );

            return 'failed';
        }

        if ($backup->completed_at === null) {
            return 'waiting';
        }

        /*
         * Never wipe after a failed backup.
         */
        if (!$backup->is_successful) {
            cache()->forget($cacheKey);

            app(EcoWipeHistoryService::class)
                ->record(
                    $server,
                    $trigger,
                    'failed',
                    false,
                    $backupUuid,
                    $requestedBy,
                    'The Pre-Wipe backup failed. The world was not touched.'
                );

            return 'failed';
        }

        $lock = cache()->lock(
            'gamenest:eco:wipe-lock:' . $server->id,
            120
        );

        if (!$lock->get()) {
            return 'waiting';
        }

        try {
            cache()->forget($cacheKey);

            try {
                $this->perform($server);

                app(EcoWipeHistoryService::class)
                    ->record(
                        $server,
                        $trigger,
                        'completed',
                        true,
                        $backupUuid,
                        $requestedBy,
                        'The protected Pre-Wipe backup completed and Eco generated a fresh world.'
                    );

                return 'completed';

            } catch (Throwable $exception) {

                app(EcoWipeHistoryService::class)
                    ->record(
                        $server,
                        $trigger,
                        'failed',
                        true,
                        $backupUuid,
                        $requestedBy,
                        'The Pre-Wipe backup succeeded, but the wipe failed: ' .
                        $exception->getMessage()
                    );

                throw $exception;
            }

        } finally {
            $lock->release();
        }
    }

    public function perform(Server $server): void
    {
        $serverRepo = app(DaemonServerRepository::class)
            ->setServer($server);

        /*
         * Stop Eco and allow its database handles to close.
         */
        try {
            $serverRepo->power('stop');
            sleep(5);
        } catch (Throwable) {
            // Already offline is acceptable.
        }

        $fileRepo = app(DaemonFileRepository::class)
            ->setServer($server);

        /*
         * Remove ONLY the active Eco world files.
         *
         * Configs/Users.eco is untouched, preserving admins,
         * whitelist, bans, and mutes.
         */
        $worldFiles = [
            'Game.db',
            'Game.eco',
            'Game-log.db',
        ];

        $storageEntries = $fileRepo->getDirectory('/Storage');
        $existingFiles = [];

        foreach ($storageEntries as $storageEntry) {
            if (!is_array($storageEntry)) {
                continue;
            }

            $name = (string) ($storageEntry['name'] ?? '');

            if (in_array($name, $worldFiles, true)) {
                $existingFiles[] = $name;
            }
        }

        if (count($existingFiles) === 0) {
            throw new RuntimeException(
                'No active Eco world files were found in Storage.'
            );
        }

        $fileRepo->deleteFiles(
            '/Storage',
            $existingFiles
        );

        /*
         * Eco generates a fresh world on startup.
         */
        $serverRepo->power('start');
    }
}
