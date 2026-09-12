<x-filament-panels::page>

    <div
        id="eco-enhanced-settings-root"
        class="eco-enhanced-compact space-y-6"
    >

        {{-- Eco Settings Search --}}
        <div
            class="sticky top-2 z-20 rounded-xl border border-gray-200 bg-white/95 p-4 shadow-sm backdrop-blur dark:border-white/10 dark:bg-gray-900/95"
        >
            <div>
                <label
                    for="eco-settings-search"
                    class="mb-1 block text-sm font-semibold"
                >
                    Search Eco Settings
                </label>

                <div class="flex gap-2">
                    <input
                        id="eco-settings-search"
                        type="search"
                        placeholder="Search category, meteor, RCON, Discord, specialties..."
                        autocomplete="off"
                        oninput="window.ecoEnhancedSettingsSearch(this.value)"
                        class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                    >

                    <button
                        type="button"
                        onclick="window.ecoEnhancedSettingsClear()"
                        class="shrink-0 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:hover:bg-white/5"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <div
                id="eco-settings-search-status"
                class="mt-2 text-xs text-gray-500 dark:text-gray-400"
            >
                Search setting names across Eco configuration.
            </div>
        </div>

        <script>
            window.ecoEnhancedSettingsSearch = function (value) {
                const query = String(value || '')
                    .trim()
                    .toLowerCase();

                const root = document.getElementById(
                    'eco-enhanced-settings-root'
                );

                const status = document.getElementById(
                    'eco-settings-search-status'
                );

                if (!root) {
                    return;
                }

                /*
                 * Remove previous highlighting.
                 */
                root
                    .querySelectorAll('.eco-enhanced-eco-search-match')
                    .forEach(function (el) {
                        el.classList.remove(
                            'eco-enhanced-search-match'
                        );

                        el.style.outline = '';
                        el.style.outlineOffset = '';
                        el.style.borderRadius = '';
                    });

                if (!query) {
                    if (status) {
                        status.textContent =
                            'Search setting names across Eco configuration.';
                    }

                    return;
                }

                /*
                 * Labels are the most reliable representation of an
                 * individual setting on this page.
                 */
                const labels = Array.from(
                    root.querySelectorAll('label')
                );

                const matches = labels.filter(function (label) {
                    return String(label.innerText || '')
                        .replace(/\s+/g, ' ')
                        .trim()
                        .toLowerCase()
                        .includes(query);
                });

                matches.forEach(function (label) {
                    label.classList.add(
                        'eco-enhanced-search-match'
                    );

                    label.style.outline =
                        '2px solid rgb(59 130 246 / 0.55)';

                    label.style.outlineOffset = '4px';
                    label.style.borderRadius = '4px';

                    /*
                     * Open Alpine-controlled collapsible ancestors
                     * containing the matching setting.
                     */
                    let parent = label.parentElement;

                    while (parent && parent !== root) {
                        if (parent.hasAttribute('x-show')) {
                            parent.style.display = '';
                        }

                        parent = parent.parentElement;
                    }
                });

                if (status) {
                    status.textContent =
                        matches.length === 1
                            ? '1 matching setting found.'
                            : matches.length + ' matching settings found.';
                }

                if (matches.length > 0) {
                    matches[0].scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            };

            window.ecoEnhancedSettingsClear = function () {
                const input = document.getElementById(
                    'eco-settings-search'
                );

                if (!input) {
                    return;
                }

                input.value = '';

                window.ecoEnhancedSettingsSearch('');

                input.focus();
            };
        </script>

        {{-- Native Eco Game Configuration ----------------------------- --}}
        <div
            x-data="{
                open: (() => {
                    const saved = localStorage.getItem('eco-enhanced-configs-game');
                    return saved === null ? true : saved === 'true';
                })()
            }"
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
        >
            <button
                type="button"
                class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-gray-50 dark:hover:bg-white/5"
                x-on:click="
                    open = !open;
                    localStorage.setItem('eco-enhanced-configs-game', open ? 'true' : 'false');
                "
            >
                <div>
                    <div class="text-base font-semibold">
                        Game Configuration
                    </div>

                    <div class="mt-1 text-xs font-normal text-gray-500 dark:text-gray-400">
                        Native Eco server, gameplay, difficulty, and world settings.
                    </div>
                </div>

                <span
                    aria-hidden="true"
                    class="inline-flex shrink-0 items-center justify-center text-lg text-gray-500"
                    x-bind:style="open
                        ? 'width:20px;height:20px;transform:rotate(180deg)'
                        : 'width:20px;height:20px;transform:rotate(0deg)'"
                >
                    &#9662;
                </span>
            </button>

            <div
                x-show="open"
                x-collapse
                x-cloak
                class="border-t border-gray-200 dark:border-white/10"
            >
                <div class="space-y-5 p-5">

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                        <div>
                            <div class="font-semibold">
                                Game Configuration Controls
                            </div>

                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Save or reload all native Eco configuration sections together.
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <x-filament::button
                                wire:click="saveAllGameSettings"
                                wire:loading.attr="disabled"
                            >
                                Save All Game Configs
                            </x-filament::button>

                            <x-filament::button
                                color="gray"
                                wire:click="refreshAllGameSettings"
                                wire:loading.attr="disabled"
                            >
                                Reload All Game Configs
                            </x-filament::button>
                        </div>
                    </div>

                    {{-- SERVER / NETWORK --}}
                    <div
                        x-data="{
                            open: (() => {
                                const saved = localStorage.getItem('eco-enhanced-native-network');
                                return saved === null ? true : saved === 'true';
                            })()
                        }"
                        class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between bg-gray-50 px-4 py-3 text-left dark:bg-white/5"
                            x-on:click="
                                open = !open;
                                localStorage.setItem('eco-enhanced-native-network', open ? 'true' : 'false');
                            "
                        >
                            <span class="font-semibold">Server & Network</span>

                            <span
                                aria-hidden="true"
                                x-bind:style="open
                                    ? 'width:20px;transform:rotate(180deg)'
                                    : 'width:20px;transform:rotate(0deg)'"
                            >&#9662;</span>
                        </button>

                        <div x-show="open" x-collapse x-cloak>
                            <div class="space-y-6 border-t border-gray-200 p-4 dark:border-white/10">

                                <div class="grid gap-4 lg:grid-cols-3">
                                    <div>
                                        <label class="mb-1 block text-sm font-semibold">Server Name</label>
                                        <input type="text"
                                               wire:model="networkName"
                                               class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-semibold">Server Category</label>
                                        <select wire:model="networkServerCategory"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                            <option value="None">None</option>
                                            <option value="Beginner">Beginner</option>
                                            <option value="Established">Established</option>
                                            <option value="Beginner Hard">Beginner Hard</option>
                                            <option value="Strange">Strange</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-semibold">
                                            Playtime Preset
                                        </label>

                                        <select
                                            wire:model="networkPlaytime"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                        >
                                            <option value="222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222">
                                                All the time
                                            </option>

                                            @if (
                                                $networkPlaytime !== '' &&
                                                $networkPlaytime !== '222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222222'
                                            )
                                                <option value="{{ $networkPlaytime }}">
                                                    Custom / Eco managed
                                                </option>
                                            @endif
                                        </select>

                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Controls when Eco lists the server as normally active.
                                        </div>
                                    </div>

                                    <div class="lg:col-span-3">
                                        <label class="mb-1 block text-sm font-semibold">Server Description</label>
                                        <textarea wire:model="networkDescription"
                                                  rows="3"
                                                  class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"></textarea>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-semibold">Discord Address</label>
                                        <input type="text"
                                               wire:model="networkDiscordAddress"
                                               class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                    </div>

                                    <label class="flex items-center gap-2 pt-7 text-sm font-medium">
                                        <input type="checkbox"
                                               wire:model="networkPublicServer">
                                        Public Server
                                    </label>

                                    <label class="flex items-center gap-2 pt-7 text-sm font-medium">
                                        <input type="checkbox"
                                               wire:model="networkUpnpEnabled">
                                        UPnP Enabled
                                    </label>
                                </div>

                                <div class="border-t border-gray-200 pt-5 dark:border-white/10">
                                    <div class="mb-3 font-semibold">Connection & Ports</div>

                                    <div class="grid gap-4 lg:grid-cols-4">
                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">IP Address</label>
                                            <input type="text" wire:model="networkIpAddress"
                                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">Game Port</label>
                                            <input type="number" wire:model="networkGamePort"
                                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">Web Port</label>
                                            <input type="number" wire:model="networkWebPort"
                                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">Steam Port</label>
                                            <input type="number" wire:model="networkSteamPort"
                                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                        </div>

                                        <div class="lg:col-span-2">
                                            <label class="mb-1 block text-sm font-semibold">Remote Address</label>
                                            <input type="text"
                                                   wire:model="networkRemoteAddress"
                                                   placeholder="public-ip:game-port"
                                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                        </div>

                                        <div class="lg:col-span-2">
                                            <label class="mb-1 block text-sm font-semibold">Web Server URL</label>
                                            <input type="text"
                                                   wire:model="networkWebServerUrl"
                                                   placeholder="http://public-ip:web-port/"
                                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t border-gray-200 pt-5 dark:border-white/10">
                                    <div class="mb-3 font-semibold">RCON</div>

                                    <div class="grid gap-4 lg:grid-cols-3">
                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">RCON IP Address</label>
                                            <input type="text"
                                                   wire:model="networkRconIpAddress"
                                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">RCON Port</label>
                                            <input type="number"
                                                   wire:model="networkRconPort"
                                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                        </div>

                                        <div x-data="{ show: false }">
                                            <label class="mb-1 block text-sm font-semibold">RCON Password</label>

                                            <div class="flex gap-2">
                                                <input x-bind:type="show ? 'text' : 'password'"
                                                       wire:model="networkRconPassword"
                                                       autocomplete="off"
                                                       class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">

                                                <x-filament::button
                                                    type="button"
                                                    color="gray"
                                                    x-on:click="show = !show"
                                                >
                                                    <span x-text="show ? 'Hide' : 'Show'"></span>
                                                </x-filament::button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t border-gray-200 pt-5 dark:border-white/10">
                                    <div class="mb-3 font-semibold">Capacity</div>

                                    <div class="grid gap-4 lg:grid-cols-4">
                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">Rate</label>
                                            <input type="number"
                                                   wire:model="networkRate"
                                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">Default Slots</label>
                                            <input type="number"
                                                   wire:model="networkDefaultSlots"
                                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">Reserved Slots</label>
                                            <input type="number"
                                                   wire:model="networkReservedSlots"
                                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">Max Users Loading At Same Time</label>
                                            <input type="number"
                                                   wire:model="networkMaxLoadingUsers"
                                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                        </div>

                                        <div class="lg:col-span-2">
                                            <label class="mb-1 block text-sm font-semibold">Relay Server Address</label>
                                            <input type="text"
                                                   wire:model="networkRelayAddress"
                                                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t border-gray-200 pt-5 dark:border-white/10">
                                    <div class="mb-3 font-semibold">Server Authentication</div>

                                    <div x-data="{ show: false }">
                                        <label class="mb-1 block text-sm font-semibold">
                                            Eco Server Authentication Token
                                        </label>

                                        <div class="flex gap-2">
                                            <input
                                                x-bind:type="show ? 'text' : 'password'"
                                                wire:model="ecoServerToken"
                                                autocomplete="off"
                                                class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-white/10 dark:bg-gray-900"
                                            >

                                            <x-filament::button
                                                type="button"
                                                color="gray"
                                                x-on:click="show = !show"
                                            >
                                                <span x-text="show ? 'Hide' : 'Show'"></span>
                                            </x-filament::button>
                                        </div>

                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Stored in the Eco Egg's ECO_AUTH_TOKEN variable.
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <x-filament::button wire:click="saveNetworkSettings">
                                        Save Server & Network
                                    </x-filament::button>

                                    <x-filament::button
                                        color="gray"
                                        wire:click="refreshNetworkSettings"
                                    >
                                        Reload From File
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    </div>


                    {{-- DIFFICULTY --}}
                    <div
                        x-data="{
                            open: (() => {
                                const saved = localStorage.getItem('eco-enhanced-native-difficulty');
                                return saved === null ? true : saved === 'true';
                            })()
                        }"
                        class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between bg-gray-50 px-4 py-3 text-left dark:bg-white/5"
                            x-on:click="
                                open = !open;
                                localStorage.setItem('eco-enhanced-native-difficulty', open ? 'true' : 'false');
                            "
                        >
                            <span class="font-semibold">Difficulty & Gameplay</span>
                            <span x-bind:style="open ? 'width:20px;transform:rotate(180deg)' : 'width:20px;transform:rotate(0deg)'">&#9662;</span>
                        </button>

                        <div x-show="open" x-collapse x-cloak>
                            <div class="space-y-5 border-t border-gray-200 p-4 dark:border-white/10">

                                <div class="grid gap-4 lg:grid-cols-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-semibold">
                                            Desired Number Of Players
                                        </label>
                                        <input
                                            type="number"
                                            min="1"
                                            wire:model="difficultyDesiredPlayers"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                        >
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Target number of active players Eco uses when balancing progression and collaboration.
                                            </div>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-semibold">
                                            Hours Played Per Day
                                        </label>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.1"
                                            wire:model="difficultyHoursPlayedPerDay"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                        >
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Expected average number of hours each player will play per day. Used when balancing progression.
                                            </div>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-semibold">
                                            Collaboration Level
                                        </label>

                                        <select
                                            wire:model="difficultyCollaborationLevel"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                        >
                                            <option value="NoCollaboration">No Collaboration</option>
                                            <option value="LowCollaboration">Low Collaboration</option>
                                            <option value="MediumCollaboration">Medium Collaboration</option>
                                            <option value="HighCollaboration">High Collaboration</option>
                                        </select>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Controls how much players must specialize and depend on each other. Higher collaboration makes individual specialization more important.
                                            </div>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-semibold">
                                            Game Speed
                                        </label>

                                        <select
                                            wire:model="difficultyGameSpeed"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                        >
                                            <option value="Very Slow">Very Slow</option>
                                            <option value="Slow">Slow</option>
                                            <option value="Normal">Normal</option>
                                            <option value="Fast">Fast</option>
                                            <option value="Very Fast">Very Fast</option>
                                        </select>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Controls the overall progression-speed preset for the server.
                                            </div>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-semibold">
                                            Animal Behavior
                                        </label>

                                        <select
                                            wire:model="difficultyAnimalBehavior"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                        >
                                            <option value="AttackNormally">Attack Normally</option>
                                            <option value="DefensiveOnly">Defensive Only</option>
                                            <option value="None">Never Attack</option>
                                        </select>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Controls how aggressively animals react to players.
                                            </div>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-sm font-semibold">
                                            Simulation Level
                                        </label>

                                        <select
                                            wire:model="difficultySimulationLevel"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                        >
                                            <option value="Generous">Generous</option>
                                            <option value="Normal">Normal</option>
                                            <option value="Hardcore">Hardcore</option>
                                        </select>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Controls Eco's simulation difficulty preset. Generous is easier, Normal is standard, and Hardcore is more demanding.
                                            </div>
                                    </div>

                                    @foreach ([
                                        [
                                            'difficultyMeteorDays',
                                            'Meteor Impact In Days',
                                            'Number of real-world days before the meteor impacts when the meteor is enabled.',
                                        ],
                                        [
                                            'difficultyMaxProfessions',
                                            'Max Professions Per Citizen',
                                            'Maximum number of professions a single citizen may learn.',
                                        ],
                                        [
                                            'difficultyMaxSpecialties',
                                            'Max Specialties Per Citizen',
                                            'Maximum number of specialties a single citizen may unlock.',
                                        ],
                                    ] as [$model, $label, $description])
                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">
                                                {{ $label }}
                                            </label>

                                            <input
                                                type="text"
                                                wire:model="{{ $model }}"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                            >

                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ $description }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                                    @php
                                        $difficultyBooleanSettings = [
                                            [
                                                'difficultyExhaustionEnabled',
                                                'Exhaustion Enabled',
                                                'Enables Eco\'s exhaustion system and its limits on specialty progression.',
                                            ],
                                            [
                                                'difficultyHasMeteor',
                                                'Meteor Enabled',
                                                'Enables the meteor countdown and impact objective.',
                                            ],
                                            [
                                                'difficultyAllowFriends',
                                                'Allow Friends To Join',
                                                'Allows Eco\'s friend-access behavior where supported.',
                                            ],
                                            [
                                                'difficultyCanAbandonSpecialties',
                                                'Can Abandon Specialties',
                                                'Allows players to abandon previously learned specialties.',
                                            ],
                                            [
                                                'difficultyAreaBonusRequiresProfession',
                                                'Area Bonus Requires Profession Citizens',
                                                'Requires qualifying profession citizens for area bonuses.',
                                            ],
                                            [
                                                'difficultyAllowDeepOceanBuilding',
                                                'Allow Deep Ocean Building',
                                                'Allows construction in normally restricted deep-ocean areas.',
                                            ],
                                            [
                                                'difficultyRequireSkillsToReplaceParts',
                                                'Require Skills To Replace Parts',
                                                'Requires the appropriate skill to replace machine or vehicle parts.',
                                            ],
                                            [
                                                'difficultyBrokenPartsDisableVehicles',
                                                'Broken Parts Disable Vehicles',
                                                'Vehicles stop functioning when required parts are broken.',
                                            ],
                                            [
                                                'difficultyPlayerCanDrown',
                                                'Player Can Drown While Swimming',
                                                'Allows players to drown while swimming when they run out of breath.',
                                            ],
                                        ];
                                    @endphp

                                    @foreach ($difficultyBooleanSettings as [$model, $label, $description])
                                        <label class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-white/10">
                                            <span class="flex items-center gap-2 font-medium">
                                                <input type="checkbox" wire:model="{{ $model }}">
                                                {{ $label }}
                                            </span>

                                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                                {{ $description }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="border-t border-gray-200 pt-5 dark:border-white/10">
                                    <div class="mb-3 font-semibold">Skills & Economy</div>

                                    @php
                                        $economyFields = [
                                            [
                                                'difficultySkillCostMultiplier',
                                                'Skill Cost Multiplier',
                                                'Multiplies the XP cost of learning specialties. 1 is normal; higher values make specialties more expensive.',
                                            ],
                                            [
                                                'difficultyAdditionalSpecialtyCost',
                                                'Cost Per Additional Specialty',
                                                'Adds extra specialty cost as a citizen learns more specialties.',
                                            ],
                                            [
                                                'difficultyCraftResourceMultiplier',
                                                'Craft Resource Multiplier',
                                                'Multiplies crafting resource requirements. 1 is normal, 0.5 is half cost, and 2 is double cost.',
                                            ],
                                            [
                                                'difficultyCraftTimeMultiplier',
                                                'Craft Time Multiplier',
                                                'Multiplies crafting time. 1 is normal, 0.5 is twice as fast, and 2 takes twice as long.',
                                            ],
                                            [
                                                'difficultyStackSizeMultiplier',
                                                'Stack Size Multiplier',
                                                'Multiplies normal stack sizes. 1 is normal and 2 approximately doubles stack size.',
                                            ],
                                            [
                                                'difficultyWeightMultiplier',
                                                'Weight Multiplier',
                                                'Multiplies item weight. 1 is normal; values below 1 make items lighter.',
                                            ],
                                            [
                                                'difficultyFuelEfficiencyMultiplier',
                                                'Fuel Efficiency Multiplier',
                                                'Multiplies fuel efficiency. Higher values make fuel last longer.',
                                            ],
                                            [
                                                'difficultyGrowthRateMultiplier',
                                                'Growth Rate Multiplier',
                                                'Multiplies plant and crop growth rate. Higher values make plants grow faster.',
                                            ],
                                            [
                                                'difficultyConnectionRangeMultiplier',
                                                'Connection Range Multiplier',
                                                'Multiplies connection range for supported machines and infrastructure.',
                                            ],
                                            [
                                                'difficultyShelfLifeMultiplier',
                                                'Shelf Life Multiplier',
                                                'Multiplies how long perishable items remain fresh. Higher values increase shelf life.',
                                            ],
                                            [
                                                'difficultyAnimalAttackFrequencyMultiplier',
                                                'Animal Attack Frequency Multiplier',
                                                'Multiplies how often animals perform unprovoked attacks. 1 is normal.',
                                            ],
                                            [
                                                'difficultyAreaBonusMinProfession',
                                                'Area Bonus Minimum Citizens',
                                                'Minimum number of qualifying profession citizens required for profession-based area bonuses.',
                                            ],
                                            [
                                                'difficultySpecialtyRefundPercentage',
                                                'Specialty Refund Percentage',
                                                'Percentage of specialty investment returned when a player abandons a specialty.',
                                            ],
                                            [
                                                'difficultyCharacterExpWithSpecialty',
                                                'Character XP With Specialty XP',
                                                'Controls character XP gained alongside specialty experience.',
                                            ],
                                            [
                                                'difficultyClaimStakes',
                                                'Claim Stakes Granted',
                                                'Number of claim stakes granted when consuming a skill scroll.',
                                            ],
                                            [
                                                'difficultyClaimPapers',
                                                'Claim Papers Granted',
                                                'Number of claim papers granted when consuming a skill scroll.',
                                            ],
                                        ];
                                    @endphp

                                    <div class="grid gap-4 lg:grid-cols-4">
                                        @foreach ($economyFields as [$model, $label, $description])
                                            <div>
                                                <label class="mb-1 block text-sm font-semibold">
                                                    {{ $label }}
                                                </label>

                                                <input
                                                    type="text"
                                                    wire:model="{{ $model }}"
                                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                                >

                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $description }}
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div
                                        class="mt-4"
                                        style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-start;"
                                    >
                                        <div style="width:320px; max-width:100%;">
                                            <label class="mb-1 block text-sm font-semibold">
                                                Endgame Craft Cost
                                            </label>

                                            <select
                                                wire:model="difficultyEndgameCraftCost"
                                                style="width:320px; max-width:100%;"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                            >
                                                <option value="Normal">Normal</option>
                                                <option value="Expensive">Expensive</option>
                                            </select>

                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Controls the preset cost for endgame recipes such as the Laser and Computer Lab.
                                            </div>
                                        </div>

                                        <div style="width:320px; max-width:100%;">
                                            <label class="mb-1 block text-sm font-semibold">
                                                Skillbook Craft Cost
                                            </label>

                                            <select
                                                wire:model="difficultySkillbookCraftCost"
                                                style="width:320px; max-width:100%;"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                            >
                                                <option value="Normal">Normal</option>
                                                <option value="Expensive">Expensive</option>
                                            </select>

                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Controls the Skillbook crafting-cost preset.
                                            </div>
                                        </div>
                                    </div>

                                <div class="flex flex-wrap gap-2">
                                    <x-filament::button wire:click="saveDifficultySettings">
                                        Save Difficulty
                                    </x-filament::button>

                                    <x-filament::button
                                        color="gray"
                                        wire:click="refreshDifficultySettings"
                                    >
                                        Reload From File
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    </div>


                    {{-- EXHAUSTION --}}
                    <div
                        x-data="{
                            open: (() => {
                                const saved = localStorage.getItem('eco-enhanced-native-exhaustion');
                                return saved === null ? false : saved === 'true';
                            })()
                        }"
                        class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between bg-gray-50 px-4 py-3 text-left dark:bg-white/5"
                            x-on:click="
                                open = !open;
                                localStorage.setItem(
                                    'eco-enhanced-native-exhaustion',
                                    open ? 'true' : 'false'
                                );
                            "
                        >
                            <span class="font-semibold">
                                Exhaustion & Playtime
                            </span>

                            <span
                                x-bind:style="open
                                    ? 'width:20px;transform:rotate(180deg)'
                                    : 'width:20px;transform:rotate(0deg)'"
                            >
                                &#9662;
                            </span>
                        </button>

                        <div x-show="open" x-collapse x-cloak>
                            <div class="space-y-6 border-t border-gray-200 p-4 dark:border-white/10">

                                <div>
                                    <div class="mb-3 font-semibold">
                                        Daily Refresh Time
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">
                                                Refresh Hour
                                            </label>

                                            <input
                                                type="number"
                                                min="0"
                                                max="23"
                                                wire:model="exhaustionRefreshHour"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                            >

                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Local hour when Eco refreshes exhaustion time. Uses 0–23.
                                            </div>
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">
                                                Refresh Minute
                                            </label>

                                            <input
                                                type="number"
                                                min="0"
                                                max="59"
                                                wire:model="exhaustionRefreshMinute"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                            >

                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Minute of the hour when the daily refresh occurs.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t border-gray-200 pt-5 dark:border-white/10">
                                    <div class="mb-3 font-semibold">
                                        Daily Exhaustion Hours
                                    </div>

                                    @php
                                        $exhaustionDays = [
                                            ['exhaustionMondayHours', 'Monday'],
                                            ['exhaustionTuesdayHours', 'Tuesday'],
                                            ['exhaustionWednesdayHours', 'Wednesday'],
                                            ['exhaustionThursdayHours', 'Thursday'],
                                            ['exhaustionFridayHours', 'Friday'],
                                            ['exhaustionSaturdayHours', 'Saturday'],
                                            ['exhaustionSundayHours', 'Sunday'],
                                        ];
                                    @endphp

                                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                        @foreach ($exhaustionDays as [$model, $day])
                                            <div>
                                                <label class="mb-1 block text-sm font-semibold">
                                                    {{ $day }}
                                                </label>

                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="0.5"
                                                    wire:model="{{ $model }}"
                                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                                >

                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    Playtime granted at the exhaustion refresh.
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="border-t border-gray-200 pt-5 dark:border-white/10">
                                    <div class="mb-3 font-semibold">
                                        Saving & Bonuses
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">

                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">
                                                Maximum Saved Hours
                                            </label>

                                            <input
                                                type="number"
                                                min="0"
                                                step="0.5"
                                                wire:model="exhaustionMaxSavedHours"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                            >

                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Maximum unused exhaustion hours a player can bank when playtime saving is enabled.
                                            </div>
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">
                                                Bonus Hours When Exhaustion Is Enabled
                                            </label>

                                            <input
                                                type="number"
                                                min="0"
                                                step="0.5"
                                                wire:model="exhaustionBonusHours"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                            >

                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Additional playtime granted when the exhaustion system is enabled.
                                            </div>
                                        </div>

                                    </div>

                                    <div class="mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-3">

                                        <label class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-white/10">
                                            <span class="flex items-center gap-2 font-medium">
                                                <input
                                                    type="checkbox"
                                                    wire:model="exhaustionAllowPlaytimeSaving"
                                                >
                                                Allow Playtime Saving
                                            </span>

                                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                                Lets players save unused playtime for later days.
                                            </span>
                                        </label>

                                        <label class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-white/10">
                                            <span class="flex items-center gap-2 font-medium">
                                                <input
                                                    type="checkbox"
                                                    wire:model="exhaustionPauseOnRest"
                                                >
                                                Pause Exhaustion While Resting
                                            </span>

                                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                                Allows exhaustion consumption to pause when Eco considers the player resting.
                                            </span>
                                        </label>

                                        <label class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-white/10">
                                            <span class="flex items-center gap-2 font-medium">
                                                <input
                                                    type="checkbox"
                                                    wire:model="exhaustionRetroactiveBonus"
                                                >
                                                Retroactive Bonus Hours
                                            </span>

                                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                                Applies exhaustion bonus hours retroactively after the server has already started.
                                            </span>
                                        </label>

                                    </div>
                                </div>

                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Vehicle-specific exhaustion settings are preserved exactly as Eco created them and remain available through the Raw .eco Editor.
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <x-filament::button wire:click="saveExhaustionSettings">
                                        Save Exhaustion
                                    </x-filament::button>

                                    <x-filament::button
                                        color="gray"
                                        wire:click="refreshExhaustionSettings"
                                    >
                                        Reload From File
                                    </x-filament::button>
                                </div>

                            </div>
                        </div>
                    </div>


                    {{-- WORLD GENERATOR --}}
                    <div
                        x-data="{
                            open: (() => {
                                const saved = localStorage.getItem('eco-enhanced-native-world');
                                return saved === null ? false : saved === 'true';
                            })()
                        }"
                        class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between bg-gray-50 px-4 py-3 text-left dark:bg-white/5"
                            x-on:click="
                                open = !open;
                                localStorage.setItem('eco-enhanced-native-world', open ? 'true' : 'false');
                            "
                        >
                            <span class="font-semibold">World Generation</span>
                            <span x-bind:style="open ? 'width:20px;transform:rotate(180deg)' : 'width:20px;transform:rotate(0deg)'">&#9662;</span>
                        </button>

                        <div x-show="open" x-collapse x-cloak>
                            <div class="space-y-4 border-t border-gray-200 p-4 dark:border-white/10">

                                <div class="max-w-xl">
                                    <label class="mb-1 block text-sm font-semibold">
                                        Map Size
                                    </label>

                                    <select
                                        wire:model="worldMapSize"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                    >
                                        <option value="72">72 × 72</option>
                                        <option value="100">100 × 100</option>
                                        <option value="140">140 × 140</option>
                                        <option value="160">160 × 160</option>
                                        <option value="200">200 × 200</option>
                                    </select>

                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Changes WorldWidth and WorldLength only. Existing worlds are not resized; the new dimensions apply when a new world is generated.
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <x-filament::button wire:click="saveWorldGeneratorSettings">
                                        Save World Size
                                    </x-filament::button>

                                    <x-filament::button
                                        color="gray"
                                        wire:click="refreshWorldGeneratorSettings"
                                    >
                                        Reload From File
                                    </x-filament::button>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>


        {{-- DiscordLink -------------------------------------------------- --}}
        <div
    x-data="{
        open: (() => {
            const saved = localStorage.getItem('eco-enhanced-configs-discordlink');
            return saved === null ? true : saved === 'true';
        })()
    }"
    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
