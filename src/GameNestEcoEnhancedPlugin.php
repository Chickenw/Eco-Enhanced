<?php

namespace GameNest\GameNestEcoEnhanced;

use Filament\Contracts\Plugin;
use Filament\Panel;
use GameNest\GameNestEcoEnhanced\Pages\EcoOverview;
use GameNest\GameNestEcoEnhanced\Pages\EcoConfigs;
use GameNest\GameNestEcoEnhanced\Pages\EcoMods;

class GameNestEcoEnhancedPlugin implements Plugin
{
    public function getId(): string
    {
        return 'gamenest-eco-enhanced';
    }

    public function register(Panel $panel): void
    {
        /*
         * Eco Overview belongs only in Pelican's server panel.
         *
         * The page itself also checks that the currently selected
         * server is actually using an Eco egg.
         */
        if ($panel->getId() === 'server') {
            $panel->pages([
                EcoOverview::class,
                EcoConfigs::class,
                EcoMods::class,
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
