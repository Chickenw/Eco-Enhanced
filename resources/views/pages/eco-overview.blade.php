<x-filament-panels::page>

    <style>
        .eco-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .eco-dashboard-card {
            min-width: 0;
            border: 1px solid rgb(229 231 235);
            border-radius: 0.75rem;
            background: white;
            padding: 0.9rem 1rem;
            box-shadow:
                0 1px 2px 0 rgb(0 0 0 / 0.04),
                0 1px 3px 0 rgb(0 0 0 / 0.03);
        }

        .dark .eco-dashboard-card {
            border-color: rgb(255 255 255 / 0.1);
            background: rgb(17 24 39);
        }

        .eco-dashboard-icon {
            display: flex;
            width: 2rem;
            height: 2rem;
            flex: 0 0 2rem;
            align-items: center;
            justify-content: center;
            border-radius: 0.6rem;
        }

        .eco-dashboard-icon svg {
            width: 1.1rem;
            height: 1.1rem;
        }

        @media (max-width: 1100px) {
            .eco-dashboard-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 650px) {
            .eco-dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    {{-- Eco Dashboard --}}

    @php
        $dashboardRcon = $this->getDashboardRconStatus();
        $gameAddress = $this->getGameAddress();
    @endphp

    <div class="eco-dashboard-grid">

        {{-- Server Status --}}
        <div class="eco-dashboard-card">
            <div class="flex items-start gap-3">

                <div class="eco-dashboard-icon bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400">
                    <svg class="" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 3l7 4v5c0 5-3.5 8-7 9-3.5-1-7-4-7-9V7l7-4z"/>
                    </svg>
                </div>

                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Server Status
                    </div>

                    <div class="mt-1.5 text-lg font-bold">
                        @if ($dashboardRcon['online'])
                            <span class="text-success-600 dark:text-success-400">
                                Online
                            </span>
                        @else
                            <span class="text-danger-600 dark:text-danger-400">
                                Offline
                            </span>
                        @endif
                    </div>

                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Eco RCON connectivity
                    </div>
                </div>

            </div>
        </div>


        {{-- Players --}}
        <div class="eco-dashboard-card">
            <div class="flex items-start gap-3">

                <div class="eco-dashboard-icon bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                    <svg class="" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M22 21v-2a4 4 0 00-3-3.87"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M16 3.13a4 4 0 010 7.75"/>
                    </svg>
                </div>

                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Players Online
                    </div>

                    <div class="mt-1.5 text-xl font-bold">
                        {{ count($players ?? []) }}
                    </div>

                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Currently connected
                    </div>
                </div>

            </div>
        </div>


        {{-- Next Wipe --}}
        <div class="eco-dashboard-card">
            <div class="flex items-start gap-3">

                <div class="eco-dashboard-icon bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400">
                    <svg class="" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="5" width="18" height="16" rx="2"/>
                        <path d="M16 3v4M8 3v4M3 11h18"/>
                    </svg>
                </div>

                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Next Wipe
                    </div>

                    <div class="mt-1.5 text-base font-bold">
                        {{ $this->getNextWipeLabel() }}
                    </div>

                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Protected automatic wipe schedule
                    </div>
                </div>

            </div>
        </div>


        {{-- Last Backup --}}
        <div class="eco-dashboard-card">
            <div class="flex items-start gap-3">

                <div class="eco-dashboard-icon bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400">
                    <svg class="" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 3l7 4v5c0 5-3.5 8-7 9-3.5-1-7-4-7-9V7l7-4z"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 12l2 2 4-4"/>
                    </svg>
                </div>

                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Last Backup
                    </div>

                    <div class="mt-1.5 text-base font-bold">
                        {{ $this->getLastSuccessfulBackupLabel() }}
                    </div>

                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Last successful Pelican backup
                    </div>
                </div>

            </div>
        </div>


        {{-- Game Address --}}
        <div class="eco-dashboard-card">
            <div class="flex items-start gap-3">

                <div class="eco-dashboard-icon bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                    <svg class="" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M10 13a5 5 0 007.07 0l2.12-2.12a5 5 0 00-7.07-7.07L11 4.93"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M14 11a5 5 0 00-7.07 0L4.8 13.12a5 5 0 107.07 7.07L13 19.07"/>
                    </svg>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Game Address
                    </div>

                    @if ($gameAddress !== 'Unavailable')
                        <a
                            href="#"
                            onclick="event.preventDefault(); navigator.clipboard.writeText(@js($gameAddress));"
                            class="mt-2 block break-all font-mono text-base font-bold text-primary-600 hover:underline dark:text-primary-400"
                            title="Open Eco server connection"
                        >
                            {{ $gameAddress }}
                        </a>

                        <button
                            type="button"
                            x-data="{ copied: false }"
                            x-on:click="navigator.clipboard.writeText(@js($gameAddress)); copied = true; setTimeout(() => copied = false, 1500);"
                            class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400"
                            title="Copy Eco server address"
                        >
                            <span x-show="!copied">Copy Address</span>
                            <span x-show="copied" x-cloak>Copied!</span>
                        </button>
                    @else
                        <div class="mt-2 text-base font-bold">
                            Unavailable
                        </div>
                    @endif
                </div>

            </div>
        </div>


        {{-- Visibility --}}
        <div class="eco-dashboard-card">
            <div class="flex items-start gap-3">

                <div class="eco-dashboard-icon bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300">
                    <svg class="" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="5" y="11" width="14" height="10" rx="2"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M8 11V7a4 4 0 018 0v4"/>
                    </svg>
                </div>

                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Visibility
                    </div>

                    <div class="mt-1.5 text-base font-bold">
                        {{
                            filter_var(
                                $eco['public_server'] ?? false,
                                FILTER_VALIDATE_BOOLEAN
                            )
                                ? 'Public'
                                : 'Private'
                        }}
                    </div>

                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Server visibility
                    </div>
                </div>

            </div>
        </div>


        {{-- Category --}}
        <div class="eco-dashboard-card">
            <div class="flex items-start gap-3">

                <div class="eco-dashboard-icon bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M20.59 13.41L11 3.83V3H4v7h.83l9.58 9.59a2 2 0 002.82 0l3.36-3.36a2 2 0 000-2.82z"
                        />
                        <circle cx="7.5" cy="6.5" r=".5" fill="currentColor"/>
                    </svg>
                </div>

                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Category
                    </div>

                    <div class="mt-1.5 text-base font-bold">
                        {{ $eco['category'] ?: 'None' }}
                    </div>

                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Eco server category
                    </div>
                </div>

            </div>
        </div>


        {{-- Ports --}}
        <div class="eco-dashboard-card">
            <div class="flex items-start gap-3">

                <div class="eco-dashboard-icon bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="4" y="4" width="16" height="6" rx="2"/>
                        <rect x="4" y="14" width="16" height="6" rx="2"/>
                        <path stroke-linecap="round" d="M8 7h.01M8 17h.01"/>
                    </svg>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Ports
                    </div>

                    <div class="mt-1.5 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">

                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Game</span>
                            <span class="ml-1 font-mono font-semibold">
                                {{ $ports['game'] ?? '—' }}
                            </span>
                        </div>

                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Web</span>
                            <span class="ml-1 font-mono font-semibold">
                                {{ $ports['web'] ?? '—' }}
                            </span>
                        </div>

                        <div>
                            <span class="text-gray-500 dark:text-gray-400">RCON</span>
                            <span class="ml-1 font-mono font-semibold">
                                {{ $ports['rcon'] ?? '—' }}
                            </span>
                        </div>

                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Steam</span>
                            <span class="ml-1 font-mono font-semibold">
                                {{ $ports['steam'] ?? '—' }}
                            </span>
                        </div>

                    </div>
                </div>

            </div>
        </div>

    </div>

    {{-- Players --}}
<x-filament::section>
        <x-slot name="heading">Players</x-slot>

        <div x-data="{ playerTab: 'online' }" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex gap-2">
                    <button type="button"
                            x-on:click="playerTab = 'online'"
                            :class="playerTab === 'online' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-200'"
                            class="rounded-lg px-3 py-2 text-sm font-semibold transition">
                        Online ({{ count($players ?? []) }})
                    </button>

                    <button type="button"
                            x-on:click="playerTab = 'history'"
                            :class="playerTab === 'history' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-200'"
                            class="rounded-lg px-3 py-2 text-sm font-semibold transition">
                        History
                    </button>
                </div>

                <x-filament::button size="sm" wire:click="refreshPlayers">
                    Refresh Players
                </x-filament::button>
            </div>

            <div
                x-show="playerTab === 'online'"
                x-cloak
                wire:poll.3s="refreshPlayersQuiet"
                class="space-y-3"
            >
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Auto refresh: 3 seconds
                </div>

                @forelse ($players as $player)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(220px,0.9fr)_minmax(260px,1.4fr)_210px_auto] xl:items-end">
                            <div class="min-w-0">
                                <div class="truncate text-base font-semibold">{{ $player['name'] }}</div>

                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    <span>Steam64: </span>
                                    @if (!empty($player['steam_id']))
                                        <span class="cursor-pointer font-mono hover:underline"
                                              title="Click to copy Steam64"
                                              x-data="{ copied: false }"
                                              x-on:click="navigator.clipboard.writeText(@js($player['steam_id'])); copied = true; setTimeout(() => copied = false, 1200);">
                                            <span x-show="!copied">{{ $player['steam_id'] }}</span>
                                            <span x-show="copied" x-cloak class="font-semibold">Copied!</span>
                                        </span>
                                    @else
                                        <span>Not exposed by player list</span>
                                    @endif
                                </div>

                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <span>IP: </span>
                                    @if (!empty($player['ip']))
                                        <span class="cursor-pointer font-mono hover:underline"
                                              title="Click to copy IP address"
                                              x-data="{ copied: false }"
                                              x-on:click="navigator.clipboard.writeText(@js($player['ip'])); copied = true; setTimeout(() => copied = false, 1200);">
                                            <span x-show="!copied">{{ $player['ip'] }}</span>
                                            <span x-show="copied" x-cloak class="font-semibold">Copied!</span>
                                        </span>
                                    @else
                                        <span>Not exposed by player list</span>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-medium">Reason</label>
                                <input type="text"
                                       wire:model="kickReasons.{{ $loop->index }}"
                                       placeholder="Used for Kick or Ban"
                                       class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-medium">Ban Duration</label>
                                <div class="flex items-center gap-2">
                                    <input type="number" min="1" step="1"
                                           wire:model="banHours.{{ $loop->index }}"
                                           placeholder="Hours"
                                           class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                                    <label class="flex shrink-0 items-center gap-1 text-xs">
                                        <input type="checkbox" wire:model="banPermanent.{{ $loop->index }}">
                                        Permanent
                                    </label>
                                </div>
                            </div>

                            <div class="flex gap-2 xl:justify-end">
                                <x-filament::button color="warning" size="sm"
                                    wire:click="kickPlayerByIndex({{ $loop->index }})"
                                    wire:confirm="Kick this player?">Kick</x-filament::button>

                                <x-filament::button color="danger" size="sm"
                                    wire:click="banPlayerByIndex({{ $loop->index }})"
                                    wire:confirm="Ban this player?">Ban</x-filament::button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                        No players online.
                    </div>
                @endforelse
            </div>

            <div x-show="playerTab === 'history'" x-cloak class="space-y-3">
                @php($historyRows = $this->getPlayerHistory())

                <div class="text-xs text-gray-500 dark:text-gray-400">
                    History begins with Eco Enhanced v1.1.5. RCON tracks sessions in the background every minute.
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Player</th>
                                <th class="px-4 py-3 text-left font-semibold">IP Addresses</th>
                                <th class="px-4 py-3 text-right font-semibold">Connections</th>
                                <th class="px-4 py-3 text-right font-semibold">Total Time</th>
                                <th class="px-4 py-3 text-left font-semibold">First Seen</th>
                                <th class="px-4 py-3 text-left font-semibold">Last Seen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @forelse ($historyRows as $row)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold">{{ $row['name'] ?: 'Unknown Player' }}</span>
                                            @if (!empty($row['online']))
                                                <x-filament::badge color="success">Online</x-filament::badge>
                                            @endif
                                        </div>

                                        @if (!empty($row['steam_id']))
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Steam64:
                                                <span class="cursor-pointer font-mono hover:underline"
                                                      x-data="{ copied: false }"
                                                      x-on:click="navigator.clipboard.writeText(@js($row['steam_id'])); copied = true; setTimeout(() => copied = false, 1200);">
                                                    <span x-show="!copied">{{ $row['steam_id'] }}</span>
                                                    <span x-show="copied" x-cloak class="font-semibold">Copied!</span>
                                                </span>
                                            </div>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                        <span
                                            class="cursor-pointer font-mono text-xs hover:underline"
                                            title="Click to copy IP addresses"
                                            x-data="{ copied: false }"
                                            x-on:click="navigator.clipboard.writeText(@js($this->formatPlayerIps($row))); copied = true; setTimeout(() => copied = false, 1200);"
                                        >
                                            <span x-show="!copied">{{ $this->formatPlayerIps($row) }}</span>
                                            <span x-show="copied" x-cloak class="font-semibold">Copied!</span>
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 text-right font-semibold">{{ (int) ($row['connections'] ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ $this->formatPlayTime((int) ($row['total_seconds'] ?? 0)) }}</td>

                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                        {{ !empty($row['first_seen_at']) ? \Carbon\Carbon::parse($row['first_seen_at'])->timezone(config('app.timezone'))->format('M j, Y g:i A') : 'Unknown' }}
                                    </td>

                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                        {{ !empty($row['last_seen_at']) ? \Carbon\Carbon::parse($row['last_seen_at'])->timezone(config('app.timezone'))->format('M j, Y g:i A') : 'Unknown' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        No player history has been recorded yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- Player Administration --}}