>
    <button
        type="button"
        class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-gray-50 dark:hover:bg-white/5"
        x-on:click="
            open = !open;
            localStorage.setItem('eco-enhanced-configs-discordlink', open ? 'true' : 'false');
        "
    >
        <div class="min-w-0">
            <div class="truncate text-base font-semibold">
                DiscordLink
            </div>
        </div>

        <span
            aria-hidden="true"
            class="inline-flex shrink-0 items-center justify-center text-lg text-gray-500 transition-transform duration-200"
            style="width: 20px; height: 20px; line-height: 20px;"
            x-bind:style="open
                ? 'width:20px;height:20px;line-height:20px;transform:rotate(180deg)'
                : 'width:20px;height:20px;line-height:20px;transform:rotate(0deg)'"
        >
            &#9662;
        </span>
    </button>

    <div
        x-show="open"
        x-collapse
        x-cloak
        class="border-t border-gray-200 dark:border-white/10"
    >
        <div class="p-5">
            <div class="mb-5 text-sm text-gray-600 dark:text-gray-400">
                Configure the DiscordLink Eco plugin without editing DiscordLink.eco manually.
            </div>

@if (!$discordLinkInstalled)

                <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200">
                    <div class="font-semibold">
                        DiscordLink is not installed or DiscordLink.eco is missing.
                    </div>

                    <div class="mt-1">
                        Install DiscordLink from the Eco Mods page first.
                        Eco Enhanced will automatically create its configuration file.
                    </div>

                    <div class="mt-3">
                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:click="refreshDiscordLinkSettings"
                        >
                            Check Again
                        </x-filament::button>
                    </div>
                </div>

            @else

                <div class="space-y-8">

                    {{-- Discord connection --}}
