<?php

namespace GameNest\GameNestEcoEnhanced\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class EcoModIoService
{
    public function configured(): bool
    {
        return $this->apiKey() !== '' || $this->accessToken() !== '';
    }

    public function settings(): array
    {
        $stored = $this->readSettings();

        return [
            'api_path' => $this->apiPath(),
            'api_key' => $stored['api_key'] ?? '',
            'access_token' => $stored['access_token'] ?? '',
        ];
    }

    public function saveSettings(
        string $apiPath,
        string $apiKey,
        string $accessToken
    ): void {
        $apiPath = rtrim(trim($apiPath), '/');

        if (
            $apiPath === '' ||
            !str_starts_with($apiPath, 'https://')
        ) {
            throw new RuntimeException(
                'The mod.io API path must be a valid HTTPS URL.'
            );
        }

        $directory = storage_path(
            'app/gamenest-eco-enhanced'
        );

        if (!is_dir($directory)) {
            if (
                !mkdir($directory, 0750, true) &&
                !is_dir($directory)
            ) {
                throw new RuntimeException(
                    'Unable to create GameNest settings directory.'
                );
            }
        }

        $data = [
            'api_path' => $apiPath,
            'api_key' => trim($apiKey) !== ''
                ? Crypt::encryptString(trim($apiKey))
                : '',
            'access_token' => trim($accessToken) !== ''
                ? Crypt::encryptString(trim($accessToken))
                : '',
        ];

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new RuntimeException(
                'Unable to encode mod.io settings.'
            );
        }

        $path = $this->settingsPath();

        if (file_put_contents($path, $json . PHP_EOL) === false) {
            throw new RuntimeException(
                'Unable to save mod.io settings.'
            );
        }

        @chmod($path, 0640);

