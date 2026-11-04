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

```bash
php artisan test
```