<div data-eco-enhanced-collapse="eco-enhanced-discord-connection">
<div
        x-data="{
            open: (() => {
                const saved = localStorage.getItem('eco-enhanced-discord-connection');
                return saved === null ? true : saved === 'true';
            })()
        }"
        class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left transition hover:bg-gray-100 dark:bg-white/5 dark:hover:bg-white/10"
            x-on:click="
                open = !open;
                localStorage.setItem('eco-enhanced-discord-connection', open ? 'true' : 'false');
            "
        >
            <span class="font-semibold">
                Discord Connection
            </span>

            <span
                aria-hidden="true"
                class="inline-flex shrink-0 items-center justify-center text-lg text-gray-500 transition-transform duration-200"
                x-bind:style="open
                    ? 'width:20px;height:20px;line-height:20px;transform:rotate(180deg)'
                    : 'width:20px;height:20px;line-height:20px;transform:rotate(0deg)'"
            >
                &#9662;
            </span>
        </button>

        <div
            x-show="open"
            x-collapse
            x-cloak
            class="border-t border-gray-200 dark:border-white/10"
        >
            <div class="p-4">
<div>
                        

                        <div class="grid gap-4 lg:grid-cols-2">

                            <div
                                x-data="{ showToken: false }"
                                class="lg:col-span-2"
                            >
                                <label class="mb-1 block text-sm font-semibold">
                                    Bot Token
                                </label>

                                <div class="flex gap-2">
                                    <input
                                        x-bind:type="showToken ? 'text' : 'password'"
                                        wire:model="discordBotToken"
                                        autocomplete="off"
                                        placeholder="Discord bot token"
                                        class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                    >

                                    <x-filament::button
                                        type="button"
                                        color="gray"
                                        x-on:click="showToken = !showToken"
                                    >
                                        <span x-text="showToken ? 'Hide' : 'Show'"></span>
                                    </x-filament::button>
                                </div>

                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Keep this secret. DiscordLink uses it to authenticate the bot.
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-semibold">
                                    Discord Server ID
                                </label>

                                <input
                                    type="text"
                                    inputmode="numeric"
                                    wire:model="discordServerId"
                                    placeholder="Example: 123456789012345678"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                >
                            </div>

                            <label class="flex items-center gap-2 pt-7 text-sm font-medium">
                                <input
                                    type="checkbox"
                                    wire:model="discordServerOwnerIsAdmin"
                                    class="rounded border-gray-300"
                                >
                                Discord server owner is a DiscordLink admin
                            </label>

                            <div class="lg:col-span-2">
                                <label class="mb-1 block text-sm font-semibold">
                                    Admin Roles
                                </label>

                                <textarea
                                    rows="4"
                                    wire:model="discordAdminRoles"
                                    placeholder="Admin&#10;Administrator&#10;Moderator&#10;Eco Admins"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                ></textarea>

                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    One Discord role per line. Comma-separated roles are also accepted.
                                </div>
                            </div>

                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>

