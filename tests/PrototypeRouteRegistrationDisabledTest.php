<?php

declare(strict_types=1);

namespace Splicewire\Beam\UxPrototype\Tests;

use Illuminate\Support\Facades\Route;

/**
 * A genuine SPA host (register_route: false) — its own top-level client router wires the glob by
 * hand; the package must not register a server route at all. Config has to be set before boot
 * (packageBooted() registers the route once, at boot), hence its own defineEnvironment() override.
 */
class PrototypeRouteRegistrationDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('beam-ux-prototype.register_route', false);
    }

    public function test_no_route_is_registered_when_register_route_is_disabled(): void
    {
        $route = collect(Route::getRoutes())->first(
            fn ($r) => $r->uri() === '_prototype/{any?}',
        );

        $this->assertNull($route);
    }
}
