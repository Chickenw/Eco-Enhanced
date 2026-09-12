<?php

namespace EcoEnhanced\Pages;

use App\Models\EggVariable;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Backups\InitiateBackupService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use EcoEnhanced\Services\EcoApiService;
use EcoEnhanced\Services\EcoPlayerHistoryService;
use EcoEnhanced\Services\EcoRconService;
use EcoEnhanced\Services\EcoWorldWipeService;
use EcoEnhanced\Services\EcoWipeScheduleService;
use EcoEnhanced\Services\EcoWipeHistoryService;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

class EcoOverview extends Page
{
    protected static ?int $navigationSort = 2;
protected string $view = 'eco-enhanced::pages.eco-overview';

    public Server $server;

    public array $ports = [];
    public array $eco = [];

    public array $players = [];
    public array $bannedPlayers = [];
    public array $admins = [];
    public array $whitelist = [];
    public array $mutedPlayers = [];

    public array $userDirectory = [
        'by_name' => [],
        'by_steam' => [],
        'by_id' => [],
    ];

    public array $banHours = [];
    public array $banPermanent = [];
    public array $kickReasons = [];
    public array $banReasons = [];

    public string $newAdmin = '';
    public string $newAdminReason = '';
    public string $newWhitelist = '';
    public string $newWhitelistReason = '';
    public string $newMute = '';
    public string $newMuteReason = '';
    public string $newMuteTime = '';

    public string $manualBanTarget = '';
    public string $manualBanReason = '';
    public string $manualBanHours = '';
    public bool $manualBanPermanent = false;

    public string $adminCommand = '';
    public string $adminOutput = '';
    public string $announcement = '';


    public string $rconStatus = 'Not Tested';
    public bool $rconOnline = false;

    public bool $scheduledWipeEnabled = false;
    public string $scheduledWipeDate = '';
    public string $scheduledWipeTime = '';