{{-- Eco/server presentation --}}
<div data-eco-enhanced-collapse="eco-enhanced-discord-server-info">
<div
        x-data="{
            open: (() => {
                const saved = localStorage.getItem('eco-enhanced-discord-server-info');
                return saved === null ? true : saved === 'true';
            })()
        }"
        class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left transition hover:bg-gray-100 dark:bg-white/5 dark:hover:bg-white/10"
            x-on:click="
                open = !open;
                localStorage.setItem('eco-enhanced-discord-server-info', open ? 'true' : 'false');
            "
        >
            <span class="font-semibold">
                Server Information
            </span>

            <span
                aria-hidden="true"
                class="inline-flex shrink-0 items-center justify-center text-lg text-gray-500 transition-transform duration-200"
                x-bind:style="open
                    ? 'width:20px;height:20px;line-height:20px;transform:rotate(180deg)'
                    : 'width:20px;height:20px;line-height:20px;transform:rotate(0deg)'"
            >
                &#9662;
            </span>
        </button>

        <div
            x-show="open"
            x-collapse
            x-cloak
            class="border-t border-gray-200 dark:border-white/10"
        >
            <div class="p-4">
<div class="">
                        

                        <div class="grid gap-4 lg:grid-cols-2">

                            <div>
                                <label class="mb-1 block text-sm font-semibold">
                                    Server Name
                                </label>

                                <input
                                    type="text"
                                    wire:model="discordServerName"
                                    placeholder="Leave blank to use Eco's configured name"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                >
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-semibold">
                                    Connection Info
                                </label>

                                <input
                                    type="text"
                                    wire:model="discordConnectionInfo"
                                    placeholder="Server ID, IP, or Eco connection link"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                >
                            </div>

                            <div class="lg:col-span-2">
                                <label class="mb-1 block text-sm font-semibold">
                                    Server Description
                                </label>

                                <textarea
                                    rows="3"
                                    wire:model="discordServerDescription"
                                    placeholder="Leave blank to use Eco's configured description"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                ></textarea>
                            </div>

                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>

{{-- Chat --}}
<div data-eco-enhanced-collapse="eco-enhanced-discord-chat-bot">
<div
        x-data="{
            open: (() => {
                const saved = localStorage.getItem('eco-enhanced-discord-chat-bot');
                return saved === null ? true : saved === 'true';
            })()
        }"
        class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left transition hover:bg-gray-100 dark:bg-white/5 dark:hover:bg-white/10"
            x-on:click="
                open = !open;
                localStorage.setItem('eco-enhanced-discord-chat-bot', open ? 'true' : 'false');
            "
        >
            <span class="font-semibold">
                Chat & Bot
            </span>

            <span
                aria-hidden="true"
                class="inline-flex shrink-0 items-center justify-center text-lg text-gray-500 transition-transform duration-200"
                x-bind:style="open
                    ? 'width:20px;height:20px;line-height:20px;transform:rotate(180deg)'
                    : 'width:20px;height:20px;line-height:20px;transform:rotate(0deg)'"
            >
                &#9662;
            </span>
        </button>

        <div
            x-show="open"
            x-collapse
            x-cloak
            class="border-t border-gray-200 dark:border-white/10"
        >
            <div class="p-4">
