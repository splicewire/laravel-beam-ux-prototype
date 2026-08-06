<?php

namespace Splicewire\Beam\UxPrototype;

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
 *  - ships the standalone `splicewire:beam:ux:prototype:{doctor,install}` commands.
 *
 * Both manifest registrations are guarded by `bound(...)` (the notifications-twin precedent) so the
 * package still boots in a host that predates the manifests.
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
        // The scaffold the InstallStep stamps: a starter prototype, the host-owned nav.ts, and the
        // convention TEMPLATE (placeholders intact — `splicewire:beam:ux:prototype:install` binds it to a host
        // instance; a raw `vendor:publish` cannot substitute, so splicewire:beam:install lands the template form).
        if ($this->app->runningInConsole()) {
            $prototypeDir = (string) config('beam-ux-prototype.prototype_dir', 'ui/src/_prototype');

            $this->publishes([
                __DIR__.'/../stubs/starter-prototype.tsx.stub' => $this->app->basePath($prototypeDir.'/starter/01-starter.tsx'),
                __DIR__.'/../stubs/nav.ts.stub' => $this->app->basePath($prototypeDir.'/_chrome/nav.ts'),
                __DIR__.'/../stubs/rushing-prototype.convention.template.md' => $this->app->basePath('docs/agents/rushing-prototype.convention.template.md'),
            ], 'beam-ux-prototype-scaffold');
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
