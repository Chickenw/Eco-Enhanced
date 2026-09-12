<?php

namespace GameNest\GameNestEcoEnhanced\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Throwable;

class EcoWipeHistoryService
{
    protected function path(): string
    {
        return '.gamenest/wipe-history.json';
    }

    protected function repo(Server $server): DaemonFileRepository
    {
        return app(DaemonFileRepository::class)
            ->setServer($server);
    }

    protected function ensureDirectory(Server $server): void
    {
        try {
            $this->repo($server)->createDirectory(
                '.gamenest',
                '/'
            );
        } catch (Throwable) {
            // Directory already existing is fine.
        }
    }

    public function all(
        Server $server,
        int $limit = 25
    ): array {
        try {
            $contents = $this->repo($server)
                ->getContent(
                    $this->path(),
                    2 * 1024 * 1024
                );

            $rows = json_decode(
                $contents,
                true
            );

            if (!is_array($rows)) {
                return [];
            }

            return array_slice(
                $rows,
                0,
                max(1, $limit)
            );

        } catch (Throwable) {
            return [];
        }
    }

    public function record(
        Server $server,
        string $trigger,
        string $status,
        bool $backupSuccessful,
        ?string $backupUuid = null,
        ?string $requestedBy = null,
        ?string $message = null
    ): void {
        $this->ensureDirectory($server);

        $rows = $this->all(
            $server,
            100
        );

        array_unshift(
            $rows,
            [
                'id' => bin2hex(random_bytes(8)),
                'occurred_at' => now()->toIso8601String(),

                'trigger' => in_array(
                    $trigger,
                    ['manual', 'scheduled'],
                    true
                )
                    ? $trigger
                    : 'manual',

                'status' => $status,

                'backup_successful' =>
                    $backupSuccessful,

                'backup_uuid' =>
                    $backupUuid,

                'requested_by' =>
                    trim((string) $requestedBy),

                'message' =>
                    trim((string) $message),
            ]
        );

        /*
         * Keep the history small.
         */
        $rows = array_slice(
            $rows,
            0,
            100
        );

        $json = json_encode(
            $rows,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            return;
        }

        $this->repo($server)->putContent(
            $this->path(),
            $json . PHP_EOL
        );
    }
}