<div class="">
                        

                        <div class="grid gap-4 lg:grid-cols-2">

                            <div>
                                <label class="mb-1 block text-sm font-semibold">
                                    Chat Sync Mode
                                </label>

                                <select
                                    wire:model="discordChatSyncMode"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                >
                                    <option value="OptOut">
                                        Opt-Out
                                    </option>

                                    <option value="OptIn">
                                        Opt-In
                                    </option>
                                </select>

                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Opt-Out syncs players by default. Opt-In requires players to enable synchronization.
                                </div>
                            </div>

                            <label class="flex items-center gap-2 pt-7 text-sm font-medium">
                                <input
                                    type="checkbox"
                                    wire:model="discordEnableBotStatus"
                                    class="rounded border-gray-300"
                                >
                                Enable Discord bot status
                            </label>

                            <div class="lg:col-span-2">
                                <label class="mb-1 block text-sm font-semibold">
                                    Invite Message
                                </label>

                                <textarea
                                    rows="3"
                                    wire:model="discordInviteMessage"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                ></textarea>

                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Use [LINK] where DiscordLink should insert the Discord invite from Network.eco.
                                </div>
                            </div>

                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>

{{-- Roles --}}
<div data-eco-enhanced-collapse="eco-enhanced-discord-roles">
<div
        x-data="{
            open: (() => {
                const saved = localStorage.getItem('eco-enhanced-discord-roles');
                return saved === null ? false : saved === 'true';
            })()
        }"
        class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left transition hover:bg-gray-100 dark:bg-white/5 dark:hover:bg-white/10"
            x-on:click="
                open = !open;
                localStorage.setItem('eco-enhanced-discord-roles', open ? 'true' : 'false');
            "
        >
            <span class="font-semibold">
                Automatic Discord Roles
            </span>

            <span
                aria-hidden="true"
                class="inline-flex shrink-0 items-center justify-center text-lg text-gray-500 transition-transform duration-200"
                x-bind:style="open
                    ? 'width:20px;height:20px;line-height:20px;transform:rotate(180deg)'
                    : 'width:20px;height:20px;line-height:20px;transform:rotate(0deg)'"
            >
                &#9662;
            </span>
        </button>

        <div
            x-show="open"
            x-collapse
            x-cloak
            class="border-t border-gray-200 dark:border-white/10"
        >
            <div class="p-4">
<div class="">
                        

                        <div class="grid gap-3 md:grid-cols-2">

                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input
                                    type="checkbox"
                                    wire:model="discordUseLinkedAccountRole"
                                    class="rounded border-gray-300"
                                >
                                Linked Account Role
                            </label>

                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input
                                    type="checkbox"
                                    wire:model="discordUseDemographicRoles"
                                    class="rounded border-gray-300"
                                >
                                Demographic Roles
                            </label>

                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input
                                    type="checkbox"
                                    wire:model="discordUseSpecialtyRoles"
                                    class="rounded border-gray-300"
                                >
                                Specialty Roles
                            </label>

                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input
                                    type="checkbox"
                                    wire:model="discordUseElectedTitleRoles"
                                    class="rounded border-gray-300"
                                >
                                Elected Title Roles
                            </label>

                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>

{{-- Chat Channel Links --}}
<div data-eco-enhanced-collapse="eco-enhanced-discord-chat-links">
<div
        x-data="{
            open: (() => {
                const saved = localStorage.getItem('eco-enhanced-discord-chat-links');
                return saved === null ? true : saved === 'true';
            })()
        }"
        class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left transition hover:bg-gray-100 dark:bg-white/5 dark:hover:bg-white/10"
            x-on:click="
                open = !open;
                localStorage.setItem('eco-enhanced-discord-chat-links', open ? 'true' : 'false');
            "
        >
            <span class="font-semibold">
                Chat Channel Links
            </span>

            <span
                aria-hidden="true"
                class="inline-flex shrink-0 items-center justify-center text-lg text-gray-500 transition-transform duration-200"
                x-bind:style="open
                    ? 'width:20px;height:20px;line-height:20px;transform:rotate(180deg)'
                    : 'width:20px;height:20px;line-height:20px;transform:rotate(0deg)'"
            >
                &#9662;
            </span>
        </button>

        <div
            x-show="open"
            x-collapse
            x-cloak
            class="border-t border-gray-200 dark:border-white/10"
        >
            <div class="p-4">
<div class="">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                

                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Link Eco chat channels with Discord channels.
                                </div>
                            </div>

                            <x-filament::button
                                type="button"
                                wire:click="addDiscordChatChannelLink"
                            >
                                Add Chat Link
                            </x-filament::button>
                        </div>

                        <div class="space-y-4">
                            @forelse ($discordChatChannelLinks as $index => $link)
                                <div
                                    wire:key="discord-chat-link-{{ $index }}"
                                    class="rounded-xl border border-gray-200 p-4 dark:border-white/10"
                                >
                                    <div class="grid gap-4 lg:grid-cols-3">

                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">
                                                Eco Channel
                                            </label>

                                            <input
                                                type="text"
                                                wire:model="discordChatChannelLinks.{{ $index }}.EcoChannel"
                                                placeholder="General"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                            >
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">
                                                Discord Channel ID
                                            </label>

                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                wire:model="discordChatChannelLinks.{{ $index }}.DiscordChannelId"
                                                placeholder="123456789012345678"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-white/10 dark:bg-gray-900"
                                            >
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">
                                                Direction
                                            </label>

                                            <input
                                                type="text"
                                                wire:model="discordChatChannelLinks.{{ $index }}.Direction"
                                                placeholder="Duplex"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                            >
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-sm font-semibold">
                                                @here / @everyone Permission
                                            </label>

                                            <input
                                                type="text"
                                                wire:model="discordChatChannelLinks.{{ $index }}.HereAndEveryoneMentionPermission"
                                                placeholder="Forbidden"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                            >
                                        </div>

                                        <div class="flex flex-col gap-2 pt-6">
                                            <label class="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    wire:model="discordChatChannelLinks.{{ $index }}.AllowUserMentions"
                                                    class="rounded border-gray-300"
                                                >
                                                Allow User Mentions
                                            </label>

                                            <label class="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    wire:model="discordChatChannelLinks.{{ $index }}.AllowRoleMentions"
                                                    class="rounded border-gray-300"
                                                >
                                                Allow Role Mentions
                                            </label>
                                        </div>

                                        <div class="flex flex-col gap-2 pt-6">
                                            <label class="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    wire:model="discordChatChannelLinks.{{ $index }}.AllowChannelMentions"
                                                    class="rounded border-gray-300"
                                                >
                                                Allow Channel Mentions
                                            </label>

                                            <label class="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    wire:model="discordChatChannelLinks.{{ $index }}.UseTimestamp"
                                                    class="rounded border-gray-300"
                                                >
                                                Use Timestamp
                                            </label>
                                        </div>

                                    </div>

                                    <div class="mt-4">
                                        <x-filament::button
                                            type="button"
                                            color="danger"
                                            size="sm"
                                            wire:click="removeDiscordChatChannelLink({{ $index }})"
                                            wire:confirm="Remove this DiscordLink chat channel link?"
                                        >
                                            Remove Chat Link
                                        </x-filament::button>
                                    </div>
                                </div>
                            @empty
                                <div class="rounded-xl border border-dashed border-gray-300 p-5 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                                    No chat channel links configured.
                                </div>
                            @endforelse
                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>

{{-- Feed Channels --}}
<div data-eco-enhanced-collapse="eco-enhanced-discord-feeds">
<div
        x-data="{
            open: (() => {
                const saved = localStorage.getItem('eco-enhanced-discord-feeds');
                return saved === null ? false : saved === 'true';
            })()
        }"
        class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left transition hover:bg-gray-100 dark:bg-white/5 dark:hover:bg-white/10"
            x-on:click="
                open = !open;
                localStorage.setItem('eco-enhanced-discord-feeds', open ? 'true' : 'false');
            "
        >
            <span class="font-semibold">
                Feed Channels
            </span>

            <span
                aria-hidden="true"
                class="inline-flex shrink-0 items-center justify-center text-lg text-gray-500 transition-transform duration-200"
                x-bind:style="open
                    ? 'width:20px;height:20px;line-height:20px;transform:rotate(180deg)'
                    : 'width:20px;height:20px;line-height:20px;transform:rotate(0deg)'"
            >
                &#9662;
            </span>
        </button>

        <div
            x-show="open"
            x-collapse
            x-cloak
            class="border-t border-gray-200 dark:border-white/10"
        >
            <div class="p-4">
