<?php

namespace Splicewire\Beam\UxPrototype\Tests;

use Illuminate\Contracts\Console\Kernel;
use Splicewire\Beam\Doctor\BeamDoctorManifest;
use Splicewire\Beam\Install\BeamInstallManifest;
use Splicewire\Beam\UxPrototype\Doctor\PrototypeWiringAudit;

/**
 * The twin self-registers DOWN into beam-core's two manifests and ships its standalone commands. Proves
 * the twin is the first consumer citizen of BeamDoctorManifest (advisory) and an install-manifest step.
 */
class PackageBootTest extends TestCase
{
    public function test_commands_are_registered(): void
    {
        $all = $this->app[Kernel::class]->all();

        $this->assertArrayHasKey('splicewire:beam:ux:prototype:doctor', $all);
        $this->assertArrayHasKey('splicewire:beam:ux:prototype:install', $all);
    }

    public function test_wiring_audit_registers_advisory_into_the_doctor_manifest(): void
    {
        $registrations = $this->app->make(BeamDoctorManifest::class)->registrations();

        $ours = array_values(array_filter(
            $registrations,
            static fn ($r) => $r->audit === PrototypeWiringAudit::class,
        ));

        $this->assertCount(1, $ours, 'the wiring audit should be registered exactly once');
        $this->assertFalse($ours[0]->gate, 'the wiring audit is report-only (advisory)');
        $this->assertSame('splicewire/laravel-beam-ux-prototype', $ours[0]->package);
    }

    public function test_install_step_registers_into_the_install_manifest(): void
    {
        $steps = $this->app->make(BeamInstallManifest::class)->steps();

        $ours = array_values(array_filter(
            $steps,
            static fn ($s) => $s->package === 'splicewire/laravel-beam-ux-prototype',
        ));

        $this->assertCount(1, $ours);
        $this->assertContains('beam-ux-prototype-scaffold', $ours[0]->publishTags);
    }

    public function test_the_wiring_audit_resolves_from_the_container(): void
    {
        $this->assertInstanceOf(PrototypeWiringAudit::class, $this->app->make(PrototypeWiringAudit::class));
    }
}
