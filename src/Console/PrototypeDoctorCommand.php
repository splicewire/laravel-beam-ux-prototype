<?php

declare(strict_types=1);

namespace Splicewire\Beam\UxPrototype\Console;

use Illuminate\Console\Command;
use Rushing\Doctor\Concerns\RunsDoctorFloor;
use Rushing\Doctor\DoctorRegistration;
use Rushing\Doctor\DoctorRunner;
use Splicewire\Beam\UxPrototype\Doctor\PrototypeWiringAudit;

/**
 * `php artisan splicewire:beam:ux:prototype:doctor` — audit that the host's rushing-prototype wiring is intact
 * (dep, router glob under a DEV guard, CSS token contract, prod-boundary script). Mirrors
 * `CommerceDoctorCommand`: the wiring audit runs through the shared {@see DoctorRunner} as a gate
 * registration at the `--floor` (default `fail`; particle-doctrine-followups ticket 06), and each
 * Finding renders through {@see RunsDoctorFloor}'s `<check>: <detail>` lines — the same lines this
 * command always printed; the shared DoctorRenderer is deliberately not adopted.
 * The same audit is also registered — advisory — into `BeamDoctorManifest` so one
 * `splicewire:beam:doctor` run aggregates it with the rest of the family; that is a second COMMAND,
 * not a second report — this command hands the runner only its own registration, never the manifest.
 *
 * `--boundary` adds the on-demand build check: it shells out to the JS `beam-verify-prototype-boundary` bin
 * (the only check that needs a bundler) and folds its result into the exit code. It stays outside the
 * runner — it is option-gated and produced by a method call, not the audit's `run()` — but honours the
 * same floor.
 */
class PrototypeDoctorCommand extends Command
{
    use RunsDoctorFloor;

    protected $signature = 'splicewire:beam:ux:prototype:doctor
        {--boundary : Also run the prod-boundary build (shells out to beam-verify-prototype-boundary; slow)}
        {--floor=fail : Severity a finding must reach to fail the run (pass|warn|fail)}';

    protected $description = 'Audit the host rushing-prototype wiring: dependency, router glob + DEV guard, CSS token contract, prod-boundary script.';

    public function handle(PrototypeWiringAudit $audit, DoctorRunner $runner): int
    {
        $floor = $this->parseFloor();

        if ($floor === null) {
            return self::FAILURE;
        }

        [$report, $failed] = $this->runAtFloor($runner, [
            new DoctorRegistration('splicewire/laravel-beam-ux-prototype', PrototypeWiringAudit::class, gate: true),
        ], $floor);

        $findings = $report->findings;

        if ((bool) $this->option('boundary')) {
            $boundary = $audit->boundaryBuild();
            $findings[] = $boundary;
            $failed = $failed || $boundary->status->atLeast($floor);
        }

        $this->renderFindings($findings);

        if ($failed) {
            $this->newLine();
            $this->components->error('Prototype wiring has gaps — see failures above (install stamps stubs; the router glob / token block / boundary script are host edits).');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