        Cache::forget(
            'gamenest-eco-enhanced.modio.game-id'
        );
    }

    public function testConnectionWithSettings(
        string $apiPath,
        string $apiKey,
        string $accessToken
    ): array {
        $apiPath = rtrim(trim($apiPath), '/');
        $apiKey = trim($apiKey);
        $accessToken = trim($accessToken);

        if (
            $apiPath === '' ||
            !str_starts_with($apiPath, 'https://')
        ) {
            throw new RuntimeException(
                'The mod.io API path must be a valid HTTPS URL.'
            );
        }

        if ($apiKey === '' && $accessToken === '') {
            throw new RuntimeException(
                'Enter a mod.io API key or access token.'
            );
        }

        $request = Http::timeout(15)
            ->acceptJson();

        if ($apiKey !== '') {
            $request = $request->withQueryParameters([
                'api_key' => $apiKey,
            ]);
        }

        if ($accessToken !== '') {
            $request = $request->withToken(
                $accessToken
            );
        }

        $response = $request->get(
            $apiPath . '/games',
            [
                '_q' => 'Eco',
                '_limit' => 1,
            ]
        );

        if (!$response->successful()) {
            throw new RuntimeException(
                'mod.io connection failed with HTTP ' .
                $response->status() .
                $this->errorSuffix($response->json())
            );
        }

        return [
            'status' => $response->status(),
            'api_path' => $apiPath,
        ];
    }

    public function testConnection(): array
    {
        $response = $this->request()->get(
            $this->apiPath() . '/games',
            [
                '_q' => 'Eco',
                '_limit' => 1,
            ]
        );

        if (!$response->successful()) {
            throw new RuntimeException(
                'mod.io connection failed with HTTP ' .
                $response->status() .
                $this->errorSuffix($response->json())
            );
        }

        return [
            'status' => $response->status(),
            'api_path' => $this->apiPath(),
        ];
    }

    public function search(string $query, int $limit = 20): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $gameId = $this->gameId();

        $response = $this->request()
            ->get(
                $this->apiPath() . '/games/' . $gameId . '/mods',
                [
                    '_q' => $query,
                    '_limit' => max(1, min($limit, 50)),
                    '_sort' => '-date_updated',
                    'status' => 1,
                ]
            );

        if (!$response->successful()) {
            throw new RuntimeException(
                'mod.io search failed with HTTP ' .
                $response->status() .
                $this->errorSuffix($response->json())
            );
        }

        $json = $response->json();

        if (!is_array($json)) {
            throw new RuntimeException(
                'mod.io returned an invalid search response.'
            );
        }

        $mods = $json['data'] ?? [];

        if (!is_array($mods)) {
            return [];
        }

        return array_values(
            array_map(
                fn (array $mod): array => $this->normalizeMod($mod),
                array_filter($mods, 'is_array')
            )
        );
    }

    public function getMod(int $modId): array
    {
        if ($modId < 1) {
            throw new RuntimeException('Invalid mod.io mod ID.');
        }

        $gameId = $this->gameId();

        $response = $this->request()
            ->get(
                $this->apiPath() .
                '/games/' .
                $gameId .
                '/mods/' .
                $modId
            );

        if (!$response->successful()) {
            throw new RuntimeException(
                'Unable to retrieve mod.io mod #' .
                $modId .
                ' (HTTP ' .
                $response->status() .
                ')' .
                $this->errorSuffix($response->json())
            );
        }

        $json = $response->json();

        if (!is_array($json)) {
            throw new RuntimeException(
                'mod.io returned invalid mod information.'
            );
        }

        return $this->normalizeMod($json);
    }

    public function dependencies(int $modId): array
    {
        if ($modId < 1) {
            throw new RuntimeException('Invalid mod.io mod ID.');
        }

        $gameId = $this->gameId();

        $response = $this->request()
            ->get(
                $this->apiPath() .
                '/games/' .
                $gameId .
                '/mods/' .
                $modId .
                '/dependencies',
                [
                    'recursive' => 'true',
                    '_limit' => 100,
                ]
            );

        if (!$response->successful()) {
            throw new RuntimeException(
                'Unable to retrieve dependencies for mod #' .
                $modId .
                ' (HTTP ' .
                $response->status() .
                ')' .
                $this->errorSuffix($response->json())
            );
        }

        $json = $response->json();

        if (!is_array($json)) {
            return [];
        }

        $dependencies = $json['data'] ?? [];

        if (!is_array($dependencies)) {
            return [];
        }

        $result = [];

        foreach ($dependencies as $dependency) {
            if (!is_array($dependency)) {
                continue;
            }

            /*
             * mod.io dependency responses identify the required mod by
             * mod_id. Retain basic metadata when supplied.
             */
            $dependencyId = (int) (
                $dependency['mod_id']
                ?? $dependency['id']
                ?? 0
            );

            if ($dependencyId < 1 || $dependencyId === $modId) {
                continue;
            }

            $result[(string) $dependencyId] = [
                'mod_id' => $dependencyId,
                'name' => trim(
                    (string) (
                        $dependency['name']
                        ?? $dependency['name_id']
                        ?? ''
                    )
                ),
            ];
        }

        return array_values($result);
    }

    public function gameId(): int
    {
        return (int) Cache::remember(
            'gamenest-eco-enhanced.modio.game-id',
            now()->addDay(),
            function (): int {
                $slug = strtolower(
                    trim(
                        (string) config(
                            'gamenest-eco-enhanced.modio_game_slug',
                            'eco'
                        )
                    )
                );

                $response = $this->request()
                    ->get(
                        $this->apiPath() . '/games',
                        [
                            '_q' => 'Eco',
                            '_limit' => 100,
                            'status' => 1,
                        ]
                    );

                if (!$response->successful()) {
                    throw new RuntimeException(
                        'Unable to locate Eco on mod.io (HTTP ' .
                        $response->status() .
                        ')' .
                        $this->errorSuffix($response->json())
                    );
                }

                $json = $response->json();
                $games = is_array($json)
                    ? ($json['data'] ?? [])
                    : [];

                if (!is_array($games)) {
                    throw new RuntimeException(
                        'mod.io returned an invalid games response.'
                    );
                }

                foreach ($games as $game) {
                    if (!is_array($game)) {
                        continue;
                    }

                    $nameId = strtolower(
                        trim((string) ($game['name_id'] ?? ''))
                    );

                    if ($nameId === $slug) {
                        $id = (int) ($game['id'] ?? 0);

                        if ($id > 0) {
                            return $id;
                        }
                    }
                }

                throw new RuntimeException(
                    'Unable to resolve the Eco game ID from mod.io.'
                );
            }
        );
    }

    protected function request()
    {
        $apiKey = $this->apiKey();
        $accessToken = $this->accessToken();

        if ($apiKey === '' && $accessToken === '') {
            throw new RuntimeException(
                'mod.io authentication is not configured.'
            );
        }

        $request = Http::timeout(15)
            ->acceptJson();

        /*
         * API-key authentication is sufficient for GameNest's
         * current read-only catalog/download operations.
         */
        if ($apiKey !== '') {
            $request = $request->withQueryParameters([
                'api_key' => $apiKey,
            ]);
        }

        /*
         * When an OAuth access token is supplied, send it using
         * mod.io's Bearer authentication scheme.
         */
        if ($accessToken !== '') {
            $request = $request->withToken(
                $accessToken
            );
        }

        return $request;
    }

    protected function apiPath(): string
    {
        $settings = $this->readSettings();

        $value = trim(
            (string) (
                $settings['api_path']
                ?? config(
                    'gamenest-eco-enhanced.modio_api_path',
                    'https://api.mod.io/v1'
                )
            )
        );

        return rtrim(
            $value !== ''
                ? $value
                : 'https://api.mod.io/v1',
            '/'
        );
    }

    protected function apiKey(): string
    {
        $settings = $this->readSettings();

        return trim(
            (string) ($settings['api_key'] ?? '')
        );
    }

    protected function accessToken(): string
    {
        $settings = $this->readSettings();

        return trim(
            (string) ($settings['access_token'] ?? '')
        );
    }

    protected function settingsPath(): string
    {
        return storage_path(
            'app/gamenest-eco-enhanced/modio.json'
        );
    }

    protected function readSettings(): array
    {
        $path = $this->settingsPath();

        if (!is_file($path)) {
            return [];
        }

        $json = file_get_contents($path);

        if ($json === false || trim($json) === '') {
            return [];
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            return [];
        }

        $result = [
            'api_path' => trim(
                (string) ($data['api_path'] ?? '')
            ),
            'api_key' => '',
            'access_token' => '',
        ];

        foreach ([
            'api_key',
            'access_token',
        ] as $key) {
            $encrypted = trim(
                (string) ($data[$key] ?? '')
            );

            if ($encrypted === '') {
                continue;
            }

            try {
                $result[$key] =
                    Crypt::decryptString($encrypted);
            } catch (Throwable) {
                $result[$key] = '';
            }
        }

        return $result;
    }

    protected function normalizeMod(array $mod): array
    {
        return [
            'id' => (int) ($mod['id'] ?? 0),

            'name' => trim(
                (string) (
                    $mod['name']
                    ?? $mod['name_id']
                    ?? 'Unknown Mod'
                )
            ),

            'name_id' => trim(
                (string) ($mod['name_id'] ?? '')
            ),

            'summary' => trim(
                (string) ($mod['summary'] ?? '')
            ),

            'author' => trim(
                (string) (
                    $mod['submitted_by']['username']
                    ?? 'Unknown'
                )
            ),

            'logo' => trim(
                (string) (
                    $mod['logo']['thumb_320x180']
                    ?? $mod['logo']['thumb_640x360']
                    ?? ''
                )
            ),

            'profile_url' => trim(
                (string) ($mod['profile_url'] ?? '')
            ),

            'date_updated' => (int) (
                $mod['date_updated'] ?? 0
            ),

            'downloads' => (int) (
                $mod['stats']['downloads_total'] ?? 0
            ),

            'subscribers' => (int) (
                $mod['stats']['subscribers_total'] ?? 0
            ),

            'latest_file' => $this->normalizeFile(
                is_array($mod['modfile'] ?? null)
                    ? $mod['modfile']
                    : []
            ),
        ];
    }

    protected function normalizeFile(array $file): array
    {
        return [
            'id' => (int) ($file['id'] ?? 0),

            'version' => trim(
                (string) ($file['version'] ?? '')
            ),

            'filename' => trim(
                (string) ($file['filename'] ?? '')
            ),

            'filesize' => (int) ($file['filesize'] ?? 0),

            'date_added' => (int) ($file['date_added'] ?? 0),

            'download_url' => trim(
                (string) (
                    $file['download']['binary_url']
                    ?? ''
                )
            ),
        ];
    }

    protected function errorSuffix(mixed $json): string
    {
        if (!is_array($json)) {
            return '.';
        }

        $message = trim(
            (string) (
                $json['error']['message']
                ?? $json['message']
                ?? ''
            )
        );

        return $message !== ''
            ? ': ' . $message
            : '.';
    }
}
