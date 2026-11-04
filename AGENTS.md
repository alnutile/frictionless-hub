<laravel-boost-guidelines>
=== .ai/frictionless-hub rules ===

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

## AI assistant

- Built on **`laravel/ai`** (the official Laravel AI SDK), using its `openrouter` driver. The agent is `App\Ai\Agents\GuideAssistant`.
- **Admin → AI assistant** (`/admin/ai`) stores the OpenRouter key, model and system prompt in the `settings` table (every value encrypted at rest). The key is never sent to the browser; the page only shows `••••` plus the last four characters. `App\Support\AiSettings::applyToRuntime()` loads it into config on each request. No `.env` entry is needed.
- The default system prompt is `resources/prompts/assistant.md` (original wording). An edited prompt overrides it; saving the default, or an empty prompt, goes back to the file.
- On every message, `App\Services\AssistantContext::build()` assembles the context: who's asking (role, whether they can edit), the walkthrough with the current screen marked, the current guide text, the initiative's data (stages and tools, friction points, story map, rating averages) and the team's notes. It's capped at 24k characters. **When a new activity ships, add its data to the context builder.**
- `POST initiatives/{initiative}/assistant` (and `POST /assistant` on the initiatives list) streams server-sent events: one JSON object per `data:` line (`text_delta`, `error`, …), then `data: [DONE]`. Provider failures become a readable `error` event. The browser reads the stream with `resources/js/lib/assistant-stream.ts`.
- Conversations are saved in `assistant_messages`, private to each user and separate per initiative. The last 20 messages are replayed to the model, and the panel shows the last 30. Replies are rendered as markdown on the server (`Guide::render`, raw HTML stripped). Requests are limited to 20 per minute per user (`throttle:assistant`).
- Tests never call a real provider. Use `GuideAssistant::fake([...])` and `GuideAssistant::assertPrompted(fn ($prompt) => …)`; `$prompt->agent->instructions()` exposes the full system prompt plus context.
- Planned: tools so the assistant can add data and write summaries, each confirmed by the user.

## Roles

`admin` manages everything, including guide text and user roles. `facilitator` creates and manages their own initiatives. `viewer` is read-only, but can add their own ratings. The first registered user becomes admin.

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- This project uses the streamlined Laravel 11+ structure: register middleware, exceptions, and routing in `bootstrap/app.php` and service providers in `bootstrap/providers.php`. There is no `app/Http/Kernel.php` or `app/Console/Kernel.php`, and commands in `app/Console/Commands/` auto-register.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
