<?php

declare(strict_types=1);

namespace Splicewire\Beam\UxPrototype\Tests;

use Illuminate\Contracts\Console\Kernel;
use Rushing\Doctor\Finding;
use Splicewire\Beam\UxPrototype\Console\PrototypeDoctorCommand;
use Splicewire\Beam\UxPrototype\Doctor\PrototypeWiringAudit;

/**
 * The standalone `splicewire:beam:ux:prototype:doctor` command: it reports the wiring gaps until the host is
 * wired, then passes — the ticket's install→doctor acceptance, exercised through the real command.
 */
class PrototypeDoctorCommandTest extends TestCase
{
    private string $fixtureBase;

    protected function tearDown(): void
    {
        if (isset($this->fixtureBase) && is_dir($this->fixtureBase)) {
            $this->rrmdir($this->fixtureBase);
        }
        parent::tearDown();
    }

    public function test_it_is_registered(): void
    {
        $this->assertArrayHasKey('splicewire:beam:ux:prototype:doctor', $this->app[Kernel::class]->all());
    }

    public function test_an_unwired_host_reports_gaps_and_fails(): void
    {
        $this->pointBaseAt(wired: false);

        $this->artisan('splicewire:beam:ux:prototype:doctor')
            ->expectsOutputToContain('dependency')
            ->assertExitCode(PrototypeDoctorCommand::FAILURE);
    }

    public function test_a_fully_wired_host_passes(): void
    {
        $this->pointBaseAt(wired: true);

        $this->artisan('splicewire:beam:ux:prototype:doctor')->assertExitCode(PrototypeDoctorCommand::SUCCESS);
    }

    // ---- --floor (particle-doctrine-followups ticket 06) ----------------------------

    public function test_a_warning_passes_at_the_default_fail_floor(): void
    {
        $this->bindWarningAudit();

        $this->artisan('splicewire:beam:ux:prototype:doctor')
            ->expectsOutputToContain('stub-wiring')
            ->assertExitCode(PrototypeDoctorCommand::SUCCESS);
    }

    public function test_a_warning_fails_at_a_warn_floor(): void
    {
        $this->bindWarningAudit();

        $this->artisan('splicewire:beam:ux:prototype:doctor', ['--floor' => 'warn'])
            ->expectsOutputToContain('stub-wiring')
            ->assertExitCode(PrototypeDoctorCommand::FAILURE);
    }

    /** Bind a warn-emitting stand-in over the wiring audit so the floor is the only variable. */
    private function bindWarningAudit(): void
    {
        $this->app->instance(PrototypeWiringAudit::class, new class('', []) extends PrototypeWiringAudit
        {
            public function run(): array
            {
                return [Finding::warn('stub-wiring', 'stub audit is uneasy')];
            }
        });
    }

    public function test_beam_doctor_aggregates_the_prototype_audit(): void
    {
        // A clean beam contract (so beam-core's own gates pass) + wired prototype UI: one
        // `splicewire:beam:doctor` run renders the twin's advisory findings alongside beam-core's.
        $this->pointBaseAt(wired: true);
        file_put_contents($this->fixtureBase.'/composer.json', json_encode(['minimum-stability' => 'dev', 'prefer-stable' => true]));
        file_put_contents($this->fixtureBase.'/composer.lock', json_encode(['packages' => [['name' => 'vendor/pkg', 'dist' => ['type' => 'zip']]], 'packages-dev' => []]));
        file_put_contents($this->fixtureBase.'/BEAM.md', "---\nsatellite: fixture\nvariant: inertia-react\n---\n# Fixture\n");

        $this->artisan('splicewire:beam:doctor')
            ->expectsOutputToContain('beam-ux-prototype')
            ->assertExitCode(0);
    }

    private function pointBaseAt(bool $wired): void
    {
        $this->fixtureBase = sys_get_temp_dir().'/beam-ux-proto-cmd-'.uniqid();
        @mkdir($this->fixtureBase.'/resources/js/pages', 0777, true);
        @mkdir($this->fixtureBase.'/resources/css', 0777, true);

        if ($wired) {
            file_put_contents($this->fixtureBase.'/package.json', json_encode([
                'dependencies' => [PrototypeWiringAudit::PACKAGE => '^0.1.0'],
                'scripts' => ['beam:verify-prototype-boundary' => 'beam-verify-prototype-boundary'],
                'prototype' => ['outDir' => 'public/build'],
            ]));
            file_put_contents(
                $this->fixtureBase.'/resources/js/pages/_prototype.tsx',
                "...(import.meta.env.DEV ? createPrototypeRoutes(import.meta.glob('../_prototype/**/*.tsx')) : [])\n",
            );
            file_put_contents(
                $this->fixtureBase.'/resources/css/app.css',
                ":root { --sidebar-deep:#111;--sidebar-foreground:#eee;--sidebar-active-foreground:#fff;--sidebar-accent:#222;--sidebar-primary:#3b82f6;--sidebar-avatar:#444;--dotted-dot:#333; }\n",
            );
        } else {
            file_put_contents($this->fixtureBase.'/package.json', json_encode(['dependencies' => []]));
        }

        $this->app->setBasePath($this->fixtureBase);
        // Rebind the audit so it reads the fixture base (the binding closure captured the real base).
        $this->app->bind(PrototypeWiringAudit::class, fn ($app) => new PrototypeWiringAudit(
            $this->fixtureBase,
            (array) config('beam-ux-prototype', []),
        ));
    }

    private function rrmdir(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
