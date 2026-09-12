<x-filament-panels::page>

    <div class="eco-enhanced-mods-polish space-y-6">

        
        {{-- mod.io Authentication --}}
        <div
            x-data="{
                showApiKey: false,
                showAccessToken: false
            }"
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
        >
            <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="font-semibold text-gray-950 dark:text-white">
                            mod.io Authentication
                        </div>

                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Configure the API endpoint and credentials used by Eco Enhanced for mod.io.
                        </div>
                    </div>

                    @php
                        $modioStatusColor = match ($modioConnectionStatus) {
                            'Connected' => 'success',
                            'Connection Failed' => 'danger',
                            'Saved' => 'info',
                            default => 'gray',
                        };
                    @endphp

                    <x-filament::badge :color="$modioStatusColor">
                        {{ $modioConnectionStatus }}
                    </x-filament::badge>
                </div>
            </div>

            <div class="space-y-4 p-5">

                {{-- API Path --}}
                <div>
                    <label class="mb-1 block text-sm font-semibold">
                        API Path
                    </label>

                    <input
                        type="url"
                        wire:model.defer="modioApiPath"
                        placeholder="https://api.mod.io/v1"
                        autocomplete="off"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-white/10 dark:bg-gray-900"
                    >

                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Use the API path supplied by mod.io. The standard fallback is
                        <span class="font-mono">https://api.mod.io/v1</span>.
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">

                    {{-- API Key --}}
                    <div>
                        <label class="mb-1 block text-sm font-semibold">
                            API Key
                        </label>

                        <div class="flex gap-2">
                            <input
                                x-bind:type="showApiKey ? 'text' : 'password'"
                                wire:model.defer="modioApiKey"
                                autocomplete="off"
                                class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-white/10 dark:bg-gray-900"
                            >

                            <button
                                type="button"
                                x-on:click="showApiKey = !showApiKey"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5"
                            >
                                <span x-text="showApiKey ? 'Hide' : 'Show'"></span>
                            </button>
                        </div>

                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Used for Eco Enhanced's mod.io catalog and download requests.
                        </div>
                    </div>

                    {{-- Access Token --}}
                    <div>
                        <label class="mb-1 block text-sm font-semibold">
                            Access Token
                        </label>

                        <div class="flex gap-2">
                            <input
                                x-bind:type="showAccessToken ? 'text' : 'password'"
                                wire:model.defer="modioAccessToken"
                                autocomplete="off"
                                class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-white/10 dark:bg-gray-900"
                            >

                            <button
                                type="button"
                                x-on:click="showAccessToken = !showAccessToken"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5"
                            >
                                <span x-text="showAccessToken ? 'Hide' : 'Show'"></span>
                            </button>
                        </div>

                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Optional OAuth token for authenticated mod.io requests.
                        </div>
                    </div>

                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="testModioAuthentication"
                        wire:loading.attr="disabled"
                        wire:target="testModioAuthentication"
                    >
                        <span
                            wire:loading.remove
                            wire:target="testModioAuthentication"
                        >
                            Test Connection
                        </span>

                        <span
                            wire:loading
                            wire:target="testModioAuthentication"
                        >
                            Testing...
                        </span>
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        wire:click="saveModioAuthentication"
                        wire:loading.attr="disabled"
                        wire:target="saveModioAuthentication"
                    >
                        <span
                            wire:loading.remove
                            wire:target="saveModioAuthentication"
                        >
                            Save Authentication
                        </span>

                        <span
                            wire:loading
                            wire:target="saveModioAuthentication"
                        >
                            Saving...
                        </span>
                    </x-filament::button>
                </div>

            </div>
        </div>

