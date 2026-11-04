# frictionless-hub

A platform for running a developer-experience (DevEx) program. It follows the process structure in the book _Frictionless_ by Nicole Forsgren and Abi Noda. **No book or workbook content is included.** The app provides configurable tools, and admins enter their own content. See [docs/SPEC.md](docs/SPEC.md).

Stack: Laravel 12, Inertia 2 + React 19, Tailwind, and Laravel Boost (MCP). Local development uses SQLite; the app is Postgres-ready.

## What's built so far

Step 1 (Discover) of the walkthrough: **Map the workflow**, **Log friction** (with a stage × dimension heatmap), **Story map** and **Rate the workflow**. There's also a docked **Guide** panel on every screen, an overview "ladder" of all 18 activities, and admin user and role management. See [docs/SPEC.md](docs/SPEC.md) for the full plan.

## Requirements

- PHP 8.4 with Composer 2
- Node 22 or newer with npm
- No database server needed: local development uses SQLite (`database/database.sqlite`)

## Set up on a new machine

```bash
git clone git@github.com:alnutile/frictionless-hub.git
cd frictionless-hub
composer setup      # installs dependencies, creates .env, builds assets, seeds demo data
composer run dev    # http://localhost:8000 (server, queue, logs, and Vite hot reload)
```

`composer setup` creates `.env` from `.env.example` and generates a fresh `APP_KEY`. **You don't need to copy `.env` between machines.** It's git-ignored on purpose, and right now the only difference from `.env.example` is the key, which is unique to each machine anyway.

> `composer setup` **rebuilds the local database** with fresh seed data. Run it once per machine. After that, just `git pull` and run whichever of these applies:
>
> ```bash
> composer install && npm install   # if dependencies changed
> php artisan migrate               # if there are new migrations
> npm run build                     # or leave `composer run dev` running for hot reload
> ```
>
> To reset to clean demo data at any time: `php artisan migrate:fresh --seed`.

### Logging in

Every seeded account has the password `password`.

| Account                                                        | Role                  | Use it for                                                               |
| -------------------------------------------------------------- | --------------------- | ------------------------------------------------------------------------ |
| `priya@larkspur.test`                                          | Facilitator           | **Start here.** DevEx lead who owns the _Larkspur DevEx 2027_ initiative |
| `admin@example.com`                                            | Admin                 | Editing the guide, user roles, managing everything                       |
| `dana@larkspur.test`                                           | Viewer                | The read-only view (VP Engineering)                                      |
| `marcus@`, `sam@`, `lin@`, `jo@`, `theo@`, `ama@larkspur.test` | Facilitator / viewers | The rest of the Larkspur cast; most of them have added ratings           |
| `facilitator@example.com`, `viewer@example.com`                | Facilitator / viewer  | Plain accounts that own nothing                                          |

**Larkspur Labs** is the benchmark scenario: a fictional ~140-developer SaaS company, seeded with data for every activity built so far. Rebuild only the scenario with `php artisan db:seed --class=LarkspurScenarioSeeder`. On an install with no seed data, the first person to register becomes the admin.

## Tests

There are three layers, and CI runs all of them on every push (see `.github/workflows/ci.yml`):

| Layer               | Where           | What it covers                                                                                                                                                                   |
| ------------------- | --------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Unit + architecture | `tests/Unit`    | Pure logic (heatmap, rating summary, reorder) and architecture rules (naming, no debug calls, no `env()` in app code)                                                            |
| Feature             | `tests/Feature` | Every HTTP endpoint: authentication, role permissions, isolation between initiatives (another initiative's records return 404), validation messages, and saved data              |
| Browser             | `tests/Browser` | Pest browser tests on Playwright: click-through journeys in workbook order, the guide panel, a phone-sized viewport, and a check that every page loads without JavaScript errors |

The `BenchmarkScenarioTest` and the browser smoke tests read the walkthrough registry, so each new activity is tested as soon as it's registered.

```bash
composer test           # unit + feature, in parallel
composer test:browser   # browser tests (one-time per machine: npx playwright install chromium)
composer lint           # pint, prettier, eslint, tsc
```

Browser tests need built assets (`npm run build`). When one fails, a screenshot is saved to `tests/Browser/Screenshots`, and CI uploads it as an artifact.
