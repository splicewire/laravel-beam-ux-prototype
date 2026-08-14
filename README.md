# laravel-beam-ux-prototype

The **Laravel twin** of [`@splicewire/beam-ux-prototype`](https://www.npmjs.com/package/@splicewire/beam-ux-prototype) —
install + doctor for the **rushing-prototype** UX-prototyping mechanism in a beam Laravel host.

The prototyping *runtime* (the glob→route mount, the generic chrome, the prod-boundary build) is
**JS-only** and stays in the npm package; per ADR-0116 Amendment 1 there is no PHP runtime here. This
twin exists because **install + doctor** is a real return only PHP serves well: one command that stamps
the prototype scaffold into a host, and a standing audit that the host's wiring is intact. It is the
**first consumer citizen** of beam-core's `BeamDoctorManifest`.

## Two host shapes

Every path in `config/beam-ux-prototype.php` resolves under two roots — `package_root` (where
`package.json` lives, and the CWD npm commands run from) and `src_root` (where the JS/TS source
tree lives). They're almost always the same directory for a genuine SPA, but **not** for a
Laravel-Inertia monolith — which is why they're two separate config keys, not one shared `ui_path`.

|  | **monolith** (default) | **SPA** |
| --- | --- | --- |
| Who | audiostud, the starter, any beam-pilot-* instance — the norm | a self-contained npm package embedded in the repo, e.g. splicewire-app |
| `package_root` | `.` (repo root) | its own subdir — conventionally **`spa`**, not `ui` (`ui` reads as "the frontend dir" generically and is ambiguous with the monolith's own `resources/js`) |
| `src_root` | `resources/js` (Laravel's own default) | `spa/src` |
| Router glob | **auto-wired** — Inertia has no top-level client router of its own to spread the glob into, so the install step stamps a dev-only Inertia host page (`resources/js/pages/_prototype.tsx`, a small **nested** React Router scoped to `/_prototype/*`) and the package auto-registers the matching `/_prototype/{any?}` Laravel route. Nothing to hand-edit. | manual — the host spreads `createPrototypeRoutes(import.meta.glob(...))` into its own top-level router by hand (`register_route: false`) |

## What it does

- **`Doctor\PrototypeWiringAudit`** (report-only) — static file/JSON inspection that a host is wired:
  - `@splicewire/beam-ux-prototype` is a dependency in the host `package.json`;
  - `createPrototypeRoutes(import.meta.glob(...))` is present under an intact `import.meta.env.DEV` guard;
  - the PrototypeDesk CSS token contract (`--sidebar*`, `--dotted-dot`) is defined in the host `:root`;
  - a `beam:verify-prototype-boundary` script + a `prototype.outDir` key are wired.
  - **`--boundary` (on-demand):** shells out to the JS `beam-verify-prototype-boundary` bin — the one check
    that needs a bundler. It is **not** reimplemented in PHP.
- **`splicewire:beam:ux:prototype:doctor`** — runs the audit standalone (fails its exit code on a gap).
- **`splicewire:beam:ux:prototype:install`** — publishes the starter prototype + `nav.ts` scaffold,
  materializes a **host-bound** instance of the convention doctrine (reads the canonical template
  shipped in the JS package, fills the host placeholders), and — monolith shape — also publishes the
  Inertia host page (route auto-registers separately, from the service provider, since it's not a
  publishable file). Prints whatever manual wiring is left (CSS tokens + boundary script always; the
  router glob too, for an SPA host).
- **Manifest registrations** — the audit registers **advisory** into `BeamDoctorManifest` (so one
  `splicewire:beam:doctor` aggregates it) and an install step into `BeamInstallManifest` (so `splicewire:beam:install`
  stamps the scaffold).

## Install

```bash
composer require splicewire/laravel-beam-ux-prototype
php artisan splicewire:beam:ux:prototype:install     # scaffold + host-bound convention doc + wiring checklist
php artisan splicewire:beam:ux:prototype:doctor       # confirm the wiring (add --boundary for the build check)
```

Config (`config/beam-ux-prototype.php`, publishable) defaults to the monolith shape. An SPA host
overrides `package_root`/`src_root`/`package_json`/`router`/`tokens_css`/`prototype_dir` to its own
`spa/` layout and sets `register_route: false` — see "Two host shapes" above.

## The seam (ADR-0116 Amendment 1)

The lazy-twin rule tests a **named return**, not "runtime only": a twin is warranted the moment it has a
return the other side can't serve. The runtime stays JS; this twin owns only install + doctor. See the
amendment in the splicewire-app ADR corpus.
