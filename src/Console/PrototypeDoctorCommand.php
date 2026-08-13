<?php

declare(strict_types=1);

namespace Splicewire\Beam\UxPrototype\Console;

use Illuminate\Console\Command;
use Rushing\Doctor\DoctorFailed;
use Rushing\Doctor\DoctorRegistration;
use Rushing\Doctor\DoctorRunner;
use Rushing\Doctor\DoctorStatus;
use Rushing\Doctor\Finding;
use Splicewire\Beam\UxPrototype\Doctor\PrototypeWiringAudit;

/**
 * `php artisan splicewire:beam:ux:prototype:doctor` — audit that the host's rushing-prototype wiring is intact
 * (dep, router glob under a DEV guard, CSS token contract, prod-boundary script). Mirrors
 * `CommerceDoctorCommand`: the wiring audit runs through the shared {@see DoctorRunner} as a gate
 * registration at the `--floor` (default `fail`; particle-doctrine-followups ticket 06), and each
 * Finding renders through this command's own lines — the shared renderer is deliberately not adopted.
 * The same audit is also registered — advisory — into `BeamDoctorManifest` so one
 * `splicewire:beam:doctor` run aggregates it with the rest of the family; that is a second COMMAND,
 * not a second report — this command hands the runner only its own registration, never the manifest.
 *
 * `--boundary` adds the on-demand build check: it shells out to the JS `verify-prototype-boundary` bin
 * (the only check that needs a bundler) and folds its result into the exit code. It stays outside the
 * runner — it is option-gated and produced by a method call, not the audit's `run()` — but honours the
 * same floor.
 */
class PrototypeDoctorCommand extends Command
{
    protected $signature = 'splicewire:beam:ux:prototype:doctor
        {--boundary : Also run the prod-boundary build (shells out to verify-prototype-boundary; slow)}
        {--floor=fail : Severity a finding must reach to fail the run (pass|warn|fail)}';

    protected $description = 'Audit the host rushing-prototype wiring: dependency, router glob + DEV guard, CSS token contract, prod-boundary script.';

    public function handle(PrototypeWiringAudit $audit, DoctorRunner $runner): int
    {
        $floor = DoctorStatus::tryFrom(strtolower((string) $this->option('floor')));

        if ($floor === null) {
            $this->components->error('Invalid --floor value; expected one of: pass, warn, fail.');

            return self::FAILURE;
        }

        $failed = false;

        try {
            $report = $runner->run([
                new DoctorRegistration('splicewire/laravel-beam-ux-prototype', PrototypeWiringAudit::class, gate: true),
            ], $floor);
        } catch (DoctorFailed $failure) {
            $report = $failure->report;
            $failed = true;
        }

        $findings = $report->findings;

        if ((bool) $this->option('boundary')) {
            $boundary = $audit->boundaryBuild();
            $findings[] = $boundary;
            $failed = $failed || $boundary->status->atLeast($floor);
        }

        foreach ($findings as $finding) {
            $this->render($finding);
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
