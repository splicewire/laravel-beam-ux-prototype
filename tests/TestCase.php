<?php

declare(strict_types=1);

namespace Splicewire\Beam\UxPrototype\Tests;

use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Splicewire\Beam\BeamServiceProvider;
use Splicewire\Beam\UxPrototype\BeamUxPrototypeServiceProvider;

/**
 * Boots beam-core (for the two manifests the twin self-registers into) + Inertia (so the
 * register_route auto-registration has the real class to gate on) alongside the twin provider.
 * Used by the boot/registration tests; the pure audit test needs no Laravel and extends PHPUnit
 * directly.
 */
class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            BeamServiceProvider::class,
            InertiaServiceProvider::class,
            BeamUxPrototypeServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Application::environment() reads $app['env'] directly — config('app.env') alone doesn't
        // retroactively change it once the app's already past environment detection.
        $app['env'] = 'local';
        $app['config']->set('app.env', 'local');
    }
}
