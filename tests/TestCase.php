<?php

namespace Splicewire\Beam\UxPrototype\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Splicewire\Beam\BeamServiceProvider;
use Splicewire\Beam\UxPrototype\BeamUxPrototypeServiceProvider;

/**
 * Boots beam-core (for the two manifests the twin self-registers into) alongside the twin provider.
 * Used by the boot/registration test; the pure audit test needs no Laravel and extends PHPUnit directly.
 */
class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            BeamServiceProvider::class,
            BeamUxPrototypeServiceProvider::class,
        ];
    }
}
