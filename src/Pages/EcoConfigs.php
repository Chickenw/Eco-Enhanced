<?php

namespace EcoEnhanced\Pages;

use App\Models\EggVariable;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Repositories\Daemon\DaemonFileRepository;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;
use Throwable;

class EcoConfigs extends Page
{
    protected static ?int $navigationSort = 2;

    protected string $view =
        'eco-enhanced::pages.eco-configs';

    public Server $server;

    // ---------------------------------------------------------------------
    // Config snapshots
    // ---------------------------------------------------------------------

    protected function ensureDaemonDirectory(
        string $name,
        string $parent
    ): void {
        try {
            $this->fileRepo()->createDirectory(
                $name,
                $parent
            );
        } catch (Throwable) {
            /*
             * Wings rejects creation when the directory already exists.
             * That's fine for our ensure operation.
             */
        }
    }

    public function refreshConfigSnapshots(): void
    {
        try {
            $this->ensureDaemonDirectory(
                '.eco-enhanced',
                '/'
            );

            $this->ensureDaemonDirectory(
                'config-backups',
                '/.eco-enhanced'
            );

            $entries = $this->fileRepo()
                ->getDirectory('/.eco-enhanced/config-backups');

            $snapshots = [];

            foreach ($entries as $entry) {
                if (is_object($entry)) {
                    $entry = (array) $entry;
                }

                if (!is_array($entry)) {
                    continue;
                }

                $name = trim(
                    (string) (
                        $entry['name']
                        ?? $entry['Name']
                        ?? ''
                    )
                );

                if (
                    preg_match(
                        '/^snapshot-\d{8}-\d{6}$/',
                        $name
                    ) !== 1
                ) {
                    continue;
                }

                $snapshots[] = $name;
            }

            rsort(
                $snapshots,
                SORT_NATURAL
            );

            $this->configSnapshots = $snapshots;

            if (
                $this->selectedConfigSnapshot !== '' &&
                !in_array(
                    $this->selectedConfigSnapshot,
                    $snapshots,
                    true
                )
            ) {
                $this->selectedConfigSnapshot = '';
            }

        } catch (Throwable $exception) {
            $this->configSnapshots = [];

            Notification::make()
                ->title('Unable to Read Config Snapshots')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function createConfigSnapshot(): void
    {
        try {
            /*
             * Reuse the proven raw-editor discovery logic so this captures
             * every .eco file, including mod-created configs.
             */
            $this->refreshRawEcoFiles();

            if (count($this->rawEcoFiles) === 0) {
                throw new RuntimeException(
                    'No .eco configuration files were found.'
                );
            }

            $this->ensureDaemonDirectory(
                '.eco-enhanced',
                '/'
            );

            $this->ensureDaemonDirectory(
                'config-backups',
                '/.eco-enhanced'
            );

            $snapshot =
                'snapshot-' .
                now()->format('Ymd-His');

            $this->ensureDaemonDirectory(
                $snapshot,
                '/.eco-enhanced/config-backups'
            );

            $manifest = [
                'created_at' => now()->toIso8601String(),
                'files' => [],
            ];

            foreach ($this->rawEcoFiles as $file) {
                $file = $this->validateRawEcoFilename(
                    $file
                );

                $content = $this->fileRepo()
                    ->getContent(
                        'Configs/' . $file,
                        4 * 1024 * 1024
                    );

                $this->fileRepo()->putContent(
                    '.eco-enhanced/config-backups/' .
                    $snapshot .
                    '/' .
                    $file,
                    $content
                );

                $manifest['files'][] = $file;
            }

            $this->fileRepo()->putContent(
                '.eco-enhanced/config-backups/' .
                $snapshot .
                '/manifest.json',
                json_encode(
                    $manifest,
                    JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_SLASHES
                ) . PHP_EOL
            );

            $this->refreshConfigSnapshots();

            $this->selectedConfigSnapshot =
                $snapshot;

            Notification::make()
                ->title('Config Snapshot Created')
                ->body(
                    count($manifest['files']) .
                    ' .eco file(s) were backed up.'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Config Snapshot Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function validateConfigSnapshot(
        string $snapshot
    ): string {
        $snapshot = trim($snapshot);

        if (
            preg_match(
                '/^snapshot-\d{8}-\d{6}$/',
                $snapshot
            ) !== 1
        ) {
            throw new RuntimeException(
                'Select a valid Eco Enhanced config snapshot.'
            );
        }

        return $snapshot;
    }

    public function restoreConfigSnapshot(): void
    {
        try {
            $snapshot =
                $this->validateConfigSnapshot(
                    $this->selectedConfigSnapshot
                );

            $manifestContent =
                $this->fileRepo()->getContent(
                    '.eco-enhanced/config-backups/' .
                    $snapshot .
                    '/manifest.json',
                    1024 * 1024
                );

            $manifest = json_decode(
                $manifestContent,
                true
            );

            $files = $manifest['files'] ?? [];

            if (!is_array($files) || count($files) === 0) {
                throw new RuntimeException(
                    'This config snapshot does not contain a valid manifest.'
                );
            }

            $restored = 0;

            foreach ($files as $file) {
                $file = trim((string) $file);

                if (
                    $file === '' ||
                    basename($file) !== $file ||
                    !str_ends_with(
                        strtolower($file),
                        '.eco'
                    )
                ) {
                    continue;
                }

                $content =
                    $this->fileRepo()->getContent(
                        '.eco-enhanced/config-backups/' .
                        $snapshot .
                        '/' .
                        $file,
                        4 * 1024 * 1024
                    );

                $this->fileRepo()->putContent(
                    'Configs/' . $file,
                    $content
                );

                $restored++;
            }

            /*
             * Refresh all friendly UI sections after restoration.
             */
            $this->loadNetworkSettings();
            $this->loadDifficultySettings();
            $this->loadWorldGeneratorSettings();
            $this->loadExhaustionSettings();
            $this->loadUsersSettings();
            $this->loadDiscordLinkSettings();

            /*
             * Refresh raw editor if it is currently open on a restored file.
             */
            $this->refreshRawEcoFiles();

            if (
                $this->rawEcoSelectedFile !== '' &&
                in_array(
                    $this->rawEcoSelectedFile,
                    $this->rawEcoFiles,
                    true
                )
            ) {
                $this->loadRawEcoFile();
            }

            Notification::make()
                ->title('Config Snapshot Restored')
                ->body(
                    $restored .
                    ' .eco file(s) were restored from ' .
                    $snapshot .
                    '. Restart Eco if required.'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Config Restore Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    // ---------------------------------------------------------------------
    // Raw .eco file editor
    // ---------------------------------------------------------------------

    public array $rawEcoFiles = [];
    public string $rawEcoSelectedFile = '';
    public string $rawEcoContent = '';
    public string $rawEcoOriginalContent = '';
    public string $rawEcoJsonStatus = '';
    public bool $rawEcoJsonValid = true;

    // ---------------------------------------------------------------------
    // Native Eco configuration
    // ---------------------------------------------------------------------

    // Network.eco
    public bool $networkPublicServer = false;
    public string $networkPlaytime = '';
    public string $networkDiscordAddress = '';
    public string $networkPassword = '';
    public string $networkName = '';
    public string $networkDescription = '';
    public string $networkServerCategory = 'None';
    public string $networkIpAddress = 'Any';
    public string $networkRemoteAddress = '';
    public string $networkWebServerUrl = '';
    public string $networkGamePort = '';
    public string $networkWebPort = '';
    public string $networkRconPort = '';
    public string $networkSteamPort = '';
    public string $networkRconIpAddress = 'Any';
    public string $networkRconPassword = '';
    public string $networkRate = '20';
    public string $networkDefaultSlots = '-1';
    public string $networkReservedSlots = '5';
    public string $networkMaxLoadingUsers = '20';
    public bool $networkUpnpEnabled = false;
    public string $networkRelayAddress = '';

    // Pelican Eco server authentication
    public string $ecoServerToken = '';

    // Difficulty.eco
    public string $difficultyDesiredPlayers = '4';
    public string $difficultyHoursPlayedPerDay = '3';
    public string $difficultyCollaborationLevel = 'MediumCollaboration';
    public string $difficultyGameSpeed = 'Normal';
    public string $difficultyAnimalBehavior = 'AttackNormally';
    public string $difficultySimulationLevel = 'Normal';

    public bool $difficultyExhaustionEnabled = true;
    public bool $difficultyHasMeteor = true;
    public bool $difficultyAllowFriends = true;
    public bool $difficultyGenerateRandomWorld = false;

    public string $difficultyMeteorDays = '30';
    public string $difficultyMaxProfessions = '10';
    public string $difficultyMaxSpecialties = '33';
    public string $difficultySkillCostMultiplier = '1';
    public string $difficultyAdditionalSpecialtyCost = '0';
    public string $difficultyCraftResourceMultiplier = '1';
    public string $difficultyCraftTimeMultiplier = '1';
    public string $difficultyClaimStakes = '0';
    public string $difficultyClaimPapers = '0';

    public bool $difficultyCanAbandonSpecialties = true;
    public bool $difficultyAreaBonusRequiresProfession = true;
    public string $difficultyAreaBonusMinProfession = '1';
    public string $difficultySpecialtyRefundPercentage = '0';
    public string $difficultyCharacterExpWithSpecialty = '0';

    public string $difficultyStackSizeMultiplier = '1';
    public string $difficultyWeightMultiplier = '1';
    public string $difficultyFuelEfficiencyMultiplier = '1';
    public string $difficultyGrowthRateMultiplier = '1';
    public string $difficultyConnectionRangeMultiplier = '1';
    public string $difficultyShelfLifeMultiplier = '1';

    public bool $difficultyAllowDeepOceanBuilding = false;
    public bool $difficultyRequireSkillsToReplaceParts = true;
    public bool $difficultyBrokenPartsDisableVehicles = true;

    public string $difficultyAnimalAttackFrequencyMultiplier = '1';

    public string $difficultyEndgameCraftCost = 'Normal';
    public string $difficultySkillbookCraftCost = 'Normal';
    public bool $difficultyPlayerCanDrown = true;

    // WorldGenerator.eco
    public string $worldMapSize = '72';

    // Exhaustion.eco
    public string $exhaustionRefreshHour = '0';
    public string $exhaustionRefreshMinute = '0';

    public string $exhaustionMondayHours = '3';
    public string $exhaustionTuesdayHours = '3';
    public string $exhaustionWednesdayHours = '3';
    public string $exhaustionThursdayHours = '3';
    public string $exhaustionFridayHours = '6';
    public string $exhaustionSaturdayHours = '6';
    public string $exhaustionSundayHours = '6';

    public bool $exhaustionAllowPlaytimeSaving = true;
    public string $exhaustionMaxSavedHours = '15';
    public bool $exhaustionPauseOnRest = true;
    public string $exhaustionBonusHours = '2';
    public bool $exhaustionRetroactiveBonus = true;

    // Config snapshots
    public array $configSnapshots = [];
    public string $selectedConfigSnapshot = '';

    public function refreshAllGameSettings(): void
    {
        $this->loadNetworkSettings();
        $this->loadDifficultySettings();
        $this->loadWorldGeneratorSettings();
        $this->loadExhaustionSettings();

        Notification::make()
            ->title('Game Configuration Refreshed')
            ->body(
                'Network.eco, Difficulty.eco, WorldGenerator.eco, and the Eco server token were reloaded.'
            )
            ->success()
            ->send();
    }

    public function saveAllGameSettings(): void
    {
        try {
            /*
             * Each existing save method already validates and preserves
             * unrelated config fields. Run them in sequence so the same
             * behavior is used by the combined Save All button.
             */
            $this->saveNetworkSettings();
            $this->saveDifficultySettings();
            $this->saveWorldGeneratorSettings();

            Notification::make()
                ->title('Game Configuration Saved')
                ->body(
                    'Network, difficulty, world size, and server authentication settings were saved.'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to Save Game Configuration')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    // ---------------------------------------------------------------------
    // Users.eco
    // ---------------------------------------------------------------------

    public string $adminCommandsLoggingLevel = 'LogFile';

    protected function defaultDiscordConnectionInfo(): string
    {
        try {
            $this->server->loadMissing('allocation');

            $host = trim(
                (string) (
                    $this->server->allocation?->ip
                    ?? ''
                )
            );

            $port = (int) (
                $this->server->allocation?->port
                ?? 0
            );

            if ($host === '') {
                return '';
            }

            return $port > 0
                ? $host . ':' . $port
                : $host;

        } catch (Throwable) {
            return '';
        }
    }

    // ---------------------------------------------------------------------
    // DiscordLink.eco
    // ---------------------------------------------------------------------

    public bool $discordLinkInstalled = false;

    public string $discordBotToken = '';
    public string $discordServerId = '';
    public bool $discordServerOwnerIsAdmin = false;
    public string $discordAdminRoles = '';

    public string $discordServerName = '';
    public string $discordServerDescription = '';
    public string $discordConnectionInfo = '';

    public string $discordChatSyncMode = 'OptOut';
    public bool $discordEnableBotStatus = false;

    public string $discordInviteMessage = '';
    public string $discordLogLevel = 'Information';
    public string $discordBackendLogLevel = 'None';
    public bool $discordEnableTraceFileLogging = false;
    public bool $discordUseVerboseDisplay = false;

    public bool $discordUseLinkedAccountRole = true;
    public bool $discordUseDemographicRoles = true;
    public bool $discordUseSpecialtyRoles = true;
    public bool $discordUseElectedTitleRoles = true;

    public string $discordEmbedColorHex = '#7289da';

    public int $discordMaxTradeWatcherDisplaysPerUser = 5;
    public bool $discordUseTradeWatcherFeeds = false;

    public array $discordChatChannelLinks = [];

    public array $discordTradeFeedChannels = [];
    public array $discordCraftingFeedChannels = [];
    public array $discordServerStatusFeedChannels = [];
    public array $discordPlayerStatusFeedChannels = [];
    public array $discordElectionFeedChannels = [];
    public array $discordServerLogFeedChannels = [];

    public array $discordServerInfoDisplayChannels = [];

    public function mount(): void
    {
        $server = Filament::getTenant();

        abort_unless($server instanceof Server, 404);
        abort_unless(static::isEcoServer($server), 404);

        $this->server = $server;

        $this->loadNetworkSettings();
        $this->loadDifficultySettings();
        $this->loadWorldGeneratorSettings();
        $this->loadExhaustionSettings();

        $this->loadUsersSettings();
        $this->loadDiscordLinkSettings();

        /*
         * Populate the Raw .eco Editor immediately when Eco Configs opens.
         *
         * Previously the raw editor remained empty until the user manually
         * clicked "Refresh File List".
         */
        $this->refreshRawEcoFiles();
    }

    public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        return $server instanceof Server
            && static::isEcoServer($server);
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function getNavigationLabel(): string
    {
        return 'Eco Configs';
    }

    public function getTitle(): string
    {
        return 'Eco Configs';
    }

    protected static function isEcoServer(Server $server): bool
    {
        $server->loadMissing('egg');

        return $server->egg
            && str_contains(
                strtolower($server->egg->name),
                'eco'
            );
    }

    // ---------------------------------------------------------------------
    // Raw .eco file editor
    // ---------------------------------------------------------------------

    public function refreshRawEcoFiles(): void
    {
        try {
            $repo = $this->fileRepo();

            $entries = $repo->getDirectory('/Configs');

            $files = [];

            foreach ($entries as $entry) {
                /*
                 * Daemon directory responses can be arrays or objects
                 * depending on the repository/response version.
                 */
                if (is_object($entry)) {
                    $entry = (array) $entry;
                }

                if (!is_array($entry)) {
                    continue;
                }

                $name = trim(
                    (string) (
                        $entry['name']
                        ?? $entry['Name']
                        ?? ''
                    )
                );

                if ($name === '') {
                    continue;
                }

                if (!str_ends_with(strtolower($name), '.eco')) {
                    continue;
                }

                /*
                 * Only permit plain filenames from /Configs.
                 * Never allow traversal or nested arbitrary paths.
                 */
                if (
                    basename($name) !== $name ||
                    str_contains($name, '/') ||
                    str_contains($name, '\\')
                ) {
                    continue;
                }

                $files[] = $name;
            }

            natcasesort($files);

            $this->rawEcoFiles = array_values(
                array_unique($files)
            );

            /*
             * Preserve current selection where possible.
             */
            if (
                $this->rawEcoSelectedFile !== '' &&
                in_array(
                    $this->rawEcoSelectedFile,
                    $this->rawEcoFiles,
                    true
                )
            ) {
                return;
            }

            $this->rawEcoSelectedFile =
                $this->rawEcoFiles[0] ?? '';

        } catch (Throwable $exception) {
            $this->rawEcoFiles = [];

            Notification::make()
                ->title('Unable to Read Config Directory')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function validateRawEcoFilename(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException(
                'Select an .eco configuration file.'
            );
        }

        if (
            basename($name) !== $name ||
            str_contains($name, '/') ||
            str_contains($name, '\\') ||
            !str_ends_with(strtolower($name), '.eco')
        ) {
            throw new RuntimeException(
                'Invalid Eco configuration filename.'
            );
        }

        if (
            !in_array(
                $name,
                $this->rawEcoFiles,
                true
            )
        ) {
            throw new RuntimeException(
                'The selected Eco configuration file is no longer available.'
            );
        }

        return $name;
    }

    public function openRawEcoEditor(string $file): void
    {
        $file = trim($file);

        /*
         * Refresh discovery first so mod-created .eco files are also
         * available and validated.
         */
        $this->refreshRawEcoFiles();

        $file = $this->validateRawEcoFilename($file);

        $this->rawEcoSelectedFile = $file;

        /*
         * Load the selected file before opening the modal.
         * This prevents the modal from showing the previously selected file.
         */
        $content = $this->fileRepo()
            ->getContent(
                'Configs/' . $file,
                4 * 1024 * 1024
            );

        $this->rawEcoContent = $content;
        $this->rawEcoOriginalContent = $content;

        $this->validateRawEcoJson(false);

        $this->dispatch(
            'eco-enhanced-open-raw-editor'
        );
    }

    public function loadRawEcoFile(): void
    {
        try {
            /*
             * Refresh the directory first so files installed by mods
             * immediately become available.
             */
            $selected = $this->rawEcoSelectedFile;

            $this->refreshRawEcoFiles();

            if (
                $selected !== '' &&
                in_array($selected, $this->rawEcoFiles, true)
            ) {
                $this->rawEcoSelectedFile = $selected;
            }

            $name = $this->validateRawEcoFilename(
                $this->rawEcoSelectedFile
            );

            $content = $this->fileRepo()
                ->getContent(
                    'Configs/' . $name,
                    4 * 1024 * 1024
                );

            $this->rawEcoContent = $content;
            $this->rawEcoOriginalContent = $content;

            $this->validateRawEcoJson(false);

            $this->dispatch(
                'eco-enhanced-raw-loaded',
                content: $this->rawEcoContent
            );

            Notification::make()
                ->title('Configuration Loaded')
                ->body($name . ' was loaded from the server.')
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to Load Configuration')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function updatedRawEcoSelectedFile(): void
    {
        if ($this->rawEcoSelectedFile === '') {
            $this->rawEcoContent = '';
            $this->rawEcoOriginalContent = '';
            $this->rawEcoJsonStatus = '';
            $this->rawEcoJsonValid = true;

            return;
        }

        $this->loadRawEcoFile();
    }

    public function updatedRawEcoContent(): void
    {
        $this->validateRawEcoJson(false);
    }

    protected function validateRawEcoJson(
        bool $throw = true
    ): bool {
        $content = trim($this->rawEcoContent);

        if ($content === '') {
            $this->rawEcoJsonValid = false;
            $this->rawEcoJsonStatus =
                'The configuration file cannot be empty.';

            if ($throw) {
                throw new RuntimeException(
                    $this->rawEcoJsonStatus
                );
            }

            return false;
        }

        json_decode($content);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->rawEcoJsonValid = false;
            $this->rawEcoJsonStatus =
                'Invalid JSON: ' .
                json_last_error_msg();

            if ($throw) {
                throw new RuntimeException(
                    $this->rawEcoJsonStatus
                );
            }

            return false;
        }

        $this->rawEcoJsonValid = true;
        $this->rawEcoJsonStatus = 'Valid JSON';

        return true;
    }

    public function saveRawEcoFile(): void
    {
        try {
            $name = $this->validateRawEcoFilename(
                $this->rawEcoSelectedFile
            );

            $this->validateRawEcoJson();

            /*
             * Reformat the JSON before writing it. This keeps .eco files
             * readable even after manual editing.
             *
             * Decode as an object rather than an associative array so empty
             * Eco objects remain {} instead of accidentally becoming [].
             */
            $decoded = json_decode(
                $this->rawEcoContent
            );

            if (
                $decoded === null &&
                trim($this->rawEcoContent) !== 'null'
            ) {
                throw new RuntimeException(
                    'Unable to decode the configuration JSON.'
                );
            }

            $json = json_encode(
                $decoded,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_UNICODE
            );

            if ($json === false) {
                throw new RuntimeException(
                    'Unable to encode the configuration JSON.'
                );
            }

            $this->fileRepo()->putContent(
                'Configs/' . $name,
                $json . PHP_EOL
            );

            $this->rawEcoContent =
                $json . PHP_EOL;

            $this->rawEcoOriginalContent =
                $this->rawEcoContent;

            $this->dispatch(
                'eco-enhanced-raw-loaded',
                content: $this->rawEcoContent
            );

            $this->rawEcoJsonValid = true;
            $this->rawEcoJsonStatus = 'Valid JSON';

            /*
             * Reload our friendly editors if one of their files was
             * changed manually.
             */
            if ($name === 'Network.eco') {
                $this->loadNetworkSettings();
            }

            if ($name === 'Difficulty.eco') {
                $this->loadDifficultySettings();
            }

            if ($name === 'WorldGenerator.eco') {
                $this->loadWorldGeneratorSettings();
            }

            if ($name === 'Users.eco') {
                $this->loadUsersSettings();
            }

            if ($name === 'DiscordLink.eco') {
                $this->loadDiscordLinkSettings();
            }

            Notification::make()
                ->title('Configuration Saved')
                ->body(
                    'Configs/' . $name .
                    ' was saved successfully.'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Configuration Not Saved')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function revertRawEcoFile(): void
    {
        $this->rawEcoContent =
            $this->rawEcoOriginalContent;

        $this->validateRawEcoJson(false);

        $this->dispatch(
            'eco-enhanced-raw-loaded',
            content: $this->rawEcoContent
        );

        Notification::make()
            ->title('Unsaved Changes Reverted')
            ->success()
            ->send();
    }

    // ---------------------------------------------------------------------
    // Generic config helpers
    // ---------------------------------------------------------------------

    protected function fileRepo(): DaemonFileRepository
    {
        return app(DaemonFileRepository::class)
            ->setServer($this->server);
    }

    protected function readEcoConfig(string $name): array
    {
        $contents = $this->fileRepo()
            ->getContent(
                'Configs/' . $name,
                4 * 1024 * 1024
            );

        $data = json_decode($contents, true);

        if (!is_array($data)) {
            throw new RuntimeException(
                'Configs/' . $name . ' is not valid JSON.'
            );
        }

        return $data;
    }

    protected function writeEcoConfig(
        string $name,
        array $data
    ): void {
        /*
         * Users.eco contains ThreadSafeAction objects.
         *
         * json_decode(..., true) converts empty {} objects into [],
         * so restore the known Eco event fields before encoding.
         */
        if ($name === 'Users.eco') {
            foreach ([
                'WhiteList',
                'BlackList',
                'MuteList',
                'Admins',
                'UsersWithReservedSlotsAtQueue',
            ] as $permissionList) {
                if (
                    !isset($data['UserPermission'][$permissionList]) ||
                    !is_array(
                        $data['UserPermission'][$permissionList]
                    )
                ) {
                    continue;
                }

                $data['UserPermission'][$permissionList]
                    ['UserIDAddedEvent'] = new stdClass();

                $data['UserPermission'][$permissionList]
                    ['UserIDRemovedEvent'] = new stdClass();
            }
        }

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            throw new RuntimeException(
                'Unable to encode Configs/' . $name . '.'
            );
        }

        $this->fileRepo()->putContent(
            'Configs/' . $name,
            $json . PHP_EOL
        );
    }

    // ---------------------------------------------------------------------
    // Native Eco config helpers
    // ---------------------------------------------------------------------

    protected function getEnvironmentVariable(
        string $environmentVariable,
        mixed $default = null
    ): mixed {
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

        if (
            !$serverVariable ||
            $serverVariable->variable_value === ''
        ) {
            return $eggVariable->default_value ?? $default;
        }

        return $serverVariable->variable_value;
    }

    protected function setEnvironmentVariable(
        string $environmentVariable,
        mixed $value
    ): bool {
        $eggVariable = EggVariable::query()
            ->where('egg_id', $this->server->egg_id)
            ->where('env_variable', $environmentVariable)
            ->first();

        if (!$eggVariable) {
            return false;
        }

        ServerVariable::query()->updateOrCreate(
            [
                'server_id' => $this->server->id,
                'variable_id' => $eggVariable->id,
            ],
            [
                'variable_value' => (string) $value,
            ]
        );

        return true;
    }

    protected function requiredInteger(
        string $value,
        string $label
    ): int {
        $value = trim($value);

        if (
            $value === '' ||
            preg_match('/^-?\d+$/', $value) !== 1
        ) {
            throw new RuntimeException(
                $label . ' must be a whole number.'
            );
        }

        return (int) $value;
    }

    protected function requiredNumber(
        string $value,
        string $label
    ): float {
        $value = trim($value);

        if ($value === '' || !is_numeric($value)) {
            throw new RuntimeException(
                $label . ' must be a number.'
            );
        }

        return (float) $value;
    }

    protected function requiredPort(
        string $value,
        string $label
    ): int {
        $port = $this->requiredInteger($value, $label);

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException(
                $label . ' must be between 1 and 65535.'
            );
        }

        return $port;
    }

    // ---------------------------------------------------------------------
    // Network.eco
    // ---------------------------------------------------------------------

    protected function loadNetworkSettings(): void
    {
        try {
            $data = $this->readEcoConfig('Network.eco');

            $this->networkPublicServer =
                (bool) ($data['PublicServer'] ?? false);

            $this->networkPlaytime =
                (string) ($data['Playtime'] ?? '');

            $this->networkDiscordAddress =
                (string) ($data['DiscordAddress'] ?? '');

            $this->networkPassword =
                (string) ($data['Password'] ?? '');

            $this->networkName =
                (string) ($data['Name'] ?? '');

            $this->networkDescription =
                (string) ($data['DetailedDescription'] ?? '');

            $this->networkServerCategory =
                (string) ($data['ServerCategory'] ?? 'None');

            $this->networkIpAddress =
                (string) ($data['IPAddress'] ?? 'Any');

            $this->networkRemoteAddress =
                (string) ($data['RemoteAddress'] ?? '');

            $this->networkWebServerUrl =
                (string) ($data['WebServerUrl'] ?? '');

            $this->networkGamePort =
                (string) ($data['GameServerPort'] ?? '');

            $this->networkWebPort =
                (string) ($data['WebServerPort'] ?? '');

            $this->networkRconPort =
                (string) ($data['RconServerPort'] ?? '');

            $this->networkSteamPort =
                (string) ($data['SteamServerPort'] ?? '');

            $this->networkRconIpAddress =
                (string) ($data['RconIPAddress'] ?? 'Any');

            $this->networkRconPassword =
                (string) ($data['RconPassword'] ?? '');

            $this->networkRate =
                (string) ($data['Rate'] ?? 20);

            $this->networkDefaultSlots =
                (string) ($data['DefaultSlots'] ?? -1);

            $this->networkReservedSlots =
                (string) ($data['ReservedSlots'] ?? 5);

            $this->networkMaxLoadingUsers =
                (string) (
                    $data['MaxUsersLoadingAtSameTime']
                    ?? 20
                );

            $this->networkUpnpEnabled =
                (bool) ($data['UPnPEnabled'] ?? false);

            $this->networkRelayAddress =
                (string) ($data['RelayServerAddress'] ?? '');

            $this->ecoServerToken =
                trim(
                    (string) $this->getEnvironmentVariable(
                        'ECO_AUTH_TOKEN',
                        ''
                    )
                );

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to Read Network.eco')
                ->body($exception->getMessage())
                ->warning()
                ->send();
        }
    }

    public function refreshNetworkSettings(): void
    {
        $this->loadNetworkSettings();

        Notification::make()
            ->title('Network Configuration Refreshed')
            ->success()
            ->send();
    }

    public function saveNetworkSettings(): void
    {
        try {
            $data = $this->readEcoConfig('Network.eco');

            $gamePort = $this->requiredPort(
                $this->networkGamePort,
                'Game Server Port'
            );

            $webPort = $this->requiredPort(
                $this->networkWebPort,
                'Web Server Port'
            );

            $rconPort = $this->requiredPort(
                $this->networkRconPort,
                'RCON Server Port'
            );

            $steamPort = $this->requiredPort(
                $this->networkSteamPort,
                'Steam Server Port'
            );

            $rate = $this->requiredInteger(
                $this->networkRate,
                'Rate'
            );

            $defaultSlots = $this->requiredInteger(
                $this->networkDefaultSlots,
                'Default Slots'
            );

            $reservedSlots = $this->requiredInteger(
                $this->networkReservedSlots,
                'Reserved Slots'
            );

            $maxLoading = $this->requiredInteger(
                $this->networkMaxLoadingUsers,
                'Max Users Loading At Same Time'
            );

            $data['PublicServer'] =
                $this->networkPublicServer;

            $data['Playtime'] =
                trim($this->networkPlaytime);

            $data['DiscordAddress'] =
                trim($this->networkDiscordAddress);

            $data['Password'] =
                $this->networkPassword;

            $data['Name'] =
                trim($this->networkName);

            $data['DetailedDescription'] =
                $this->networkDescription;

            $data['ServerCategory'] =
                trim($this->networkServerCategory);

            $data['IPAddress'] =
                trim($this->networkIpAddress) ?: 'Any';

            $data['RemoteAddress'] =
                trim($this->networkRemoteAddress);

            $data['WebServerUrl'] =
                trim($this->networkWebServerUrl);

            $data['GameServerPort'] = $gamePort;
            $data['WebServerPort'] = $webPort;
            $data['RconServerPort'] = $rconPort;
            $data['SteamServerPort'] = $steamPort;

            $data['RconIPAddress'] =
                trim($this->networkRconIpAddress) ?: 'Any';

            $data['RconPassword'] =
                $this->networkRconPassword;

            $data['Rate'] = $rate;
            $data['DefaultSlots'] = $defaultSlots;
            $data['ReservedSlots'] = $reservedSlots;

            $data['MaxUsersLoadingAtSameTime'] =
                $maxLoading;

            $data['UPnPEnabled'] =
                $this->networkUpnpEnabled;

            $data['RelayServerAddress'] =
                trim($this->networkRelayAddress);

            $this->writeEcoConfig(
                'Network.eco',
                $data
            );

            /*
             * Keep Pelican's Eco Egg values synchronized so a restart does
             * not overwrite values changed through Eco Configs.
             */
            $this->setEnvironmentVariable(
                'PUB_SRV',
                $this->networkPublicServer ? 'true' : 'false'
            );

            $this->setEnvironmentVariable(
                'PLAYTIME',
                trim($this->networkPlaytime)
            );

            $this->setEnvironmentVariable(
                'DISCORD_SRV',
                trim($this->networkDiscordAddress)
            );

            $this->setEnvironmentVariable(
                'SRV_PW',
                $this->networkPassword
            );

            $this->setEnvironmentVariable(
                'SRV_NAME',
                trim($this->networkName)
            );

            $this->setEnvironmentVariable(
                'DEDES',
                $this->networkDescription
            );

            $this->setEnvironmentVariable(
                'SRV_CAT',
                trim($this->networkServerCategory)
            );

            $this->setEnvironmentVariable(
                'REMOTE_ADDRESS',
                trim($this->networkRemoteAddress)
            );

            $this->setEnvironmentVariable(
                'WEBSRVURL',
                trim($this->networkWebServerUrl)
            );

            $this->setEnvironmentVariable(
                'WEB_PORT',
                $webPort
            );

            $this->setEnvironmentVariable(
                'RCON_PORT',
                $rconPort
            );

            $this->setEnvironmentVariable(
                'STEAM_PORT',
                $steamPort
            );

            $this->setEnvironmentVariable(
                'RCON_PW',
                $this->networkRconPassword
            );

            $this->setEnvironmentVariable(
                'MAX_CON',
                $defaultSlots
            );

            $this->setEnvironmentVariable(
                'UPNP',
                $this->networkUpnpEnabled ? 'true' : 'false'
            );

            $token = trim($this->ecoServerToken);

            if ($token !== '') {
                $this->setEnvironmentVariable(
                    'ECO_AUTH_TOKEN',
                    $token
                );
            }

            $this->loadNetworkSettings();

            Notification::make()
                ->title('Network Configuration Saved')
                ->body(
                    'Network.eco and matching Eco server variables were updated. Restart Eco to apply startup-only settings.'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to Save Network Configuration')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    // ---------------------------------------------------------------------
    // Difficulty.eco
    // ---------------------------------------------------------------------

    protected function loadDifficultySettings(): void
    {
        try {
            $data = $this->readEcoConfig('Difficulty.eco');

            $game = $data['GameSettings'] ?? [];
            $advanced = $game['AdvancedGameSettings'] ?? [];

            $this->difficultyDesiredPlayers =
                (string) ($game['DesiredNumberOfPlayers'] ?? 4);

            $this->difficultyHoursPlayedPerDay =
                (string) ($game['HoursPlayedPerDay'] ?? 3);

            $this->difficultyCollaborationLevel =
                (string) (
                    $game['CollaborationLevel']
                    ?? 'MediumCollaboration'
                );

            $this->difficultyGameSpeed =
                (string) ($game['GameSpeed'] ?? 'Normal');

            $this->difficultyAnimalBehavior =
                (string) (
                    $game['AnimalBehavior']
                    ?? 'AttackNormally'
                );

            $this->difficultySimulationLevel =
                (string) (
                    $game['SimulationLevel']
                    ?? 'Normal'
                );

            $this->difficultyExhaustionEnabled =
                (bool) ($game['ExhaustionEnabled'] ?? true);

            $this->difficultyHasMeteor =
                (bool) ($game['HasMeteor'] ?? true);

            $this->difficultyAllowFriends =
                (bool) ($game['AllowFriendsToJoin'] ?? true);

            $this->difficultyGenerateRandomWorld =
                (bool) ($game['GenerateRandomWorld'] ?? false);

            $this->difficultyMeteorDays =
                (string) ($advanced['MeteorImpactInDays'] ?? 30);

            $this->difficultyMaxProfessions =
                (string) (
                    $advanced['MaxProfessionsPerCitizen']
                    ?? 10
                );

            $this->difficultyMaxSpecialties =
                (string) (
                    $advanced['MaxSpecialtiesPerCitizen']
                    ?? 33
                );

            $this->difficultySkillCostMultiplier =
                (string) (
                    $advanced['SkillCostMultiplier']
                    ?? 1
                );

            $this->difficultyAdditionalSpecialtyCost =
                (string) (
                    $advanced['CostPerAdditionalSpecialty']
                    ?? 0
                );

            $this->difficultyCraftResourceMultiplier =
                (string) (
                    $advanced['CraftResourceMultiplier']
                    ?? 1
                );

            $this->difficultyCraftTimeMultiplier =
                (string) (
                    $advanced['CraftTimeMultiplier']
                    ?? 1
                );

            $this->difficultyClaimStakes =
                (string) (
                    $advanced[
                        'ClaimStakesGrantedUponSkillscrollConsumed'
                    ] ?? 0
                );

            $this->difficultyClaimPapers =
                (string) (
                    $advanced[
                        'ClaimPapersGrantedUponSkillscrollConsumed'
                    ] ?? 0
                );

            $this->difficultyCanAbandonSpecialties =
                (bool) (
                    $advanced['CanAbandonSpecialties']
                    ?? true
                );

            $this->difficultyAreaBonusRequiresProfession =
                (bool) (
                    $advanced[
                        'AreaBonusRequiresProfessionCitizens'
                    ] ?? true
                );

            $this->difficultyAreaBonusMinProfession =
                (string) (
                    $advanced[
                        'AreaBonusMinProfessionCitizens'
                    ] ?? 1
                );

            $this->difficultySpecialtyRefundPercentage =
                (string) (
                    $advanced['SpecialtyRefundPercentage']
                    ?? 0
                );

            $this->difficultyCharacterExpWithSpecialty =
                (string) (
                    $advanced[
                        'GainCharacterExperienceWithSpecialtyExperience'
                    ] ?? 0
                );

            $this->difficultyStackSizeMultiplier =
                (string) (
                    $advanced['StackSizeMultiplier']
                    ?? 1
                );

            $this->difficultyWeightMultiplier =
                (string) (
                    $advanced['WeightMultiplier']
                    ?? 1
                );

            $this->difficultyFuelEfficiencyMultiplier =
                (string) (
                    $advanced['FuelEfficiencyMultiplier']
                    ?? 1
                );

            $this->difficultyGrowthRateMultiplier =
                (string) (
                    $advanced['GrowthRateMultiplier']
                    ?? 1
                );

            $this->difficultyConnectionRangeMultiplier =
                (string) (
                    $advanced['ConnectionRangeMultiplier']
                    ?? 1
                );

            $this->difficultyShelfLifeMultiplier =
                (string) (
                    $advanced['ShelfLifeMultiplier']
                    ?? 1
                );

            $this->difficultyAllowDeepOceanBuilding =
                (bool) (
                    $advanced['AllowDeepOceanBuilding']
                    ?? false
                );

            $this->difficultyRequireSkillsToReplaceParts =
                (bool) (
                    $advanced['RequireSkillsToReplaceParts']
                    ?? true
                );

            $this->difficultyBrokenPartsDisableVehicles =
                (bool) (
                    $advanced['BrokenPartsWillDisableVehicles']
                    ?? true
                );

            $this->difficultyAnimalAttackFrequencyMultiplier =
                (string) (
                    $advanced[
                        'AnimalUnprovokedAttackFrequencyMultiplier'
                    ] ?? 1
                );

            $this->difficultyEndgameCraftCost =
                (string) ($data['EndgameCraftCost'] ?? 'Normal');

            $this->difficultySkillbookCraftCost =
                (string) ($data['SkillbookCraftCost'] ?? 'Normal');

            $this->difficultyPlayerCanDrown =
                (bool) (
                    $data['PlayerCanDrownWhenSwimming']
                    ?? true
                );

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to Read Difficulty.eco')
                ->body($exception->getMessage())
                ->warning()
                ->send();
        }
    }

    public function refreshDifficultySettings(): void
    {
        $this->loadDifficultySettings();

        Notification::make()
            ->title('Difficulty Configuration Refreshed')
            ->success()
            ->send();
    }

    public function saveDifficultySettings(): void
    {
        try {
            $data = $this->readEcoConfig('Difficulty.eco');

            $data['GameSettings'] ??= [];
            $data['GameSettings']['AdvancedGameSettings'] ??= [];

            $game =& $data['GameSettings'];
            $advanced =& $game['AdvancedGameSettings'];

            $game['DesiredNumberOfPlayers'] =
                $this->requiredInteger(
                    $this->difficultyDesiredPlayers,
                    'Desired Number Of Players'
                );

            $game['HoursPlayedPerDay'] =
                $this->requiredNumber(
                    $this->difficultyHoursPlayedPerDay,
                    'Hours Played Per Day'
                );

            $game['CollaborationLevel'] =
                trim($this->difficultyCollaborationLevel);

            $game['GameSpeed'] =
                trim($this->difficultyGameSpeed);

            $game['AnimalBehavior'] =
                trim($this->difficultyAnimalBehavior);

            $game['SimulationLevel'] =
                trim($this->difficultySimulationLevel);

            $game['ExhaustionEnabled'] =
                $this->difficultyExhaustionEnabled;

            $game['HasMeteor'] =
                $this->difficultyHasMeteor;

            $game['AllowFriendsToJoin'] =
                $this->difficultyAllowFriends;

            $game['GenerateRandomWorld'] =
                $this->difficultyGenerateRandomWorld;

            $advanced['MeteorImpactInDays'] =
                $this->requiredNumber(
                    $this->difficultyMeteorDays,
                    'Meteor Impact In Days'
                );

            $advanced['MaxProfessionsPerCitizen'] =
                $this->requiredNumber(
                    $this->difficultyMaxProfessions,
                    'Max Professions Per Citizen'
                );

            $advanced['MaxSpecialtiesPerCitizen'] =
                $this->requiredNumber(
                    $this->difficultyMaxSpecialties,
                    'Max Specialties Per Citizen'
                );

            $advanced['SkillCostMultiplier'] =
                $this->requiredNumber(
                    $this->difficultySkillCostMultiplier,
                    'Skill Cost Multiplier'
                );

            $advanced['CostPerAdditionalSpecialty'] =
                $this->requiredNumber(
                    $this->difficultyAdditionalSpecialtyCost,
                    'Cost Per Additional Specialty'
                );

            $advanced['CraftResourceMultiplier'] =
                $this->requiredNumber(
                    $this->difficultyCraftResourceMultiplier,
                    'Craft Resource Multiplier'
                );

            $advanced['CraftTimeMultiplier'] =
                $this->requiredNumber(
                    $this->difficultyCraftTimeMultiplier,
                    'Craft Time Multiplier'
                );

            $advanced[
                'ClaimStakesGrantedUponSkillscrollConsumed'
            ] = $this->requiredNumber(
                $this->difficultyClaimStakes,
                'Claim Stakes Granted'
            );

            $advanced[
                'ClaimPapersGrantedUponSkillscrollConsumed'
            ] = $this->requiredNumber(
                $this->difficultyClaimPapers,
                'Claim Papers Granted'
            );

            $advanced['CanAbandonSpecialties'] =
                $this->difficultyCanAbandonSpecialties;

            $advanced['AreaBonusRequiresProfessionCitizens'] =
                $this->difficultyAreaBonusRequiresProfession;

            $advanced['AreaBonusMinProfessionCitizens'] =
                $this->requiredInteger(
                    $this->difficultyAreaBonusMinProfession,
                    'Area Bonus Minimum Profession Citizens'
                );

            $advanced['SpecialtyRefundPercentage'] =
                $this->requiredNumber(
                    $this->difficultySpecialtyRefundPercentage,
                    'Specialty Refund Percentage'
                );

            $advanced[
                'GainCharacterExperienceWithSpecialtyExperience'
            ] = $this->requiredNumber(
                $this->difficultyCharacterExpWithSpecialty,
                'Character Experience With Specialty Experience'
            );

            $advanced['StackSizeMultiplier'] =
                $this->requiredNumber(
                    $this->difficultyStackSizeMultiplier,
                    'Stack Size Multiplier'
                );

            $advanced['WeightMultiplier'] =
                $this->requiredNumber(
                    $this->difficultyWeightMultiplier,
                    'Weight Multiplier'
                );

            $advanced['FuelEfficiencyMultiplier'] =
                $this->requiredNumber(
                    $this->difficultyFuelEfficiencyMultiplier,
                    'Fuel Efficiency Multiplier'
                );

            $advanced['GrowthRateMultiplier'] =
                $this->requiredNumber(
                    $this->difficultyGrowthRateMultiplier,
                    'Growth Rate Multiplier'
                );

            $advanced['ConnectionRangeMultiplier'] =
                $this->requiredNumber(
                    $this->difficultyConnectionRangeMultiplier,
                    'Connection Range Multiplier'
                );

            $advanced['ShelfLifeMultiplier'] =
                $this->requiredNumber(
                    $this->difficultyShelfLifeMultiplier,
                    'Shelf Life Multiplier'
                );

            $advanced['AllowDeepOceanBuilding'] =
                $this->difficultyAllowDeepOceanBuilding;

            $advanced['RequireSkillsToReplaceParts'] =
                $this->difficultyRequireSkillsToReplaceParts;

            $advanced['BrokenPartsWillDisableVehicles'] =
                $this->difficultyBrokenPartsDisableVehicles;

            $advanced[
                'AnimalUnprovokedAttackFrequencyMultiplier'
            ] = $this->requiredNumber(
                $this->difficultyAnimalAttackFrequencyMultiplier,
                'Animal Attack Frequency Multiplier'
            );

            $data['EndgameCraftCost'] =
                trim($this->difficultyEndgameCraftCost);

            $data['SkillbookCraftCost'] =
                trim($this->difficultySkillbookCraftCost);

            $data['PlayerCanDrownWhenSwimming'] =
                $this->difficultyPlayerCanDrown;

            $this->writeEcoConfig(
                'Difficulty.eco',
                $data
            );

            $this->loadDifficultySettings();

            Notification::make()
                ->title('Difficulty Configuration Saved')
                ->body(
                    'Difficulty.eco was updated. Restart Eco for startup-only settings.'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to Save Difficulty Configuration')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    // ---------------------------------------------------------------------
    // Exhaustion.eco
    // ---------------------------------------------------------------------

    protected function loadExhaustionSettings(): void
    {
        try {
            $data = $this->readEcoConfig(
                'Exhaustion.eco'
            );

            $this->exhaustionRefreshHour =
                (string) ($data['RefreshLocalHours'] ?? 0);

            $this->exhaustionRefreshMinute =
                (string) ($data['RefreshLocalMinutes'] ?? 0);

            $this->exhaustionMondayHours =
                (string) ($data['MondayExhaustionRefreshHours'] ?? 3);

            $this->exhaustionTuesdayHours =
                (string) ($data['TuesdayExhaustionRefreshHours'] ?? 3);

            $this->exhaustionWednesdayHours =
                (string) ($data['WednesdayExhaustionRefreshHours'] ?? 3);

            $this->exhaustionThursdayHours =
                (string) ($data['ThursdayExhaustionRefreshHours'] ?? 3);

            $this->exhaustionFridayHours =
                (string) ($data['FridayExhaustionRefreshHours'] ?? 6);

            $this->exhaustionSaturdayHours =
                (string) ($data['SaturdayExhaustionRefreshHours'] ?? 6);

            $this->exhaustionSundayHours =
                (string) ($data['SundayExhaustionRefreshHours'] ?? 6);

            $this->exhaustionAllowPlaytimeSaving =
                (bool) ($data['AllowPlaytimeSaving'] ?? true);

            $this->exhaustionMaxSavedHours =
                (string) ($data['MaxSavedHours'] ?? 15);

            $this->exhaustionPauseOnRest =
                (bool) ($data['AllowExhaustionPauseOnRest'] ?? true);

            $this->exhaustionBonusHours =
                (string) ($data['BonusHoursOnExhaustionEnabled'] ?? 2);

            $this->exhaustionRetroactiveBonus =
                (bool) ($data['BonusRetroactiveHoursAfterStart'] ?? true);

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to Read Exhaustion.eco')
                ->body($exception->getMessage())
                ->warning()
                ->send();
        }
    }

    public function refreshExhaustionSettings(): void
    {
        $this->loadExhaustionSettings();

        Notification::make()
            ->title('Exhaustion Configuration Refreshed')
            ->success()
            ->send();
    }

    public function saveExhaustionSettings(): void
    {
        try {
            $hour = $this->requiredInteger(
                $this->exhaustionRefreshHour,
                'Refresh Hour'
            );

            $minute = $this->requiredInteger(
                $this->exhaustionRefreshMinute,
                'Refresh Minute'
            );

            if ($hour < 0 || $hour > 23) {
                throw new RuntimeException(
                    'Refresh Hour must be between 0 and 23.'
                );
            }

            if ($minute < 0 || $minute > 59) {
                throw new RuntimeException(
                    'Refresh Minute must be between 0 and 59.'
                );
            }

            $data = $this->readEcoConfig(
                'Exhaustion.eco'
            );

            $data['RefreshLocalHours'] = (float) $hour;
            $data['RefreshLocalMinutes'] = (float) $minute;

            $days = [
                'MondayExhaustionRefreshHours' =>
                    $this->exhaustionMondayHours,

                'TuesdayExhaustionRefreshHours' =>
                    $this->exhaustionTuesdayHours,

                'WednesdayExhaustionRefreshHours' =>
                    $this->exhaustionWednesdayHours,

                'ThursdayExhaustionRefreshHours' =>
                    $this->exhaustionThursdayHours,

                'FridayExhaustionRefreshHours' =>
                    $this->exhaustionFridayHours,

                'SaturdayExhaustionRefreshHours' =>
                    $this->exhaustionSaturdayHours,

                'SundayExhaustionRefreshHours' =>
                    $this->exhaustionSundayHours,
            ];

            foreach ($days as $key => $value) {
                $hours = $this->requiredNumber(
                    $value,
                    $key
                );

                if ($hours < 0) {
                    throw new RuntimeException(
                        'Daily exhaustion hours cannot be negative.'
                    );
                }

                $data[$key] = $hours;
            }

            $data['AllowPlaytimeSaving'] =
                $this->exhaustionAllowPlaytimeSaving;

            $data['MaxSavedHours'] =
                $this->requiredNumber(
                    $this->exhaustionMaxSavedHours,
                    'Maximum Saved Hours'
                );

            $data['AllowExhaustionPauseOnRest'] =
                $this->exhaustionPauseOnRest;

            $data['BonusHoursOnExhaustionEnabled'] =
                $this->requiredNumber(
                    $this->exhaustionBonusHours,
                    'Bonus Hours'
                );

            $data['BonusRetroactiveHoursAfterStart'] =
                $this->exhaustionRetroactiveBonus;

            /*
             * Everything else — especially Vehicles — remains untouched.
             */
            $this->writeEcoConfig(
                'Exhaustion.eco',
                $data
            );

            $this->loadExhaustionSettings();

            Notification::make()
                ->title('Exhaustion Configuration Saved')
                ->body('Exhaustion.eco was updated.')
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to Save Exhaustion Configuration')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    // ---------------------------------------------------------------------
    // WorldGenerator.eco
    // ---------------------------------------------------------------------

    protected function loadWorldGeneratorSettings(): void
    {
        try {
            $data = $this->readEcoConfig(
                'WorldGenerator.eco'
            );

            $width = (int) (
                $data['Dimensions']['WorldWidth']
                ?? 72
            );

            $this->worldMapSize =
                in_array(
                    $width,
                    [72, 100, 140, 160, 200],
                    true
                )
                    ? (string) $width
                    : (string) $width;

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to Read WorldGenerator.eco')
                ->body($exception->getMessage())
                ->warning()
                ->send();
        }
    }

    public function refreshWorldGeneratorSettings(): void
    {
        $this->loadWorldGeneratorSettings();

        Notification::make()
            ->title('World Generator Configuration Refreshed')
            ->success()
            ->send();
    }

    public function saveWorldGeneratorSettings(): void
    {
        try {
            $size = $this->requiredInteger(
                $this->worldMapSize,
                'World Size'
            );

            if (
                !in_array(
                    $size,
                    [72, 100, 140, 160, 200],
                    true
                )
            ) {
                throw new RuntimeException(
                    'World Size must be 72, 100, 140, 160, or 200.'
                );
            }

            $data = $this->readEcoConfig(
                'WorldGenerator.eco'
            );

            /*
             * Preserve Eco's complete generator configuration. Eco Enhanced
             * deliberately edits dimensions only.
             */
            $data['Dimensions']['WorldWidth'] = $size;
            $data['Dimensions']['WorldLength'] = $size;

            $this->writeEcoConfig(
                'WorldGenerator.eco',
                $data
            );

            $this->loadWorldGeneratorSettings();

            Notification::make()
                ->title('World Size Saved')
                ->body(
                    $size .
                    ' x ' .
                    $size .
                    ' will be used the next time Eco generates a world.'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to Save World Size')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    // ---------------------------------------------------------------------
    // Users.eco
    // ---------------------------------------------------------------------

    protected function loadUsersSettings(): void
    {
        try {
            $data = $this->readEcoConfig('Users.eco');

            $value = trim(
                (string) (
                    $data['AdminCommandsLoggingLevel']
                    ?? ''
                )
            );

            $allowed = $this->allowedAdminLoggingLevels();

            $this->adminCommandsLoggingLevel =
                in_array($value, $allowed, true)
                    ? $value
                    : 'LogFile';

        } catch (Throwable $exception) {
            $this->adminCommandsLoggingLevel = 'LogFile';

            Notification::make()
                ->title('Unable to Read Users.eco')
                ->body($exception->getMessage())
                ->warning()
                ->send();
        }
    }

    public function saveUsersSettings(): void
    {
        if (
            !in_array(
                $this->adminCommandsLoggingLevel,
                $this->allowedAdminLoggingLevels(),
                true
            )
        ) {
            Notification::make()
                ->title('Invalid Logging Setting')
                ->danger()
                ->send();

            return;
        }

        try {
            $data = $this->readEcoConfig('Users.eco');

            $data['AdminCommandsLoggingLevel'] =
                $this->adminCommandsLoggingLevel;

            $this->writeEcoConfig(
                'Users.eco',
                $data
            );

            Notification::make()
                ->title('Eco Configuration Saved')
                ->body(
                    'Admin Command Logging was updated. ' .
                    'Restart Eco once for the change to take effect.'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to Save Eco Configuration')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function allowedAdminLoggingLevels(): array
    {
        return [
            'LogFile',
            'LogFileAndNotifyAdmins',
            'LogFileAndNotifyEveryone',
            'None',
        ];
    }

    // ---------------------------------------------------------------------
    // DiscordLink.eco
    // ---------------------------------------------------------------------

    protected function loadDiscordLinkSettings(): void
    {
        try {
            $data = $this->readEcoConfig(
                'DiscordLink.eco'
            );

            $this->discordLinkInstalled = true;

            $this->discordBotToken =
                (string) ($data['BotToken'] ?? '');

            $serverId = $data['DiscordServerId'] ?? 0;

            $this->discordServerId =
                ((string) $serverId === '0')
                    ? ''
                    : trim((string) $serverId);

            $this->discordServerOwnerIsAdmin =
                (bool) (
                    $data['DiscordServerOwnerIsAdmin']
                    ?? false
                );

            $roles = $data['AdminRoles'] ?? [];

            if (!is_array($roles)) {
                $roles = [];
            }

            $this->discordAdminRoles =
                implode(
                    PHP_EOL,
                    array_values(
                        array_filter(
                            array_map(
                                fn ($role) =>
                                    trim((string) $role),
                                $roles
                            ),
                            fn ($role) => $role !== ''
                        )
                    )
                );

            $this->discordServerName =
                (string) ($data['ServerName'] ?? '');

            $this->discordServerDescription =
                (string) (
                    $data['ServerDescription']
                    ?? ''
                );

            $this->discordConnectionInfo =
                trim(
                    (string) (
                        $data['ConnectionInfo']
                        ?? ''
                    )
                );

            if ($this->discordConnectionInfo === '') {
                $this->discordConnectionInfo =
                    $this->defaultDiscordConnectionInfo();
            }

            $syncMode = trim(
                (string) (
                    $data['ChatSyncMode']
                    ?? 'OptOut'
                )
            );

            $this->discordChatSyncMode =
                in_array(
                    $syncMode,
                    ['OptOut', 'OptIn'],
                    true
                )
                    ? $syncMode
                    : 'OptOut';

            $this->discordEnableBotStatus =
                (bool) (
                    $data['EnableBotStatus']
                    ?? false
                );

            $this->discordInviteMessage =
                (string) (
                    $data['InviteMessage']
                    ?? 'Join us on Discord!\n[LINK]'
                );

            $this->discordLogLevel =
                (string) (
                    $data['LogLevel']
                    ?? 'Information'
                );

            $this->discordBackendLogLevel =
                (string) (
                    $data['BackendLogLevel']
                    ?? 'None'
                );

            $this->discordEnableTraceFileLogging =
                (bool) (
                    $data['EnableTraceFileLogging']
                    ?? false
                );

            $this->discordUseVerboseDisplay =
                (bool) (
                    $data['UseVerboseDisplay']
                    ?? false
                );

            $this->discordUseLinkedAccountRole =
                (bool) (
                    $data['UseLinkedAccountRole']
                    ?? true
                );

            $this->discordUseDemographicRoles =
                (bool) (
                    $data['UseDemographicRoles']
                    ?? true
                );

            $this->discordUseSpecialtyRoles =
                (bool) (
                    $data['UseSpecialtyRoles']
                    ?? true
                );

            $this->discordUseElectedTitleRoles =
                (bool) (
                    $data['UseElectedTitleRoles']
                    ?? true
                );

            $this->discordEmbedColorHex =
                (string) (
                    $data['EmbedColorHex']
                    ?? '#7289da'
                );

            $this->discordMaxTradeWatcherDisplaysPerUser =
                max(
                    0,
                    (int) (
                        $data['MaxTradeWatcherDisplaysPerUser']
                        ?? 5
                    )
                );

            $this->discordUseTradeWatcherFeeds =
                (bool) (
                    $data['UseTradeWatcherFeeds']
                    ?? false
                );

            $this->discordChatChannelLinks =
                $this->normalizeLoadedDiscordRows(
                    $data['ChatChannelLinks'] ?? []
                );

            $this->discordTradeFeedChannels =
                $this->normalizeLoadedDiscordRows(
                    $data['TradeFeedChannels'] ?? []
                );

            $this->discordCraftingFeedChannels =
                $this->normalizeLoadedDiscordRows(
                    $data['CraftingFeedChannels'] ?? []
                );

            $this->discordServerStatusFeedChannels =
                $this->normalizeLoadedDiscordRows(
                    $data['ServerStatusFeedChannels'] ?? []
                );

            $this->discordPlayerStatusFeedChannels =
                $this->normalizeLoadedDiscordRows(
                    $data['PlayerStatusFeedChannels'] ?? []
                );

            $this->discordElectionFeedChannels =
                $this->normalizeLoadedDiscordRows(
                    $data['ElectionFeedChannels'] ?? []
                );

            $this->discordServerLogFeedChannels =
                $this->normalizeLoadedDiscordRows(
                    $data['ServerLogFeedChannels'] ?? []
                );

            $this->discordServerInfoDisplayChannels =
                $this->normalizeLoadedDiscordRows(
                    $data['ServerInfoDisplayChannels'] ?? []
                );

        } catch (Throwable) {
            /*
             * DiscordLink is optional. No warning is needed when the
             * config simply does not exist.
             */
            $this->discordLinkInstalled = false;
        }
    }

    public function refreshDiscordLinkSettings(): void
    {
        $this->loadDiscordLinkSettings();

        Notification::make()
            ->title(
                $this->discordLinkInstalled
                    ? 'DiscordLink Configuration Refreshed'
                    : 'DiscordLink Not Installed'
            )
            ->color(
                $this->discordLinkInstalled
                    ? 'success'
                    : 'warning'
            )
            ->send();
    }

    public function saveDiscordLinkSettings(): void
    {
        try {
            if (!$this->discordLinkInstalled) {
                throw new RuntimeException(
                    'Configs/DiscordLink.eco was not found.'
                );
            }

            $serverId = trim(
                $this->discordServerId
            );

            if (
                $serverId !== '' &&
                preg_match('/^\d{5,20}$/', $serverId) !== 1
            ) {
                throw new RuntimeException(
                    'Discord Server ID must contain only digits.'
                );
            }

            if (
                !in_array(
                    $this->discordChatSyncMode,
                    ['OptOut', 'OptIn'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Invalid DiscordLink Chat Sync Mode.'
                );
            }

            $color = trim(
                $this->discordEmbedColorHex
            );

            if (
                preg_match(
                    '/^#[0-9a-fA-F]{6}$/',
                    $color
                ) !== 1
            ) {
                throw new RuntimeException(
                    'Embed color must be a six-digit hex color such as #7289da.'
                );
            }

            $roles = preg_split(
                '/[\r\n,]+/',
                $this->discordAdminRoles
            );

            if (!is_array($roles)) {
                $roles = [];
            }

            $roles = array_values(
                array_unique(
                    array_filter(
                        array_map(
                            fn ($role) =>
                                trim((string) $role),
                            $roles
                        ),
                        fn ($role) => $role !== ''
                    )
                )
            );

            $data = $this->readEcoConfig(
                'DiscordLink.eco'
            );

            /*
             * Change only Eco Enhanced-exposed settings.
             * All channel links, feeds, displays, replacements,
             * and future DiscordLink fields remain untouched.
             */
            $data['BotToken'] =
                $this->discordBotToken;

            $data['DiscordServerId'] =
                $serverId === ''
                    ? 0
                    : (int) $serverId;

            $data['DiscordServerOwnerIsAdmin'] =
                $this->discordServerOwnerIsAdmin;

            $data['AdminRoles'] = $roles;

            $data['ServerName'] =
                trim($this->discordServerName);

            $data['ServerDescription'] =
                trim(
                    $this->discordServerDescription
                );

            $data['ConnectionInfo'] =
                trim(
                    $this->discordConnectionInfo
                );

            $data['ChatSyncMode'] =
                $this->discordChatSyncMode;

            $data['EnableBotStatus'] =
                $this->discordEnableBotStatus;

            $data['InviteMessage'] =
                $this->discordInviteMessage;

            $data['LogLevel'] =
                trim($this->discordLogLevel);

            $data['BackendLogLevel'] =
                trim(
                    $this->discordBackendLogLevel
                );

            $data['EnableTraceFileLogging'] =
                $this->discordEnableTraceFileLogging;

            $data['UseVerboseDisplay'] =
                $this->discordUseVerboseDisplay;

            $data['UseLinkedAccountRole'] =
                $this->discordUseLinkedAccountRole;

            $data['UseDemographicRoles'] =
                $this->discordUseDemographicRoles;

            $data['UseSpecialtyRoles'] =
                $this->discordUseSpecialtyRoles;

            $data['UseElectedTitleRoles'] =
                $this->discordUseElectedTitleRoles;

            $data['EmbedColorHex'] = $color;

            $data['MaxTradeWatcherDisplaysPerUser'] =
                max(
                    0,
                    $this->discordMaxTradeWatcherDisplaysPerUser
                );

            $data['UseTradeWatcherFeeds'] =
                $this->discordUseTradeWatcherFeeds;

            $data['ChatChannelLinks'] =
                $this->normalizeChatChannelLinksForSave(
                    $this->discordChatChannelLinks
                );

            $data['TradeFeedChannels'] =
                $this->normalizeFeedChannelsForSave(
                    $this->discordTradeFeedChannels
                );

            $data['CraftingFeedChannels'] =
                $this->normalizeFeedChannelsForSave(
                    $this->discordCraftingFeedChannels
                );

            $data['ServerStatusFeedChannels'] =
                $this->normalizeFeedChannelsForSave(
                    $this->discordServerStatusFeedChannels
                );

            $data['PlayerStatusFeedChannels'] =
                $this->normalizeFeedChannelsForSave(
                    $this->discordPlayerStatusFeedChannels
                );

            $data['ElectionFeedChannels'] =
                $this->normalizeFeedChannelsForSave(
                    $this->discordElectionFeedChannels
                );

            $data['ServerLogFeedChannels'] =
                $this->normalizeFeedChannelsForSave(
                    $this->discordServerLogFeedChannels
                );

            $data['ServerInfoDisplayChannels'] =
                $this->normalizeServerInfoDisplaysForSave(
                    $this->discordServerInfoDisplayChannels
                );

            $this->writeEcoConfig(
                'DiscordLink.eco',
                $data
            );

            $this->loadDiscordLinkSettings();

            Notification::make()
                ->title('DiscordLink Configuration Saved')
                ->body(
                    'DiscordLink.eco was updated. ' .
                    'Restart DiscordLink or Eco if a changed setting does not apply immediately.'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title(
                    'Unable to Save DiscordLink Configuration'
                )
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    // ---------------------------------------------------------------------
    // DiscordLink channels / feeds
    // ---------------------------------------------------------------------

    protected function normalizeLoadedDiscordRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        return array_values(
            array_filter(
                $rows,
                fn ($row) => is_array($row)
            )
        );
    }

    public function addDiscordChatChannelLink(): void
    {
        $this->discordChatChannelLinks[] = [
            'AllowUserMentions' => true,
            'AllowRoleMentions' => true,
            'AllowChannelMentions' => true,
            'Direction' => 'Duplex',
            'HereAndEveryoneMentionPermission' => 'Forbidden',
            'UseTimestamp' => true,
            'EcoChannel' => 'General',
            'DiscordChannelId' => '',
            'Id' => (string) Str::uuid(),
        ];
    }

    public function removeDiscordChatChannelLink(int $index): void
    {
        if (!array_key_exists($index, $this->discordChatChannelLinks)) {
            return;
        }

        unset($this->discordChatChannelLinks[$index]);

        $this->discordChatChannelLinks =
            array_values($this->discordChatChannelLinks);
    }

    public function addDiscordFeedChannel(string $type): void
    {
        $property = $this->discordFeedProperty($type);

        if ($property === null) {
            return;
        }

        $this->{$property}[] = [
            'DiscordChannelId' => '',
            'Id' => (string) Str::uuid(),
        ];
    }

    public function removeDiscordFeedChannel(
        string $type,
        int $index
    ): void {
        $property = $this->discordFeedProperty($type);

        if (
            $property === null ||
            !array_key_exists($index, $this->{$property})
        ) {
            return;
        }

        unset($this->{$property}[$index]);

        $this->{$property} =
            array_values($this->{$property});
    }

    protected function discordFeedProperty(string $type): ?string
    {
        return match ($type) {
            'trade' =>
                'discordTradeFeedChannels',

            'crafting' =>
                'discordCraftingFeedChannels',

            'server-status' =>
                'discordServerStatusFeedChannels',

            'player-status' =>
                'discordPlayerStatusFeedChannels',

            'election' =>
                'discordElectionFeedChannels',

            'server-log' =>
                'discordServerLogFeedChannels',

            default => null,
        };
    }

    public function addDiscordServerInfoDisplay(): void
    {
        $this->discordServerInfoDisplayChannels[] = [
            'UseName' => true,
            'UseDescription' => false,
            'UseLogo' => true,
            'UseConnectionInfo' => true,
            'UseWebServerAddress' => true,
            'UsePlayerCount' => true,
            'UsePlayerList' => true,
            'UsePlayerListLoggedInTime' => false,
            'UsePlayerListExhaustionTime' => false,
            'UseIngameTime' => true,
            'UseTimeRemaining' => true,
            'UseServerTime' => true,
            'UseExhaustionResetServerTime' => false,
            'UseExhaustionResetTimeLeft' => false,
            'UseExhaustedPlayerCount' => false,
            'UseSettlementCount' => false,
            'UseSettlementList' => true,
            'UseElectionCount' => false,
            'UseElectionList' => true,
            'UseLawCount' => false,
            'UseLawList' => true,
            'DiscordChannelId' => '',
            'Id' => (string) Str::uuid(),
        ];
    }

    public function removeDiscordServerInfoDisplay(int $index): void
    {
        if (
            !array_key_exists(
                $index,
                $this->discordServerInfoDisplayChannels
            )
        ) {
            return;
        }

        unset($this->discordServerInfoDisplayChannels[$index]);

        $this->discordServerInfoDisplayChannels =
            array_values(
                $this->discordServerInfoDisplayChannels
            );
    }

    protected function normalizeServerInfoDisplaysForSave(
        array $rows
    ): array {
        $result = [];

        $booleanFields = [
            'UseName',
            'UseDescription',
            'UseLogo',
            'UseConnectionInfo',
            'UseWebServerAddress',
            'UsePlayerCount',
            'UsePlayerList',
            'UsePlayerListLoggedInTime',
            'UsePlayerListExhaustionTime',
            'UseIngameTime',
            'UseTimeRemaining',
            'UseServerTime',
            'UseExhaustionResetServerTime',
            'UseExhaustionResetTimeLeft',
            'UseExhaustedPlayerCount',
            'UseSettlementCount',
            'UseSettlementList',
            'UseElectionCount',
            'UseElectionList',
            'UseLawCount',
            'UseLawList',
        ];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $channelId = trim(
                (string) ($row['DiscordChannelId'] ?? '')
            );

            if ($channelId === '') {
                continue;
            }

            $normalized = [];

            foreach ($booleanFields as $field) {
                $normalized[$field] =
                    (bool) ($row[$field] ?? false);
            }

            $normalized['DiscordChannelId'] =
                $this->normalizeDiscordSnowflake(
                    $channelId,
                    'Server Info Display Discord Channel ID'
                );

            $normalized['Id'] =
                trim((string) ($row['Id'] ?? ''))
                ?: (string) Str::uuid();

            $result[] = $normalized;
        }

        return $result;
    }

    protected function normalizeDiscordSnowflake(
        mixed $value,
        string $label
    ): int {
        $value = trim((string) $value);

        if (
            $value === '' ||
            preg_match('/^\d{5,20}$/', $value) !== 1
        ) {
            throw new RuntimeException(
                $label . ' must be a Discord channel ID containing only digits.'
            );
        }

        return (int) $value;
    }

    protected function normalizeFeedChannelsForSave(
        array $rows
    ): array {
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $channelId = trim(
                (string) ($row['DiscordChannelId'] ?? '')
            );

            if ($channelId === '') {
                continue;
            }

            $result[] = [
                'DiscordChannelId' =>
                    $this->normalizeDiscordSnowflake(
                        $channelId,
                        'Discord Channel ID'
                    ),

                'Id' =>
                    trim((string) ($row['Id'] ?? ''))
                    ?: (string) Str::uuid(),
            ];
        }

        return $result;
    }

    protected function normalizeChatChannelLinksForSave(
        array $rows
    ): array {
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $channelId = trim(
                (string) ($row['DiscordChannelId'] ?? '')
            );

            if ($channelId === '') {
                continue;
            }

            $ecoChannel = trim(
                (string) ($row['EcoChannel'] ?? '')
            );

            if ($ecoChannel === '') {
                $ecoChannel = 'General';
            }

            $result[] = [
                'AllowUserMentions' =>
                    (bool) (
                        $row['AllowUserMentions']
                        ?? true
                    ),

                'AllowRoleMentions' =>
                    (bool) (
                        $row['AllowRoleMentions']
                        ?? true
                    ),

                'AllowChannelMentions' =>
                    (bool) (
                        $row['AllowChannelMentions']
                        ?? true
                    ),

                'Direction' =>
                    trim(
                        (string) (
                            $row['Direction']
                            ?? 'Duplex'
                        )
                    ) ?: 'Duplex',

                'HereAndEveryoneMentionPermission' =>
                    trim(
                        (string) (
                            $row[
                                'HereAndEveryoneMentionPermission'
                            ]
                            ?? 'Forbidden'
                        )
                    ) ?: 'Forbidden',

                'UseTimestamp' =>
                    (bool) (
                        $row['UseTimestamp']
                        ?? true
                    ),

                'EcoChannel' => $ecoChannel,

                'DiscordChannelId' =>
                    $this->normalizeDiscordSnowflake(
                        $channelId,
                        'Discord Channel ID'
                    ),

                'Id' =>
                    trim((string) ($row['Id'] ?? ''))
                    ?: (string) Str::uuid(),
            ];
        }

        return $result;
    }

}