<x-filament::section>
            <x-slot name="heading">
                mod.io
            </x-slot>

            <x-slot name="description">
                Search the Eco mod.io catalog. Installation support will be enabled after catalog access is verified.
            </x-slot>

            <form
                wire:submit.prevent="searchMods"
                class="flex flex-col gap-3 sm:flex-row"
            >
                <div class="flex-1">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model="search"
                            placeholder="Search Eco mods..."
                        />
                    </x-filament::input.wrapper>
                </div>

                <div class="flex gap-2">
                    <x-filament::button type="submit">
                        Search mod.io
                    </x-filament::button>

                    @if ($search !== '' || $searched)
                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:click="clearSearch"
                        >
                            Clear
                        </x-filament::button>
                    @endif
                </div>
            </form>
        </x-filament::section>




        <link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/material-darker.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/selection/active-line.min.js"></script>

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.min.js"></script>


{{-- Mod Configurations --}}
        <details
            open
            class="group overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
        >
            <summary
                class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 select-none hover:bg-gray-50 dark:hover:bg-white/5"
            >
                <div>
                    <div class="font-semibold text-gray-950 dark:text-white">
                        Mod Configurations
                    </div>

                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ count($modConfigs) }} configuration file(s) detected
                    </div>
                </div>

                <span
                    class="transition-transform group-open:rotate-180"
                    aria-hidden="true"
                >
                    ▼
                </span>
            </summary>

            <div class="border-t border-gray-200 p-3 dark:border-white/10">
                @if (count($modConfigs) > 0)

                    <div class="space-y-2">
                        @foreach ($modConfigs as $configIndex => $config)
                            <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-gray-700">
                                <div class="min-w-0">
                                    <div class="font-medium text-gray-950 dark:text-white">
                                        {{ $config['name'] }}
                                    </div>

                                    <div class="truncate font-mono text-xs text-gray-500 dark:text-gray-400">
                                        {{ $config['path'] }}
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <x-filament::badge color="gray">
                                        {{ number_format(($config['size'] ?? 0) / 1024, 1) }} KB
                                    </x-filament::badge>

                                    <x-filament::button
                                        type="button"
                                        size="sm"
                                        wire:click="editModConfigByIndex({{ $configIndex }})"
                                    >
                                        Edit
                                    </x-filament::button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                @else

                    <div class="rounded-xl border border-dashed border-gray-300 px-5 py-8 text-center dark:border-white/10">
                        <div class="font-semibold text-gray-950 dark:text-white">
                            No mod configuration files found
                        </div>

                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Config files created by installed Eco mods will appear here automatically.
                        </div>
                    </div>

                @endif
            </div>
        </details>

        



        @if (count($installedMods) > 0)
            
        @endif


        @if ($searched)

            @if (count($mods) === 0)

                <x-filament::section>
                    <div class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        No Eco mods were found.
                    </div>
                </x-filament::section>

            @else

                <div class="grid gap-4 lg:grid-cols-2">

                    @foreach ($mods as $mod)

                        <x-filament::section>

                            <div class="flex gap-4">

                                @if (!empty($mod['logo']))
                                    <div class="shrink-0">
                                        <img
                                            src="{{ $mod['logo'] }}"
                                            alt=""
                                            class="h-20 w-32 rounded-lg object-cover"
                                        />
                                    </div>
                                @endif

                                <div class="min-w-0 flex-1">

                                    <div class="flex flex-wrap items-start justify-between gap-2">

                                        <div>
                                            <div class="text-base font-semibold text-gray-950 dark:text-white">
                                                {{ $mod['name'] }}
                                            </div>

                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                by {{ $mod['author'] }}
                                            </div>
                                        </div>

                                        @if (!empty($mod['latest_file']['version']))
                                            <x-filament::badge color="info">
                                                {{ $mod['latest_file']['version'] }}
                                            </x-filament::badge>
                                        @endif

                                    </div>


                                    @if (!empty($mod['summary']))
                                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                                            {{ $mod['summary'] }}
                                        </p>
                                    @endif


                                    <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-gray-500 dark:text-gray-400">

                                        <span>
                                            Mod ID:
                                            {{ $mod['id'] }}
                                        </span>

                                        @if (!empty($mod['downloads']))
                                            <span>
                                                Downloads:
                                                {{ number_format($mod['downloads']) }}
                                            </span>
                                        @endif

                                        @if (!empty($mod['date_updated']))
                                            <span>
                                                Updated:
                                                {{ date('M j, Y', $mod['date_updated']) }}
                                            </span>
                                        @endif

                                        @if (!empty($mod['latest_file']['filesize']))
                                            <span>
                                                Size:
                                                {{ number_format(
                                                    $mod['latest_file']['filesize'] / 1048576,
                                                    1
                                                ) }} MB
                                            </span>
                                        @endif

                                    </div>


                                    <div class="mt-4 flex flex-wrap gap-2">

                                        @if (!empty($mod['profile_url']))
                                            <x-filament::button
                                                tag="a"
                                                href="{{ $mod['profile_url'] }}"
                                                target="_blank"
                                                color="gray"
                                                size="sm"
                                            >
                                                View on mod.io
                                            </x-filament::button>
                                        @endif

                                        @if ($this->isInstalled((int) $mod['id']))
                                            <x-filament::badge color="success">
                                                Installed
                                            </x-filament::badge>
                                        @else
                                            <x-filament::button
                                                type="button"
                                                color="primary"
                                                size="sm"
                                                wire:click="installMod({{ (int) $mod['id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="installMod({{ (int) $mod['id'] }})"
                                            >
                                                <span
                                                    wire:loading.remove
                                                    wire:target="installMod({{ (int) $mod['id'] }})"
                                                >
                                                    Install
                                                </span>

                                                <span
                                                    wire:loading
                                                    wire:target="installMod({{ (int) $mod['id'] }})"
                                                >
                                                    Installing...
                                                </span>
                                            </x-filament::button>
                                        @endif

                                    </div>

                                </div>

                            </div>

                        </x-filament::section>

                    @endforeach

                </div>

            @endif

        @endif


        {{-- Installed Mods --}}
        @if (true)

            <details
                open
                class="group overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 select-none hover:bg-gray-50 dark:hover:bg-white/5"
                >
                    <div>
                        <div class="font-semibold text-gray-950 dark:text-white">
                            Installed Mods
                        </div>

                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span>
                                {{ count($installedMods) }} Installed
                            </span>

                            <span>•</span>

                            <span>
                                {{
                                    collect($installedMods)
                                        ->filter(fn ($mod) => !empty($mod['dependency']))
                                        ->count()
                                }} Dependencies
                            </span>

                            <span>•</span>

                            <span>
                                {{
                                    collect($installedMods)
                                        ->reject(fn ($mod) => !empty($mod['dependency']))
                                        ->count()
                                }} Direct Installs
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span
                            class="transition-transform group-open:rotate-180"
                            aria-hidden="true"
                        >
                            ▼
                        </span>
                    </div>
                </summary>

                <div class="border-t border-gray-200 p-3 dark:border-white/10">

                    <div class="mb-3 flex justify-end">
                        <x-filament::button
                            color="gray"
                            size="sm"
                            wire:click="checkForUpdates"
                            wire:loading.attr="disabled"
                            wire:target="checkForUpdates"
                        >
                            <span
                                wire:loading.remove
                                wire:target="checkForUpdates"
                            >
                                Check for Updates
                            </span>

                            <span
                                wire:loading
                                wire:target="checkForUpdates"
                            >
                                Checking...
                            </span>
                        </x-filament::button>
                    </div>

                    <div class="space-y-2">

                        @forelse ($installedMods as $installed)

                            @php
                                $installedModId =
                                    (int) ($installed['mod_id'] ?? 0);

                                $updateAvailable =
                                    $installedModId > 0 &&
                                    $this->hasUpdate($installedModId);

                                $availableVersion =
                                    $updateAvailable
                                        ? $this->latestVersion($installedModId)
                                        : null;
                            @endphp

                            <div
                                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-gray-700"
                            >

                                <div>
                                    <div class="font-medium text-gray-950 dark:text-white">
                                        {{ $installed['name'] ?? 'Unknown Mod' }}
                                    </div>

                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">

                                        <span>
                                            Version {{ $installed['version'] ?? 'Unknown' }}
                                        </span>

                                        @if ($updateAvailable)

                                            <x-filament::badge color="warning">
                                                Update Available
                                            </x-filament::badge>

                                            @if ($availableVersion)
                                                <span class="font-medium text-warning-600 dark:text-warning-400">
                                                    {{ $installed['version'] ?? 'Unknown' }}
                                                    →
                                                    {{ $availableVersion }}
                                                </span>
                                            @endif

                                        @else

                                            <x-filament::badge color="success">
                                                Current
                                            </x-filament::badge>

                                        @endif

                                        @if (!empty($installed['dependency']))
                                            <x-filament::badge color="info">
                                                Dependency
                                            </x-filament::badge>
                                        @else
                                            <x-filament::badge color="gray">
                                                Direct Install
                                            </x-filament::badge>
                                        @endif

                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5">

                                    @if ($updateAvailable)

                                        <x-filament::button
                                            color="success"
                                            size="sm"
                                            wire:click="updateMod({{ $installedModId }})"
                                            wire:loading.attr="disabled"
                                            wire:target="updateMod({{ $installedModId }})"
                                        >
                                            <span
                                                wire:loading.remove
                                                wire:target="updateMod({{ $installedModId }})"
                                            >
                                                Update
                                            </span>

                                            <span
                                                wire:loading
                                                wire:target="updateMod({{ $installedModId }})"
                                            >
                                                Updating...
                                            </span>
                                        </x-filament::button>

                                    @endif

                                    @if (($installed['enabled'] ?? true) === true)

                                        <x-filament::badge color="success">
                                            Installed
                                        </x-filament::badge>

                                        <x-filament::button
                                            color="warning"
                                            size="sm"
                                            wire:click="disableMod({{ (int) ($installed['mod_id'] ?? 0) }})"
                                            wire:loading.attr="disabled"
                                        >
                                            Disable
                                        </x-filament::button>

                                    @else

                                        <x-filament::badge color="warning">
                                            Disabled
                                        </x-filament::badge>

                                        <x-filament::button
                                            color="success"
                                            size="sm"
                                            wire:click="enableMod({{ (int) ($installed['mod_id'] ?? 0) }})"
                                            wire:loading.attr="disabled"
                                        >
                                            Enable
                                        </x-filament::button>

                                    @endif

                                    <x-filament::button
                                        color="gray"
                                        size="sm"
                                        wire:click="reinstallMod({{ (int) ($installed['mod_id'] ?? 0) }})"
                                        wire:loading.attr="disabled"
                                    >
                                        Reinstall
                                    </x-filament::button>

                                    <x-filament::button
                                        color="danger"
                                        size="sm"
                                        wire:click="deleteMod({{ (int) ($installed['mod_id'] ?? 0) }})"
                                        wire:loading.attr="disabled"
                                    >
                                        Delete
                                    </x-filament::button>

                                </div>

                            </div>

                        @empty

                            <div class="rounded-xl border border-dashed border-gray-300 px-5 py-10 text-center dark:border-white/10">
                                <div class="font-semibold text-gray-950 dark:text-white">
                                    No mods installed yet
                                </div>

                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Search the mod.io catalog above to install your first Eco mod.
                                </div>
                            </div>

                        @endforelse

                    </div>

                </div>

            </details>

        @endif


        {{-- Mod Configurations --}}
        @if (count($modConfigs) > 0)

            <details
                class="group overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3 select-none hover:bg-gray-50 dark:hover:bg-white/5"
                >
                    <div>
                        <div class="font-semibold text-gray-950 dark:text-white">
                            Mod Configurations

                            <span class="ml-1 text-xs font-normal text-gray-500 dark:text-gray-400">
                                ({{ count($modConfigs) }})
                            </span>
                        </div>

                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            Edit configuration files created by installed Eco mods.
                        </div>
                    </div>

                    <span
                        class="transition-transform group-open:rotate-180"
                        aria-hidden="true"
                    >
                        ▼
                    </span>
                </summary>

                <div class="border-t border-gray-200 p-3 dark:border-white/10">

                    <div class="space-y-2">

                        @foreach ($modConfigs as $config)

                            <div
                                class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 p-3 dark:border-gray-700"
                            >

                                <div class="min-w-0">

                                    <div class="font-medium text-gray-950 dark:text-white">
                                        {{ $config['name'] }}
                                    </div>

                                    <div class="truncate text-xs text-gray-500 dark:text-gray-400">
                                        {{ $config['path'] }}
                                    </div>

                                </div>

                                <div class="flex shrink-0 items-center gap-2">

                                    <x-filament::badge color="gray">
                                        {{ number_format(($config['size'] ?? 0) / 1024, 1) }} KB
                                    </x-filament::badge>

                                    <x-filament::button
                                        size="sm"
                                        wire:click="editModConfig('{{ $config['path'] }}')"
                                        wire:loading.attr="disabled"
                                    >
                                        Edit
                                    </x-filament::button>

                                </div>

                            </div>

                        @endforeach

                    </div>

                </div>

            </details>

        @endif

