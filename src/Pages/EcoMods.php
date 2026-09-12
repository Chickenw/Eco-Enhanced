<?php

namespace EcoEnhanced\Pages;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use EcoEnhanced\Services\EcoModIoService;
use RuntimeException;
use Illuminate\Support\Facades\Cache;
use Throwable;

class EcoMods extends Page
{
    protected static ?int $navigationSort = 2;
protected string $view = 'eco-enhanced::pages.eco-mods';

    public Server $server;

    public string $search = '';

    public array $mods = [];

    public array $installedMods = [];

    public array $modConfigs = [];

    public ?string $editingConfigPath = null;
    public string $editingConfigContent = '';

    public bool $searched = false;

    public string $modioApiPath = '';
    public string $modioApiKey = '';
    public string $modioAccessToken = '';
    public string $modioConnectionStatus = 'Not Tested';

    public function mount(): void
    {
        $server = Filament::getTenant();

        abort_unless($server instanceof Server, 404);
        abort_unless(static::isEcoServer($server), 404);

        $this->server = $server;

        $this->loadModioAuthentication();
        $this->loadInstalledMods();
        $this->loadModConfigs();
    }
public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        return $server instanceof Server
            && static::isEcoServer($server);
    }
    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-puzzle-piece';
    }

    public static function getNavigationLabel(): string
    {
        return 'Eco Mods';
    }

    public function getTitle(): string
    {
        return 'Eco Mods';
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

    protected function loadModioAuthentication(): void
    {
        $settings = app(
            EcoModIoService::class
        )->settings();

        $this->modioApiPath =
            (string) (
                $settings['api_path']
                ?? 'https://api.mod.io/v1'
            );

        $this->modioApiKey =
            (string) ($settings['api_key'] ?? '');

        $this->modioAccessToken =
            (string) (
                $settings['access_token']
                ?? ''
            );
    }

    public function saveModioAuthentication(): void
    {
        try {
            app(EcoModIoService::class)
                ->saveSettings(
                    $this->modioApiPath,
                    $this->modioApiKey,
                    $this->modioAccessToken
                );

            $this->modioConnectionStatus =
                'Saved';

            Notification::make()
                ->title('mod.io Authentication Saved')
                ->body(
                    'The API path and authentication credentials were saved.'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Unable to Save mod.io Authentication')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function testModioAuthentication(): void
    {
        try {
            /*
             * Test the values currently entered in the form
             * without writing them to disk.
             */
            app(EcoModIoService::class)
                ->testConnectionWithSettings(
                    $this->modioApiPath,
                    $this->modioApiKey,
                    $this->modioAccessToken
                );

            $this->modioConnectionStatus =
                'Connected';

            Notification::make()
                ->title('mod.io Connected')
                ->body(
                    'Eco Enhanced successfully connected to the configured mod.io API path.'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            $this->modioConnectionStatus =
                'Connection Failed';

            Notification::make()
                ->title('mod.io Connection Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function searchMods(): void
    {
        $query = trim($this->search);

        if ($query === '') {
            $this->mods = [];
            $this->searched = false;

            Notification::make()
                ->title('Enter a Mod Name')
                ->body('Search for an Eco mod by name or keyword.')
                ->warning()
                ->send();

            return;
        }

        try {
            $service = app(EcoModIoService::class);

            if (!$service->configured()) {
                throw new RuntimeException(
                    'The mod.io API key has not been configured.'
                );
            }

            $this->mods = $service->search($query, 24);
            $this->searched = true;

            $this->loadInstalledMods();

            Notification::make()
                ->title('mod.io Search Complete')
                ->body(
                    count($this->mods) .
                    ' mod(s) found for "' .
                    $query .
                    '".'
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            $this->mods = [];
            $this->searched = true;

            Notification::make()
                ->title('mod.io Search Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->mods = [];
        $this->searched = false;
    }

    public function installMod(int $modId): void
    {
        try {
            if ($modId < 1) {
                throw new RuntimeException('Invalid mod.io mod ID.');
            }

            $service = app(EcoModIoService::class);

            /*
             * mod.io returns the complete recursive dependency set.
             *
             * Install dependencies first, then install the mod the user
             * actually selected.
             */
            $dependencies = $service->dependencies($modId);

            $installedDependencies = [];

            foreach ($dependencies as $dependency) {
                $dependencyId = (int) (
                    $dependency['mod_id'] ?? 0
                );

                if ($dependencyId < 1) {
                    continue;
                }

                /*
                 * Do not reinstall dependencies Eco Enhanced already knows about.
                 */
                if (
                    isset(
                        $this->installedMods[
                            (string) $dependencyId
                        ]
                    )
                ) {
                    $manifest = $this->readManifest();
                    $existing = $manifest[(string) $dependencyId] ?? [];

                    if (is_array($existing)) {
                        $requiredBy = $existing['required_by'] ?? [];

                        if (!is_array($requiredBy)) {
                            $requiredBy = $requiredBy
                                ? [(int) $requiredBy]
                                : [];
                        }

                        if (!in_array($modId, $requiredBy, true)) {
                            $requiredBy[] = $modId;
                        }

                        $existing['required_by'] = array_values(
                            array_unique($requiredBy)
                        );

                        $manifest[(string) $dependencyId] = $existing;
                        $this->writeManifest($manifest);
                        $this->loadInstalledMods();
                    }

                    continue;
                }

                $dependencyMod = $service->getMod(
                    $dependencyId
                );

                $this->installSingleMod(
                    $dependencyMod,
                    true,
                    $modId
                );

                $installedDependencies[] =
                    (string) (
                        $dependencyMod['name']
                        ?? ('Mod #' . $dependencyId)
                    );

                /*
                 * Refresh after each package so another dependency in the
                 * chain cannot accidentally be installed twice.
                 */
                $this->loadInstalledMods();
            }

            if (
                isset(
                    $this->installedMods[
                        (string) $modId
                    ]
                )
            ) {
                throw new RuntimeException(
                    'This mod is already recorded as installed.'
                );
            }

            $mod = $service->getMod($modId);

            $this->installSingleMod(
                $mod,
                false,
                null
            );

            $this->loadInstalledMods();

            $dependencyText = '';

            if (count($installedDependencies) > 0) {
                $dependencyText =
                    ' Dependencies installed: ' .
                    implode(', ', $installedDependencies) .
                    '.';
            }

            Notification::make()
                ->title('Eco Mod Installed')
                ->body(
                    ($mod['name'] ?? 'Mod') .
                    ' was installed successfully.' .
                    $dependencyText
                )
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Mod Installation Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function installSingleMod(
        array $mod,
        bool $dependency = false,
        ?int $requiredBy = null
    ): void {
        $modId = (int) ($mod['id'] ?? 0);

        if ($modId < 1) {
            throw new RuntimeException(
                'mod.io returned an invalid mod ID.'
            );
        }

        /*
         * This also protects against a dependency appearing more than once
         * in a recursive dependency chain.
         */
        $manifest = $this->readManifest();

        if (isset($manifest[(string) $modId])) {
            return;
        }

        $file = $mod['latest_file'] ?? [];

        $fileId = (int) ($file['id'] ?? 0);

        $downloadUrl = trim(
            (string) ($file['download_url'] ?? '')
        );

        if ($fileId < 1 || $downloadUrl === '') {
            throw new RuntimeException(
                ($mod['name'] ?? ('Mod #' . $modId)) .
                ' does not have a downloadable mod file.'
            );
        }

        $repo = app(DaemonFileRepository::class)
            ->setServer($this->server);

        $this->ensureDirectory(
            $repo,
            '.eco-enhanced',
            '/'
        );

        $this->ensureDirectory(
            $repo,
            'modio',
            '/.eco-enhanced'
        );

        $stageName = (string) $modId;
        $stageRoot =
            '/.eco-enhanced/modio/' .
            $stageName;

        try {
            if (
                $this->directoryExists(
                    $repo,
                    $stageRoot
                )
            ) {
                $repo->deleteFiles(
                    '/.eco-enhanced/modio',
                    [$stageName]
                );
            }

            $this->ensureDirectory(
                $repo,
                $stageName,
                '/.eco-enhanced/modio'
            );

            $archiveName =
                'mod-' .
                $modId .
                '-' .
                $fileId .
                '.zip';

            $repo->pull(
                $downloadUrl,
                $stageRoot,
                [
                    'filename' => $archiveName,
                    'foreground' => true,
                ]
            );

            $repo->decompressFile(
                $stageRoot,
                $archiveName
            );

            $repo->deleteFiles(
                $stageRoot,
                [$archiveName]
            );

            $deploymentRoot =
                $this->detectDeploymentRoot(
                    $repo,
                    $stageRoot
                );

            $installedPaths = [];

            foreach (
                ['Mods', 'UserCode']
                as $ecoFolder
            ) {
                $source =
                    rtrim(
                        $deploymentRoot,
                        '/'
                    ) .
                    '/' .
                    $ecoFolder;

                if (
                    !$this->directoryExists(
                        $repo,
                        $source
                    )
                ) {
                    continue;
                }

                $target = '/' . $ecoFolder;

                $this->ensureDirectory(
                    $repo,
                    $ecoFolder,
                    '/'
                );

                $this->mergeDirectory(
                    $repo,
                    $source,
                    $target,
                    $installedPaths
                );
            }

            /*
             * Deploy optional Eco configuration files separately.
             *
             * Mods commonly ship Configs/*.eco.template files. Existing
             * server configs must never be overwritten because they may
             * contain tokens, passwords, channel IDs, or administrator
             * customizations.
             */
            $configSource =
                rtrim(
                    $deploymentRoot,
                    '/'
                ) .
                '/Configs';

            if (
                $this->directoryExists(
                    $repo,
                    $configSource
                )
            ) {
                $this->deployEcoConfigDirectory(
                    $repo,
                    $configSource,
                    $installedPaths
                );
            }

            if (count($installedPaths) === 0) {
                throw new RuntimeException(
                    'Eco Enhanced could not find a supported Mods, UserCode, or Configs structure in ' .
                    ($mod['name'] ?? ('mod #' . $modId)) .
                    '.'
                );
            }

            /*
             * Re-read the manifest because another dependency may have been
             * written immediately before this package.
             */
            $manifest = $this->readManifest();

            $manifest[(string) $modId] = [
                'mod_id' => $modId,
                'file_id' => $fileId,
                'name' => (string) (
                    $mod['name']
                    ?? 'Unknown Mod'
                ),
                'version' => (string) (
                    $file['version']
                    ?? ''
                ),
                'installed_at' =>
                    now()->toIso8601String(),
                'dependency' => $dependency,
                'required_by' => $requiredBy !== null ? [$requiredBy] : [],
                'paths' => array_values(
                    array_unique(
                        $installedPaths
                    )
                ),
            ];

            $this->writeManifest($manifest);

        } finally {
            /*
             * Staging is temporary even when an installation fails.
             */
            try {
                if (
                    $this->directoryExists(
                        $repo,
                        $stageRoot
                    )
                ) {
                    $repo->deleteFiles(
                        '/.eco-enhanced/modio',
                        [$stageName]
                    );
                }
            } catch (Throwable) {
                //
            }
        }
    }



    public function checkForUpdates(): void
    {
        foreach ($this->installedMods as $installed) {
            $modId = (int) ($installed['mod_id'] ?? 0);

            if ($modId > 0) {
                Cache::forget(
                    'eco-enhanced-mod-update-' . $modId
                );
            }
        }

        Notification::make()
            ->title('Update Check Complete')
            ->body('Installed mods were checked against mod.io.')
            ->success()
            ->send();
    }

    public function hasUpdate(int $modId): bool
    {
        try {
            $installed = $this->installedMods[(string) $modId] ?? null;

            if (!is_array($installed)) {
                return false;
            }

            $latest = app(EcoModIoService::class)->getMod($modId);

            $latestFileId = (int) (
                $latest['latest_file']['id'] ?? 0
            );

            $installedFileId = (int) (
                $installed['file_id'] ?? 0
            );

            return $latestFileId > 0
                && $installedFileId > 0
                && $latestFileId !== $installedFileId;

        } catch (Throwable) {
            return false;
        }
    }

    public function updateMod(int $modId): void
    {
        $this->reinstallMod($modId);
    }

    public function latestVersion(int $modId): ?string
    {
        try {
            $latest = app(EcoModIoService::class)->getMod($modId);

            $version = trim((string) (
                $latest['latest_file']['version'] ?? ''
            ));

            return $version !== '' ? $version : null;
        } catch (Throwable) {
            return null;
        }
    }


    public function reinstallMod(int $modId): void
    {
        try {
            $manifest = $this->readManifest();
            $old = $manifest[(string) $modId] ?? null;

            if (!is_array($old)) {
                throw new RuntimeException('Installed mod was not found.');
            }

            if (($old['enabled'] ?? true) === false) {
                throw new RuntimeException(
                    'Enable this mod before reinstalling or updating it.'
                );
            }

            $repo = app(DaemonFileRepository::class)
                ->setServer($this->server);

            foreach (($old['paths'] ?? []) as $path) {
                $path = trim((string) $path);

                if ($path === '') {
                    continue;
                }

                $directory = dirname($path);
                $file = basename($path);

                $repo->deleteFiles(
                    $directory === '.' ? '/' : '/' . trim($directory, '/'),
                    [$file]
                );
            }

            unset($manifest[(string) $modId]);

            $this->cleanupEmptyDirectories(
                $repo,
                $old['paths'] ?? []
            );

            $this->writeManifest($manifest);
            $this->loadInstalledMods();

            $service = app(EcoModIoService::class);

            /*
             * Ensure dependencies exist before reinstalling/updating
             * the requested mod.
             */
            $dependencies = $service->dependencies($modId);

            foreach ($dependencies as $dependency) {
                $dependencyId = (int) ($dependency['mod_id'] ?? 0);

                if ($dependencyId < 1) {
                    continue;
                }

                $this->loadInstalledMods();

                if (
                    isset(
                        $this->installedMods[
                            (string) $dependencyId
                        ]
                    )
                ) {
                    continue;
                }

                $dependencyMod = $service->getMod($dependencyId);

                $this->installSingleMod(
                    $dependencyMod,
                    true,
                    $modId
                );
            }

            $mod = $service->getMod($modId);

            $requiredBy = $old['required_by'] ?? [];

            if (!is_array($requiredBy)) {
                $requiredBy = $requiredBy
                    ? [(int) $requiredBy]
                    : [];
            }

            $this->installSingleMod(
                $mod,
                (bool) ($old['dependency'] ?? false),
                count($requiredBy) > 0
                    ? (int) $requiredBy[0]
                    : null
            );

            $this->loadInstalledMods();

            Notification::make()
                ->title('Eco Mod Reinstalled')
                ->body(($mod['name'] ?? 'Mod') . ' was reinstalled successfully.')
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Reinstall Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function disableMod(int $modId): void
    {
        try {
            $manifest = $this->readManifest();
            $entry = $manifest[(string) $modId] ?? null;

            if (!is_array($entry)) {
                throw new RuntimeException('Installed mod was not found.');
            }

            if (($entry['enabled'] ?? true) === false) {
                return;
            }

            if (!empty($entry['dependency'])) {
                $requiredBy = $entry['required_by'] ?? [];

                if (!is_array($requiredBy)) {
                    $requiredBy = $requiredBy ? [(int) $requiredBy] : [];
                }

                foreach ($requiredBy as $parentId) {
                    $parent = $manifest[(string) ((int) $parentId)] ?? null;

                    if (
                        is_array($parent)
                        && ($parent['enabled'] ?? true) === true
                    ) {
                        throw new RuntimeException(
                            ($entry['name'] ?? 'Dependency') .
                            ' is still required by ' .
                            ($parent['name'] ?? ('Mod #' . $parentId)) .
                            '.'
                        );
                    }
                }
            }

            $repo = app(DaemonFileRepository::class)
                ->setServer($this->server);

            $this->ensureDirectory($repo, '.eco-enhanced', '/');
            $this->ensureDirectory($repo, 'disabled', '/.eco-enhanced');
            $this->ensureDirectory(
                $repo,
                (string) $modId,
                '/.eco-enhanced/disabled'
            );

            foreach (($entry['paths'] ?? []) as $path) {
                $path = trim((string) $path);

                if ($path === '') {
                    continue;
                }

                $destination =
                    '.eco-enhanced/disabled/' .
                    $modId .
                    '/' .
                    ltrim($path, '/');

                $this->ensureDirectoryPath(
                    $repo,
                    dirname('/' . $destination)
                );

                $repo->renameFiles(
                    '/',
                    [[
                        'from' => ltrim($path, '/'),
                        'to' => $destination,
                    ]]
                );
            }

            $entry['enabled'] = false;
            $manifest[(string) $modId] = $entry;

            $this->writeManifest($manifest);
            $this->loadInstalledMods();

            Notification::make()
                ->title('Eco Mod Disabled')
                ->body(($entry['name'] ?? 'Mod') . ' was disabled.')
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Disable Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function enableMod(int $modId): void
    {
        try {
            $manifest = $this->readManifest();
            $entry = $manifest[(string) $modId] ?? null;

            if (!is_array($entry)) {
                throw new RuntimeException('Installed mod was not found.');
            }

            if (($entry['enabled'] ?? true) === true) {
                return;
            }

            $repo = app(DaemonFileRepository::class)
                ->setServer($this->server);

            foreach (($entry['paths'] ?? []) as $path) {
                $path = trim((string) $path);

                if ($path === '') {
                    continue;
                }

                $source =
                    '.eco-enhanced/disabled/' .
                    $modId .
                    '/' .
                    ltrim($path, '/');

                $this->ensureDirectoryPath(
                    $repo,
                    dirname('/' . ltrim($path, '/'))
                );

                $repo->renameFiles(
                    '/',
                    [[
                        'from' => $source,
                        'to' => ltrim($path, '/'),
                    ]]
                );
            }

            $entry['enabled'] = true;
            $manifest[(string) $modId] = $entry;

            $this->writeManifest($manifest);

            try {
                $repo->deleteFiles(
                    '/.eco-enhanced/disabled',
                    [(string) $modId]
                );
            } catch (Throwable) {
                //
            }

            $this->loadInstalledMods();

            Notification::make()
                ->title('Eco Mod Enabled')
                ->body(($entry['name'] ?? 'Mod') . ' was enabled.')
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Enable Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function ensureDirectoryPath(
        DaemonFileRepository $repo,
        string $path
    ): void {
        $parts = array_values(
            array_filter(
                explode('/', trim($path, '/'))
            )
        );

        $root = '/';

        foreach ($parts as $part) {
            $this->ensureDirectory(
                $repo,
                $part,
                $root
            );

            $root = rtrim($root, '/') . '/' . $part;
        }
    }

    public function deleteMod(int $modId): void
    {
        try {
            $manifest = $this->readManifest();

            $entry = $manifest[(string) $modId] ?? null;

            if (!is_array($entry)) {
                throw new RuntimeException('Installed mod was not found.');
            }

            /*
             * Protect dependencies that are still required by another
             * installed Eco Enhanced-managed mod.
             */
            foreach ($manifest as $otherId => $other) {
                if ((int) $otherId === $modId || !is_array($other)) {
                    continue;
                }

                if ((int) ($other['required_by'] ?? 0) === $modId) {
                    continue;
                }

                if (
                    (int) ($other['required_by'] ?? 0) > 0
                    && (int) ($entry['mod_id'] ?? 0) ===
                       (int) ($other['required_by'] ?? 0)
                ) {
                    continue;
                }
            }

            if (!empty($entry['dependency'])) {
                $requiredBy = $entry['required_by'] ?? [];

                if (!is_array($requiredBy)) {
                    $requiredBy = $requiredBy
                        ? [(int) $requiredBy]
                        : [];
                }

                $activeParents = array_values(
                    array_filter(
                        $requiredBy,
                        fn ($parentId) =>
                            isset($manifest[(string) ((int) $parentId)])
                    )
                );

                if (count($activeParents) > 0) {
                    $names = [];

                    foreach ($activeParents as $parentId) {
                        $names[] =
                            $manifest[(string) ((int) $parentId)]['name']
                            ?? ('Mod #' . $parentId);
                    }

                    throw new RuntimeException(
                        ($entry['name'] ?? 'This dependency') .
                        ' is required by ' .
                        implode(', ', $names) .
                        '. Delete the dependent mod first.'
                    );
                }
            }

            $repo = app(DaemonFileRepository::class)
                ->setServer($this->server);

            $cleanupPaths = $entry['paths'] ?? [];

            foreach (($entry['paths'] ?? []) as $path) {
                $path = trim((string) $path);

                if ($path === '') {
                    continue;
                }

                $directory = dirname($path);
                $file = basename($path);

                $repo->deleteFiles(
                    $directory === '.' ? '/' : '/' . trim($directory, '/'),
                    [$file]
                );
            }

            unset($manifest[(string) $modId]);

            /*
             * Remove this mod from dependency parent lists.
             * Delete a dependency only when no installed mods still require it.
             */
            foreach ($manifest as $dependencyId => $dependencyEntry) {
                if (!is_array($dependencyEntry)) {
                    continue;
                }

                if (empty($dependencyEntry['dependency'])) {
                    continue;
                }

                $requiredBy = $dependencyEntry['required_by'] ?? [];

                if (!is_array($requiredBy)) {
                    $requiredBy = $requiredBy
                        ? [(int) $requiredBy]
                        : [];
                }

                $requiredBy = array_values(
                    array_filter(
                        $requiredBy,
                        fn ($parentId) =>
                            (int) $parentId !== $modId
                            && isset(
                                $manifest[
                                    (string) ((int) $parentId)
                                ]
                            )
                    )
                );

                if (count($requiredBy) > 0) {
                    $dependencyEntry['required_by'] = $requiredBy;
                    $manifest[(string) $dependencyId] = $dependencyEntry;
                    continue;
                }

                $cleanupPaths = array_merge(
                    $cleanupPaths,
                    $dependencyEntry['paths'] ?? []
                );

                foreach (($dependencyEntry['paths'] ?? []) as $dependencyPath) {
                    $dependencyPath = trim((string) $dependencyPath);

                    if ($dependencyPath === '') {
                        continue;
                    }

                    $dependencyDirectory = dirname($dependencyPath);
                    $dependencyFile = basename($dependencyPath);

                    $repo->deleteFiles(
                        $dependencyDirectory === '.'
                            ? '/'
                            : '/' . trim($dependencyDirectory, '/'),
                        [$dependencyFile]
                    );
                }

                unset($manifest[(string) $dependencyId]);
            }

            $this->cleanupEmptyDirectories(
                $repo,
                $cleanupPaths
            );

            $this->writeManifest($manifest);
            $this->loadInstalledMods();

            Notification::make()
                ->title('Eco Mod Deleted')
                ->body(($entry['name'] ?? 'Mod') . ' was removed.')
                ->success()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Delete Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function detectDeploymentRoot(
        DaemonFileRepository $repo,
        string $stageRoot
    ): string {
        $entries = $repo->getDirectory($stageRoot);

        /*
         * Direct package:
         *
         * Mods/
         * UserCode/
         */
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (($entry['file'] ?? true) === true) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));

            if (in_array($name, ['Mods', 'UserCode', 'Configs'], true)) {
                return $stageRoot;
            }
        }

        /*
         * Wrapper package:
         *
         * SomeMod/
         *     Mods/
         *     UserCode/
         */
        $directories = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (($entry['file'] ?? true) === true) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));

            if ($name !== '') {
                $directories[] = $name;
            }
        }

        foreach ($directories as $directory) {
            $candidate = rtrim($stageRoot, '/') . '/' . $directory;

            foreach (['Mods', 'UserCode', 'Configs'] as $ecoFolder) {
                if (
                    $this->directoryExists(
                        $repo,
                        $candidate . '/' . $ecoFolder
                    )
                ) {
                    return $candidate;
                }
            }
        }

        throw new RuntimeException(
            'Unsupported Eco mod archive structure. ' .
            'Expected Mods/, UserCode/, Configs/, or a wrapper folder containing them.'
        );
    }

    /**
     * Deploy Eco mod configuration files safely.
     *
     * Rules:
     *  - *.eco.template is copied to Configs if missing.
     *  - Matching *.eco is created from that template if missing.
     *  - A packaged *.eco file is copied only when the active file is missing.
     *  - Existing configs/templates are never overwritten.
     *  - Active *.eco files are deliberately NOT added to installedPaths so
     *    uninstalling a mod cannot delete a user's configured file.
     */
    protected function deployEcoConfigDirectory(
        DaemonFileRepository $repo,
        string $source,
        array &$installedPaths
    ): void {
        $entries = $repo->getDirectory($source);

        $targetEntries = [];

        try {
            foreach ($repo->getDirectory('/Configs') as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $name = trim((string) ($entry['name'] ?? ''));

                if ($name !== '') {
                    $targetEntries[$name] = $entry;
                }
            }
        } catch (Throwable) {
            $this->ensureDirectory(
                $repo,
                'Configs',
                '/'
            );
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            /*
             * Eco configuration packages are expected to contain files
             * directly inside Configs/. Ignore nested folders for safety.
             */
            if (($entry['file'] ?? true) !== true) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $sourcePath =
                rtrim($source, '/') .
                '/' .
                $name;

            /*
             * Standard Eco template:
             *
             * DiscordLink.eco.template
             *      ->
             * Configs/DiscordLink.eco.template
             * Configs/DiscordLink.eco
             */
            if (str_ends_with(strtolower($name), '.eco.template')) {
                $activeName = substr(
                    $name,
                    0,
                    -strlen('.template')
                );

                /*
                 * Read before moving the staged template.
                 */
                $contents = $repo->getContent(
                    ltrim($sourcePath, '/'),
                    4 * 1024 * 1024
                );

                /*
                 * Keep the original template for reference, but do not
                 * overwrite a template already on the server.
                 */
                if (!isset($targetEntries[$name])) {
                    $repo->renameFiles(
                        '/',
                        [
                            [
                                'from' => ltrim($sourcePath, '/'),
                                'to' => 'Configs/' . $name,
                            ],
                        ]
                    );

                    $installedPaths[] =
                        'Configs/' .
                        $name;

                    $targetEntries[$name] = [
                        'name' => $name,
                        'file' => true,
                    ];
                }

                /*
                 * Create the active configuration only when it does not
                 * already exist.
                 */
                if (!isset($targetEntries[$activeName])) {
                    $repo->putContent(
                        'Configs/' . $activeName,
                        $contents
                    );

                    $targetEntries[$activeName] = [
                        'name' => $activeName,
                        'file' => true,
                    ];
                }

                continue;
            }

            /*
             * Some mods ship an active *.eco directly rather than a template.
             * Copy it only if the server does not already have that config.
             */
            if (str_ends_with(strtolower($name), '.eco')) {
                if (isset($targetEntries[$name])) {
                    continue;
                }

                $contents = $repo->getContent(
                    ltrim($sourcePath, '/'),
                    4 * 1024 * 1024
                );

                $repo->putContent(
                    'Configs/' . $name,
                    $contents
                );

                $targetEntries[$name] = [
                    'name' => $name,
                    'file' => true,
                ];
            }
        }
    }

    protected function mergeDirectory(
        DaemonFileRepository $repo,
        string $source,
        string $target,
        array &$installedPaths
    ): void {
        $sourceEntries = $repo->getDirectory($source);

        $targetEntries = [];

        try {
            foreach ($repo->getDirectory($target) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $name = trim((string) ($entry['name'] ?? ''));

                if ($name !== '') {
                    $targetEntries[$name] = $entry;
                }
            }
        } catch (Throwable) {
            //
        }

        foreach ($sourceEntries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $isFile = (bool) ($entry['file'] ?? true);

            $sourcePath = rtrim($source, '/') . '/' . $name;
            $targetPath = rtrim($target, '/') . '/' . $name;

            if ($isFile) {
                if (isset($targetEntries[$name])) {
                    throw new RuntimeException(
                        'Install stopped because "' .
                        ltrim($targetPath, '/') .
                        '" already exists. ' .
                        'Eco Enhanced will not overwrite existing mod files during a new installation.'
                    );
                }

                $repo->renameFiles(
                    '/',
                    [
                        [
                            'from' => ltrim($sourcePath, '/'),
                            'to' => ltrim($targetPath, '/'),
                        ],
                    ]
                );

                $installedPaths[] = ltrim($targetPath, '/');

                continue;
            }

            if (!isset($targetEntries[$name])) {
                $this->ensureDirectory(
                    $repo,
                    $name,
                    $target
                );
            } elseif (
                (bool) ($targetEntries[$name]['file'] ?? true)
            ) {
                throw new RuntimeException(
                    'Install stopped because "' .
                    ltrim($targetPath, '/') .
                    '" exists as a file.'
                );
            }

            $this->mergeDirectory(
                $repo,
                $sourcePath,
                $targetPath,
                $installedPaths
            );
        }
    }

    protected function cleanupEmptyDirectories(
        DaemonFileRepository $repo,
        array $paths
    ): void {
        $directories = [];

        foreach ($paths as $path) {
            $path = trim((string) $path);

            if ($path === '') {
                continue;
            }

            $directory = dirname($path);

            while (
                $directory !== '.'
                && $directory !== '/'
                && $directory !== ''
            ) {
                $directories[$directory] = true;
                $directory = dirname($directory);
            }
        }

        $directories = array_keys($directories);

        usort(
            $directories,
            fn ($a, $b) => substr_count($b, '/') <=> substr_count($a, '/')
        );

        foreach ($directories as $directory) {
            try {
                $entries = $repo->getDirectory('/' . trim($directory, '/'));

                if (count($entries) === 0) {
                    $parent = dirname($directory);
                    $name = basename($directory);

                    $repo->deleteFiles(
                        $parent === '.' ? '/' : '/' . trim($parent, '/'),
                        [$name]
                    );
                }
            } catch (Throwable) {
                //
            }
        }
    }

    protected function ensureDirectory(
        DaemonFileRepository $repo,
        string $name,
        string $root
    ): void {
        $path =
            rtrim($root, '/') .
            '/' .
            trim($name, '/');

        if ($path === '') {
            $path = '/';
        }

        if ($this->directoryExists($repo, $path)) {
            return;
        }

        $repo->createDirectory(
            trim($name, '/'),
            $root
        );
    }

    protected function directoryExists(
        DaemonFileRepository $repo,
        string $path
    ): bool {
        try {
            $repo->getDirectory($path);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected function loadModConfigs(): void
    {
        $this->modConfigs = [];

        try {
            $repo = app(DaemonFileRepository::class)
                ->setServer($this->server);

            if (!$this->directoryExists($repo, '/Configs/Mods')) {
                return;
            }

            $this->scanConfigDirectory(
                $repo,
                '/Configs/Mods',
                $this->modConfigs
            );

            usort(
                $this->modConfigs,
                fn (array $a, array $b) =>
                    strcasecmp(
                        (string) ($a['path'] ?? ''),
                        (string) ($b['path'] ?? '')
                    )
            );
        } catch (Throwable) {
            $this->modConfigs = [];
        }
    }

    protected function scanConfigDirectory(
        DaemonFileRepository $repo,
        string $path,
        array &$results
    ): void {
        $entries = $repo->getDirectory($path);

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $fullPath = rtrim($path, '/') . '/' . $name;
            $isFile = (bool) ($entry['file'] ?? true);

            if (!$isFile) {
                $this->scanConfigDirectory(
                    $repo,
                    $fullPath,
                    $results
                );

                continue;
            }

            $extension = strtolower(
                pathinfo($name, PATHINFO_EXTENSION)
            );

            if (str_contains('/' . ltrim($fullPath, '/'), '/Internal/')) {
                continue;
            }

            if (!in_array(
                $extension,
                ['json', 'eco', 'txt', 'cfg', 'ini'],
                true
            )) {
                continue;
            }

            $results[] = [
                'name' => $name,
                'path' => ltrim($fullPath, '/'),
                'size' => (int) ($entry['size'] ?? 0),
            ];
        }
    }

    public function editModConfigByIndex(int $index): void
    {
        /*
         * Resolve the path server-side instead of passing a full path
         * through the Livewire expression.
         */
        if (!isset($this->modConfigs[$index])) {
            Notification::make()
                ->title('Config Load Failed')
                ->body('The selected mod configuration is no longer available.')
                ->danger()
                ->send();

            return;
        }

        $path = trim(
            (string) (
                $this->modConfigs[$index]['path']
                ?? ''
            )
        );

        if ($path === '') {
            Notification::make()
                ->title('Config Load Failed')
                ->body('The selected configuration path is invalid.')
                ->danger()
                ->send();

            return;
        }

        $this->editModConfig($path);
    }

    public function editModConfig(string $path): void
    {
        try {
            $path = ltrim(trim($path), '/');

            if (
                $path === ''
                || !str_starts_with($path, 'Configs/Mods/')
                || str_contains($path, '/Internal/')
            ) {
                throw new RuntimeException('Invalid mod configuration path.');
            }

            $contents = app(DaemonFileRepository::class)
                ->setServer($this->server)
                ->getContent($path, 1024 * 1024);

            $this->editingConfigPath = $path;
            $this->editingConfigContent = $contents;

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Config Load Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function reloadModConfig(): void
    {
        if ($this->editingConfigPath === null) {
            return;
        }

        $this->editModConfig($this->editingConfigPath);
    }

    public function closeModConfigEditor(): void
    {
        $this->editingConfigPath = null;
        $this->editingConfigContent = '';
    }

    public function saveModConfig(): void
    {
        try {
            if ($this->editingConfigPath === null) {
                throw new RuntimeException('No configuration is being edited.');
            }

            $path = ltrim($this->editingConfigPath, '/');

            if (
                !str_starts_with($path, 'Configs/Mods/')
                || str_contains($path, '/Internal/')
            ) {
                throw new RuntimeException('Invalid mod configuration path.');
            }

            $extension = strtolower(
                pathinfo($path, PATHINFO_EXTENSION)
            );

            if ($extension === 'json') {
                json_decode(
                    $this->editingConfigContent,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                $this->editingConfigContent =
                    json_encode(
                        json_decode(
                            $this->editingConfigContent,
                            true,
                            512,
                            JSON_THROW_ON_ERROR
                        ),
                        JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_SLASHES |
                        JSON_UNESCAPED_UNICODE
                    ) . PHP_EOL;
            }

            app(DaemonFileRepository::class)
                ->setServer($this->server)
                ->putContent(
                    $path,
                    $this->editingConfigContent
                );

            $this->loadModConfigs();

            Notification::make()
                ->title('Config Saved')
                ->body(basename($path) . ' was saved successfully.')
                ->success()
                ->send();

        } catch (JsonException $exception) {
            Notification::make()
                ->title('Invalid JSON')
                ->body('The configuration was not saved because the JSON is invalid.')
                ->danger()
                ->send();

        } catch (Throwable $exception) {
            Notification::make()
                ->title('Config Save Failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function manifestPath(): string
    {
        return '.eco-enhanced/modio-installed.json';
    }

    protected function readManifest(): array
    {
        try {
            $contents = app(DaemonFileRepository::class)
                ->setServer($this->server)
                ->getContent(
                    $this->manifestPath(),
                    2 * 1024 * 1024
                );

            $data = json_decode($contents, true);

            return is_array($data) ? $data : [];
        } catch (Throwable) {
            return [];
        }
    }

    protected function writeManifest(array $manifest): void
    {
        $json = json_encode(
            $manifest,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new RuntimeException(
                'Unable to encode the Eco Enhanced mod manifest.'
            );
        }

        app(DaemonFileRepository::class)
            ->setServer($this->server)
            ->putContent(
                $this->manifestPath(),
                $json . PHP_EOL
            );
    }

    protected function loadInstalledMods(): void
    {
        $this->installedMods = $this->readManifest();
    }

    public function isInstalled(int $modId): bool
    {
        return isset(
            $this->installedMods[(string) $modId]
        );
    }
}
