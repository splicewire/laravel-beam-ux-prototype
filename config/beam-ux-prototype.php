<?php

declare(strict_types=1);

return [
    /*
     | Where package.json lives + where npm commands (build, beam:verify-prototype-boundary) run
     | from. '.' is the default: a Laravel-Inertia MONOLITH — the norm for beam-tier satellites
     | (audiostud, the starter, any beam-pilot-* instance) — has one package.json at the repo root.
     | A genuine standalone SPA (its own npm package embedded in the repo, e.g. splicewire-app)
     | overrides this to its own subdir, conventionally `spa` — NOT `ui`; `ui` reads as "the frontend
     | dir" generically and was already ambiguous with the monolith's own resources/js.
     */
    'package_root' => '.',

    /*
     | Where the JS/TS source tree lives. For a monolith this is Laravel's own `resources/js` — no
     | nested package root. A genuine SPA's source lives inside its own package, e.g. `spa/src`.
     */
    'src_root' => 'resources/js',

    /*
     | The host package.json — carries the `@splicewire/beam-ux-prototype` dependency, the
     | `beam:verify-prototype-boundary` script, and the `prototype.outDir` key. Relative to the base path.
     */
    'package_json' => 'package.json',

    /*
     | Where the `createPrototypeRoutes(import.meta.glob(...))` wiring lives. Inertia has no top-level
     | client router of its own to spread the glob into (routing is server-driven, one page component
     | per Laravel route) — so the monolith default is a dev-only Inertia HOST PAGE the install step
     | stamps: a small, NESTED React Router scoped to `/_prototype/*`, using the same
     | createPrototypeRoutes mechanism a genuine SPA spreads into its own top-level router. Because
     | it's a net-new file (not an edit to host code the installer can't safely touch), the package
     | auto-registers the matching route too — see `register_route` below. The audit greps this file
     | (and, if absent there, scans `src_root` for the call) for the glob macro under an intact
     | `import.meta.env.DEV` guard — the load-bearing seam the runtime cannot own.
     */
    'router' => 'resources/js/pages/_prototype.tsx',

    /*
     | The host stylesheet that must define the PrototypeDesk CSS token contract in `:root`. The
     | beam starter kit's own Vite/Tailwind convention keeps CSS out of the JS tree entirely
     | (resources/css/, not resources/js/) — checked live against the starter, audiostud, and a
     | fresh pilot instance, which all agree.
     */
    'tokens_css' => 'resources/css/app.css',

    /*
     | The prototype root the InstallStep stamps its starter + nav.ts stubs into.
     */
    'prototype_dir' => 'resources/js/_prototype',

    /*
     | Auto-register the dev-only `/_prototype/{any?}` Laravel route + Inertia host page (monolith
     | shape only — a no-op, never registered, if `inertiajs/inertia-laravel` isn't installed). A
     | genuine SPA has its own top-level client router and needs no server route for this — set this
     | false there (the install step then prints the manual router-glob instruction instead).
     */
    'register_route' => true,

    /*
     | The host brand lockup the `_chrome` desk wrapper injects — filled into the published
     | host-bound convention instance so a new host knows which component to wire.
     */
    'brand_import' => '@/components/brand/BrandLockup',

    /*
     | The PrototypeDesk token contract (README source of truth). The audit fails if any is missing
     | from the host `:root`; `dotted-bg` is a utility that reads `--dotted-dot`.
     */
    'required_tokens' => [
        '--sidebar-deep',
        '--sidebar-foreground',
        '--sidebar-active-foreground',
        '--sidebar-accent',
        '--sidebar-primary',
        '--sidebar-avatar',
        '--dotted-dot',
    ],

    /*
     | The npm script the boundary build shells out to (on-demand `--boundary` check only).
     */
    'boundary_command' => 'npm run beam:verify-prototype-boundary',
];