@if ($editingConfigPath !== null)
            <x-filament::section>
                <x-slot name="heading">
                    Editing: {{ basename($editingConfigPath) }}
                </x-slot>

                <div class="space-y-3">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $editingConfigPath }}
                    </div>

                    <div
                        wire:ignore
                        x-data="{
                            editor: null,
                            jsonStatus: 'unknown',

                            checkJson() {
                                const path = @js($editingConfigPath ?? '');

                                if (!path.toLowerCase().endsWith('.json')) {
                                    this.jsonStatus = 'text';
                                    return;
                                }

                                try {
                                    JSON.parse(this.editor.getValue());
                                    this.jsonStatus = 'valid';
                                } catch (e) {
                                    this.jsonStatus = 'invalid';
                                }
                            },

                            formatJson() {
                                try {
                                    const parsed = JSON.parse(
                                        this.editor.getValue()
                                    );

                                    const formatted = JSON.stringify(
                                        parsed,
                                        null,
                                        4
                                    );

                                    this.editor.setValue(formatted);
                                    this.checkJson();
                                } catch (e) {
                                    this.jsonStatus = 'invalid';
                                }
                            },

                            findText() {
                                this.editor.focus();
                                this.editor.execCommand('find');
                            },

                            replaceText() {
                                this.editor.focus();
                                this.editor.execCommand('replace');
                            },

                            initEditor() {
                                const textarea = this.$refs.editor;

                                if (typeof CodeMirror === 'undefined') {
                                    return;
                                }

                                this.editor = CodeMirror.fromTextArea(textarea, {
                                    lineNumbers: true,
                                    mode: 'application/json',
                                    theme: 'material-darker',
                                    indentUnit: 4,
                                    tabSize: 4,
                                    indentWithTabs: false,
                                    lineWrapping: false,
                                    matchBrackets: true,
                                    autoCloseBrackets: true,
                                    styleActiveLine: true,
                                    extraKeys: {
                                        'Ctrl-S': () => {
                                            this.$wire.saveModConfig();
                                        },
                                        'Cmd-S': () => {
                                            this.$wire.saveModConfig();
                                        }
                                    }
                                });

                                this.editor.on('change', () => {
                                    this.$wire.set(
                                        'editingConfigContent',
                                        this.editor.getValue()
                                    );

                                    this.checkJson();
                                });

                                this.checkJson();
                            }
                        }"
                        x-init="initEditor()"
                        class="overflow-hidden rounded-xl border border-gray-700"
                    >
                        <div class="flex flex-wrap items-center gap-2 border-b border-gray-700 bg-gray-900 px-3 py-2">
                            <button
                                type="button"
                                x-on:click="findText()"
                                class="rounded-md bg-gray-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-600"
                            >
                                Find
                            </button>

                            <button
                                type="button"
                                x-on:click="replaceText()"
                                class="rounded-md bg-gray-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-600"
                            >
                                Replace
                            </button>

                            <button
                                type="button"
                                x-on:click="formatJson()"
                                class="rounded-md bg-gray-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-600"
                            >
                                Format JSON
                            </button>

                            <div class="ml-auto text-xs">
                                <span
                                    x-show="jsonStatus === 'valid'"
                                    class="font-medium text-green-400"
                                >
                                    ✓ Valid JSON
                                </span>

                                <span
                                    x-show="jsonStatus === 'invalid'"
                                    class="font-medium text-red-400"
                                >
                                    ✕ Invalid JSON
                                </span>

                                <span
                                    x-show="jsonStatus === 'text'"
                                    class="text-gray-400"
                                >
                                    Text file
                                </span>
                            </div>
                        </div>

                        <textarea
                            x-ref="editor"
                            spellcheck="false"
                        >{{ $editingConfigContent }}</textarea>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-filament::button
                            wire:click="saveModConfig"
                            wire:loading.attr="disabled"
                            wire:target="saveModConfig"
                        >
                            Save
                        </x-filament::button>

                        <x-filament::button
                            color="gray"
                            wire:click="reloadModConfig"
                        >
                            Reload
                        </x-filament::button>

                        <x-filament::button
                            color="gray"
                            wire:click="closeModConfigEditor"
                        >
                            Cancel
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endif




    </div>


