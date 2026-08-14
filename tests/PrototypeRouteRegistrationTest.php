<?php

declare(strict_types=1);

namespace Splicewire\Beam\UxPrototype\Tests;

use Illuminate\Support\Facades\Route;

/**
 * The monolith default (register_route, Inertia installed): the package auto-registers the
 * dev-only `/_prototype/{any?}` route — the one thing a genuine SPA host wires by hand instead
 * (its own top-level client router, no Laravel route needed at all).
 */
class PrototypeRouteRegistrationTest extends TestCase
{
    public function test_it_registers_the_dev_only_prototype_route_by_default(): void
    {
        $route = collect(Route::getRoutes())->first(
            fn ($r) => $r->uri() === '_prototype/{any?}',
        );

        $this->assertNotNull($route, 'expected a registered /_prototype/{any?} route');
    }
}
