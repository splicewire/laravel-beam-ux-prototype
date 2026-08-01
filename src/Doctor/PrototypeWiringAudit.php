<?php

namespace Splicewire\Beam\UxPrototype\Doctor;

use Illuminate\Support\Facades\Process;
use Rushing\Doctor\Finding;
use Rushing\Doctor\DoctorAudit;

/**
 * Report-only audit that a beam host's rushing-prototype wiring is intact (beam-ux-prototype-extract
 * ticket 08). Follows the publishing-audit doctrine — it "surfaces exactly the things `install` cannot
 * do for you": the router glob, the `:root` token block, and the boundary script are host edits an
 * `InstallStep` can't stamp, so the doctor names them until they're in place.
 *
 * Every check in {@see run()} is STATIC file/JSON inspection (the same shape as beam-core's
 * `McpIsolationAudit` / publishing's `PublishingReadinessAudit`) — no bundler, no browser. The ONE
 * check that needs a build — the prod-boundary tree-shake proof — is {@see boundaryBuild()}, kept OUT
 * of `run()` and invoked on-demand by the command's `--boundary` flag, and it does NOT reimplement the
 * check: it shells out to the existing JS `verify-prototype-boundary` bin and maps its exit to a Finding.
 */
class PrototypeWiringAudit implements DoctorAudit
{
    public const PACKAGE = '@splicewire/beam-ux-prototype';

    /**
     * @param  array<string, mixed>  $config  the resolved `beam-ux-prototype` config
     */
    public function __construct(
        private string $base,
        private array $config,
    ) {}

    /**
     * The static wiring checks — all report-only (advisory in the beam:doctor aggregate; the standalone
     * `splicewire:prototype:doctor` command still fails its own exit code on a Fail).
     *
     * @return list<Finding>
     */
    public function run(): array
    {
        $pkg = $this->packageJson();

        return [
            $this->dependency($pkg),
            $this->routerGlob(),
            $this->cssTokens(),
            $this->boundaryScript($pkg),
        ];
    }

    /** `@splicewire/beam-ux-prototype` is present in the host ui/package.json dependencies. */
    private function dependency(?array $pkg): Finding
    {
        if ($pkg === null) {
            return Finding::fail('dependency', $this->rel($this->str('package_json')).' is missing or unreadable.');
        }

        $deps = array_merge(
            (array) ($pkg['dependencies'] ?? []),
            (array) ($pkg['devDependencies'] ?? []),
        );

        return array_key_exists(self::PACKAGE, $deps)
            ? Finding::pass('dependency', self::PACKAGE.' is a host dependency ('.$deps[self::PACKAGE].').')
            : Finding::fail('dependency', self::PACKAGE.' is not in '.$this->rel($this->str('package_json')).' — run `npm i '.self::PACKAGE.'`.');
    }

    /**
     * The `createPrototypeRoutes(import.meta.glob(...))` call is wired under an intact
     * `import.meta.env.DEV` guard — grep, the same static-read shape as the existing audits.
     */
    private function routerGlob(): Finding
    {
        $carriers = $this->routerCandidates();

        $withCall = array_filter($carriers, static fn (string $src): bool => str_contains($src, 'createPrototypeRoutes'));

        if ($withCall === []) {
            return Finding::fail('router-glob', 'No `createPrototypeRoutes(...)` call found under '.$this->rel($this->str('ui_path')).'/src — the runtime is installed but not wired.');
        }

        foreach ($withCall as $src) {
            $hasGlob = str_contains($src, 'import.meta.glob');
            $hasGuard = str_contains($src, 'import.meta.env.DEV');

            if ($hasGlob && $hasGuard) {
                return Finding::pass('router-glob', '`createPrototypeRoutes(import.meta.glob(...))` is wired under an `import.meta.env.DEV` guard.');
            }
        }

        return Finding::fail('router-glob', '`createPrototypeRoutes` is present but its `import.meta.glob(...)` macro and/or `import.meta.env.DEV` guard is missing — prototypes could leak into prod (or never mount).');
    }

