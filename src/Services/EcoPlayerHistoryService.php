<?php

namespace GameNest\GameNestEcoEnhanced\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use RuntimeException;
use Throwable;

class EcoPlayerHistoryService
{
    public function __construct(
        protected EcoRconService $rcon,
        protected EcoApiService $api,
    ) {
    }

    public function poll(Server $server): void
    {
        $output = $this->rcon->execute($server, 'manage players');
        $names = $this->parseOnlineNames($output);

        $directory = [
            'by_name' => [],
            'by_steam' => [],
        ];

        try {
            $directory = $this->api->directory($server);
        } catch (Throwable) {
            // History still works name-first if the Eco API is unavailable.
        }

        $players = [];
        $ipDirectory = $this->nidIpDirectory($server);

        foreach ($names as $name) {
            $identity = $directory['by_name'][mb_strtolower($name)] ?? [];

            $steamId = trim(
                (string) ($identity['steam_id'] ?? '')
            );

            $players[] = [
                'name' => $name,
                'steam_id' => $steamId,
                'ip' => $steamId !== ''
                    ? (string) ($ipDirectory[$steamId]['latest_ip'] ?? '')
                    : '',
            ];
        }

        $this->sync($server, $players);
    }

    public function sync(Server $server, array $onlinePlayers): void
    {
        $this->mutate($server, function (array $data) use ($onlinePlayers): array {
            $now = now()->utc();
            $nowIso = $now->toIso8601String();

            $data['players'] ??= [];
            $data['last_poll_at'] = $nowIso;

            $currentKeys = [];

            foreach ($onlinePlayers as $player) {
                if (!is_array($player)) {
                    continue;
                }

                $name = trim((string) ($player['name'] ?? ''));
                $steamId = trim((string) ($player['steam_id'] ?? ''));
                $ip = trim((string) ($player['ip'] ?? ''));

                if ($name === '' && $steamId === '') {
                    continue;
                }

                $key = $this->identityKey($name, $steamId);
                $currentKeys[$key] = true;

                $record = $data['players'][$key] ?? [
                    'name' => $name,
                    'steam_id' => $steamId,
                    'last_ip' => $ip,
                    'ip_history' => $ip !== '' ? [$ip] : [],
                    'connections' => 0,
                    'total_seconds' => 0,
                    'first_seen_at' => $nowIso,
                    'last_seen_at' => $nowIso,
                    'online' => false,
                    'session_started_at' => null,
                    'last_accounted_at' => null,
                ];

                if ($steamId !== '') {
                    $nameKey = $this->identityKey($name, '');

                    if (
                        $nameKey !== $key &&
                        isset($data['players'][$nameKey]) &&
                        !isset($data['players'][$key])
                    ) {
                        $record = array_merge(
                            $data['players'][$nameKey],
                            [
                                'name' => $name,
                                'steam_id' => $steamId,
                            ]
                        );

                        unset($data['players'][$nameKey]);
                    }
                }

                if (!($record['online'] ?? false)) {
                    $record['connections'] = (int) ($record['connections'] ?? 0) + 1;
                    $record['session_started_at'] = $nowIso;
                    $record['last_accounted_at'] = $nowIso;
                } else {
                    $record['total_seconds'] =
                        (int) ($record['total_seconds'] ?? 0) +
                        $this->accountedDeltaSeconds(
                            $record['last_accounted_at'] ?? null,
                            $now
                        );

                    $record['last_accounted_at'] = $nowIso;
                }

                if ($name !== '') {
                    $record['name'] = $name;
                }

                if ($steamId !== '') {
                    $record['steam_id'] = $steamId;
                }

                if ($ip !== '') {
                    $record['last_ip'] = $ip;

                    $record['ip_history'] ??= [];

                    if (!in_array($ip, $record['ip_history'], true)) {
                        $record['ip_history'][] = $ip;
                    }
                }

                $record['online'] = true;
                $record['last_seen_at'] = $nowIso;

                $data['players'][$key] = $record;
            }

            foreach ($data['players'] as $key => &$record) {
                if (!($record['online'] ?? false) || isset($currentKeys[$key])) {
                    continue;
                }

                $record['total_seconds'] =
                    (int) ($record['total_seconds'] ?? 0) +
                    $this->accountedDeltaSeconds(
                        $record['last_accounted_at'] ?? null,
                        $now
                    );

                $record['online'] = false;
                $record['session_started_at'] = null;
                $record['last_accounted_at'] = null;
                $record['last_seen_at'] = $nowIso;
            }
            unset($record);

            return $data;
        });
    }

    public function enrichOnlineIps(
        Server $server,
        array $players
    ): array {
        $ips = $this->nidIpDirectory($server);

        foreach ($players as &$player) {
            if (!is_array($player)) {
                continue;
            }

            $steamId = trim(
                (string) ($player['steam_id'] ?? '')
            );

            if ($steamId === '') {
                continue;
            }

            $ip = trim(
                (string) ($ips[$steamId]['latest_ip'] ?? '')
            );

            if ($ip !== '') {
                $player['ip'] = $ip;
            }
        }

        unset($player);

        return $players;
    }

