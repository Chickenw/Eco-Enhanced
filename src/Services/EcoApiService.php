<?php

namespace GameNest\GameNestEcoEnhanced\Services;

use App\Models\EggVariable;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class EcoApiService
{
    /**
     * Read the Eco REST API keys from Configs/Users.eco through Wings.
     *
     * This deliberately does not read /var/lib/pelican/volumes directly,
     * allowing the Panel and Wings node to be separated later.
     */
    public function getApiKeys(Server $server): array
    {
        $contents = app(DaemonFileRepository::class)
            ->setServer($server)
            ->getContent('Configs/Users.eco', 1024 * 1024);

        $data = json_decode($contents, true);

        if (!is_array($data)) {
            throw new RuntimeException('Eco Configs/Users.eco is not valid JSON.');
        }

        $keys = [];

        foreach (['APIAdminAuthToken', 'APIAuthToken'] as $name) {
            $value = trim((string) ($data[$name] ?? ''));

            if ($value !== '' && !in_array($value, $keys, true)) {
                $keys[] = $value;
            }
        }

        return $keys;
    }

    public function hasApiKey(Server $server): bool
    {
        try {
            return count($this->getApiKeys($server)) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Return Eco users from /api/v1/users.
     *
     * WGS-compatible authentication:
     *  1. X-API-Key header (preferred)
     *  2. api_key query string (compatibility fallback)
     */
    public function users(Server $server): array
    {
        $keys = $this->getApiKeys($server);

        if (count($keys) === 0) {
            throw new RuntimeException(
                'Users.eco has no APIAdminAuthToken or APIAuthToken.'
            );
        }

        $server->loadMissing('allocation');

        $host = trim((string) ($server->allocation?->ip ?? ''));

        if ($host === '') {
            throw new RuntimeException('Eco server has no primary allocation IP.');
        }

        $webPort = (int) $this->getEnvironmentVariable(
            $server,
            'WEB_PORT',
            ((int) ($server->allocation?->port ?? 0)) + 1
        );

        if ($webPort < 1) {
            throw new RuntimeException('Eco Web port is not configured.');
        }

        $urlHost = str_contains($host, ':') && !str_starts_with($host, '[')
            ? '[' . $host . ']'
            : $host;

        $url = 'http://' . $urlHost . ':' . $webPort . '/api/v1/users';
        $failures = [];

        foreach ($keys as $key) {
            try {
                $response = Http::timeout(8)
                    ->acceptJson()
                    ->withHeaders(['X-API-Key' => $key])
                    ->get($url);

                if ($response->successful()) {
                    $json = $response->json();

                    return is_array($json) ? $json : [];
                }

                $failures[] = 'X-API-Key HTTP ' . $response->status();
            } catch (Throwable $exception) {
                $failures[] = 'X-API-Key: ' . $exception->getMessage();
            }

            try {
                $response = Http::timeout(8)
                    ->acceptJson()
                    ->get($url, ['api_key' => $key]);

                if ($response->successful()) {
                    $json = $response->json();

                    return is_array($json) ? $json : [];
                }

                $failures[] = 'api_key HTTP ' . $response->status();
            } catch (Throwable $exception) {
                $failures[] = 'api_key: ' . $exception->getMessage();
            }
        }

        throw new RuntimeException(
            'Eco Users API lookup failed. ' .
            implode(' | ', array_slice($failures, -4))
        );
    }

    /**
     * Match RCON online players to Eco /api/v1/users and attach Steam64 IDs.
     */
    public function enrichPlayers(Server $server, array $players): array
    {
        if (count($players) === 0) {
            return $players;
        }

        $users = $this->users($server);
        $byName = [];

        foreach ($users as $user) {
            if (!is_array($user)) {
                continue;
            }

            $name = $this->firstString(
                $user,
                ['Name', 'Username', 'UserName', 'DisplayName']
            );

            if ($name === '') {
                continue;
            }

            $steamId = $this->firstString(
                $user,
                [
                    'SteamId',
                    'SteamID',
                    'SteamId64',
                    'SteamID64',
                    'Steam64Id',
                    'Steam64ID',
                ]
            );

            $byName[mb_strtolower(trim($name))] = [
                'steam_id' => $steamId,
                'api_name' => $name,
            ];
        }

        foreach ($players as &$player) {
            if (!is_array($player)) {
                continue;
            }

            $name = trim((string) ($player['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $match = $byName[mb_strtolower($name)] ?? null;

            if (!$match) {
                continue;
            }

            if (($player['steam_id'] ?? '') === '' && $match['steam_id'] !== '') {
                $player['steam_id'] = $match['steam_id'];
            }
        }
        unset($player);

        return $players;
    }

    /**
     * Build a reusable Name <-> Steam64 directory from Eco's users API.
     */
    public function directory(Server $server): array
    {
        $users = $this->users($server);

        $directory = [
            'by_name' => [],
            'by_steam' => [],
            'by_id' => [],
        ];

        foreach ($users as $user) {
            if (!is_array($user)) {
                continue;
            }

            $name = $this->firstString(
                $user,
                ['Name', 'Username', 'UserName', 'DisplayName']
            );

            $steamId = $this->firstString(
                $user,
                [
                    'SteamId',
                    'SteamID',
                    'SteamId64',
                    'SteamID64',
                    'Steam64Id',
                    'Steam64ID',
                ]
            );

            $ecoId = $this->firstString(
                $user,
                [
                    'SlgId',
                    'SLGId',
                    'SlgID',
                    'SLGID',
                    'UserId',
                    'UserID',
                    'UserGuid',
                    'UserGUID',
                    'Id',
                    'ID',
                ]
            );

            if (
                $ecoId !== '' &&
                preg_match(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                    $ecoId
                ) !== 1
            ) {
                $ecoId = '';
            }

            if ($name === '' && $steamId === '' && $ecoId === '') {
                continue;
            }

            $identity = [
                'name' => $name,
                'steam_id' => $steamId,
                'eco_id' => $ecoId,
            ];

            if ($name !== '') {
                $directory['by_name'][mb_strtolower(trim($name))] = $identity;
            }

            if ($steamId !== '') {
                $directory['by_steam'][trim($steamId)] = $identity;
            }

            if ($ecoId !== '') {
                $directory['by_id'][mb_strtolower(trim($ecoId))] = $identity;
            }
        }

        return $directory;
    }

    private function firstString(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = trim((string) $data[$key]);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function getEnvironmentVariable(
        Server $server,
        string $environmentVariable,
        mixed $default = null
    ): mixed {
        $eggVariable = EggVariable::query()
            ->where('egg_id', $server->egg_id)
            ->where('env_variable', $environmentVariable)
            ->first();

        if (!$eggVariable) {
            return $default;
        }

        $serverVariable = ServerVariable::query()
            ->where('server_id', $server->id)
            ->where('variable_id', $eggVariable->id)
            ->first();

        if (!$serverVariable || $serverVariable->variable_value === '') {
            return $eggVariable->default_value ?? $default;
        }

        return $serverVariable->variable_value;
    }
}