<div class="">
                        

                        <div class="mb-5 grid gap-4 md:grid-cols-2">

                            <div>
                                <label class="mb-1 block text-sm font-semibold">
                                    Max Trade Watcher Displays Per User
                                </label>

                                <input
                                    type="number"
                                    min="0"
                                    wire:model="discordMaxTradeWatcherDisplaysPerUser"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                >
                            </div>

                            <label class="flex items-center gap-2 pt-7 text-sm font-medium">
                                <input
                                    type="checkbox"
                                    wire:model="discordUseTradeWatcherFeeds"
                                    class="rounded border-gray-300"
                                >
                                Enable Trade Watcher Feeds
                            </label>

                        </div>

                        @php
                            $feedGroups = [
                                'trade' => [
                                    'label' => 'Trade Feed',
                                    'property' => 'discordTradeFeedChannels',
                                ],
                                'crafting' => [
                                    'label' => 'Crafting Feed',
                                    'property' => 'discordCraftingFeedChannels',
                                ],
                                'server-status' => [
                                    'label' => 'Server Status Feed',
                                    'property' => 'discordServerStatusFeedChannels',
                                ],
                                'player-status' => [
                                    'label' => 'Player Status Feed',
                                    'property' => 'discordPlayerStatusFeedChannels',
                                ],
                                'election' => [
                                    'label' => 'Election Feed',
                                    'property' => 'discordElectionFeedChannels',
                                ],
                                'server-log' => [
                                    'label' => 'Server Log Feed',
                                    'property' => 'discordServerLogFeedChannels',
                                ],
                            ];
                        @endphp

                        <div class="grid gap-4 lg:grid-cols-2">
                            @foreach ($feedGroups as $feedType => $feed)
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                                    <div class="mb-3 flex items-center justify-between gap-3">
                                        <div class="font-semibold">
                                            {{ $feed['label'] }}
                                        </div>

                                        <x-filament::button
                                            type="button"
                                            size="sm"
                                            wire:click="addDiscordFeedChannel('{{ $feedType }}')"
                                        >
                                            Add Channel
                                        </x-filament::button>
                                    </div>

                                    <div class="space-y-2">
                                        @forelse ($this->{$feed['property']} as $index => $channel)
                                            <div
                                                wire:key="discord-feed-{{ $feedType }}-{{ $index }}"
                                                class="flex gap-2"
                                            >
                                                <input
                                                    type="text"
                                                    inputmode="numeric"
                                                    wire:model="{{ $feed['property'] }}.{{ $index }}.DiscordChannelId"
                                                    placeholder="Discord Channel ID"
                                                    class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-white/10 dark:bg-gray-900"
                                                >

                                                <x-filament::button
                                                    type="button"
                                                    color="danger"
                                                    size="sm"
                                                    wire:click="removeDiscordFeedChannel('{{ $feedType }}', {{ $index }})"
                                                    wire:confirm="Remove this Discord feed channel?"
                                                >
                                                    Remove
                                                </x-filament::button>
                                            </div>
                                        @empty
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                No channels configured.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>

{{-- Server Info Displays --}}
<div data-eco-enhanced-collapse="eco-enhanced-discord-server-displays">
<div
        x-data="{
            open: (() => {
                const saved = localStorage.getItem('eco-enhanced-discord-server-displays');
                return saved === null ? true : saved === 'true';
            })()
        }"
        class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left transition hover:bg-gray-100 dark:bg-white/5 dark:hover:bg-white/10"
            x-on:click="
                open = !open;
                localStorage.setItem('eco-enhanced-discord-server-displays', open ? 'true' : 'false');
            "
        >
            <span class="font-semibold">
                Server Info Displays
            </span>

            <span
                aria-hidden="true"
                class="inline-flex shrink-0 items-center justify-center text-lg text-gray-500 transition-transform duration-200"
                x-bind:style="open
                    ? 'width:20px;height:20px;line-height:20px;transform:rotate(180deg)'
                    : 'width:20px;height:20px;line-height:20px;transform:rotate(0deg)'"
            >
                &#9662;
            </span>
        </button>

        <div
            x-show="open"
            x-collapse
            x-cloak
            class="border-t border-gray-200 dark:border-white/10"
        >
            <div class="p-4">
<div class="">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                

                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    Publish a live Eco server-information display into a Discord channel.
                                </div>
                            </div>

                            <x-filament::button
                                type="button"
                                wire:click="addDiscordServerInfoDisplay"
                            >
                                Add Server Info Display
                            </x-filament::button>
                        </div>

                        <div class="space-y-4">
                            @forelse ($discordServerInfoDisplayChannels as $index => $display)

                                <div
                                    wire:key="discord-server-info-display-{{ $index }}"
                                    class="rounded-xl border border-gray-200 p-4 dark:border-white/10"
                                >
                                    <div class="mb-4">
                                        <label class="mb-1 block text-sm font-semibold">
                                            Discord Channel ID
                                        </label>

                                        <input
                                            type="text"
                                            inputmode="numeric"
                                            wire:model="discordServerInfoDisplayChannels.{{ $index }}.DiscordChannelId"
                                            placeholder="123456789012345678"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-white/10 dark:bg-gray-900"
                                        >
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">

                                        @php
                                            $serverInfoFields = [
                                                'UseName' => 'Server Name',
                                                'UseDescription' => 'Server Description',
                                                'UseLogo' => 'Server Logo',
                                                'UseConnectionInfo' => 'Connection Info',
                                                'UseWebServerAddress' => 'Webpage Address',
                                                'UsePlayerCount' => 'Player Count',
                                                'UsePlayerList' => 'Online Players',
                                                'UsePlayerListLoggedInTime' => 'Session Time',
                                                'UsePlayerListExhaustionTime' => 'Player Exhaustion Time',
                                                'UseIngameTime' => 'In-Game Time',
                                                'UseTimeRemaining' => 'Meteor / Time Remaining',
                                                'UseServerTime' => 'Server Time',
                                                'UseExhaustionResetServerTime' => 'Exhaustion Reset Server Time',
                                                'UseExhaustionResetTimeLeft' => 'Exhaustion Reset Time Left',
                                                'UseExhaustedPlayerCount' => 'Exhausted Player Count',
                                                'UseSettlementCount' => 'Active Settlement Count',
                                                'UseSettlementList' => 'Active Settlements',
                                                'UseElectionCount' => 'Active Election Count',
                                                'UseElectionList' => 'Active Elections',
                                                'UseLawCount' => 'Law Count',
                                                'UseLawList' => 'Active Laws',
                                            ];
                                        @endphp

                                        @foreach ($serverInfoFields as $field => $label)
                                            <label class="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    wire:model="discordServerInfoDisplayChannels.{{ $index }}.{{ $field }}"
                                                    class="rounded border-gray-300"
                                                >

                                                {{ $label }}
                                            </label>
                                        @endforeach

                                    </div>

                                    <div class="mt-5">
                                        <x-filament::button
                                            type="button"
                                            color="danger"
                                            size="sm"
                                            wire:click="removeDiscordServerInfoDisplay({{ $index }})"
                                            wire:confirm="Remove this Discord server info display?"
                                        >
                                            Remove Server Info Display
                                        </x-filament::button>
                                    </div>
                                </div>

                            @empty
                                <div class="rounded-xl border border-dashed border-gray-300 p-5 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                                    No Server Info Display channels configured.
                                </div>
                            @endforelse
                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>

{{-- Advanced --}}
<div data-eco-enhanced-collapse="eco-enhanced-discord-logging">
<div
        x-data="{
            open: (() => {
                const saved = localStorage.getItem('eco-enhanced-discord-logging');
                return saved === null ? false : saved === 'true';
            })()
        }"
        class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
    >
        <button
            type="button"
            class="flex w-full items-center justify-between gap-4 bg-gray-50 px-4 py-3 text-left transition hover:bg-gray-100 dark:bg-white/5 dark:hover:bg-white/10"
            x-on:click="
                open = !open;
                localStorage.setItem('eco-enhanced-discord-logging', open ? 'true' : 'false');
            "
        >
            <span class="font-semibold">
                Logging & Appearance
            </span>

            <span
                aria-hidden="true"
                class="inline-flex shrink-0 items-center justify-center text-lg text-gray-500 transition-transform duration-200"
                x-bind:style="open
                    ? 'width:20px;height:20px;line-height:20px;transform:rotate(180deg)'
                    : 'width:20px;height:20px;line-height:20px;transform:rotate(0deg)'"
            >
                &#9662;
            </span>
        </button>

        <div
            x-show="open"
            x-collapse
            x-cloak
            class="border-t border-gray-200 dark:border-white/10"
        >
            <div class="p-4">
<div class="">
                        

                        <div class="grid gap-4 lg:grid-cols-3">

                            <div>
                                <label class="mb-1 block text-sm font-semibold">
                                    Log Level
                                </label>

                                <select
                                    wire:model="discordLogLevel"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                >
                                    <option value="Trace">Trace</option>
                                    <option value="Debug">Debug</option>
                                    <option value="Warning">Warning</option>
                                    <option value="Information">Information</option>
                                    <option value="Error">Error</option>
                                    <option value="Silent">Silent</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-semibold">
                                    Backend Log Level
                                </label>

                                <select
                                    wire:model="discordBackendLogLevel"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                                >
                                    <option value="Trace">Trace</option>
                                    <option value="Debug">Debug</option>
                                    <option value="Information">Information</option>
                                    <option value="Warning">Warning</option>
                                    <option value="Error">Error</option>
                                    <option value="Critical">Critical</option>
                                    <option value="None">None</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-semibold">
                                    Embed Color
                                </label>

                                <input
                                    type="text"
                                    wire:model="discordEmbedColorHex"
                                    placeholder="#7289da"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-white/10 dark:bg-gray-900"
                                >
                            </div>

                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input
                                    type="checkbox"
                                    wire:model="discordEnableTraceFileLogging"
                                    class="rounded border-gray-300"
                                >
                                Trace File Logging
                            </label>

                            <label class="flex items-center gap-2 text-sm font-medium">
                                <input
                                    type="checkbox"
                                    wire:model="discordUseVerboseDisplay"
                                    class="rounded border-gray-300"
                                >
                                Verbose Display
                            </label>

                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>

                    <div class="flex flex-wrap gap-2 border-t border-gray-200 pt-6 dark:border-white/10">
                        <x-filament::button
                            type="button"
                            wire:click="saveDiscordLinkSettings"
                            wire:loading.attr="disabled"
                        >
                            Save DiscordLink Settings
                        </x-filament::button>

                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:click="refreshDiscordLinkSettings"
                        >
                            Reload From File
                        </x-filament::button>
                    </div>

                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Channel links, feeds, displays, demographic replacements, and other advanced DiscordLink collections are preserved but are not edited by this page yet.
                    </div>

                </div>

            @endif
        </div>
    </div>
