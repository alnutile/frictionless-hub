# frictionless-hub

A platform for running a developer-experience (DevEx) program. It follows the process structure in the book _Frictionless_ by Nicole Forsgren and Abi Noda. **No book or workbook content is included.** The app provides configurable tools, and admins enter their own content. See [docs/SPEC.md](docs/SPEC.md).

Stack: Laravel 12, Inertia 2 + React 19, Tailwind, and Laravel Boost (MCP). Local development uses SQLite; the app is Postgres-ready.

## What's built so far

Step 1 (Discover) of the walkthrough: **Map the workflow**, **Log friction** (with a stage × dimension heatmap), **Story map** and **Rate the workflow**. There's also a docked **Guide** panel on every screen with a context-aware **AI assistant** (OpenRouter, set up by an admin), an overview "ladder" of all 18 activities, and admin user and role management. See [docs/SPEC.md](docs/SPEC.md) for the full plan.

## Set up on a new machine

> **Using an AI assistant?** Tell it *"Set this up on my computer"*. The steps it follows are in [`.ai/guidelines/frictionless-hub.md`](.ai/guidelines/frictionless-hub.md), which Claude Code, Codex and OpenCode read automatically.

### Option A: Docker (recommended, matches production)

Needs only **Docker** (Docker Desktop, OrbStack or Docker Engine with Compose v2) and `make`.

```bash
git clone git@github.com:alnutile/frictionless-hub.git
cd frictionless-hub
make setup      # builds images, installs deps, creates .env, migrates + seeds Postgres
```

| URL | What |
|---|---|
| http://localhost:8000 | The app (hot reload through Vite on :5173) |
| http://localhost:8025 | Mailpit, which catches outgoing email |

The stack is app (PHP 8.4 + nginx), queue worker, Vite, Postgres 17, Redis 7 and Mailpit. Run `make` to list every command. The day-to-day ones:

```bash
make up / make down     # start / stop (data is kept)
make fresh              # reset the database to clean demo data
make test               # unit + feature tests (SQLite in memory)
make test-pgsql         # same suite against Postgres
make lint               # pint, prettier, eslint, tsc
make shell              # bash inside the app container
make prod-up            # run the *production* image like Kubernetes, on http://localhost:8090
```

Ports can be changed with `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT` and `PROD_PORT`, for example `APP_PORT=8001 make up`.

### Option B: Native (no Docker)

Needs PHP 8.4 with Composer 2 and Node 22+. Uses a SQLite file, with no database server.

```bash
composer setup      # installs dependencies, creates .env, builds assets, seeds demo data
composer run dev    # http://localhost:8000
```

### About `.env`

**You never copy `.env` between machines.** It's git-ignored. `make setup` / `composer setup` create it from `.env.example` and generate a machine-specific `APP_KEY`. In Docker, `compose.yaml` supplies the Postgres, Redis and Mailpit settings as environment variables, which take precedence over `.env`. So the same `.env` works for both options.

Both setup commands **rebuild the local database** with fresh seed data. Run them once per machine. After a `git pull`:

| Docker | Native |
|---|---|
| `make up` (rebuild with `docker compose build` if the Dockerfile changed) | `composer install && npm install` if lockfiles changed |
| `make artisan cmd=migrate` | `php artisan migrate` |

### AI assistant

The Guide panel includes a chat that knows the current screen, its guide text and the initiative's data. To turn it on, sign in as an admin, open **Admin → AI assistant**, paste an [OpenRouter](https://openrouter.ai) key, choose a model, then click **Test connection**. The system prompt can be edited on the same page. The key is stored encrypted in the database, so nothing goes in `.env`.

### Production image

`Dockerfile` (target `production`) is a non-root, stateless image configured entirely through environment variables, built for Kubernetes/EKS. The same image runs web, queue worker, scheduler and migrations. See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

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

There are three layers. CI (`.github/workflows/ci.yml`) runs all of them on every push, runs unit + feature tests on both SQLite and Postgres, and builds and smoke-tests the production image:

| Layer               | Where           | What it covers                                                                                                                                                                   |
| ------------------- | --------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Unit + architecture | `tests/Unit`    | Pure logic (heatmap, rating summary, reorder) and architecture rules (naming, no debug calls, no `env()` in app code)                                                            |
| Feature             | `tests/Feature` | Every HTTP endpoint: authentication, role permissions, isolation between initiatives (another initiative's records return 404), validation messages, and saved data              |
| Browser             | `tests/Browser` | Pest browser tests on Playwright: click-through journeys in workbook order, the guide panel, a phone-sized viewport, and a check that every page loads without JavaScript errors |

The `BenchmarkScenarioTest` and the browser smoke tests read the walkthrough registry, so each new activity is tested as soon as it's registered.

Tests can never wipe your dev database. `phpunit.xml` pins them to in-memory SQLite (`phpunit.pgsql.xml` to a Postgres database named `testing`), and `tests/TestCase.php` refuses to run against any other database.

```bash
composer test           # unit + feature, in parallel (or: make test / make test-pgsql)
composer test:browser   # browser tests (one-time per machine: npx playwright install chromium)
composer lint           # pint, prettier, eslint, tsc
```

Browser tests need built assets (`npm run build`). When one fails, a screenshot is saved to `tests/Browser/Screenshots`, and CI uploads it as an artifact.
