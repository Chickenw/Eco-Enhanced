<?php

namespace GameNest\GameNestEcoEnhanced\Services;

use App\Models\Allocation;
use App\Models\EggVariable;
use App\Models\Server;
use App\Models\ServerVariable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EcoPortAllocator
{
    public const BLOCK_SIZE = 4;

    /**
     * Configure an Eco server's complete four-port block.
     *
     * +0 = Game
     * +1 = Web
     * +2 = RCON
     * +3 = Steam
     */
    public function configure(Server $server): void
    {
        $server->refresh();

        $primary = Allocation::query()->find($server->allocation_id);

        if (!$primary) {
            throw new RuntimeException(
                "Primary allocation not found for server {$server->id}."
            );
        }

        $basePort = (int) $primary->port;

        $ports = [
            'game'  => $basePort,
            'web'   => $basePort + 1,
            'rcon'  => $basePort + 2,
            'steam' => $basePort + 3,
        ];

        if ($ports['steam'] > 65535) {
            throw new RuntimeException(
                'Eco port block exceeds the maximum valid port.'
            );
        }

        DB::transaction(function () use ($server, $primary, $ports) {

            /*
             * Find allocations that already exist for this Eco block.
             */
            $existing = Allocation::query()
                ->where('node_id', $primary->node_id)
                ->where('ip', $primary->ip)
                ->whereIn('port', array_values($ports))
                ->lockForUpdate()
                ->get()
                ->keyBy('port');

            /*
             * Verify the primary Game allocation.
             */
            $gameAllocation = $existing->get($ports['game']);

            if (!$gameAllocation) {
                throw new RuntimeException(
                    "Primary Eco allocation {$primary->ip}:{$ports['game']} disappeared."
                );
            }

            if ((int) $gameAllocation->server_id !== (int) $server->id) {
                throw new RuntimeException(
                    "Primary Eco allocation no longer belongs to server {$server->id}."
                );
            }

            /*
             * Validate secondary ports before changing anything.
             */
            foreach (['web', 'rcon', 'steam'] as $type) {
                $port = $ports[$type];
                $allocation = $existing->get($port);

                if (!$allocation) {
                    continue;
                }

                if (
                    $allocation->server_id !== null &&
                    (int) $allocation->server_id !== (int) $server->id
                ) {
                    throw new RuntimeException(
                        "Cannot configure Eco: {$primary->ip}:{$port} is already assigned to another server."
                    );
                }
            }

            /*
             * Create missing allocations or reuse existing free ones.
             */
            foreach (['web', 'rcon', 'steam'] as $type) {
                $port = $ports[$type];

                $allocation = $existing->get($port);

                if (!$allocation) {
                    $allocation = new Allocation();

                    $allocation->node_id = $primary->node_id;
                    $allocation->ip = $primary->ip;
                    $allocation->port = $port;
                    $allocation->ip_alias = $primary->ip_alias;
                    $allocation->notes = "GameNest Eco {$type} port";
                    $allocation->is_locked = false;

                    $allocation->save();

                    Log::info(
                        '[GameNest Eco Enhanced] Created Eco allocation.',
                        [
                            'server_id' => $server->id,
                            'type' => $type,
                            'ip' => $primary->ip,
                            'port' => $port,
                        ]
                    );
                }

                if ($allocation->server_id === null) {
                    $allocation->server_id = $server->id;
                }

                $allocation->is_locked = true;
                $allocation->save();

                Log::info(
                    '[GameNest Eco Enhanced] Assigned Eco allocation.',
                    [
                        'server_id' => $server->id,
                        'type' => $type,
                        'ip' => $primary->ip,
                        'port' => $port,
                    ]
                );
            }

            /*
             * Automatically populate the Eco Egg variables.
             *
             * Each new Eco server receives its own random RCON password.
             * Existing non-default passwords are preserved when networking
             * is configured again later.
             */
            $this->ensureRandomRconPassword($server);

            /*
             * Eco Playtime default.
             *
             * GameNest defaults new Eco servers to "All the time".
             * This is Eco's encoded weekly availability value.
             */
            $this->setEnvironmentVariable(
                $server,
                'PLAYTIME',
                '222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222'
            );

            $this->setEnvironmentVariable(
                $server,
                'WEB_PORT',
                $ports['web']
            );

            $this->setEnvironmentVariable(
                $server,
                'RCON_PORT',
                $ports['rcon']
            );

            $this->setEnvironmentVariable(
                $server,
                'STEAM_PORT',
                $ports['steam']
            );

            /*
             * Pre-populate Eco's public connection values for new servers.
             */
            // Always use the real allocation IP for networking.
            // ip_alias is display-only.
            $publicHost = trim((string) $primary->ip);

            if ($publicHost !== '') {
                $this->setEnvironmentVariable(
                    $server,
                    'REMOTE_ADDRESS',
                    $publicHost . ':' . $ports['game']
                );

                $webUrlHost = str_contains($publicHost, ':')
                    && !str_starts_with($publicHost, '[')
                        ? '[' . $publicHost . ']'
                        : $publicHost;

                $this->setEnvironmentVariable(
                    $server,
                    'WEBSRVURL',
                    'http://' .
                    $webUrlHost .
                    ':' .
                    $ports['web'] .
                    '/'
                );
            }
        });

        Log::info(
            '[GameNest Eco Enhanced] Eco networking configured.',
            [
                'server_id' => $server->id,
                'server_uuid' => $server->uuid,
                'node_id' => $primary->node_id,
                'ip' => $primary->ip,
                'game' => $ports['game'],
                'web' => $ports['web'],
                'rcon' => $ports['rcon'],
                'steam' => $ports['steam'],
            ]
        );
    }

    /**
     * Give a new Eco server a unique RCON password.
     *
     * Preserve a password once it differs from the Egg default so running
     * EcoPortAllocator again cannot unexpectedly break RCON access.
     */
    private function ensureRandomRconPassword(Server $server): void
    {
        $eggVariable = EggVariable::query()
            ->where('egg_id', $server->egg_id)
            ->where('env_variable', 'RCON_PW')
            ->first();

        if (!$eggVariable) {
            Log::warning(
                '[GameNest Eco Enhanced] RCON_PW Egg variable not found.',
                [
                    'server_id' => $server->id,
                ]
            );

            return;
        }

        $serverVariable = ServerVariable::query()
            ->where('server_id', $server->id)
            ->where('variable_id', $eggVariable->id)
            ->first();

        $current = trim(
            (string) ($serverVariable?->variable_value ?? '')
        );

        $default = trim(
            (string) ($eggVariable->default_value ?? '')
        );

        /*
         * A non-empty value that no longer matches the Egg default has
         * already been customized/generated. Never replace it.
         */
        if (
            $current !== '' &&
            ($default === '' || $current !== $default)
        ) {
            return;
        }

        $password = bin2hex(random_bytes(16));

        ServerVariable::query()->updateOrCreate(
            [
                'server_id' => $server->id,
                'variable_id' => $eggVariable->id,
            ],
            [
                'variable_value' => $password,
            ]
        );

        Log::info(
            '[GameNest Eco Enhanced] Generated unique Eco RCON password.',
            [
                'server_id' => $server->id,
            ]
        );
    }

    /**
     * Set an Egg environment variable for a specific server.
     */
    private function setEnvironmentVariable(
        Server $server,
        string $environmentVariable,
        int|string $value
    ): void {
        $eggVariable = EggVariable::query()
            ->where('egg_id', $server->egg_id)
            ->where('env_variable', $environmentVariable)
            ->first();

        if (!$eggVariable) {
            Log::warning(
                '[GameNest Eco Enhanced] Eco Egg variable not found.',
                [
                    'server_id' => $server->id,
                    'environment_variable' => $environmentVariable,
                ]
            );

            return;
        }

        ServerVariable::query()->updateOrCreate(
            [
                'server_id' => $server->id,
                'variable_id' => $eggVariable->id,
            ],
            [
                'variable_value' => (string) $value,
            ]
        );

        Log::info(
            '[GameNest Eco Enhanced] Updated Eco environment variable.',
            [
                'server_id' => $server->id,
                'environment_variable' => $environmentVariable,
                'value' => (string) $value,
            ]
        );
    }
}
