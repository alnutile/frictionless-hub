# Frictionless Hub: project notes

These notes are maintained for anyone (human or AI agent) picking up this codebase on another machine.

## What this is

A Laravel 12 + Inertia 2 + React 19 app for running a developer-experience program, structured around the step order of the book *Frictionless* and its workbook. See `docs/SPEC.md` for the full plan and `README.md` for user-facing setup.

## Content policy (non-negotiable)

- No text from the book or workbook goes into code, migrations, seeders, fixtures, tests or guide files. That includes templates, questions, rubric criteria, examples and email copy.
- The code provides generic, configurable tools; admins enter their own content.
- Guide text in `resources/guide/*.md` and the Larkspur scenario are original writing. Keep any new content original.

## Setup and Composer scripts

| Command | What it does |
|---|---|
| `composer setup` | First run on a machine. Runs `composer install`, creates `.env` from `.env.example` only if it's missing, generates `APP_KEY`, creates `database/database.sqlite`, runs `migrate:fresh --seed` (**wipes the local DB**), then `npm install` and `npm run build`. |
| `composer run dev` | Server, queue, logs (pail) and Vite together. Serves http://localhost:8000. |
| `composer test` | Unit + Feature suites in parallel (Pest). |
| `composer test:browser` | Browser suite (Pest browser plugin, Playwright). Needs `npx playwright install chromium` once per machine and built assets. |
| `composer test:all` | Both of the above. |
| `composer lint` | `pint --test`, `prettier --check`, `eslint`, `tsc --noEmit`. Read-only. |

- `.env` is git-ignored and never copied between machines. `.env.example` holds every non-secret default (SQLite, `APP_NAME="Frictionless Hub"`).
- After `git pull`: run `composer install && npm install` if lockfiles changed, `php artisan migrate` if there are new migrations, then `npm run build` (or keep `composer run dev` running).
- Feature tests that assert Inertia components need a current Vite build. A new page that's missing from the build fails with "Not a valid Inertia response".

## Testing conventions

- The runner is **Pest 4** (PHPUnit 12). Pest 5 needs Laravel 13, so it isn't used. Existing tests are PHPUnit-style classes; browser tests use Pest function syntax.
- `tests/Unit`: pure logic plus `ArchTest.php` (naming, no debug calls, no `env()` in `app/`).
- `tests/Feature`: every endpoint. Cover auth, role permissions, isolation between initiatives (another initiative's record returns **404**), validation messages and saved data.
- `tests/Browser`: user journeys. Select elements with `@data-test` hooks, never visible text, because the docked guide panel repeats many page titles. Browser steps time out after 10s (`tests/Pest.php`).
- `BenchmarkScenarioTest` and `tests/Browser/SmokeTest.php` iterate over `App\Support\Walkthrough`, so a new activity is smoke-tested as soon as it has a route.
- CI (`.github/workflows/ci.yml`) runs on every push: lint, Unit+Feature with coverage (fails below 85%), and Browser (uploads failure screenshots).

## Adding a walkthrough activity

1. Set its `route` in `App\Support\Walkthrough::activities()`. The order follows the workbook steps.
2. Write the controller under `App\Http\Controllers\Initiatives`, authorizing through `InitiativePolicy` (`view` to read, `update` to change). Use scoped route bindings under `initiatives/{initiative}`.
3. Write the page in `resources/js/pages/initiatives/` using `InitiativeLayout`, with `data-test` hooks for anything a browser test touches.
4. Replace the placeholder in `resources/guide/{key}.md` with original guide text.
5. Add a section to `Database\Seeders\LarkspurScenarioSeeder` (the fictional benchmark company) and a progress count in `InitiativeController::progress()`.
6. Add Feature tests (endpoints) and a Browser journey test.

## Roles

`admin` manages everything, including guide text and user roles. `facilitator` creates and manages their own initiatives. `viewer` is read-only, but can add their own ratings. The first registered user becomes admin.