<div
    x-data="{
        open: (() => {
            const saved = localStorage.getItem('eco-enhanced-overview-permissions');
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
            localStorage.setItem('eco-enhanced-overview-permissions', open ? 'true' : 'false');
        "
    >
        <div class="min-w-0">
            <div class="truncate text-base font-semibold">
                Player & Permission Administration
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
<div x-data="{ adminTab: 'admins' }" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        x-on:click="adminTab = 'admins'"
                        :class="adminTab === 'admins'
                            ? 'bg-primary-600 text-white'
                            : 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-200'"
                        class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                    >
                        Admins ({{ count($admins ?? []) }})
                    </button>

                    <button
                        type="button"
                        x-on:click="adminTab = 'whitelist'"
                        :class="adminTab === 'whitelist'
                            ? 'bg-primary-600 text-white'
                            : 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-200'"
                        class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                    >
                        Whitelist ({{ count($whitelist ?? []) }})
                    </button>

                    <button
                        type="button"
                        x-on:click="adminTab = 'banned'"
                        :class="adminTab === 'banned'
                            ? 'bg-primary-600 text-white'
                            : 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-200'"
                        class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                    >
                        Banned ({{ count($bannedPlayers ?? []) }})
                    </button>

                    <button
                        type="button"
                        x-on:click="adminTab = 'muted'"
                        :class="adminTab === 'muted'
                            ? 'bg-primary-600 text-white'
                            : 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-200'"
                        class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                    >
                        Muted ({{ count($mutedPlayers ?? []) }})
                    </button>
                </div>

                <x-filament::button color="gray" size="sm" wire:click="refreshAdminLists">
                    Refresh Lists
                </x-filament::button>
            </div>

            <div x-show="adminTab === 'admins'" x-cloak class="space-y-3">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input type="text" wire:model="newAdmin" placeholder="Player name or Steam64"
                           class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                    <x-filament::button wire:click="addAdmin">Add Admin</x-filament::button>
                </div>

                <div class="divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 dark:divide-white/10 dark:border-white/10">
                    @forelse ($admins as $admin)
                        @php($identity = $this->getIdentityFor((string) $admin))
                        <div class="flex items-center justify-between gap-4 px-4 py-3">
                            <div class="min-w-0">
                                <div class="truncate font-semibold">{{ $identity['name'] ?: 'Unknown Player' }}</div>
                                @if (!empty($identity['steam_id']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Steam64:
                                        <span class="cursor-pointer font-mono hover:underline"
                                              x-data="{ copied: false }"
                                              x-on:click="navigator.clipboard.writeText(@js($identity['steam_id'])); copied = true; setTimeout(() => copied = false, 1200);">
                                            <span x-show="!copied">{{ $identity['steam_id'] }}</span>
                                            <span x-show="copied" x-cloak class="font-semibold">Copied!</span>
                                        </span>
                                    </div>
                                @else
                                    <div class="truncate font-mono text-xs text-gray-500 dark:text-gray-400">{{ $identity['raw'] }}</div>
                                @endif
                            </div>

                            <x-filament::button color="danger" size="sm"
                                wire:click="removeAdminByIndex({{ $loop->index }})"
                                wire:confirm="Remove this admin?">Remove</x-filament::button>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No admins returned by Eco.</div>
                    @endforelse
                </div>
            </div>

            <div x-show="adminTab === 'whitelist'" x-cloak class="space-y-3">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input type="text" wire:model="newWhitelist" placeholder="Player name or Steam64"
                           class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                    <x-filament::button wire:click="addWhitelist">Add to Whitelist</x-filament::button>
                </div>

                <div class="divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 dark:divide-white/10 dark:border-white/10">
                    @forelse ($whitelist as $entry)
                        @php($identity = $this->getIdentityFor((string) $entry))
                        <div class="flex items-center justify-between gap-4 px-4 py-3">
                            <div class="min-w-0">
                                <div class="truncate font-semibold">{{ $identity['name'] ?: 'Unknown Player' }}</div>
                                @if (!empty($identity['steam_id']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Steam64:
                                        <span class="cursor-pointer font-mono hover:underline"
                                              x-data="{ copied: false }"
                                              x-on:click="navigator.clipboard.writeText(@js($identity['steam_id'])); copied = true; setTimeout(() => copied = false, 1200);">
                                            <span x-show="!copied">{{ $identity['steam_id'] }}</span>
                                            <span x-show="copied" x-cloak class="font-semibold">Copied!</span>
                                        </span>
                                    </div>
                                @else
                                    <div class="truncate font-mono text-xs text-gray-500 dark:text-gray-400">{{ $identity['raw'] }}</div>
                                @endif
                            </div>

                            <x-filament::button color="danger" size="sm"
                                wire:click="removeWhitelistByIndex({{ $loop->index }})"
                                wire:confirm="Remove this whitelist entry?">Remove</x-filament::button>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Whitelist is empty.</div>
                    @endforelse
                </div>
            </div>

            <div x-show="adminTab === 'banned'" x-cloak class="space-y-3">
                <div class="grid gap-2 lg:grid-cols-[minmax(220px,1fr)_minmax(260px,1.4fr)_150px_auto_auto] lg:items-end">
                    <div>
                        <label class="mb-1 block text-xs font-medium">Player</label>
                        <input type="text" wire:model="manualBanTarget" placeholder="Player name or Steam64"
                               class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium">Reason</label>
                        <input type="text" wire:model="manualBanReason" placeholder="Ban reason"
                               class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium">Hours</label>
                        <input type="number" min="1" step="1" wire:model="manualBanHours" placeholder="Hours"
                               class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                    </div>

                    <label class="flex items-center gap-2 pb-2 text-xs">
                        <input type="checkbox" wire:model="manualBanPermanent">
                        Permanent
                    </label>

                    <x-filament::button color="danger" wire:click="addManualBan">
                        Ban Player
                    </x-filament::button>
                </div>

                <div class="text-xs text-gray-500 dark:text-gray-400">
                    Offline players can be banned directly by Steam64.
                </div>

                <div class="divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 dark:divide-white/10 dark:border-white/10">
                    @forelse ($bannedPlayers as $entry)
                        @php($identity = $this->getIdentityFor((string) ($entry['name'] ?? '')))
                        <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="truncate font-semibold">{{ $identity['name'] ?: 'Unknown Player' }}</div>
                                @if (!empty($identity['steam_id']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Steam64:
                                        <span class="cursor-pointer font-mono hover:underline"
                                              x-data="{ copied: false }"
                                              x-on:click="navigator.clipboard.writeText(@js($identity['steam_id'])); copied = true; setTimeout(() => copied = false, 1200);">
                                            <span x-show="!copied">{{ $identity['steam_id'] }}</span>
                                            <span x-show="copied" x-cloak class="font-semibold">Copied!</span>
                                        </span>
                                    </div>
                                @else
                                    <div class="truncate font-mono text-xs text-gray-500 dark:text-gray-400">{{ $identity['raw'] }}</div>
                                @endif
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $this->getBanTimeRemaining($entry['expires']) }}
                                    @if (($entry['expires'] ?? 'Unknown') !== 'Unknown')
                                        · {{ $entry['expires'] }}
                                    @endif
                                </div>
                            </div>

                            <x-filament::button color="success" size="sm"
                                wire:click="unbanPlayerByIndex({{ $loop->index }})"
                                wire:confirm="Unban this player?">Unban</x-filament::button>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No banned players.</div>
                    @endforelse
                </div>
            </div>

            <div x-show="adminTab === 'muted'" x-cloak class="space-y-3">
                <div class="grid gap-2 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_160px_auto]">
                    <input type="text" wire:model="newMute" placeholder="Player name or Steam64"
                           class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                    <input type="text" wire:model="newMuteReason" placeholder="Mute reason"
                           class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                    <input type="text" wire:model="newMuteTime" placeholder="30m / 6h / 2d / blank"
                           class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900">
                    <x-filament::button wire:click="addMute">Mute</x-filament::button>
                </div>

                <div class="divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 dark:divide-white/10 dark:border-white/10">
                    @forelse ($mutedPlayers as $entry)
                        @php($identity = $this->getIdentityFor((string) ($entry['name'] ?? '')))
                        <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="truncate font-semibold">{{ $identity['name'] ?: 'Unknown Player' }}</div>
                                @if (!empty($identity['steam_id']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Steam64:
                                        <span class="cursor-pointer font-mono hover:underline"
                                              x-data="{ copied: false }"
                                              x-on:click="navigator.clipboard.writeText(@js($identity['steam_id'])); copied = true; setTimeout(() => copied = false, 1200);">
                                            <span x-show="!copied">{{ $identity['steam_id'] }}</span>
                                            <span x-show="copied" x-cloak class="font-semibold">Copied!</span>
                                        </span>
                                    </div>
                                @else
                                    <div class="truncate font-mono text-xs text-gray-500 dark:text-gray-400">{{ $identity['raw'] }}</div>
                                @endif
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $this->getBanTimeRemaining($entry['expires']) }}
                                    @if (($entry['expires'] ?? 'Unknown') !== 'Unknown')
                                        · {{ $entry['expires'] }}
                                    @endif
                                </div>
                            </div>

                            <x-filament::button color="success" size="sm"
                                wire:click="unmuteByIndex({{ $loop->index }})"
                                wire:confirm="Unmute this player?">Unmute</x-filament::button>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No muted players.</div>
                    @endforelse
                </div>
            </div>
        </div>
        </div>
    </div>
</div>


    {{-- Eco Admin Command Center --}}
    <div
        x-data="{
            open: (() => {
                const saved = localStorage.getItem('eco-enhanced-overview-command-center');
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
                    'eco-enhanced-overview-command-center',
                    open ? 'true' : 'false'
                );
            "
        >
            <div class="min-w-0">
                <div class="truncate text-base font-semibold">
                    Eco Admin Command Center
                </div>
            </div>

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
            <div class="space-y-6 p-5">

                <div>
                    <div class="mb-2 text-sm font-semibold">
                        Quick Actions
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-filament::button
                            color="gray"
                            wire:click="meteorStatus"
                        >
                            Meteor Status
                        </x-filament::button>

                        <x-filament::button
                            color="gray"
                            wire:click="climateStatus"
                        >
                            Climate Status
                        </x-filament::button>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold">
                        Server Announcement
                    </label>

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input
                            type="text"
                            wire:model="announcement"
                            placeholder="Message to all players..."
                            class="flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                        >

                        <x-filament::button wire:click="sendAnnouncement">
                            Send Announcement
                        </x-filament::button>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold">
                        Eco RCON Command
                    </label>

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input
                            type="text"
                            wire:model="adminCommand"
                            placeholder="Example: /manage players"
                            class="flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-white/10 dark:bg-gray-900"
                        >

                        <x-filament::button wire:click="runAdminCommand">
                            Run Command
                        </x-filament::button>
                    </div>

                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Commands work with or without the leading slash.
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold">
                        Command Output
                    </label>

                    <div class="min-h-28 whitespace-pre-wrap rounded-xl border border-gray-200 bg-gray-50 p-4 font-mono text-sm dark:border-white/10 dark:bg-gray-950">{{ $adminOutput !== '' ? $adminOutput : 'No command output yet.' }}</div>
                </div>

            </div>
        </div>
    </div>

<x-filament::section>
        <x-slot name="heading">
            World & Wipes
        </x-slot>

        <div class="space-y-3">

            {{-- ========================================================= --}}
            {{-- WIPE NOW                                                  --}}
            {{-- ========================================================= --}}

            <details
               
                class="group overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
            
                wire:key="eco-wipe-now-panel"
                x-data="{ open: false }"
                x-bind:open="open"
                x-on:toggle="open = $el.open"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-4 bg-gray-50 px-4 py-3 dark:bg-white/5"
                >
                    <div>
                        <div class="font-semibold">
                            Wipe Now
                        </div>

                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            Perform a complete Eco save reset with a protected Pre-Wipe backup.
                        </div>
                    </div>

                    <span
                        class="transition-transform group-open:rotate-180"
                        aria-hidden="true"
                    >
                        ▼
                    </span>
                </summary>

                <div class="space-y-4 border-t border-gray-200 p-4 dark:border-white/10">

                    <div
                        class="rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200"
                    >
                        <div class="font-semibold">
                            Complete Eco world reset
                        </div>

                        <div class="mt-1 text-xs">
                            Eco Enhanced does not offer partial Eco wipes. The active
                            save must remain internally consistent.
                        </div>
                    </div>

                    <div class="grid gap-2 text-sm md:grid-cols-2">

                        <div class="flex items-center gap-2">
                            <span class="text-success-600">✓</span>
                            Save the live Eco world first
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-success-600">✓</span>
                            Create a locked Pre-Wipe backup
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-success-600">✓</span>
                            Stop Eco before touching the save
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-success-600">✓</span>
                            Reset the complete active Eco save
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-success-600">✓</span>
                            Preserve Configs and Mods
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-success-600">✓</span>
                            Start Eco and generate a fresh world
                        </div>

                    </div>

                    <div class="flex flex-wrap gap-2">

                        <x-filament::button
                            type="button"
                            color="danger"
                            wire:key="eco-world-wipe"
                            wire:click="wipeEcoWorld"
                            wire:confirm="WIPE THE ECO WORLD? Eco Enhanced will create a locked Pre-Wipe backup first. The wipe will only continue if that backup succeeds. The complete active Eco save will then be reset and Eco will generate a fresh world. Configs and Mods are preserved."
                            wire:loading.attr="disabled"
                        >
                            Wipe Eco World
                        </x-filament::button>

                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:key="eco-world-save"
                            wire:click="saveEcoWorld"
                        >
                            Save World
                        </x-filament::button>

                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:key="eco-world-backup"
                            wire:click="createEcoBackup"
                            wire:loading.attr="disabled"
                        >
                            Create Normal Backup
                        </x-filament::button>

                    </div>

                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Pre-Wipe backups are locked automatically. If the safety
                        backup fails, Eco Enhanced cancels the wipe and leaves the
                        active Eco save untouched.
                    </div>

                </div>
            </details>


            {{-- ========================================================= --}}
            {{-- WIPE SCHEDULE                                             --}}
            {{-- ========================================================= --}}

            <details
               
                class="group overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
            
                wire:key="eco-wipe-schedule-panel"
                x-data="{ open: false }"
                x-bind:open="open"
                x-on:toggle="open = $el.open"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-4 bg-gray-50 px-4 py-3 dark:bg-white/5"
                >
                    <div>
                        <div class="font-semibold">
                            Wipe Schedule
                        </div>

                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            Schedule one protected automatic Eco wipe.
                        </div>
                    </div>

                    <div class="flex items-center gap-3">

                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            Next:
                            <span class="font-semibold text-gray-900 dark:text-white">
                                {{ $this->getNextWipeLabel() }}
                            </span>
                        </span>

                        <span
                            class="transition-transform group-open:rotate-180"
                            aria-hidden="true"
                        >
                            ▼
                        </span>

                    </div>
                </summary>

                <div class="space-y-4 border-t border-gray-200 p-4 dark:border-white/10">

                    <label class="flex items-center gap-2 text-sm font-medium">
                        <input
                            type="checkbox"
                            wire:model="scheduledWipeEnabled"
                            class="rounded border-gray-300"
                        >

                        Enable Scheduled Wipe
                    </label>

                    <div class="grid gap-3 md:grid-cols-2">

                        <div>
                            <label class="mb-1 block text-sm font-semibold">
                                Wipe Date
                            </label>

                            <input
                                type="date"
                                wire:model="scheduledWipeDate"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                            >
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-semibold">
                                Wipe Time
                            </label>

                            <input
                                type="time"
                                wire:model="scheduledWipeTime"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-gray-900"
                            >
                        </div>

                    </div>

                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Times are shown in Central Time. Scheduled wipes use the
                        same protected Pre-Wipe backup flow as Wipe Now.
                    </div>

                    <div class="flex flex-wrap gap-2">

                        <x-filament::button
                            wire:click="saveScheduledWipe"
                            wire:loading.attr="disabled"
                        >
                            Save Schedule
                        </x-filament::button>

                        @if ($scheduledWipeEnabled)
                            <x-filament::button
                                color="danger"
                                wire:click="cancelScheduledWipe"
                                wire:confirm="Cancel the scheduled Eco wipe?"
                            >
                                Cancel Scheduled Wipe
                            </x-filament::button>
                        @endif

                    </div>

                </div>
            </details>


            {{-- ========================================================= --}}
            {{-- WIPE HISTORY                                              --}}
            {{-- ========================================================= --}}

            <details
                class="group overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
            
                wire:key="eco-wipe-history-panel"
                x-data="{ open: false }"
                x-bind:open="open"
                x-on:toggle="open = $el.open"
            >
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-4 bg-gray-50 px-4 py-3 dark:bg-white/5"
                >
                    <div>
                        <div class="font-semibold">
                            Wipe History
                        </div>

                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            Recent manual and scheduled Eco wipes.
                        </div>
                    </div>

                    <div class="flex items-center gap-2">

                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ count($this->getWipeHistory()) }} recorded
                        </span>

                        <span
                            class="transition-transform group-open:rotate-180"
                            aria-hidden="true"
                        >
                            ▼
                        </span>

                    </div>
                </summary>

                <div class="border-t border-gray-200 dark:border-white/10">

                    <div class="overflow-x-auto">

                        <table class="w-full text-left text-sm">

                            <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-2 font-semibold">
                                        Date
                                    </th>

                                    <th class="px-4 py-2 font-semibold">
                                        Type
                                    </th>

                                    <th class="px-4 py-2 font-semibold">
                                        Pre-Wipe Backup
                                    </th>

                                    <th class="px-4 py-2 font-semibold">
                                        Result
                                    </th>

                                    <th class="px-4 py-2 font-semibold">
                                        Requested By
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">

                                @foreach ($this->getWipeHistory() as $row)

                                    <tr>

                                        <td class="px-4 py-2 whitespace-nowrap">
                                            {{ $row['occurred_at'] ?? 'Unknown' }}
                                        </td>

                                        <td class="px-4 py-2">
                                            <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium dark:bg-white/10">
                                                {{ ($row['trigger'] ?? '') === 'scheduled' ? 'Scheduled' : 'Manual' }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span
                                                @class([
                                                    'rounded-md px-2 py-1 text-xs font-medium',
                                                    'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400'
                                                        => !empty($row['backup_successful']),
                                                    'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400'
                                                        => empty($row['backup_successful']),
                                                ])
                                            >
                                                {{ !empty($row['backup_successful']) ? 'Successful' : 'Failed' }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span
                                                @class([
                                                    'rounded-md px-2 py-1 text-xs font-medium',
                                                    'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400'
                                                        => ($row['status'] ?? '') === 'completed',
                                                    'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400'
                                                        => ($row['status'] ?? '') !== 'completed',
                                                ])
                                            >
                                                {{ ($row['status'] ?? '') === 'completed' ? 'Completed' : 'Failed' }}
                                            </span>

                                            @if (!empty($row['message']))
                                                <div class="mt-1 max-w-lg text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $row['message'] }}
                                                </div>
                                            @endif
                                        </td>

                                        <td class="px-4 py-2 whitespace-nowrap">
                                            {{ $row['requested_by'] ?? 'Unknown' }}
                                        </td>

                                    </tr>

                                @endforeach

                                @if (count($this->getWipeHistory()) === 0)

                                    <tr>
                                        <td
                                            colspan="5"
                                            class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            No Eco Enhanced wipe history has been recorded yet.
                                        </td>
                                    </tr>

                                @endif

                            </tbody>

                        </table>

                    </div>

                </div>
            </details>

        </div>
    </x-filament::section>

</x-filament-panels::page>