    /** The PrototypeDesk CSS token contract is defined in the host `:root`. */
    private function cssTokens(): Finding
    {
        $css = $this->read($this->str('tokens_css'));

        if ($css === null) {
            return Finding::fail('css-tokens', $this->rel($this->str('tokens_css')).' is missing — the PrototypeDesk token contract is undefined.');
        }

        /** @var list<string> $required */
        $required = (array) ($this->config['required_tokens'] ?? []);
        $missing = array_values(array_filter($required, static fn (string $t): bool => ! str_contains($css, $t)));

        return $missing === []
            ? Finding::pass('css-tokens', count($required).' PrototypeDesk tokens defined in '.$this->rel($this->str('tokens_css')).'.')
            : Finding::fail('css-tokens', 'Missing PrototypeDesk tokens in '.$this->rel($this->str('tokens_css')).': '.implode(', ', $missing).'.');
    }

    /** The `verify:prod-boundary` script + a `prototype.outDir` key are wired in ui/package.json. */
    private function boundaryScript(?array $pkg): Finding
    {
        if ($pkg === null) {
            return Finding::fail('boundary-script', $this->rel($this->str('package_json')).' is missing or unreadable.');
        }

        $script = (array) ($pkg['scripts'] ?? []);
        $hasScript = array_key_exists('verify:prod-boundary', $script);
        $hasOutDir = isset($pkg['prototype']['outDir']);

        if ($hasScript && $hasOutDir) {
            return Finding::pass('boundary-script', '`verify:prod-boundary` script + `prototype.outDir` ('.$pkg['prototype']['outDir'].') are wired.');
        }

        $gaps = [];
        if (! $hasScript) {
            $gaps[] = 'a `verify:prod-boundary` script invoking `verify-prototype-boundary`';
        }
        if (! $hasOutDir) {
            $gaps[] = 'a `prototype.outDir` key (the build output the CLI scans)';
        }

        return Finding::fail('boundary-script', 'Missing in '.$this->rel($this->str('package_json')).': '.implode(' and ', $gaps).'.');
    }

    /**
     * On-demand: shell out to the JS `verify-prototype-boundary` bin (the ONLY check needing a build)
     * and map its exit/output to a Finding. Not part of {@see run()} — the command opts in via
     * `--boundary`. Never reimplements the walk; the bin is the source of truth.
     */
    public function boundaryBuild(): Finding
    {
        $ui = $this->path($this->str('ui_path'));

        if (! is_dir($ui)) {
            return Finding::fail('boundary-build', $this->rel($this->str('ui_path')).' is not a directory — cannot run the boundary build.');
        }

        $result = Process::path($ui)->timeout(600)->run((string) ($this->config['boundary_command'] ?? 'npm run verify:prod-boundary'));

        if ($result->successful()) {
            return Finding::pass('boundary-build', 'verify-prototype-boundary passed — no prototype code survived the prod build.');
        }

        $tail = trim((string) ($result->errorOutput() ?: $result->output()));
        $tail = $tail === '' ? '(no output)' : mb_substr($tail, -400);

        return Finding::fail('boundary-build', 'verify-prototype-boundary failed (exit '.$result->exitCode().'): '.$tail);
    }

    /**
     * The router carrier sources to grep: the configured `router` file if present, else every
     * `.tsx`/`.ts` file under `ui/src` (bounded static scan). Returns file contents.
     *
     * @return list<string>
     */
    private function routerCandidates(): array
    {
        $configured = $this->read($this->str('router'));
        if ($configured !== null) {
            return [$configured];
        }

        $root = $this->path($this->str('ui_path').'/src');
        if (! is_dir($root)) {
            return [];
        }

        $sources = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (in_array($file->getExtension(), ['ts', 'tsx'], true)) {
                $contents = @file_get_contents($file->getPathname());
                if ($contents !== false) {
                    $sources[] = $contents;
                }
            }
        }

        return $sources;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function packageJson(): ?array
    {
        $raw = $this->read($this->str('package_json'));
        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function read(string $relative): ?string
    {
        $path = $this->path($relative);
        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    private function path(string $relative): string
    {
        return rtrim($this->base, '/').'/'.ltrim($relative, '/');
    }

    private function rel(string $relative): string
    {
        return ltrim($relative, '/');
    }

    private function str(string $key): string
    {
        return (string) ($this->config[$key] ?? '');
    }
}