</div>



        {{-- Config Snapshots --------------------------------------------- --}}
        <div
            x-data="{
                open: (() => {
                    const saved = localStorage.getItem('eco-enhanced-config-snapshots');
                    return saved === null ? false : saved === 'true';
                })()
            }"
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
        >
            <button
                type="button"
                class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-gray-50 dark:hover:bg-white/5"
                x-on:click="
                    open = !open;
                    localStorage.setItem(
                        'eco-enhanced-config-snapshots',
                        open ? 'true' : 'false'
                    );

                    if (open && $wire.configSnapshots.length === 0) {
                        $wire.refreshConfigSnapshots();
                    }
                "
            >
                <div>
                    <div class="text-base font-semibold">
                        Config Snapshots
                    </div>

                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Fast backups of every .eco file without creating a full server backup.
                    </div>
                </div>

                <span
                    aria-hidden="true"
                    style="width:20px;height:20px;line-height:20px;"
                    x-bind:style="open
                        ? 'width:20px;height:20px;transform:rotate(180deg)'
                        : 'width:20px;height:20px;transform:rotate(0deg)'"
                >
                    &#9662;
                </span>
            </button>

            <div
                x-show="open"
                x-collapse
                x-cloak
                class="border-t border-gray-200 dark:border-white/10"
            >
                <div class="space-y-5 p-5">

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm dark:border-white/10 dark:bg-white/5">
                        Snapshots copy all current <strong>Configs/*.eco</strong>
                        files into Eco Enhanced's protected
                        <code>/.eco-enhanced/config-backups</code> area.
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-filament::button
                            wire:click="createConfigSnapshot"
                            wire:loading.attr="disabled"
                        >
                            Create Config Snapshot
                        </x-filament::button>

                        <x-filament::button
                            color="gray"
                            wire:click="refreshConfigSnapshots"
                        >
                            Refresh Snapshots
                        </x-filament::button>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold">
                            Restore Snapshot
                        </label>

                        <div class="flex flex-wrap gap-2">
                            <select
                                wire:model="selectedConfigSnapshot"
                                class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-white/10 dark:bg-gray-900"
                            >
                                <option value="">
                                    Select a config snapshot...
                                </option>

                                @foreach ($configSnapshots as $snapshot)
                                    <option value="{{ $snapshot }}">
                                        {{ $snapshot }}
                                    </option>
                                @endforeach
                            </select>

                            <x-filament::button
                                color="danger"
                                wire:click="restoreConfigSnapshot"
                                wire:confirm="Restore every .eco file from this snapshot? Current config changes will be overwritten."
                                :disabled="$selectedConfigSnapshot === ''"
                            >
                                Restore Snapshot
                            </x-filament::button>
                        </div>

                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Restore affects configuration files only. Worlds, mods, logs, and server data are untouched.
                        </div>
                    </div>

                </div>
            </div>
        </div>


        {{-- Raw .eco Editor ---------------------------------------------- --}}
        <div
            x-data="{
                open: (() => {
                    const saved = localStorage.getItem('eco-enhanced-configs-raw-editor');
                    return saved === null ? false : saved === 'true';
                })()
            }"
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
        >
            <button
                type="button"
                class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-gray-50 dark:hover:bg-white/5"
                x-on:click="
                    open = !open;
                    localStorage.setItem(
                        'eco-enhanced-configs-raw-editor',
                        open ? 'true' : 'false'
                    );

                    if (open && $wire.rawEcoFiles.length === 0) {
                        $wire.refreshRawEcoFiles();
                    }
                "
            >
                <div class="min-w-0">
                    <div class="truncate text-base font-semibold">
                        Raw .eco File Editor
                    </div>

                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Directly edit Eco configuration files stored in /Configs.
                    </div>
                </div>

                <span
                    aria-hidden="true"
                    class="inline-flex shrink-0 items-center justify-center text-lg text-gray-500 transition-transform duration-200"
                    style="width:20px;height:20px;line-height:20px;"
                    x-bind:style="open
                        ? 'width:20px;height:20px;line-height:20px;transform:rotate(180deg)'
                        : 'width:20px;height:20px;line-height:20px;transform:rotate(0deg)'"
                >
                    &#9662;
                </span>
            </button>

            <div
                x-show="open"
                x-collapse
                x-cloak
                class="border-t border-gray-200 dark:border-white/10"
            >
                <div class="space-y-5 p-5">

                    <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200">
                        <strong>Advanced editor:</strong>
                        Changes made here directly modify the server's .eco files.
                        Invalid values may prevent Eco or a mod from starting correctly.
                        JSON syntax is validated before Eco Enhanced allows a file to be saved.
                    </div>

                    <div
                        x-data="{ ecoEditorModalOpen: false }"
                        x-on:eco-enhanced-open-raw-editor.window="
                            ecoEditorModalOpen = true
                        "
                        class="space-y-4"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                {{ count($rawEcoFiles) }} .eco file(s) found in
                                <span class="font-mono">/Configs</span>
                            </div>

                            <x-filament::button
                                type="button"
                                color="gray"
                                wire:click="refreshRawEcoFiles"
                            >
                                Refresh File List
                            </x-filament::button>
                        </div>

                        @if (count($rawEcoFiles) > 0)
                            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                                @foreach ($rawEcoFiles as $file)
                                    <div
                                        wire:key="raw-eco-{{ md5($file) }}"
                                        class="flex items-center justify-between gap-4 border-b border-gray-200 px-4 py-3 last:border-b-0 dark:border-white/10"
                                    >
                                        <div class="min-w-0">
                                            <div class="truncate font-mono text-sm font-semibold">
                                                {{ $file }}
                                            </div>

                                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                Configs/{{ $file }}
                                            </div>
                                        </div>

                                        <x-filament::button
                                            type="button"
                                            size="sm"
                                            wire:click="openRawEcoEditor('{{ $file }}')"
                                        >
                                            Edit
                                        </x-filament::button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                                No .eco configuration files were found in /Configs.
                            </div>
                        @endif

                        <template x-teleport="body">
                            <div
                                x-show="ecoEditorModalOpen"
                                x-cloak
                                x-on:keydown.escape.window="ecoEditorModalOpen = false"
                                class="fixed inset-0 z-[99999] flex items-center justify-center p-6"
                                style="background: rgba(0, 0, 0, 0.65);"
                            >
                                <div
                                    x-on:click.stop
                                    class="flex flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/10 dark:bg-gray-900 dark:ring-white/10"
                                    style="
                                        width: min(1200px, calc(100vw - 48px));
                                        height: min(850px, calc(100vh - 48px));
                                        max-width: 1200px;
                                        max-height: calc(100vh - 48px);
                                    "
                                >
                                <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-white/10">
                                    <div>
                                        <div class="text-base font-semibold">
                                            Edit .eco Configuration
                                        </div>

                                        <div class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">
                                            Configs/{{ $rawEcoSelectedFile }}
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        x-on:click="ecoEditorModalOpen = false"
                                        class="rounded-lg px-3 py-2 text-sm font-semibold hover:bg-gray-100 dark:hover:bg-white/5"
                                    >
                                        Close
                                    </button>
                                </div>

                                <div
                                    class="flex min-h-0 flex-1 flex-col overflow-hidden p-5"
                                >
                                    <div class="mb-3 flex items-center justify-between gap-3">
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            Direct JSON editor
                                        </div>

                                        @if ($rawEcoJsonValid)
                                            <span class="inline-flex items-center rounded-lg bg-success-50 px-2.5 py-1 text-xs font-semibold text-success-700 dark:bg-success-500/10 dark:text-success-300">
                                                ✓ {{ $rawEcoJsonStatus ?: 'Valid JSON' }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-lg bg-danger-50 px-2.5 py-1 text-xs font-semibold text-danger-700 dark:bg-danger-500/10 dark:text-danger-300">
                                                ✕ {{ $rawEcoJsonStatus }}
                                            </span>
                                        @endif
                                    </div>

                                    <textarea
                                        wire:model.defer="rawEcoContent"
                                        spellcheck="false"
                                        autocomplete="off"
                                        class="w-full flex-1 resize-none rounded-xl border border-gray-300 bg-gray-950 p-4 font-mono text-sm leading-6 text-gray-100 dark:border-white/10"
                                        style="
                                            tab-size: 4;
                                            min-height: 0;
                                            height: 100%;
                                            flex: 1 1 0%;
                                        "
                                    ></textarea>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-5 py-4 dark:border-white/10">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Saving automatically formats the file as readable JSON.
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <x-filament::button
                                            type="button"
                                            color="gray"
                                            wire:click="loadRawEcoFile"
                                        >
                                            Reload From File
                                        </x-filament::button>

                                        <x-filament::button
                                            type="button"
                                            color="gray"
                                            wire:click="revertRawEcoFile"
                                        >
                                            Revert Unsaved Changes
                                        </x-filament::button>

                                        <x-filament::button
                                            type="button"
                                            wire:click="saveRawEcoFile"
                                            wire:loading.attr="disabled"
                                            :disabled="!$rawEcoJsonValid"
                                        >
                                            Save .eco File
                                        </x-filament::button>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </template>
                    </div>

                </div>
            </div>
        </div>

        {{-- Users.eco ---------------------------------------------------- --}}
        <div
    x-data="{
        open: (() => {
            const saved = localStorage.getItem('eco-enhanced-configs-users');
            return saved === null ? false : saved === 'true';
        })()
    }"
    class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
>
    <button
        type="button"
        class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-gray-50 dark:hover:bg-white/5"
        x-on:click="
            open = !open;
            localStorage.setItem('eco-enhanced-configs-users', open ? 'true' : 'false');
        "
    >
        <div class="min-w-0">
            <div class="truncate text-base font-semibold">
                Users.eco
            </div>
        </div>

        <span
            aria-hidden="true"
            class="inline-flex shrink-0 items-center justify-center text-lg text-gray-500 transition-transform duration-200"
            style="width: 20px; height: 20px; line-height: 20px;"
            x-bind:style="open
                ? 'width:20px;height:20px;line-height:20px;transform:rotate(180deg)'
                : 'width:20px;height:20px;line-height:20px;transform:rotate(0deg)'"
        >
            &#9662;
        </span>
    </button>

    <div
        x-show="open"
        x-collapse
        x-cloak
        class="border-t border-gray-200 dark:border-white/10"
    >
        <div class="p-5">
            <div class="mb-5 text-sm text-gray-600 dark:text-gray-400">
                User, moderation, and administrative behavior for this Eco server.
            </div>

<div class="space-y-6">

                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">

                    <div>
                        <label class="mb-1 block text-sm font-semibold">
                            Admin Command Logging
                        </label>

                        <select
                            wire:model="adminCommandsLoggingLevel"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                        >
                            <option value="LogFile">
                                Log File Only (Recommended)
                            </option>

                            <option value="LogFileAndNotifyAdmins">
                                Log File + Notify Admins
                            </option>

                            <option value="LogFileAndNotifyEveryone">
                                Log File + Notify Everyone
                            </option>

                            <option value="None">
                                No Logging / No Notifications
                            </option>
                        </select>

                        <div class="mt-2 max-w-3xl text-xs text-gray-500 dark:text-gray-400">
                            <strong>Log File Only</strong> is Eco Enhanced's default.
                            It records administrative commands without showing
                            Eco Enhanced's background RCON queries to players in-game.
                        </div>
                    </div>

                    <x-filament::button
                        wire:click="saveUsersSettings"
                    >
                        Save Users.eco Settings
                    </x-filament::button>

                </div>

                @if (
                    $adminCommandsLoggingLevel === 'LogFileAndNotifyAdmins' ||
                    $adminCommandsLoggingLevel === 'LogFileAndNotifyEveryone'
                )
                    <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200">
                        This notification mode can display background Eco Enhanced RCON
                        commands such as player, admin, whitelist, ban, and mute
                        list refreshes inside Eco.
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>

    </div>


@once
    <link
        rel="stylesheet"
        href="/vendor/eco-enhanced-codemirror/codemirror.min.css"
    >

    <link
        rel="stylesheet"
        href="/vendor/eco-enhanced-codemirror/dialog.min.css"
    >

    <style>
        .eco-enhanced-eco-code-editor {
            overflow: hidden;
            border: 1px solid rgb(209 213 219);
            border-radius: 0.75rem;
            background: #0d1117;
        }

        .eco-enhanced-eco-code-editor .CodeMirror {
            height: 620px;
            font-family:
                ui-monospace,
                SFMono-Regular,
                Menlo,
                Monaco,
                Consolas,
                "Liberation Mono",
                "Courier New",
                monospace;
            font-size: 14px;
            line-height: 1.55;
            background: #0d1117;
            color: #e6edf3;
        }

        .eco-enhanced-eco-code-editor .CodeMirror-gutters {
            background: #161b22;
            border-right: 1px solid #30363d;
        }

        .eco-enhanced-eco-code-editor .CodeMirror-linenumber {
            color: #7d8590;
        }

        .eco-enhanced-eco-code-editor .CodeMirror-activeline-background {
            background: rgba(255, 255, 255, 0.045);
        }

        .eco-enhanced-eco-code-editor .CodeMirror-cursor {
            border-left-color: #ffffff;
        }

        .eco-enhanced-eco-code-editor .CodeMirror-matchingbracket {
            color: #ffffff !important;
            background: rgba(88, 166, 255, 0.25);
            outline: 1px solid rgba(88, 166, 255, 0.6);
        }

        .eco-enhanced-eco-code-editor .cm-property {
            color: #79c0ff;
        }

        .eco-enhanced-eco-code-editor .cm-string {
            color: #a5d6ff;
        }

        .eco-enhanced-eco-code-editor .cm-number {
            color: #ffa657;
        }

        .eco-enhanced-eco-code-editor .cm-atom {
            color: #ff7b72;
        }

        .eco-enhanced-eco-code-editor .cm-keyword {
            color: #ff7b72;
        }

        .CodeMirror-dialog {
            background: #161b22 !important;
            color: #e6edf3 !important;
            border-bottom: 1px solid #30363d !important;
            padding: 8px 10px !important;
        }

        .CodeMirror-dialog input {
            background: #0d1117 !important;
            color: #e6edf3 !important;
            border: 1px solid #30363d !important;
            border-radius: 6px;
            padding: 4px 8px !important;
            outline: none;
        }

        @media (max-width: 768px) {
            .eco-enhanced-eco-code-editor .CodeMirror {
                height: 480px;
                font-size: 13px;
            }
        }
    </style>

    <script
        src="/vendor/eco-enhanced-codemirror/codemirror.min.js"
    ></script>

    <script
        src="/vendor/eco-enhanced-codemirror/javascript.min.js"
    ></script>

    <script
        src="/vendor/eco-enhanced-codemirror/searchcursor.min.js"
    ></script>

    <script
        src="/vendor/eco-enhanced-codemirror/search.min.js"
    ></script>

    <script
        src="/vendor/eco-enhanced-codemirror/dialog.min.js"
    ></script>

    <script
        src="/vendor/eco-enhanced-codemirror/matchbrackets.min.js"
    ></script>

    <script
        src="/vendor/eco-enhanced-codemirror/closebrackets.min.js"
    ></script>

    <script
        src="/vendor/eco-enhanced-codemirror/active-line.min.js"
    ></script>
@endonce


<style>
    /* Eco Enhanced Eco Configs compact layout */
    .eco-enhanced-eco-compact label {
        margin-bottom: 0.2rem !important;
    }

    .eco-enhanced-eco-compact input[type="text"],
    .eco-enhanced-eco-compact input[type="number"],
    .eco-enhanced-eco-compact input[type="password"],
    .eco-enhanced-eco-compact select {
        padding-top: 0.42rem !important;
        padding-bottom: 0.42rem !important;
    }

    .eco-enhanced-eco-compact textarea:not(.CodeMirror textarea) {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }

    .eco-enhanced-eco-compact .space-y-6 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 1rem !important;
    }

    .eco-enhanced-eco-compact .space-y-5 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 0.85rem !important;
    }

    .eco-enhanced-eco-compact .space-y-4 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 0.7rem !important;
    }

    .eco-enhanced-eco-compact .gap-4 {
        gap: 0.75rem !important;
    }

    .eco-enhanced-eco-compact .gap-3 {
        gap: 0.6rem !important;
    }

    .eco-enhanced-eco-compact .p-5 {
        padding: 1rem !important;
    }

    .eco-enhanced-eco-compact .p-4 {
        padding: 0.8rem !important;
    }

    .eco-enhanced-eco-compact .pt-5 {
        padding-top: 0.9rem !important;
    }

    .eco-enhanced-eco-compact .pt-6 {
        padding-top: 1rem !important;
    }

    .eco-enhanced-eco-compact .mb-5 {
        margin-bottom: 0.9rem !important;
    }

    .eco-enhanced-eco-compact .mb-4 {
        margin-bottom: 0.75rem !important;
    }

    .eco-enhanced-eco-compact .mb-3 {
        margin-bottom: 0.55rem !important;
    }

    .eco-enhanced-eco-compact .mt-4 {
        margin-top: 0.75rem !important;
    }

    .eco-enhanced-eco-compact .mt-2 {
        margin-top: 0.35rem !important;
    }

    .eco-enhanced-eco-compact .mt-1 {
        margin-top: 0.18rem !important;
    }

    /* Helper text */
    .eco-enhanced-eco-compact .text-xs {
        line-height: 1.25rem !important;
    }

    /* Checkbox cards */
    .eco-enhanced-eco-compact label.rounded-lg.border {
        padding: 0.55rem 0.7rem !important;
    }

    /* Section headers */
    .eco-enhanced-eco-compact button[class*="px-4"][class*="py-3"] {
        padding-top: 0.6rem !important;
        padding-bottom: 0.6rem !important;
    }

    .eco-enhanced-eco-compact button[class*="px-5"][class*="py-4"] {
        padding-top: 0.75rem !important;
        padding-bottom: 0.75rem !important;
    }

    /* Keep the code editor roomy even though everything else is compact. */
    .eco-enhanced-eco-compact .eco-enhanced-eco-code-editor .CodeMirror {
        height: 560px;
    }

    @media (max-width: 768px) {
        .eco-enhanced-eco-compact .eco-enhanced-eco-code-editor .CodeMirror {
            height: 440px;
        }
    }
</style>

</x-filament-panels::page>
