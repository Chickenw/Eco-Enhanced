<?php

namespace EcoEnhanced\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

class EcoWipeScheduleService
{
    private const CONFIG_PATH = 'Configs/EcoEnhanced.json';

    public function get(Server $server): array
    {
        $defaults = [
            'enabled' => false,
            'scheduled_at' => null,
            'last_triggered_at' => null,
        ];

        try {
            $contents = app(DaemonFileRepository::class)
                ->setServer($server)
                ->getContent(self::CONFIG_PATH, 1024 * 1024);

            $data = json_decode($contents, true);

            if (!is_array($data)) {
                return $defaults;
            }

            $wipe = $data['scheduled_wipe'] ?? [];

            if (!is_array($wipe)) {
                return $defaults;
            }

            return array_merge($defaults, $wipe);

        } catch (FileNotFoundException) {
            return $defaults;
        } catch (Throwable) {
            return $defaults;
        }
    }

    public function save(
        Server $server,
        bool $enabled,
        ?string $scheduledAt
    ): void {
        $data = $this->readConfig($server);

        $existing = $data['scheduled_wipe'] ?? [];

        if (!is_array($existing)) {
            $existing = [];
        }

        $data['scheduled_wipe'] = array_merge(
            $existing,
            [
                'enabled' => $enabled,
                'scheduled_at' => $scheduledAt,
            ]
        );

        $this->writeConfig($server, $data);
    }

    public function disable(Server $server): void
    {
        $data = $this->readConfig($server);

        $existing = $data['scheduled_wipe'] ?? [];

        if (!is_array($existing)) {
            $existing = [];
        }

        $data['scheduled_wipe'] = array_merge(
            $existing,
            [
                'enabled' => false,
            ]
        );

        $this->writeConfig($server, $data);
    }

    public function markTriggered(Server $server): void
    {
        $data = $this->readConfig($server);

        $existing = $data['scheduled_wipe'] ?? [];

        if (!is_array($existing)) {
            $existing = [];
        }

        $data['scheduled_wipe'] = array_merge(
            $existing,
            [
                'enabled' => false,
                'last_triggered_at' => now('UTC')->toIso8601String(),
            ]
        );

        $this->writeConfig($server, $data);
    }

    public function isDue(Server $server): bool
    {
        $schedule = $this->get($server);

        if (!($schedule['enabled'] ?? false)) {
            return false;
        }

        $scheduledAt = trim(
            (string) ($schedule['scheduled_at'] ?? '')
        );

        if ($scheduledAt === '') {
            return false;
        }

        try {
            return Carbon::parse($scheduledAt)
                ->utc()
                ->lessThanOrEqualTo(now('UTC'));
        } catch (Throwable) {
            return false;
        }
    }

    private function readConfig(Server $server): array
    {
        try {
            $contents = app(DaemonFileRepository::class)
                ->setServer($server)
                ->getContent(self::CONFIG_PATH, 1024 * 1024);

            $data = json_decode($contents, true);

            return is_array($data) ? $data : [];

        } catch (FileNotFoundException) {
            return [];
        }
    }

    private function writeConfig(Server $server, array $data): void
    {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            throw new RuntimeException(
                'Unable to encode Eco Enhanced Eco configuration.'
            );
        }

        app(DaemonFileRepository::class)
            ->setServer($server)
            ->putContent(
                self::CONFIG_PATH,
                $json . PHP_EOL
            );
    }
}
