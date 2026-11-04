# Frictionless Hub: project notes

These notes are maintained for anyone (human or AI agent) picking up this codebase on another machine.

## What this is

A Laravel 12 + Inertia 2 + React 19 app for running a developer-experience program, structured around the step order of the book *Frictionless* and its workbook. See `docs/SPEC.md` for the full plan and `README.md` for user-facing setup.

## Content policy (non-negotiable)

- No text from the book or workbook goes into code, migrations, seeders, fixtures, tests or guide files. That includes templates, questions, rubric criteria, examples and email copy.
- The code provides generic, configurable tools; admins enter their own content.
- Guide text in `resources/guide/*.md` and the Larkspur scenario are original writing. Keep any new content original.

## "Set this up on my computer" (procedure for AI agents)

Follow these steps in order, and report each result to the user.

1. **Check prerequisites.** Run `docker compose version` and `make --version`.
   - Both work → **Docker path** (preferred; matches production).
   - Otherwise check `php -v` (needs 8.4+), `composer -V` and `node -v` (22+) → **native path**.
   - Neither → tell the user to install Docker Desktop or OrbStack (macOS/Windows) or Docker Engine (Linux), then stop.
2. **Check ports** 8000, 5173, 5432 and 8025 (e.g. `lsof -iTCP -sTCP:LISTEN -P | grep -E ':(8000|5173|5432|8025) '`). If one is taken, prefix commands with `APP_PORT=`, `VITE_PORT=`, `FORWARD_DB_PORT=` or `FORWARD_MAILPIT_DASHBOARD_PORT=`.
3. **Run setup.**
   - Docker: `make setup`. This builds images, starts Postgres/Redis/Mailpit/app/queue/vite, runs `composer install` in the container, creates `.env` if missing, generates `APP_KEY`, runs `migrate:fresh --seed` and builds assets.
   - Native: `composer setup` (SQLite).
   - Either one **wipes the local database**. On a machine that already has data, ask before running it.
4. **Verify.** `curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/up` should print `200`. Then run `make test` (or `composer test`) and confirm it passes.
5. **Hand over.** Open http://localhost:8000 and sign in as `priya@larkspur.test` with password `password` (the facilitator who owns the Larkspur benchmark initiative). Admin is `admin@example.com`.

Never commit `.env`, and never copy it from another machine.

## Commands

| Docker (`make …`) | Native (`composer …`) | What it does |
|---|---|---|
| `setup` | `setup` | First-run setup (see above). Wipes the local DB |
| `up` / `down` / `destroy` | `run dev` | Start / stop / stop and delete volumes. Native `dev` runs server, queue, pail and Vite |
| `fresh` | `php artisan migrate:fresh --seed` | Reset to demo data |
| `test` | `test` | Unit + Feature, SQLite in memory |
| `test-pgsql` | n/a | Unit + Feature against the Postgres `testing` database |
| n/a | `test:browser` | Browser suite. Runs natively and in CI (needs `npx playwright install chromium` once), not inside the app container |
| `lint` | `lint` | pint, prettier, eslint, tsc (read-only checks) |
| `prod-build` / `prod-up` / `prod-smoke` / `prod-down` | n/a | Build and run the production image like Kubernetes (migrate job, web, worker, scheduler) on :8090 |
| `shell`, `artisan cmd="…"`, `logs` | n/a | Utilities |

## Docker layout

- `Dockerfile` has targets `development` (source bind-mounted, host UID/GID mapped, `sockets` extension for Pest browser) and `production` (non-root uid 33, port 8080, OPcache on, caches built at startup, no migrations at startup). Base image `serversideup/php:8.4-fpm-nginx-vX`, pinned.
- `compose.yaml` is the dev stack. Values under `environment` override `.env`, because Laravel never overwrites real environment variables. The Vite service keeps its Linux `node_modules` in a named volume, and `VITE_IN_DOCKER=true` switches `vite.config.js` to listen on 0.0.0.0 with HMR via localhost.
- `compose.production.yaml` mirrors the Kubernetes topology. `docs/DEPLOYMENT.md` documents the runtime contract (env vars, roles, probes).
- **Test isolation:** Docker exposes container env vars through `$_SERVER`, which Laravel reads first. `phpunit.xml` / `phpunit.pgsql.xml` therefore set both `<env force="true">` and `<server>`, and `tests/TestCase.php` aborts if the database isn't `:memory:` or `testing`. Don't remove either safeguard. Without them, a test run once wiped the dev database.

## Testing conventions

- The runner is **Pest 4** (PHPUnit 12). Pest 5 needs Laravel 13, so it isn't used. Existing tests are PHPUnit-style classes; browser tests use Pest function syntax.
- `tests/Unit`: pure logic plus `ArchTest.php` (naming, no debug calls, no `env()` in `app/`).
- `tests/Feature`: every endpoint. Cover auth, role permissions, isolation between initiatives (another initiative's record returns **404**), validation messages and saved data.
- `tests/Browser`: user journeys. Select elements with `@data-test` hooks, never visible text, because the docked guide panel repeats many page titles. Browser steps time out after 10s (`tests/Pest.php`).
- `BenchmarkScenarioTest` and `tests/Browser/SmokeTest.php` iterate over `App\Support\Walkthrough`, so a new activity is smoke-tested as soon as it has a route.
- CI (`.github/workflows/ci.yml`) runs on every push: lint; Unit+Feature on SQLite (with coverage, fails below 85%) and on Postgres; Browser (uploads failure screenshots); and a production-image build plus smoke test.

## Adding a walkthrough activity

1. Set its `route` in `App\Support\Walkthrough::activities()`. The order follows the workbook steps.
2. Write the controller under `App\Http\Controllers\Initiatives`, authorizing through `InitiativePolicy` (`view` to read, `update` to change). Use scoped route bindings under `initiatives/{initiative}`.
3. Write the page in `resources/js/pages/initiatives/` using `InitiativeLayout`, with `data-test` hooks for anything a browser test touches.
4. Replace the placeholder in `resources/guide/{key}.md` with original guide text.
5. Add a section to `Database\Seeders\LarkspurScenarioSeeder` (the fictional benchmark company) and a progress count in `InitiativeController::progress()`.
6. Add Feature tests (endpoints) and a Browser journey test.

## Roles

`admin` manages everything, including guide text and user roles. `facilitator` creates and manages their own initiatives. `viewer` is read-only, but can add their own ratings. The first registered user becomes admin.
