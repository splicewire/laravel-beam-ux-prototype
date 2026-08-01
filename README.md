# laravel-beam-ux-prototype

The **Laravel twin** of [`@splicewire/beam-ux-prototype`](https://www.npmjs.com/package/@splicewire/beam-ux-prototype) —
install + doctor for the **rushing-prototype** UX-prototyping mechanism in a beam Laravel host.

The prototyping *runtime* (the glob→route mount, the generic chrome, the prod-boundary build) is
**JS-only** and stays in the npm package; per ADR-0116 Amendment 1 there is no PHP runtime here. This
twin exists because **install + doctor** is a real return only PHP serves well: one command that stamps
the prototype scaffold into a host, and a standing audit that the host's wiring is intact. It is the
**first consumer citizen** of beam-core's `BeamDoctorManifest`.

## What it does

- **`Doctor\PrototypeWiringAudit`** (report-only) — static file/JSON inspection that a host is wired:
  - `@splicewire/beam-ux-prototype` is a dependency in `ui/package.json`;
  - `createPrototypeRoutes(import.meta.glob(...))` is present under an intact `import.meta.env.DEV` guard;
  - the PrototypeDesk CSS token contract (`--sidebar*`, `--dotted-dot`) is defined in the host `:root`;
  - a `verify:prod-boundary` script + a `prototype.outDir` key are wired.
  - **`--boundary` (on-demand):** shells out to the JS `verify-prototype-boundary` bin — the one check
    that needs a bundler. It is **not** reimplemented in PHP.
- **`splicewire:prototype:doctor`** — runs the audit standalone (fails its exit code on a gap).
- **`splicewire:prototype:install`** — publishes the starter prototype + `nav.ts` scaffold, materializes
  a **host-bound** instance of the convention doctrine (reads the canonical template shipped in the JS
  package, fills the host placeholders), and prints the manual wiring the doctor checks.
- **Manifest registrations** — the audit registers **advisory** into `BeamDoctorManifest` (so one
  `splicewire:beam:doctor` aggregates it) and an install step into `BeamInstallManifest` (so `splicewire:beam:install`
  stamps the scaffold).

## Install

```bash
composer require splicewire/laravel-beam-ux-prototype
php artisan splicewire:prototype:install     # scaffold + host-bound convention doc + wiring checklist
php artisan splicewire:prototype:doctor       # confirm the wiring (add --boundary for the build check)
```

Config (`config/beam-ux-prototype.php`, publishable) points at the host UI paths (`ui/`, the router file,
`index.css`, the prototype dir), the brand import injected into the desk wrapper, and the required token
list. Override per host.

## The seam (ADR-0116 Amendment 1)

The lazy-twin rule tests a **named return**, not "runtime only": a twin is warranted the moment it has a
return the other side can't serve. The runtime stays JS; this twin owns only install + doctor. See the
amendment in the splicewire-app ADR corpus.
