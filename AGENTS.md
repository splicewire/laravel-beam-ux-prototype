> You are in **splicewire/laravel-beam-ux-prototype** — the Laravel twin of `@splicewire/beam-ux-prototype`, providing install + doctor for the rushing-prototype UX-prototyping mechanism in a beam Laravel host.

A Laravel package that stamps the convention doc + starter stubs into a beam host and audits the
host's wiring (dependency, router glob under a DEV guard, CSS token contract, prod-boundary
script). The prototyping runtime itself stays JS-only in the npm twin; this package owns only the
install/doctor return.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
