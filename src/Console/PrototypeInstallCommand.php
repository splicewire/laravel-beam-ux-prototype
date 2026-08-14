<?php

declare(strict_types=1);

namespace Splicewire\Beam\UxPrototype\Console;

use Illuminate\Console\Command;
use Splicewire\Beam\UxPrototype\BeamUxPrototypeServiceProvider;

/**
 * `php artisan splicewire:beam:ux:prototype:install` — stamp what a package CAN stamp and name what it can't.
 *
 * It (1) publishes the starter prototype + `nav.ts` scaffold stubs (the `beam-ux-prototype-scaffold`
 * tag `splicewire:beam:install` also runs), and (2) materializes a HOST-BOUND instance of the convention doctrine:
 * it reads the canonical template — preferring the ONE source shipped in the JS package at
 * `{package_root}/node_modules/@splicewire/beam-ux-prototype/convention/…` (falling back to the copy
 * bundled here) — fills the `{{…}}` host placeholders from config, and writes
 * `docs/agents/rushing-prototype.convention.md`.
 *
 * For the monolith default (Inertia, no top-level client router of its own), the router glob is also
 * stamped/auto-registered — see {@see BeamUxPrototypeServiceProvider}.
 * What's left manual either way: the `:root` CSS token block and the `beam:verify-prototype-boundary`
 * script — both printed here, and both audited by the doctor.
 */
class PrototypeInstallCommand extends Command
{
    protected $signature = 'splicewire:beam:ux:prototype:install {--force : Overwrite an existing published convention doc / stubs}';

    protected $description = 'Scaffold the rushing-prototype stubs + a host-bound convention doc, and print the manual wiring the doctor checks.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $base = $this->laravel->basePath();

        $this->components->info('Publishing the rushing-prototype scaffold (starter prototype + nav.ts)…');
        $this->call('vendor:publish', array_filter([
            '--tag' => 'beam-ux-prototype-scaffold',
            '--force' => $force ?: null,
        ]));

        $this->materializeConvention($base, $force);

        $this->newLine();
        $this->components->warn('Manual wiring a package cannot edit for you (the doctor audits each):');

        $routerPath = config('beam-ux-prototype.router', 'resources/js/pages/_prototype.tsx');
        $routerLine = (bool) config('beam-ux-prototype.register_route', true)
            ? "Router glob — auto-wired: `{$routerPath}` (published) + the dev-only `/_prototype/{any?}` Inertia route (auto-registered) mount the gallery. Nothing to edit."
            : "Router glob — add `...(import.meta.env.DEV ? createPrototypeRoutes(import.meta.glob('../_prototype/**/*.tsx')) : [])` to {$routerPath}.";

        $this->components->bulletList([
            $routerLine,
            'CSS tokens — define the PrototypeDesk token contract in '.config('beam-ux-prototype.tokens_css', 'resources/js/app.css').':root (see the package README table).',
            'Boundary script — add a `beam:verify-prototype-boundary` script (invoking `beam-verify-prototype-boundary`) + a `prototype.outDir` key to '.config('beam-ux-prototype.package_json', 'package.json').'.',
        ]);
        $this->components->info('Then run `php artisan splicewire:beam:ux:prototype:doctor` to confirm the wiring (add `--boundary` for the build check).');

        return self::SUCCESS;
    }

    /**
     * Read the canonical convention template (JS-package copy preferred; bundled copy as fallback),
     * fill the host placeholders, and write the host-bound instance.
     */
    private function materializeConvention(string $base, bool $force): void
    {
        $root = rtrim((string) config('beam-ux-prototype.package_root', '.'), '/');
        $nodeModules = $root === '.' ? 'node_modules' : "{$root}/node_modules";
        $jsSource = $base.'/'.$nodeModules.'/@splicewire/beam-ux-prototype/convention/rushing-prototype.convention.template.md';
        $bundled = __DIR__.'/../../stubs/rushing-prototype.convention.template.md';

        $templatePath = is_file($jsSource) ? $jsSource : $bundled;
        $template = @file_get_contents($templatePath);

        if ($template === false) {
            $this->components->warn('Could not read the convention template — skipping the host-bound doc (run the JS package install first).');

            return;
        }

        $prototypeDir = (string) config('beam-ux-prototype.prototype_dir', 'resources/js/_prototype');
        $bound = strtr($template, [
            '{{PROTOTYPE_DIR}}' => $prototypeDir,
            '{{BRAND_IMPORT}}' => (string) config('beam-ux-prototype.brand_import', '@/components/brand/BrandLockup'),
            '{{DATE}}' => date('Y-m-d'),
            // relative form used inside the glob string in the doc body
            '{{PROTOTYPE_DIR relative}}' => '../_prototype',
        ]);

        $target = $base.'/docs/agents/rushing-prototype.convention.md';

        if (is_file($target) && ! $force) {
            $this->components->warn('docs/agents/rushing-prototype.convention.md exists — pass --force to overwrite (kept the existing host copy).');

            return;
        }

        @mkdir(dirname($target), 0777, true);
        file_put_contents($target, $bound);
        $this->components->info('Wrote host-bound docs/agents/rushing-prototype.convention.md (source: '.($templatePath === $jsSource ? 'JS package' : 'bundled fallback').').');
    }
}