    public function mount(): void
    {
        $server = Filament::getTenant();

        abort_unless($server instanceof Server, 404);
        abort_unless(static::isEcoServer($server), 404);

        $this->server = $server;
        $this->loadEcoInformation();

        // Do not fail page load just because the game is offline.
        $this->refreshPlayersQuiet();
        $this->refreshAdminListsQuiet();
        $this->loadScheduledWipe();
    }
public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        return $server instanceof Server
            && static::isEcoServer($server);
    }
    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-globe-alt';
    }

    public static function getNavigationLabel(): string
    {
        return 'Eco Overview';
    }

    public function getTitle(): string
    {
        return 'Eco Overview';
    }

    public function getLastSuccessfulBackupLabel(): string
    {
        try {
            $backup = $this->server
                ->backups()
                ->where('is_successful', true)
                ->whereNotNull('completed_at')
                ->orderByDesc('completed_at')
                ->first();

            if (!$backup) {
                return 'None yet';
            }

            return $backup->completed_at
                ->timezone('America/Chicago')
                ->format('M j, g:i A');
        } catch (Throwable) {
            return 'Unavailable';
        }
    }

    public function getDashboardRconStatus(): array
    {
        try {
            app(EcoRconService::class)
                ->testConnection($this->server);

            return [
                'online' => true,
                'label' => 'Online',
            ];
        } catch (Throwable) {
            return [
                'online' => false,
                'label' => 'Offline',
            ];
        }
    }


    protected static function isEcoServer(Server $server): bool
    {
        $server->loadMissing('egg');

        return $server->egg
            && str_contains(strtolower($server->egg->name), 'eco');
    }

    protected function loadEcoInformation(): void
    {
        $this->server->refresh();
        $this->server->loadMissing(['allocation', 'allocations', 'egg', 'node']);

        $primaryPort = (int) ($this->server->allocation?->port ?? 0);
        $primaryIp = $this->server->allocation?->ip ?? '';

        $this->ports = [
            'game' => $primaryPort,
            'web' => (int) $this->getEnvironmentVariable('WEB_PORT', $primaryPort > 0 ? $primaryPort + 1 : 0),
            'rcon' => (int) $this->getEnvironmentVariable('RCON_PORT', $primaryPort > 0 ? $primaryPort + 2 : 0),
            'steam' => (int) $this->getEnvironmentVariable('STEAM_PORT', $primaryPort > 0 ? $primaryPort + 3 : 0),
        ];

        // The player/user REST API is authenticated by APIAdminAuthToken or
        // APIAuthToken inside Configs/Users.eco, not by Eco's long server-auth
        // token. Read the real API-key state through Wings.
        $tokenConfigured = app(EcoApiService::class)
            ->hasApiKey($this->server);

        $this->eco = [
            'ip' => $primaryIp,
            'server_name' => $this->getEnvironmentVariable('SRV_NAME', $this->server->name),
            'public_server' => $this->getEnvironmentVariable('PUB_SRV', 'false'),
            'category' => $this->getEnvironmentVariable('SRV_CAT', 'None'),
            'discord' => $this->getEnvironmentVariable('DISCORD_SRV', ''),
            'token_configured' => $tokenConfigured,
            'rcon_configured' => $this->hasEnvironmentVariableValue('RCON_PW'),
        ];
    }

    public function testRcon(): void
    {
        try {
            app(EcoRconService::class)->testConnection($this->server);

            $this->rconOnline = true;
            $this->rconStatus = 'Connected';

            Notification::make()
                ->title('Eco RCON Connected')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            $this->rconOnline = false;
            $this->rconStatus = 'Connection Failed';

            Notification::make()
                ->title('Eco RCON Connection Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    // ---------------------------------------------------------------------
    // Players
    // ---------------------------------------------------------------------

    public function refreshPlayers(): void
    {
        try {
            $this->players = $this->loadPlayers();

            try {
                $this->players = app(EcoPlayerHistoryService::class)
                    ->enrichOnlineIps($this->server, $this->players);
            } catch (Throwable) {
                //
            }

            Notification::make()
                ->title('Players Refreshed')
                ->body(count($this->players) . ' player(s) online.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to Load Players')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function refreshPlayersQuiet(): void
    {
        try {
            $this->players = $this->loadPlayers();

            try {
                $this->players = app(EcoPlayerHistoryService::class)
                    ->enrichOnlineIps($this->server, $this->players);
            } catch (Throwable) {
                //
            }
        } catch (Throwable) {
            // Polling should never spam notifications while the game is offline.
        }
    }

    protected function loadPlayers(): array
    {
        // RCON remains authoritative for live online presence.
        $output = $this->rcon('manage players');
        $players = $this->parsePlayers($output);

        if (count($players) === 0) {
            return $players;
        }

        // Enrich the RCON rows with Steam64 from Eco's /api/v1/users.
        // If the REST API is temporarily unavailable, player detection must
        // still work, so retain the RCON-only result.
        try {
            return app(EcoApiService::class)
                ->enrichPlayers($this->server, $players);
        } catch (\Throwable) {
            return $players;
        }
    }

    protected function parsePlayers(string $output): array
    {
        $output = trim($output);

        if ($output === '' || preg_match('/^Online:\s*$/i', $output)) {
            return [];
        }

        $output = preg_replace('/^Online:\s*/i', '', $output) ?? $output;

        $rawEntries = preg_split('/\r\n|\r|\n|,\s*(?=[^\s])/', trim($output)) ?: [];
        $results = [];

        foreach ($rawEntries as $raw) {
            $line = trim($raw, " \t,-");
            if ($line === '') {
                continue;
            }

            // Future-proofing: if Eco/Nid ever includes a Steam64 or IP in the
            // player-list response, expose it automatically.
            preg_match('/(?<!\d)(7656119\d{10})(?!\d)/', $line, $steam);
            preg_match('/\b(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)\b/', $line, $ip);

            $name = $line;
            if (!empty($steam[1])) {
                $name = trim(str_replace($steam[1], '', $name), " \t|-()");
            }
            if (!empty($ip[0])) {
                $name = trim(str_replace($ip[0], '', $name), " \t|-()");
            }

            $results[] = [
                'name' => $name !== '' ? $name : $line,
                'steam_id' => $steam[1] ?? '',
                'ip' => $ip[0] ?? '',
            ];
        }

        return array_values($results);
    }

    public function kickPlayerByIndex(int $index): void
    {
        $entry = $this->playerAt($index);
        if (!$entry) {
            return;
        }

        $name = $this->validateActionTarget($entry['name'] ?? '');
        $reason = $this->cleanReason($this->kickReasons[$index] ?? '', 'Kicked by Eco Enhanced');

        try {
            $response = $this->rcon('manage kick ' . $name . ', ' . $reason);

            Notification::make()
                ->title('Player Kicked')
                ->body(trim($response) !== '' ? trim($response) : $name . ' was kicked.')
                ->success()
                ->send();

            unset($this->kickReasons[$index]);
            $this->refreshPlayersQuiet();
        } catch (Throwable $exception) {
            $this->notifyError('Kick Failed', $exception);
        }
    }

    public function banPlayerByIndex(int $index): void
    {
        $entry = $this->playerAt($index);
        if (!$entry) {
            return;
        }

        $name = $this->validateActionTarget($entry['name'] ?? '');

        $target = trim((string) ($entry['steam_id'] ?? '')) !== ''
            ? $this->validateManualTarget((string) $entry['steam_id'])
            : $name;

        $permanent = (bool) ($this->banPermanent[$index] ?? false);
        $hours = trim((string) ($this->banHours[$index] ?? ''));
        $reason = $this->cleanReason(
            $this->kickReasons[$index] ?? '',
            'Banned by Eco Enhanced'
        );

        if (
            !$permanent &&
            ($hours === '' || !ctype_digit($hours) || (int) $hours < 1)
        ) {
            Notification::make()
                ->title('Ban Failed')
                ->body('Enter the number of hours, or select Permanent.')
                ->danger()
                ->send();

            return;
        }

        try {
            $data = $this->readUsersEcoPermissions();

            $values = $data['UserPermission']['BlackList']
                ['Collection']
                ['Eco.Gameplay.Players.TimeUser']
                ['$values'] ?? [];

            if (!is_array($values)) {
                $values = [];
            }

            foreach ($values as $value) {
                if (
                    is_array($value) &&
                    trim((string) ($value['UserName'] ?? '')) === $target
                ) {
                    $this->notifyDuplicate('Banned player', $target);
                    return;
                }
            }

            $releaseDate = $permanent
                ? '9999-12-31T23:59:59.9999999+00:00'
                : now()
                    ->addHours((int) $hours)
                    ->utc()
                    ->format('Y-m-d\TH:i:s.uP');

            $values[] = [
                'UserName' => $target,
                'ReleaseDate' => $releaseDate,
            ];

            $this->setUsersEcoTimedValues(
                $data,
                'BlackList',
                $values
            );

            $this->writeUsersEcoPermissions($data);

            $command = 'manage ban ' . $target . ', ' . $reason;

            if (!$permanent) {
                $command .= ', ' . ((int) $hours) . 'h';
            }

            $live = $this->tryLivePermissionSync($command);

            unset(
                $this->banHours[$index],
                $this->banPermanent[$index],
                $this->kickReasons[$index]
            );

            $this->refreshPlayersQuiet();
            $this->refreshBansQuiet();

            Notification::make()
                ->title('Player Banned')
                ->body(
                    $live
                        ? (
                            $permanent
                                ? $name . ' was permanently banned and applied live.'
                                : $name . ' was banned for ' . ((int) $hours) . ' hour(s) and applied live.'
                        )
                        : (
                            $permanent
                                ? $name . ' was permanently banned. Eco will apply it on next start.'
                                : $name . ' was banned for ' . ((int) $hours) . ' hour(s). Eco will apply it on next start.'
                        )
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            $this->notifyError('Ban Failed', $exception);
        }
    }

    protected function playerAt(int $index): ?array
    {
        if (!array_key_exists($index, $this->players)) {
            Notification::make()
                ->title('Player Action Failed')
                ->body('Player entry is no longer available. Refresh the player list and try again.')
                ->danger()
                ->send();
            return null;
        }

        return $this->players[$index];
    }

    // ---------------------------------------------------------------------
    // Admin / whitelist / ban / mute collection management
    //
    // Configs/Users.eco is the persistent source of truth.
    // RCON is used only as a live-sync layer when Eco is online.
    // ---------------------------------------------------------------------

    protected function readUsersEcoPermissions(): array
    {
        $contents = app(DaemonFileRepository::class)
            ->setServer($this->server)
            ->getContent('Configs/Users.eco', 2 * 1024 * 1024);

        $data = json_decode($contents, true);

        if (!is_array($data)) {
            throw new RuntimeException(
                'Configs/Users.eco is not valid JSON.'
            );
        }

        return $data;
    }

    protected function writeUsersEcoPermissions(array $data): void
    {
        /*
         * Eco expects ThreadSafeAction fields to remain JSON objects {}.
         * json_decode(..., true) converts empty objects into PHP arrays,
         * which json_encode() would otherwise write back as [].
         */
        foreach ([
            'WhiteList',
            'BlackList',
            'MuteList',
            'Admins',
            'UsersWithReservedSlotsAtQueue',
        ] as $permissionList) {
            if (
                !isset($data['UserPermission'][$permissionList]) ||
                !is_array($data['UserPermission'][$permissionList])
            ) {
                continue;
            }

            $data['UserPermission'][$permissionList]
                ['UserIDAddedEvent'] = new \stdClass();

            $data['UserPermission'][$permissionList]
                ['UserIDRemovedEvent'] = new \stdClass();
        }

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            throw new RuntimeException(
                'Unable to encode Configs/Users.eco.'
            );
        }

        app(DaemonFileRepository::class)
            ->setServer($this->server)
            ->putContent(
                'Configs/Users.eco',
                $json . PHP_EOL
            );
    }

    protected function usersEcoSimpleValues(
        array $data,
        string $list
    ): array {
        $values = $data['UserPermission'][$list]
            ['Collection']['System.String']['$values'] ?? [];

        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn ($value) => trim((string) $value),
                $values
            ),
            fn ($value) => $value !== ''
        ));
    }

    protected function setUsersEcoSimpleValues(
        array &$data,
        string $list,
        array $values
    ): void {
        $data['UserPermission'][$list]
            ['Collection']['System.String']['$values']
            = array_values(array_unique(array_filter(
                array_map(
                    fn ($value) => trim((string) $value),
                    $values
                ),
                fn ($value) => $value !== ''
            )));
    }

    protected function usersEcoTimedValues(
        array $data,
        string $list
    ): array {
        $values = $data['UserPermission'][$list]
            ['Collection']['Eco.Gameplay.Players.TimeUser']['$values'] ?? [];

        if (!is_array($values)) {
            return [];
        }

        $rows = [];

        foreach ($values as $value) {
            if (!is_array($value)) {
                continue;
            }

            $name = trim((string) ($value['UserName'] ?? ''));

            if ($name === '') {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'expires' => trim(
                    (string) ($value['ReleaseDate'] ?? 'Unknown')
                ) ?: 'Unknown',
            ];
        }

        return $rows;
    }

    protected function setUsersEcoTimedValues(
        array &$data,
        string $list,
        array $values
    ): void {
        $data['UserPermission'][$list]
            ['Collection']['Eco.Gameplay.Players.TimeUser']['$values']
            = array_values($values);
    }

    protected function tryLivePermissionSync(string $command): bool
    {
        try {
            $this->rcon($command);

            return true;
        } catch (Throwable) {
            // Server may be offline. Config change remains persistent.
            return false;
        }
    }

    protected function ecoReleaseDateFromDuration(string $duration): string
    {
        $duration = strtolower(trim($duration));

        if ($duration === '') {
            return '9999-12-31T23:59:59.9999999+00:00';
        }

        if (
            preg_match(
                '/^(\d+)([mhdw])$/',
                $duration,
                $matches
            ) !== 1
        ) {
            throw new RuntimeException(
                'Time must use Eco format such as 30m, 6h, 2d, or 1w.'
            );
        }

        $amount = (int) $matches[1];

        $expires = match ($matches[2]) {
            'm' => now()->addMinutes($amount),
            'h' => now()->addHours($amount),
            'd' => now()->addDays($amount),
            'w' => now()->addWeeks($amount),
        };

        return $expires->utc()->format('Y-m-d\TH:i:s.uP');
    }

    public function refreshAdminLists(): void
    {
        try {
            $this->refreshAdminListsInternal();

            Notification::make()
                ->title('Eco Administration Refreshed')
                ->body('Loaded directly from Configs/Users.eco.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            $this->notifyError(
                'Unable to Refresh Administration',
                $exception
            );
        }
    }

    public function refreshAdminListsQuiet(): void
    {
        try {
            $this->refreshAdminListsInternal();
        } catch (Throwable) {
            //
        }
    }

    protected function refreshAdminListsInternal(): void
    {
        $data = $this->readUsersEcoPermissions();

        $this->admins = $this->usersEcoSimpleValues(
            $data,
            'Admins'
        );

        $this->whitelist = $this->usersEcoSimpleValues(
            $data,
            'WhiteList'
        );

        $this->bannedPlayers = $this->usersEcoTimedValues(
            $data,
            'BlackList'
        );

        $this->mutedPlayers = $this->usersEcoTimedValues(
            $data,
            'MuteList'
        );

        $this->refreshUserDirectoryQuiet();
    }

    public function refreshBans(): void
    {
        try {
            $this->refreshBansQuiet();

            Notification::make()
                ->title('Banned Players Refreshed')
                ->body(
                    count($this->bannedPlayers) .
                    ' banned player(s).'
                )
                ->success()
                ->send();
        } catch (Throwable $exception) {
            $this->notifyError(
                'Unable to Load Banned Players',
                $exception
            );
        }
    }

    protected function refreshBansQuiet(): void
    {
        $data = $this->readUsersEcoPermissions();

        $this->bannedPlayers =
            $this->usersEcoTimedValues(
                $data,
                'BlackList'
            );
    }

    public function addAdmin(): void
    {
        try {
            $target = $this->preferredIdentityTarget(
                $this->newAdmin
            );

            $data = $this->readUsersEcoPermissions();
            $admins = $this->usersEcoSimpleValues(
                $data,
                'Admins'
            );

            if (in_array($target, $admins, true)) {
                $this->notifyDuplicate('Admin', $target);
                return;
            }

            $admins[] = $target;

            $this->setUsersEcoSimpleValues(
                $data,
                'Admins',
                $admins
            );

            $this->writeUsersEcoPermissions($data);

            $live = $this->tryLivePermissionSync(
                'manage admin ' .
                $target .
                ', Added as admin by Eco Enhanced'
            );

            /*
             * Eco may append its internal user UUID to Users.eco when the
             * live RCON command is executed.
             *
             * Eco Enhanced deliberately stores the Steam64/name target instead,
             * because it is useful outside the running world and survives
             * world wipes cleanly.
             *
             * Re-apply the intended admin collection after the live sync so
             * the runtime change takes effect without leaving a duplicate
             * Eco UUID in Users.eco.
             */
            if ($live) {
                try {
                    $afterLive = $this->readUsersEcoPermissions();

                    $this->setUsersEcoSimpleValues(
                        $afterLive,
                        'Admins',
                        $admins
                    );

                    $this->writeUsersEcoPermissions(
                        $afterLive
                    );
                } catch (Throwable) {
                    /*
                     * The live admin action already succeeded. A cleanup
                     * failure should not turn that successful action into
                     * an error notification.
                     */
                }
            }

            $this->newAdmin = '';
            $this->refreshAdminListsInternal();

            Notification::make()
                ->title('Admin Added')
                ->body(
                    $live
                        ? $target . ' saved and applied live.'
                        : $target . ' saved. Eco will apply it on next start.'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            $this->notifyError(
                'Add Admin Failed',
                $exception
            );
        }
    }

    public function removeAdminByIndex(int $index): void
    {
        $target = $this->collectionValue(
            $this->admins,
            $index,
            'Admin'
        );

        if ($target === null) {
            return;
        }

        try {
            $data = $this->readUsersEcoPermissions();
            $admins = $this->usersEcoSimpleValues(
                $data,
                'Admins'
            );

            $admins = array_values(array_filter(
                $admins,
                fn ($value) => $value !== $target
            ));

            $this->setUsersEcoSimpleValues(
                $data,
                'Admins',
                $admins
            );

            $this->writeUsersEcoPermissions($data);

            $this->tryLivePermissionSync(
                'manage removeadmin ' .
                $target .
                ', Removed by Eco Enhanced'
            );

            $this->refreshAdminListsInternal();

            Notification::make()
                ->title('Admin Removed')
                ->body($target)
                ->success()
                ->send();

        } catch (Throwable $exception) {
            $this->notifyError(
                'Remove Admin Failed',
                $exception
            );
        }
    }

    public function addWhitelist(): void
    {
        try {
            $target = $this->preferredIdentityTarget(
                $this->newWhitelist
            );

            $data = $this->readUsersEcoPermissions();
            $whitelist = $this->usersEcoSimpleValues(
                $data,
                'WhiteList'
            );

            if (in_array($target, $whitelist, true)) {
                $this->notifyDuplicate(
                    'Whitelist',
                    $target
                );
                return;
            }

            $whitelist[] = $target;

            $this->setUsersEcoSimpleValues(
                $data,
                'WhiteList',
                $whitelist
            );

            $this->writeUsersEcoPermissions($data);

            $live = $this->tryLivePermissionSync(
                'manage whitelist ' .
                $target .
                ', Whitelisted by Eco Enhanced'
            );

            /*
             * Eco may append its internal UUID to Users.eco after the live
             * whitelist command. Re-apply Eco Enhanced's intended persistent
             * whitelist values so the config stays Steam64/name based.
             */
            if ($live) {
                try {
                    $afterLive = $this->readUsersEcoPermissions();

                    $this->setUsersEcoSimpleValues(
                        $afterLive,
                        'WhiteList',
                        $whitelist
                    );

                    $this->writeUsersEcoPermissions(
                        $afterLive
                    );
                } catch (Throwable) {
                    //
                }
            }

            $this->newWhitelist = '';
            $this->refreshAdminListsInternal();

            Notification::make()
                ->title('Player Whitelisted')
                ->body($target)
                ->success()
                ->send();

        } catch (Throwable $exception) {
            $this->notifyError(
                'Whitelist Failed',
                $exception
            );
        }
    }

    public function removeWhitelistByIndex(int $index): void
    {
        $target = $this->collectionValue(
            $this->whitelist,
            $index,
            'Whitelist'
        );

        if ($target === null) {
            return;
        }

        try {
            $data = $this->readUsersEcoPermissions();
            $whitelist = $this->usersEcoSimpleValues(
                $data,
                'WhiteList'
            );

            $whitelist = array_values(array_filter(
                $whitelist,
                fn ($value) => $value !== $target
            ));

            $this->setUsersEcoSimpleValues(
                $data,
                'WhiteList',
                $whitelist
            );

            $this->writeUsersEcoPermissions($data);

            $this->tryLivePermissionSync(
                'manage unwhitelist ' .
                $target .
                ', Removed by Eco Enhanced'
            );

            $this->refreshAdminListsInternal();

            Notification::make()
                ->title('Whitelist Entry Removed')
                ->body($target)
                ->success()
                ->send();

        } catch (Throwable $exception) {
            $this->notifyError(
                'Remove Whitelist Failed',
                $exception
            );
        }
    }

    public function addMute(): void
    {
        try {
            $target = $this->preferredIdentityTarget(
                $this->newMute
            );

            $reason = $this->cleanReason(
                $this->newMuteReason,
                'Muted by Eco Enhanced'
            );

            $time = trim($this->newMuteTime);

            $releaseDate =
                $this->ecoReleaseDateFromDuration($time);

            $data = $this->readUsersEcoPermissions();
            $muted = $this->usersEcoTimedValues(
                $data,
                'MuteList'
            );

            foreach ($muted as $entry) {
                if (($entry['name'] ?? '') === $target) {
                    $this->notifyDuplicate(
                        'Muted player',
                        $target
                    );
                    return;
                }
            }

            $rawMuted = $data['UserPermission']['MuteList']
                ['Collection']
                ['Eco.Gameplay.Players.TimeUser']
                ['$values'] ?? [];

            if (!is_array($rawMuted)) {
                $rawMuted = [];
            }

            $rawMuted[] = [
                'UserName' => $target,
                'ReleaseDate' => $releaseDate,
            ];

            $this->setUsersEcoTimedValues(
                $data,
                'MuteList',
                $rawMuted
            );

            $this->writeUsersEcoPermissions($data);

            $command =
                'manage mute ' .
                $target .
                ', ' .
                $reason;

            if ($time !== '') {
                $command .= ', ' . strtolower($time);
            }

            $this->tryLivePermissionSync($command);

            $this->newMute = '';
            $this->newMuteReason = '';
            $this->newMuteTime = '';

            $this->refreshAdminListsInternal();

            Notification::make()
                ->title('Player Muted')
                ->body($target)
                ->success()
                ->send();

        } catch (Throwable $exception) {
            $this->notifyError(
                'Mute Failed',
                $exception
            );
        }
    }

    public function unmuteByIndex(int $index): void
    {
        $target = $this->timedCollectionValue(
            $this->mutedPlayers,
            $index,
            'Muted player'
        );

        if ($target === null) {
            return;
        }

        try {
            $data = $this->readUsersEcoPermissions();

            $values = $data['UserPermission']['MuteList']
                ['Collection']
                ['Eco.Gameplay.Players.TimeUser']
                ['$values'] ?? [];

            if (!is_array($values)) {
                $values = [];
            }

            $values = array_values(array_filter(
                $values,
                fn ($value) =>
                    is_array($value) &&
                    trim((string) ($value['UserName'] ?? ''))
                        !== $target
            ));

            $this->setUsersEcoTimedValues(
                $data,
                'MuteList',
                $values
            );

            $this->writeUsersEcoPermissions($data);

            $this->tryLivePermissionSync(
                'manage unmute ' .
                $target .
                ', Unmuted by Eco Enhanced'
            );

            $this->refreshAdminListsInternal();

            Notification::make()
                ->title('Player Unmuted')
                ->body($target)
                ->success()
                ->send();

        } catch (Throwable $exception) {
            $this->notifyError(
                'Unmute Failed',
                $exception
            );
        }
    }

    public function unbanPlayerByIndex(int $index): void
    {
        $target = $this->timedCollectionValue(
            $this->bannedPlayers,
            $index,
            'Banned player'
        );

        if ($target === null) {
            return;
        }

        try {
            $data = $this->readUsersEcoPermissions();

            $values = $data['UserPermission']['BlackList']
                ['Collection']
                ['Eco.Gameplay.Players.TimeUser']
                ['$values'] ?? [];

            if (!is_array($values)) {
                $values = [];
            }

            $values = array_values(array_filter(
                $values,
                fn ($value) =>
                    is_array($value) &&
                    trim((string) ($value['UserName'] ?? ''))
                        !== $target
            ));

            $this->setUsersEcoTimedValues(
                $data,
                'BlackList',
                $values
            );

            $this->writeUsersEcoPermissions($data);

            $this->tryLivePermissionSync(
                'manage unban ' .
                $target .
                ', Unbanned by Eco Enhanced'
            );

            $this->refreshAdminListsInternal();

            Notification::make()
                ->title('Player Unbanned')
                ->body($target)
                ->success()
                ->send();

        } catch (Throwable $exception) {
            $this->notifyError(
                'Unban Failed',
                $exception
            );
        }
    }

    protected function parseSimpleCollection(string $output): array
    {
        $lines = $this->cleanCollectionLines($output);
        $results = [];

        foreach ($lines as $line) {
            // Strip common bullet/list decoration but preserve IDs/usernames.
            $value = trim(preg_replace('/^\s*[-*•]\s*/u', '', $line) ?? $line);

            // Some Eco versions prefix collection output with labels.
            $value = preg_replace('/^(Admins?|Whitelist(?:ed)?(?: Users?)?):\s*/i', '', $value) ?? $value;
            $value = trim($value);

            if ($value !== '' && !$this->isCollectionHeader($value)) {
                $results[] = $value;
            }
        }

        return array_values(array_unique($results));
    }

    protected function parseTimedCollection(string $output): array
    {
        $lines = $this->cleanCollectionLines($output);
        $results = [];

        foreach ($lines as $line) {
            if (preg_match(
                '/^(.*?)\s+(\d{2}\/\d{2}\/\d{4})\s+(\d{2}:\d{2}:\d{2})$/',
                $line,
                $matches
            )) {
                $results[] = [
                    'name' => trim($matches[1]),
                    'expires' => $matches[2] . ' ' . $matches[3],
                ];
                continue;
            }

            $clean = trim(preg_replace('/^\s*[-*•]\s*/u', '', $line) ?? $line);
            if ($clean !== '' && !$this->isCollectionHeader($clean)) {
                $results[] = [
                    'name' => $clean,
                    'expires' => 'Unknown',
                ];
            }
        }

        return $results;
    }

    protected function cleanCollectionLines(string $output): array
    {
        $output = trim($output);

        if ($output === '' || preg_match('/\bNone\.\s*$/i', $output)) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];

        return array_values(array_filter(array_map('trim', $lines), function ($line) {
            if ($line === '') return false;
            if (str_starts_with($line, 'Requested collection')) return false;
            return true;
        }));
    }

    protected function isCollectionHeader(string $value): bool
    {
        return (bool) preg_match(
            '/^(Admins?|Whitelist(?:ed)?(?: Users?)?|Banned(?: Users?)?|Muted(?: Users?)?|BlackList|MuteList)\s*:?\s*$/i',
            trim($value)
        );
    }

    protected function collectionContains(array $collection, string $target): bool
    {
        foreach ($collection as $entry) {
            if (strcasecmp(trim((string) $entry), $target) === 0) {
                return true;
            }
        }

        return false;
    }

    protected function collectionValue(array $collection, int $index, string $label): ?string
    {
        if (!array_key_exists($index, $collection)) {
            Notification::make()
                ->title($label . ' Action Failed')
                ->body('The entry is no longer available. Refresh and try again.')
                ->danger()
                ->send();
            return null;
        }

        return $this->validateManualTarget((string) $collection[$index]);
    }

    protected function timedCollectionValue(array $collection, int $index, string $label): ?string
    {
        if (!array_key_exists($index, $collection)) {
            Notification::make()
                ->title($label . ' Action Failed')
                ->body('The entry is no longer available. Refresh and try again.')
                ->danger()
                ->send();
            return null;
        }

        return $this->validateManualTarget((string) ($collection[$index]['name'] ?? ''));
    }

    protected function refreshUserDirectoryQuiet(): void
    {
        try {
            $this->userDirectory = app(EcoApiService::class)
                ->directory($this->server);
        } catch (Throwable) {
            // Keep RCON administration usable if the REST API is unavailable.
        }
    }

    protected function getHistoryIdentityFor(string $value): ?array
    {
        $raw = trim($value);

        if ($raw === '') {
            return null;
        }

        $key = mb_strtolower($raw);

        try {
            foreach ($this->getPlayerHistory() as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $name = trim(
                    (string) ($row['name'] ?? '')
                );

                $steamId = trim(
                    (string) ($row['steam_id'] ?? '')
                );

                if (
                    ($steamId !== '' && $steamId === $raw) ||
                    ($name !== '' && mb_strtolower($name) === $key)
                ) {
                    return [
                        'name' => $name,
                        'steam_id' => $steamId,
                        'raw' => $raw,
                    ];
                }
            }
        } catch (Throwable) {
            //
        }

        return null;
    }

    public function getIdentityFor(string $value): array
    {
        $raw = trim($value);

        if ($raw === '') {
            return [
                'name' => '',
                'steam_id' => '',
                'raw' => '',
            ];
        }

        $bySteam = $this->userDirectory['by_steam'] ?? [];
        $byName = $this->userDirectory['by_name'] ?? [];
        $byId = $this->userDirectory['by_id'] ?? [];

        $idKey = mb_strtolower($raw);

        /*
         * Eco internal ID lookup.
         */
        if (isset($byId[$idKey])) {
            $name = trim(
                (string) ($byId[$idKey]['name'] ?? '')
            );

            $steamId = trim(
                (string) ($byId[$idKey]['steam_id'] ?? '')
            );

            /*
             * Eco's API can know the Steam ID but not return a useful
             * display name after a wipe. Fall back to Eco Enhanced history.
             */
            if ($name === '' && $steamId !== '') {
                $history = $this->getHistoryIdentityFor(
                    $steamId
                );

                if ($history !== null) {
                    $name = $history['name'] ?? '';
                }
            }

            return [
                'name' => $name,
                'steam_id' => $steamId,
                'raw' => $raw,
            ];
        }

        /*
         * Steam64 lookup from Eco API.
         */
        if (isset($bySteam[$raw])) {
            $name = trim(
                (string) ($bySteam[$raw]['name'] ?? '')
            );

            $steamId = trim(
                (string) (
                    $bySteam[$raw]['steam_id']
                    ?? $raw
                )
            );

            if ($name === '') {
                $history = $this->getHistoryIdentityFor(
                    $steamId
                );

                if ($history !== null) {
                    $name = $history['name'] ?? '';
                }
            }

            return [
                'name' => $name,
                'steam_id' => $steamId,
                'raw' => $raw,
            ];
        }

        /*
         * Player-name lookup from Eco API.
         */
        $key = mb_strtolower($raw);

        if (isset($byName[$key])) {
            return [
                'name' => trim(
                    (string) (
                        $byName[$key]['name']
                        ?? $raw
                    )
                ),
                'steam_id' => trim(
                    (string) (
                        $byName[$key]['steam_id']
                        ?? ''
                    )
                ),
                'raw' => $raw,
            ];
        }

        /*
         * Eco Enhanced history is persistent across world wipes and does not
         * require Eco's REST API to be online.
         */
        $history = $this->getHistoryIdentityFor($raw);

        if ($history !== null) {
            return $history;
        }

        $looksLikeSteam =
            preg_match(
                '/^7656119\d{10}$/',
                $raw
            ) === 1;

        $looksLikeEcoId = preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $raw
        ) === 1;

        return [
            'name' => ($looksLikeSteam || $looksLikeEcoId)
                ? ''
                : $raw,
            'steam_id' => $looksLikeSteam
                ? $raw
                : '',
            'raw' => $looksLikeEcoId
                ? ''
                : $raw,
        ];
    }

    protected function preferredIdentityTarget(string $value): string
    {
        $value = $this->validateManualTarget($value);
        $identity = $this->getIdentityFor($value);

        if (($identity['steam_id'] ?? '') !== '') {
            return $this->validateManualTarget((string) $identity['steam_id']);
        }

        return $value;
    }

    public function formatPlayerIps(array $row): string
    {
        $ips = $row['ip_history'] ?? [];

        if (!is_array($ips)) {
            $ips = [];
        }

        $lastIp = trim((string) ($row['last_ip'] ?? ''));

        if ($lastIp !== '') {
            $ips[] = $lastIp;
        }

        $ips = array_values(array_unique(array_filter(
            array_map(
                fn ($ip) => trim((string) $ip),
                $ips
            )
        )));

        return count($ips) > 0
            ? implode(', ', $ips)
            : 'Not known yet';
    }

    public function getPlayerHistory(): array
    {
        try {
            return app(EcoPlayerHistoryService::class)
                ->history($this->server);
        } catch (Throwable) {
            return [];
        }
    }

    public function formatPlayTime(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0
            ? $hours . 'h ' . $minutes . 'm'
            : $minutes . 'm';
    }

    public function addManualBan(): void
    {
        try {
            $target = $this->preferredIdentityTarget(
                $this->manualBanTarget
            );

            $reason = $this->cleanReason(
                $this->manualBanReason,
                'Banned by Eco Enhanced'
            );

            $permanent = (bool) $this->manualBanPermanent;
            $hours = trim($this->manualBanHours);

            if (
                !$permanent &&
                ($hours === '' || !ctype_digit($hours) || (int) $hours < 1)
            ) {
                Notification::make()
                    ->title('Ban Failed')
                    ->body('Enter the number of hours, or select Permanent.')
                    ->danger()
                    ->send();

                return;
            }

            $data = $this->readUsersEcoPermissions();

            $values = $data['UserPermission']['BlackList']
                ['Collection']
                ['Eco.Gameplay.Players.TimeUser']
                ['$values'] ?? [];

            if (!is_array($values)) {
                $values = [];
            }

            foreach ($values as $value) {
                if (
                    is_array($value) &&
                    trim((string) ($value['UserName'] ?? '')) === $target
                ) {
                    $this->notifyDuplicate(
                        'Banned player',
                        $target
                    );
                    return;
                }
            }

            $releaseDate = $permanent
                ? '9999-12-31T23:59:59.9999999+00:00'
                : now()
                    ->addHours((int) $hours)
                    ->utc()
                    ->format('Y-m-d\TH:i:s.uP');

            $values[] = [
                'UserName' => $target,
                'ReleaseDate' => $releaseDate,
            ];

            $this->setUsersEcoTimedValues(
                $data,
                'BlackList',
                $values
            );

            $this->writeUsersEcoPermissions($data);

            $command = 'manage ban ' . $target . ', ' . $reason;

            if (!$permanent) {
                $command .= ', ' . ((int) $hours) . 'h';
            }

            $live = $this->tryLivePermissionSync($command);

            $this->manualBanTarget = '';
            $this->manualBanReason = '';
            $this->manualBanHours = '';
            $this->manualBanPermanent = false;

            $this->refreshAdminListsInternal();

            Notification::make()
                ->title('Player Banned')
                ->body(
                    $live
                        ? $target . ' was saved and applied live.'
                        : $target . ' was saved. Eco will apply it on next start.'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Ban Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    // ---------------------------------------------------------------------
    // Existing command center
    // ---------------------------------------------------------------------

    public function runAdminCommand(): void
    {
        $command = trim($this->adminCommand);

        if ($command === '') {
            Notification::make()->title('Command Required')->warning()->send();
            return;
        }

        $command = ltrim($command, "/ \t");

        if ($this->containsControlCharacters($command)) {
            Notification::make()
                ->title('Invalid Command')
                ->body('Commands must be entered on a single line.')
                ->danger()
                ->send();
            return;
        }

        try {
            $response = $this->rcon($command);
            $this->adminOutput = trim($response) !== '' ? trim($response) : 'Command completed successfully.';

            Notification::make()
                ->title('Eco Command Executed')
                ->body($command)
                ->success()
                ->send();

            $this->refreshPlayersQuiet();
            $this->refreshAdminListsQuiet();
        } catch (Throwable $exception) {
            $this->adminOutput = 'ERROR: ' . $exception->getMessage();
            $this->notifyError('Eco Command Failed', $exception);
        }
    }

    public function sendAnnouncement(): void
    {
        $message = trim($this->announcement);

        if ($message === '') {
            Notification::make()
                ->title('Announcement Required')
                ->body('Enter an announcement message first.')
                ->warning()
                ->send();
            return;
        }

        if ($this->containsControlCharacters($message) || str_contains($message, ',')) {
            Notification::make()
                ->title('Invalid Announcement')
                ->body('Announcements must be a single line and cannot contain commas.')
                ->danger()
                ->send();
            return;
        }

        try {
            $response = $this->rcon('manage announce ' . $message);
            $this->adminOutput = trim($response) !== '' ? trim($response) : 'Announcement sent successfully.';
            $this->announcement = '';

            Notification::make()->title('Announcement Sent')->success()->send();
        } catch (Throwable $exception) {
            $this->adminOutput = 'ERROR: ' . $exception->getMessage();
            $this->notifyError('Announcement Failed', $exception);
        }
    }

    public function createEcoBackup(): void
    {
        try {
            app(InitiateBackupService::class)
                ->setIsLocked(false)
                ->handle(
                    $this->server,
                    'Eco Manual Backup - ' . now('America/Chicago')->format('Y-m-d H:i:s')
                );

            Notification::make()
                ->title('Backup Started')
                ->body('Pelican is creating the Eco server backup.')
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Backup Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function createPreWipeBackup(): void
    {
        try {
            app(InitiateBackupService::class)
                ->setIsLocked(true)
                ->handle(
                    $this->server,
                    'Pre-Wipe - ' . now('America/Chicago')->format('Y-m-d H:i:s'),
                    true
                );

            Notification::make()
                ->title('Pre-Wipe Backup Started')
                ->body(
                    'A locked Pre-Wipe backup is being created. ' .
                    'Wait for it to complete before wiping the world.'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Pre-Wipe Backup Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getWipeHistory(): array
    {
        return app(EcoWipeHistoryService::class)
            ->all(
                $this->server,
                20
            );
    }

    protected function loadScheduledWipe(): void
    {
        $schedule = app(EcoWipeScheduleService::class)
            ->get($this->server);

        $this->scheduledWipeEnabled =
            (bool) ($schedule['enabled'] ?? false);

        $scheduledAt = trim(
            (string) ($schedule['scheduled_at'] ?? '')
        );

        if ($scheduledAt === '') {
            $this->scheduledWipeDate = '';
            $this->scheduledWipeTime = '';

            return;
        }

        try {
            $local = Carbon::parse($scheduledAt)
                ->timezone('America/Chicago');

            $this->scheduledWipeDate =
                $local->format('Y-m-d');

            $this->scheduledWipeTime =
                $local->format('H:i');

        } catch (Throwable) {
            $this->scheduledWipeDate = '';
            $this->scheduledWipeTime = '';
        }
    }

    public function saveScheduledWipe(): void
    {
        try {
            if (!$this->scheduledWipeEnabled) {
                app(EcoWipeScheduleService::class)
                    ->disable($this->server);

                Notification::make()
                    ->title('Scheduled Wipe Disabled')
                    ->success()
                    ->send();

                return;
            }

            $date = trim($this->scheduledWipeDate);
            $time = trim($this->scheduledWipeTime);

            if ($date === '' || $time === '') {
                throw new RuntimeException(
                    'Choose both a wipe date and time.'
                );
            }

            $local = Carbon::createFromFormat(
                'Y-m-d H:i',
                $date . ' ' . $time,
                'America/Chicago'
            );

            if ($local->lessThanOrEqualTo(now('America/Chicago'))) {
                throw new RuntimeException(
                    'Scheduled wipe must be in the future.'
                );
            }

            app(EcoWipeScheduleService::class)
                ->save(
                    $this->server,
                    true,
                    $local->utc()->toIso8601String()
                );

            $this->loadScheduledWipe();

            Notification::make()
                ->title('Scheduled Wipe Saved')
                ->body(
                    'Next wipe: ' .
                    $this->getNextWipeLabel()
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Schedule Save Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function cancelScheduledWipe(): void
    {
        try {
            app(EcoWipeScheduleService::class)
                ->disable($this->server);

            $this->scheduledWipeEnabled = false;

            Notification::make()
                ->title('Scheduled Wipe Cancelled')
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Schedule Cancel Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getWipeStatus(): array
    {
        try {
            $wipeService = app(EcoWorldWipeService::class);
            $pendingUuid = cache()->get(
                $wipeService->pendingKey($this->server)
            );

            if ($pendingUuid) {
                $backup = $this->server
                    ->backups()
                    ->where('uuid', $pendingUuid)
                    ->first();

                if (!$backup) {
                    return [
                        'state' => 'waiting',
                        'label' => 'Preparing Wipe',
                        'message' => 'Waiting for the protected Pre-Wipe backup.',
                    ];
                }

                if ($backup->completed_at === null) {
                    return [
                        'state' => 'backup',
                        'label' => 'Creating Pre-Wipe Backup',
                        'message' => 'The world will wipe automatically when the backup finishes.',
                    ];
                }

                if (!$backup->is_successful) {
                    return [
                        'state' => 'failed',
                        'label' => 'Backup Failed',
                        'message' => 'The world will not be wiped because the safety backup failed.',
                    ];
                }

                return [
                    'state' => 'wipe',
                    'label' => 'Wipe Starting',
                    'message' => 'Backup complete. Waiting for the wipe processor.',
                ];
            }

            $schedule = app(EcoWipeScheduleService::class)
                ->get($this->server);

            if ($schedule['enabled'] ?? false) {
                return [
                    'state' => 'scheduled',
                    'label' => 'Wipe Scheduled',
                    'message' => 'Automatic wipe is armed for ' . $this->getNextWipeLabel() . '.',
                ];
            }

            return [
                'state' => 'idle',
                'label' => 'Ready',
                'message' => 'No world wipe is currently pending.',
            ];

        } catch (Throwable) {
            return [
                'state' => 'idle',
                'label' => 'Ready',
                'message' => 'No world wipe is currently pending.',
            ];
        }
    }

    public function getNextWipeLabel(): string
    {
        $schedule = app(EcoWipeScheduleService::class)
            ->get($this->server);

        if (!($schedule['enabled'] ?? false)) {
            return 'Not scheduled';
        }

        $scheduledAt = trim(
            (string) ($schedule['scheduled_at'] ?? '')
        );

        if ($scheduledAt === '') {
            return 'Not scheduled';
        }

        try {
            return Carbon::parse($scheduledAt)
                ->timezone('America/Chicago')
                ->format('M j, Y g:i A');
        } catch (Throwable) {
            return 'Invalid schedule';
        }
    }

    public function getWipeTimezoneLabel(): string
    {
        return (string) 'America/Chicago';
    }

    public function wipeEcoWorld(): void
    {
        try {
            app(EcoWorldWipeService::class)
                ->start(
                    $this->server,
                    'manual',
                    auth()->user()?->username
                        ?? auth()->user()?->name
                        ?? 'Panel User'
                );

            Notification::make()
                ->title('Pre-Wipe Backup Started')
                ->body(
                    'The locked backup is being created. ' .
                    'Eco Enhanced will wipe and restart Eco automatically when it completes.'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('World Wipe Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function processPendingWipe(): void
    {
        try {
            $status = app(EcoWorldWipeService::class)
                ->process($this->server);

            if ($status === 'completed') {
                Notification::make()
                    ->title('Eco World Wiped')
                    ->body(
                        'The locked Pre-Wipe backup completed successfully. ' .
                        'The world was wiped and Eco was started again.'
                    )
                    ->success()
                    ->send();
            }

            if ($status === 'failed') {
                Notification::make()
                    ->title('World Wipe Cancelled')
                    ->body(
                        'The Pre-Wipe backup failed or disappeared. ' .
                        'The current Eco world was not touched.'
                    )
                    ->danger()
                    ->send();
            }

        } catch (Throwable $exception) {
            Notification::make()
                ->title('World Wipe Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function performEcoWorldWipe(): void
    {
        $serverRepo = app(DaemonServerRepository::class)
            ->setServer($this->server);

        /*
         * Stop Eco before touching the active database files.
         */
        try {
            $serverRepo->power('stop');
            sleep(5);
        } catch (Throwable) {
            /*
             * If Eco is already offline we can still perform the wipe.
             */
        }

        $fileRepo = app(DaemonFileRepository::class)
            ->setServer($this->server);

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
         * Configs/Users.eco is intentionally untouched, so admins,
         * whitelist, bans and mutes persist through the wipe.
         */
        $serverRepo->power('start');

        Notification::make()
            ->title('Eco World Wiped')
            ->body(
                'The locked Pre-Wipe backup completed successfully. ' .
                'The world was wiped and Eco was started again.'
            )
            ->success()
            ->send();
    }

    public function saveEcoWorld(): void
    {
        $this->runQuickEcoCommand('manage save', 'Save World');
    }

    public function meteorStatus(): void
    {
        $this->runQuickEcoCommand('meteor status', 'Meteor Status');
    }

    public function climateStatus(): void
    {
        $this->runQuickEcoCommand('climate status', 'Climate Status');
    }

    protected function runQuickEcoCommand(string $command, string $label): void
    {
        try {
            $response = $this->rcon($command);
            $this->adminOutput = trim($response) !== '' ? trim($response) : $label . ' completed successfully.';
            Notification::make()->title($label)->success()->send();
        } catch (Throwable $exception) {
            $this->adminOutput = 'ERROR: ' . $exception->getMessage();
            $this->notifyError($label . ' Failed', $exception);
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    protected function rcon(string $command): string
    {
        return app(EcoRconService::class)->execute($this->server, $command);
    }

    protected function validateActionTarget(string $target): string
    {
        $target = $this->validateManualTarget($target);

        $found = false;
        foreach ($this->players as $entry) {
            if (strcasecmp((string) ($entry['name'] ?? ''), $target) === 0) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new RuntimeException(
                'Player is no longer in the current online player list. Refresh players and try again.'
            );
        }

        return $target;
    }

    protected function validateManualTarget(string $target): string
    {
        $target = trim($target);

        if ($target === '') {
            throw new RuntimeException('Player name or ID is required.');
        }

        if ($this->containsControlCharacters($target) || str_contains($target, ',')) {
            throw new RuntimeException('Player name or ID contains invalid characters.');
        }

        return $target;
    }

    protected function cleanReason(string $reason, string $default): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            return $default;
        }

        if ($this->containsControlCharacters($reason) || str_contains($reason, ',')) {
            throw new RuntimeException('Reasons must be a single line and cannot contain commas.');
        }

        return $reason;
    }

    protected function containsControlCharacters(string $value): bool
    {
        return str_contains($value, "\n")
            || str_contains($value, "\r")
            || str_contains($value, "\0");
    }

    protected function notifyDuplicate(string $list, string $target): void
    {
        Notification::make()
            ->title('Already Present')
            ->body($target . ' is already in ' . $list . '.')
            ->warning()
            ->send();
    }

    protected function notifyError(string $title, Throwable $exception): void
    {
        Notification::make()
            ->title($title)
            ->body($exception->getMessage())
            ->danger()
            ->send();
    }

    public function getBanTimeRemaining(string $expires): string
    {
        $expires = trim($expires);

        if ($expires === '' || strtolower($expires) === 'unknown') {
            return 'Unknown / Permanent';
        }

        try {
            $timezone = new \DateTimeZone('America/Chicago');

            $expiration = \DateTimeImmutable::createFromFormat(
                'm/d/Y H:i:s',
                $expires,
                $timezone
            );

            if (!$expiration) {
                return 'Unknown';
            }

            $now = new \DateTimeImmutable('now', $timezone);

            if ($expiration <= $now) {
                return 'Expired';
            }

            $seconds = $expiration->getTimestamp() - $now->getTimestamp();
            $days = intdiv($seconds, 86400);
            $seconds %= 86400;
            $hours = intdiv($seconds, 3600);
            $seconds %= 3600;
            $minutes = intdiv($seconds, 60);

            $parts = [];
            if ($days > 0) $parts[] = $days . ' ' . ($days === 1 ? 'day' : 'days');
            if ($hours > 0) $parts[] = $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');
            if ($minutes > 0) $parts[] = $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes');

            return empty($parts)
                ? 'Less than 1 minute'
                : implode(' ', array_slice($parts, 0, 2));
        } catch (Throwable) {
            return 'Unknown';
        }
    }

    protected function getEnvironmentVariable(string $environmentVariable, mixed $default = null): mixed
    {
        $eggVariable = EggVariable::query()
            ->where('egg_id', $this->server->egg_id)
            ->where('env_variable', $environmentVariable)
            ->first();

        if (!$eggVariable) {
            return $default;
        }

        $serverVariable = ServerVariable::query()
            ->where('server_id', $this->server->id)
            ->where('variable_id', $eggVariable->id)
            ->first();

        if (!$serverVariable || $serverVariable->variable_value === '') {
            return $eggVariable->default_value ?? $default;
        }

        return $serverVariable->variable_value;
    }

    protected function hasEnvironmentVariableValue(string $environmentVariable): bool
    {
        $value = $this->getEnvironmentVariable($environmentVariable, '');

        return is_string($value)
            ? trim($value) !== ''
            : !empty($value);
    }

    public function getGameAddress(): string
    {
        if (empty($this->eco['ip']) || empty($this->ports['game'])) {
            return 'Unavailable';
        }

        return $this->eco['ip'] . ':' . $this->ports['game'];
    }

    public function getWebAddress(): string
    {
        if (empty($this->eco['ip']) || empty($this->ports['web'])) {
            return 'Unavailable';
        }

        return 'http://' . $this->eco['ip'] . ':' . $this->ports['web'];
    }
}