<style>
    /* Eco Enhanced Eco Mods compact polish */

    .eco-enhanced-mods-polish .fi-section {
        --tw-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
    }

    .eco-enhanced-mods-polish .space-y-6 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 1rem !important;
    }

    .eco-enhanced-mods-polish .space-y-4 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 0.75rem !important;
    }

    .eco-enhanced-mods-polish .space-y-3 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 0.55rem !important;
    }

    .eco-enhanced-mods-polish .p-6 {
        padding: 1rem !important;
    }

    .eco-enhanced-mods-polish .p-4 {
        padding: 0.8rem !important;
    }

    .eco-enhanced-mods-polish .p-3 {
        padding: 0.7rem !important;
    }

    .eco-enhanced-mods-polish input,
    .eco-enhanced-mods-polish select {
        min-height: 2.25rem;
    }

    .eco-enhanced-mods-polish button {
        white-space: nowrap;
    }

    @media (max-width: 768px) {
        .eco-enhanced-mods-polish button {
            white-space: normal;
        }
    }
</style>


    @if ($editingConfigPath !== null)
        <div
            class="fixed inset-0 z-[99999] flex items-center justify-center p-6"
            style="background: rgba(0,0,0,.65);"
        >
            <div
                class="flex flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900"
                style="
                    width: min(1200px, calc(100vw - 48px));
                    height: min(850px, calc(100vh - 48px));
                    max-width: 1200px;
                    max-height: calc(100vh - 48px);
                "
            >
                <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-white/10">
                    <div class="min-w-0">
                        <div class="text-base font-semibold">
                            Edit Mod Configuration
                        </div>

                        <div class="mt-1 truncate font-mono text-xs text-gray-500 dark:text-gray-400">
                            {{ $editingConfigPath }}
                        </div>
                    </div>

                    <x-filament::button
                        type="button"
                        color="gray"
                        size="sm"
                        wire:click="closeModConfigEditor"
                    >
                        Close
                    </x-filament::button>
                </div>

                <div class="flex min-h-0 flex-1 flex-col p-5">
                    <textarea
                        wire:model.defer="editingConfigContent"
                        spellcheck="false"
                        autocomplete="off"
                        class="w-full flex-1 resize-none rounded-xl border border-gray-300 bg-gray-950 p-4 font-mono text-sm leading-6 text-gray-100 dark:border-white/10"
                        style="
                            tab-size: 4;
                            min-height: 0;
                            height: 100%;
                        "
                    ></textarea>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-5 py-4 dark:border-white/10">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        JSON files are validated before saving.
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:click="reloadModConfig"
                        >
                            Reload From File
                        </x-filament::button>

                        <x-filament::button
                            type="button"
                            wire:click="saveModConfig"
                            wire:loading.attr="disabled"
                            wire:target="saveModConfig"
                        >
                            <span
                                wire:loading.remove
                                wire:target="saveModConfig"
                            >
                                Save Config
                            </span>

                            <span
                                wire:loading
                                wire:target="saveModConfig"
                            >
                                Saving...
                            </span>
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</x-filament-panels::page>
