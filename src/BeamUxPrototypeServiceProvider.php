<?php

declare(strict_types=1);

namespace Splicewire\Beam\UxPrototype;

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Splicewire\Beam\Doctor\BeamDoctorManifest;
use Splicewire\Beam\Install\BeamInstallManifest;
use Splicewire\Beam\UxPrototype\Console\PrototypeDoctorCommand;
use Splicewire\Beam\UxPrototype\Console\PrototypeInstallCommand;
use Splicewire\Beam\UxPrototype\Doctor\PrototypeWiringAudit;

/**
 * The Laravel twin of `@splicewire/beam-ux-prototype` (ADR-0116 Amendment 1). The runtime stays
 * JS-only; this provider owns the ONE thing PHP serves better — install + doctor for the host's
 * rushing-prototype wiring:
 *
 *  - binds {@see PrototypeWiringAudit} (base-path + config) so the container can resolve it;
 *  - registers that audit — ADVISORY — DOWN into beam-core's {@see BeamDoctorManifest}, so one
 *    `splicewire:beam:doctor` run aggregates it (the manifest's first consumer citizen);
 *  - registers an install step DOWN into {@see BeamInstallManifest} (publish tags for the config +
 *    the scaffold stubs), so `splicewire:beam:install` stamps the starter + nav.ts + convention template;
 *  - for the monolith default (`register_route`, Inertia installed): also publishes the
 *    `_prototype.tsx` Inertia host page and auto-registers the dev-only `/_prototype/{any?}` route —
 *    Inertia has no top-level client router of its own to spread the glob into, so this ONE net-new
 *    page + route is the package's job, unlike the router-glob edit a genuine SPA host makes by hand;
 *  - ships the standalone `splicewire:beam:ux:prototype:{doctor,install}` commands.
 *
 * Both manifest registrations are guarded by `bound(...)` (the notifications-twin precedent) so the
 * package still boots in a host that predates the manifests. `inertiajs/inertia-laravel` is never a
 * hard dependency — the route registration is `class_exists()`-gated, a no-op for a genuine SPA host.
 */
class BeamUxPrototypeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-beam-ux-prototype')
            ->hasConfigFile('beam-ux-prototype')
            ->hasCommands([
                PrototypeDoctorCommand::class,
                PrototypeInstallCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // The audit resolves from the container with the host base path + config, so BeamDoctorManifest
        // can `make()` it with no per-check wiring in the aggregating command.
        $this->app->bind(PrototypeWiringAudit::class, fn ($app) => new PrototypeWiringAudit(
            $app->basePath(),
            (array) config('beam-ux-prototype', []),
        ));
    }

    public function packageBooted(): void
    {
        // The scaffold the InstallStep stamps: a starter prototype, the host-owned nav.ts, the
        // convention TEMPLATE (placeholders intact — `splicewire:beam:ux:prototype:install` binds it to a host
        // instance; a raw `vendor:publish` cannot substitute, so splicewire:beam:install lands the template form),
        // and — monolith shape only (`register_route`) — the Inertia prototype-host page. That last
        // one is a net-new file, not an edit to host code, so it's safe to auto-stamp like the rest;
        // a genuine SPA host (register_route: false) wires its own top-level router by hand instead.
        if ($this->app->runningInConsole()) {
            $prototypeDir = (string) config('beam-ux-prototype.prototype_dir', 'resources/js/_prototype');

            $publishes = [
                __DIR__.'/../stubs/starter-prototype.tsx.stub' => $this->app->basePath($prototypeDir.'/starter/01-starter.tsx'),
                __DIR__.'/../stubs/nav.ts.stub' => $this->app->basePath($prototypeDir.'/_chrome/nav.ts'),
                __DIR__.'/../stubs/rushing-prototype.convention.template.md' => $this->app->basePath('docs/agents/rushing-prototype.convention.template.md'),
            ];

            if ((bool) config('beam-ux-prototype.register_route', true)) {
                $routerPath = (string) config('beam-ux-prototype.router', 'resources/js/pages/_prototype.tsx');
                $publishes[__DIR__.'/../stubs/prototype-host.tsx.stub'] = $this->app->basePath($routerPath);
            }

            $this->publishes($publishes, 'beam-ux-prototype-scaffold');
        }

        // Monolith shape only, and only if the host actually has Inertia installed: the dev-only
        // `/_prototype/{any?}` route Inertia's own routing has no reason to know about otherwise.
        // Gated on the environment, not just APP_DEBUG, so a misconfigured prod box doesn't expose it.
        if (
            (bool) config('beam-ux-prototype.register_route', true)
            && class_exists(Inertia::class)
            && $this->app->environment('local')
        ) {
            // Derived from `router`, not a separate config key — Inertia resolves a page by its path
            // under {src_root}/pages/ minus the extension, so this stays correct for free if a host
            // ever repoints `router` (e.g. a different pages/ subdir).
            $pagesRoot = rtrim((string) config('beam-ux-prototype.src_root', 'resources/js'), '/').'/pages/';
            $router = (string) config('beam-ux-prototype.router', 'resources/js/pages/_prototype.tsx');
            $page = str_starts_with($router, $pagesRoot)
                ? preg_replace('/\.tsx?$/', '', substr($router, strlen($pagesRoot)))
                : '_prototype';

            Route::get('/_prototype/{any?}', fn () => Inertia::render($page))->where('any', '.*');
        }

        // Register the wiring audit (advisory) DOWN into the beam-doctor aggregation manifest.
        if ($this->app->bound(BeamDoctorManifest::class)) {
            $this->app->make(BeamDoctorManifest::class)->register(
                package: 'splicewire/laravel-beam-ux-prototype',
                audit: PrototypeWiringAudit::class,
                gate: false,
            );
        }

        // Register the install step DOWN into the beam-install manifest (config + scaffold tags).
        if ($this->app->bound(BeamInstallManifest::class)) {
            $this->app->make(BeamInstallManifest::class)->register(
                package: 'splicewire/laravel-beam-ux-prototype',
                publishTags: ['beam-ux-prototype-config', 'beam-ux-prototype-scaffold'],
            );
        }
    }
}
