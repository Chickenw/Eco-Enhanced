<?php

namespace EcoEnhanced;

use Filament\Contracts\Plugin;
use Filament\Panel;
use EcoEnhanced\Pages\EcoOverview;
use EcoEnhanced\Pages\EcoConfigs;

class EcoEnhancedPlugin implements Plugin
{
    public function getId(): string
    {
        return 'eco-enhanced';
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
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
