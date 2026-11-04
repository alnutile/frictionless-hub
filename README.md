# frictionless-hub

A platform for running a developer-experience (DevEx) program. It follows the process structure in the book *Frictionless* by Nicole Forsgren and Abi Noda. **No book or workbook content is included.** The app provides configurable tools, and admins enter their own content. See [docs/SPEC.md](docs/SPEC.md).

Stack: Laravel 12, Inertia 2 + React 19, Tailwind, and Laravel Boost (MCP). Local development uses SQLite; the app is Postgres-ready.

## Run locally

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer run dev            # http://localhost:8000
```

The seeded logins (password `password`) are `admin@example.com`, `facilitator@example.com` and `viewer@example.com`, plus the cast of the **Larkspur Labs** benchmark scenario: `priya@larkspur.test` (DevEx lead, owns the initiative), `marcus@`, `dana@`, `sam@`, `lin@`, `jo@`, `theo@` and `ama@larkspur.test`. Rebuild just the scenario with `php artisan db:seed --class=LarkspurScenarioSeeder`. On a fresh install with no seed, the first account to register becomes the admin.

## Tests

There are three layers, and CI runs all of them on every push (see `.github/workflows/ci.yml`):

| Layer | Where | What it covers |
|---|---|---|
| Unit + architecture | `tests/Unit` | Pure logic (heatmap, rating summary, reorder) and architecture rules (naming, no debug calls, no `env()` in app code) |
| Feature | `tests/Feature` | Every HTTP endpoint: authentication, role permissions, isolation between initiatives (another initiative's records return 404), validation messages, and saved data |
| Browser | `tests/Browser` | Pest browser tests on Playwright: click-through journeys in workbook order, the guide panel, a phone-sized viewport, and a check that every page loads without JavaScript errors |

The `BenchmarkScenarioTest` and the browser smoke tests read the walkthrough registry, so each new activity is tested as soon as it's registered.

```bash
composer test           # unit + feature, in parallel
composer test:browser   # browser tests (one-time setup: npx playwright install chromium)
composer lint           # pint, prettier, eslint, tsc
```

Browser tests need built assets (`npm run build`). When one fails, a screenshot is saved to `tests/Browser/Screenshots`, and CI uploads it as an artifact.
