<?php

declare(strict_types=1);

namespace Splicewire\Beam\UxPrototype\Console;

use Illuminate\Console\Command;
use Rushing\Doctor\DoctorStatus;
use Rushing\Doctor\Finding;
use Splicewire\Beam\UxPrototype\Doctor\PrototypeWiringAudit;

/**
 * `php artisan splicewire:prototype:doctor` — audit that the host's rushing-prototype wiring is intact
 * (dep, router glob under a DEV guard, CSS token contract, prod-boundary script). Mirrors
 * `PublishingDoctorCommand` / `CommerceDoctorCommand`: instantiate the audit, render each Finding, fail
 * the exit code on any Fail. Also registered — advisory — into `BeamDoctorManifest` so one
 * `splicewire:beam:doctor` run aggregates it with the rest of the family.
 *
 * `--boundary` adds the on-demand build check: it shells out to the JS `verify-prototype-boundary` bin
 * (the only check that needs a bundler) and folds its result into the exit code.
 */
class PrototypeDoctorCommand extends Command
{
    protected $signature = 'splicewire:prototype:doctor {--boundary : Also run the prod-boundary build (shells out to verify-prototype-boundary; slow)}';

    protected $description = 'Audit the host rushing-prototype wiring: dependency, router glob + DEV guard, CSS token contract, prod-boundary script.';

    public function handle(PrototypeWiringAudit $audit): int
    {
        $failed = false;

        $findings = $audit->run();

        if ((bool) $this->option('boundary')) {
            $findings[] = $audit->boundaryBuild();
        }

        foreach ($findings as $finding) {
            $this->render($finding);
            $failed = $failed || $finding->status === DoctorStatus::Fail;
        }

        if ($failed) {
            $this->newLine();
            $this->components->error('Prototype wiring has gaps — see failures above (install stamps stubs; the router glob / token block / boundary script are host edits).');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function render(Finding $finding): void
    {
        match ($finding->status) {
            DoctorStatus::Pass => $this->components->info($finding->check.': '.$finding->detail),
            DoctorStatus::Warn => $this->components->warn($finding->check.': '.$finding->detail),
            DoctorStatus::Fail => $this->components->error($finding->check.': '.$finding->detail),
        };
    }
}
