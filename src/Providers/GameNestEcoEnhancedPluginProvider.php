<?php

namespace GameNest\GameNestEcoEnhanced\Providers;

use App\Models\Server;
use GameNest\GameNestEcoEnhanced\Services\EcoPortAllocator;
use Illuminate\Console\Scheduling\Schedule;
use GameNest\GameNestEcoEnhanced\Services\EcoPlayerHistoryService;
use GameNest\GameNestEcoEnhanced\Services\EcoWorldWipeService;
use GameNest\GameNestEcoEnhanced\Services\EcoWipeScheduleService;
use GameNest\GameNestEcoEnhanced\Services\EcoWipeHistoryService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class GameNestEcoEnhancedPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EcoPortAllocator::class);
        $this->app->singleton(EcoPlayerHistoryService::class);
        $this->app->singleton(EcoWorldWipeService::class);
        $this->app->singleton(EcoWipeScheduleService::class);
        $this->app->singleton(EcoWipeHistoryService::class);
    }

    public function boot(): void
    {
        Server::created(function (Server $server) {
            try {
                /*
                 * Delay until the surrounding Pelican creation transaction
                 * has successfully committed.
                 */
                app()->terminating(function () use ($server) {
                    try {
                        $server->refresh();
                        $server->loadMissing('egg');

                        if (!$server->egg) {
                            return;
                        }

                        /*
                         * Only touch Eco eggs.
                         *
                         * This deliberately uses the Egg name for our first
                         * implementation so Minecraft/etc. remain untouched.
                         */
                        if (!str_contains(
                            strtolower($server->egg->name),
                            'eco'
                        )) {
                            return;
                        }

                        app(EcoPortAllocator::class)->configure($server);

                    } catch (\Throwable $exception) {
                        Log::error(
                            '[GameNest Eco Enhanced] Failed to configure Eco allocations.',
                            [
                                'server_id' => $server->id,
                                'error' => $exception->getMessage(),
                            ]
                        );
                    }
                });

            } catch (\Throwable $exception) {
                Log::error(
                    '[GameNest Eco Enhanced] Server creation listener failed.',
                    [
                        'server_id' => $server->id,
                        'error' => $exception->getMessage(),
                    ]
                );
            }
        });
        /*
         * Persistent player history. Pelican's Laravel scheduler runs this
         * every minute, so totals do not depend on Eco Overview being open.
         */
        app(Schedule::class)
            ->call(function (): void {
                Server::query()
                    ->with('egg')
                    ->get()
                    ->filter(
                        fn (Server $server) =>
                            $server->egg &&
                            str_contains(strtolower($server->egg->name), 'eco')
                    )
                    ->each(function (Server $server): void {
                        try {
                            app(EcoPlayerHistoryService::class)->poll($server);
                        } catch (\Throwable $exception) {
                            // Offline/stopped Eco servers are normal.
                        }
                    });
            })
            ->name('gamenest-eco-player-history')
            ->everyMinute()
            ->withoutOverlapping();


        /*
         * Continue protected Eco world wipes server-side.
         *
         * This means the browser does not need to remain open after
         * a wipe has been requested. Once the locked Pre-Wipe backup
         * completes, the scheduler performs the actual wipe.
         */
        app(Schedule::class)
            ->call(function (): void {
                Server::query()
                    ->with('egg')
                    ->get()
                    ->filter(
                        fn (Server $server) =>
                            $server->egg &&
                            str_contains(strtolower($server->egg->name), 'eco')
                    )
                    ->each(function (Server $server): void {
                        try {
                            $status = app(EcoWorldWipeService::class)
                                ->process($server);

                            if ($status === 'completed') {
                                Log::info(
                                    '[GameNest Eco Enhanced] Eco world wipe completed.',
                                    ['server_id' => $server->id]
                                );
                            }

                            if ($status === 'failed') {
                                Log::error(
                                    '[GameNest Eco Enhanced] Eco world wipe cancelled because its Pre-Wipe backup failed or disappeared.',
                                    ['server_id' => $server->id]
                                );
                            }
                        } catch (\Throwable $exception) {
                            Log::error(
                                '[GameNest Eco Enhanced] Pending Eco world wipe failed.',
                                [
                                    'server_id' => $server->id,
                                    'error' => $exception->getMessage(),
                                ]
                            );
                        }
                    });
            })
            ->name('gamenest-eco-world-wipes')
            ->everyMinute()
            ->withoutOverlapping();


        /*
         * Trigger one-time scheduled Eco wipes.
         */
        app(Schedule::class)
            ->call(function (): void {
                Server::query()
                    ->with('egg')
                    ->get()
                    ->filter(
                        fn (Server $server) =>
                            $server->egg &&
                            str_contains(
                                strtolower($server->egg->name),
                                'eco'
                            )
                    )
                    ->each(function (Server $server): void {
                        try {
                            $scheduleService =
                                app(EcoWipeScheduleService::class);

                            if (!$scheduleService->isDue($server)) {
                                return;
                            }

                            $wipeService =
                                app(EcoWorldWipeService::class);

                            /*
                             * If a wipe is already pending, consider this
                             * schedule consumed rather than starting another.
                             */
                            if (
                                cache()->has(
                                    $wipeService->pendingKey($server)
                                )
                            ) {
                                $scheduleService->markTriggered($server);

                                return;
                            }

                            /*
                             * Start protected backup/wipe flow.
                             */
                            $wipeService->start(
                                $server,
                                'scheduled',
                                'GameNest Scheduler'
                            );

                            /*
                             * One-time schedule: disable after successfully
                             * initiating the protected wipe.
                             */
                            $scheduleService->markTriggered($server);

                            Log::info(
                                '[GameNest Eco Enhanced] Scheduled Eco wipe started.',
                                [
                                    'server_id' => $server->id,
                                ]
                            );

                        } catch (\Throwable $exception) {
                            /*
                             * Leave schedule enabled on failure so the next
                             * minute can retry.
                             */
                            Log::error(
                                '[GameNest Eco Enhanced] Scheduled Eco wipe failed to start.',
                                [
                                    'server_id' => $server->id,
                                    'error' => $exception->getMessage(),
                                ]
                            );
                        }
                    });
            })
            ->name('gamenest-eco-scheduled-wipes')
            ->everyMinute()
            ->withoutOverlapping();


    }
}