    private function nidIpDirectory(Server $server): array
    {
        try {
            $contents = app(DaemonFileRepository::class)
                ->setServer($server)
                ->getContent(
                    'Logs/NidToolbox/IPLogger/0-Global.log',
                    5 * 1024 * 1024
                );
        } catch (Throwable) {
            return [];
        }

        $result = [];

        $lines = preg_split(
            '/\r\n|\r|\n/',
            trim($contents)
        ) ?: [];

        foreach ($lines as $line) {
            $line = trim((string) $line);

            if (
                $line === ''
                || str_starts_with($line, 'Date')
            ) {
                continue;
            }

            /*
             * Example:
             * 08/29/2026  11:10:13  Login  98.187.245.236
             * 76561198401951887  <slg>  2055  ChickenWings
             */
            if (!preg_match(
                '/^(\d{2}\/\d{2}\/\d{4})\s+' .
                '(\d{2}:\d{2}:\d{2})\s+' .
                '(Login|Logout)\s+' .
                '(\S+)\s+' .
                '(\d{17})\s+/i',
                $line,
                $match
            )) {
                continue;
            }

            if (strcasecmp($match[3], 'Login') !== 0) {
                continue;
            }

            $ip = trim($match[4]);
            $steamId = trim($match[5]);

            if (
                !filter_var($ip, FILTER_VALIDATE_IP)
                || $steamId === ''
            ) {
                continue;
            }

            $result[$steamId] ??= [
                'latest_ip' => '',
                'ips' => [],
            ];

            $result[$steamId]['latest_ip'] = $ip;

            if (
                !in_array(
                    $ip,
                    $result[$steamId]['ips'],
                    true
                )
            ) {
                $result[$steamId]['ips'][] = $ip;
            }
        }

        return $result;
    }

    public function history(Server $server): array
    {
        $data = $this->read($server);
        $rows = array_values($data['players'] ?? []);

        foreach ($rows as &$row) {
            $ips = $row['ip_history'] ?? [];

            if (!is_array($ips)) {
                $ips = [];
            }

            $lastIp = trim((string) ($row['last_ip'] ?? ''));

            if ($lastIp !== '' && !in_array($lastIp, $ips, true)) {
                $ips[] = $lastIp;
            }

            $row['ip_history'] = array_values(
                array_unique(
                    array_filter(
                        array_map('trim', $ips)
                    )
                )
            );
        }

        unset($row);

        usort(
            $rows,
            fn (array $a, array $b) =>
                strcmp(
                    (string) ($b['last_seen_at'] ?? ''),
                    (string) ($a['last_seen_at'] ?? '')
                )
        );

        return $rows;
    }

    private function parseOnlineNames(string $output): array
    {
        $output = trim($output);

        if ($output === '' || $output === 'Online:') {
            return [];
        }

        $output = preg_replace('/^Online:\s*/i', '', $output);

        if ($output === null || trim($output) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];

        return array_values(array_filter(array_map(
            fn ($line) => trim((string) $line, " \t,-"),
            $lines
        )));
    }

    private function accountedDeltaSeconds(?string $lastAccountedAt, $now): int
    {
        if (!$lastAccountedAt) {
            return 0;
        }

        try {
            $last = \Carbon\Carbon::parse($lastAccountedAt)->utc();
            $seconds = max(0, $last->diffInSeconds($now, false));

            return min($seconds, 120);
        } catch (Throwable) {
            return 0;
        }
    }

    private function identityKey(string $name, string $steamId): string
    {
        if ($steamId !== '') {
            return 'steam:' . $steamId;
        }

        return 'name:' . mb_strtolower(trim($name));
    }

    private function path(Server $server): string
    {
        $dir = storage_path('app/gamenest-eco-enhanced/player-history');

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create GameNest player-history directory.');
        }

        return $dir . '/server-' . $server->id . '.json';
    }

    private function read(Server $server): array
    {
        $path = $this->path($server);

        if (!is_file($path)) {
            return [
                'version' => 1,
                'server_id' => $server->id,
                'last_poll_at' => null,
                'players' => [],
            ];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded)
            ? $decoded
            : [
                'version' => 1,
                'server_id' => $server->id,
                'last_poll_at' => null,
                'players' => [],
            ];
    }

    private function mutate(Server $server, callable $callback): void
    {
        $path = $this->path($server);
        $lockPath = $path . '.lock';

        $lock = fopen($lockPath, 'c+');

        if ($lock === false) {
            throw new RuntimeException('Unable to open player-history lock file.');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Unable to lock player-history data.');
            }

            $data = $this->read($server);
            $data = $callback($data);

            $json = json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );

            if ($json === false) {
                throw new RuntimeException('Unable to encode player-history data.');
            }

            file_put_contents($path, $json . PHP_EOL, LOCK_EX);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
