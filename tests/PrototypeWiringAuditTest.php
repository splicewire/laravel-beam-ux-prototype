<?php

namespace Splicewire\Beam\UxPrototype\Tests;

use PHPUnit\Framework\TestCase;
use Rushing\Doctor\DoctorStatus;
use Rushing\Doctor\Finding;
use Splicewire\Beam\UxPrototype\Doctor\PrototypeWiringAudit;

/**
 * Pure static-inspection tests for the wiring audit — no Laravel boot: the audit reads files under a
 * fixture base and returns {@see Finding}s. Exercises each of the four checks green and red. The
 * on-demand `boundaryBuild()` (which shells out) is deliberately not exercised here.
 */
class PrototypeWiringAuditTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/beam-ux-proto-audit-'.uniqid();
        @mkdir($this->base.'/ui/src/app', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->base);
        parent::tearDown();
    }

    private function config(): array
    {
        return require __DIR__.'/../config/beam-ux-prototype.php';
    }

    private function audit(): PrototypeWiringAudit
    {
        return new PrototypeWiringAudit($this->base, $this->config());
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function finding(array $findings, string $check): Finding
    {
        foreach ($findings as $f) {
            if ($f->check === $check) {
                return $f;
            }
        }
        $this->fail("no finding for check '{$check}'");
    }

    private function writeFullyWiredHost(): void
    {
        file_put_contents($this->base.'/ui/package.json', json_encode([
            'dependencies' => [PrototypeWiringAudit::PACKAGE => '^0.1.0'],
            'scripts' => ['beam:verify-prototype-boundary' => 'beam-verify-prototype-boundary'],
            'prototype' => ['outDir' => '../public/ui'],
        ]));
        file_put_contents(
            $this->base.'/ui/src/app/router.tsx',
            "export const router = createBrowserRouter([\n  ...(import.meta.env.DEV ? createPrototypeRoutes(import.meta.glob('../_prototype/**/*.tsx')) : []),\n]);\n",
        );
        file_put_contents(
            $this->base.'/ui/src/index.css',
            ":root {\n --sidebar-deep: #111; --sidebar-foreground: #eee; --sidebar-active-foreground: #fff;\n --sidebar-accent: #222; --sidebar-primary: #3b82f6; --sidebar-avatar: #444; --dotted-dot: #333;\n}\n",
        );
    }

    public function test_a_fully_wired_host_passes_every_static_check(): void
    {
        $this->writeFullyWiredHost();

        foreach ($this->audit()->run() as $finding) {
            $this->assertSame(DoctorStatus::Pass, $finding->status, "expected {$finding->check} to pass: {$finding->detail}");
        }
    }

    public function test_missing_dependency_fails(): void
    {
        $this->writeFullyWiredHost();
        file_put_contents($this->base.'/ui/package.json', json_encode([
            'scripts' => ['beam:verify-prototype-boundary' => 'beam-verify-prototype-boundary'],
            'prototype' => ['outDir' => '../public/ui'],
        ]));

        $this->assertSame(DoctorStatus::Fail, $this->finding($this->audit()->run(), 'dependency')->status);
    }

    public function test_router_glob_without_dev_guard_fails(): void
    {
        $this->writeFullyWiredHost();
        // createPrototypeRoutes present, but the DEV guard stripped — prototypes could leak into prod.
        file_put_contents(
            $this->base.'/ui/src/app/router.tsx',
            "export const router = createBrowserRouter([\n  ...createPrototypeRoutes(import.meta.glob('../_prototype/**/*.tsx')),\n]);\n",
        );

        $finding = $this->finding($this->audit()->run(), 'router-glob');
        $this->assertSame(DoctorStatus::Fail, $finding->status);
    }

    public function test_absent_router_glob_fails(): void
    {
        $this->writeFullyWiredHost();
        file_put_contents($this->base.'/ui/src/app/router.tsx', "export const router = createBrowserRouter([]);\n");

        $this->assertSame(DoctorStatus::Fail, $this->finding($this->audit()->run(), 'router-glob')->status);
    }

    public function test_missing_css_tokens_fails_and_names_them(): void
    {
        $this->writeFullyWiredHost();
        file_put_contents($this->base.'/ui/src/index.css', ":root { --sidebar-deep: #111; }\n");

        $finding = $this->finding($this->audit()->run(), 'css-tokens');
        $this->assertSame(DoctorStatus::Fail, $finding->status);
        $this->assertStringContainsString('--dotted-dot', $finding->detail);
    }

    public function test_missing_boundary_script_or_outdir_fails(): void
    {
        $this->writeFullyWiredHost();
        file_put_contents($this->base.'/ui/package.json', json_encode([
            'dependencies' => [PrototypeWiringAudit::PACKAGE => '^0.1.0'],
            // no scripts, no prototype.outDir
        ]));

        $this->assertSame(DoctorStatus::Fail, $this->finding($this->audit()->run(), 'boundary-script')->status);
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
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
