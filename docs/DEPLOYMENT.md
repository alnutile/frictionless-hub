# Deployment: running the production image

The production image (`Dockerfile`, target `production`) is built to run on Kubernetes (EKS is the target). `compose.production.yaml` runs exactly the same topology locally, and CI smoke-tests it on every push.

## Image

| Property | Value |
|---|---|
| Base | `serversideup/php:8.4-fpm-nginx` (pinned in `Dockerfile`), Debian |
| Listens on | `8080` (HTTP; TLS terminates at the load balancer) |
| User | `www-data`, uid/gid `33` (non-root) |
| Contents | App code, production Composer dependencies, built Vite assets. No tests, no dev dependencies, no `node_modules`, no `.env` |
| Startup | Caches config, routes, views and events (`AUTORUN_ENABLED=true`). Does **not** migrate (`AUTORUN_LARAVEL_MIGRATION=false`) |
| Logs | stdout/stderr (`LOG_CHANNEL=stderr`) |
| Health | `GET /up` returns 200 when the app boots. Docker `HEALTHCHECK` uses it |

Build it with `make prod-build` or `docker build --target production -t frictionless-hub:production .`

## One image, four roles

| Role | Command | Kubernetes object | Probe |
|---|---|---|---|
| Web | *(default)* | Deployment + Service, port 8080 | HTTP `GET /up` (readiness and liveness) |
| Queue worker | `php /var/www/html/artisan queue:work --tries=3 --max-time=3600` | Deployment | exec `healthcheck-queue` |
| Scheduler | `php /var/www/html/artisan schedule:work` | Deployment, **1 replica** | exec `healthcheck-schedule` |
| Migrations | `php /var/www/html/artisan migrate --force` | Job (or a Helm pre-upgrade hook) that runs before new web pods | none |

Web and worker scale horizontally. Run exactly one scheduler.

## Configuration (environment variables)

Everything is read from the environment, so no `.env` file exists in the image. Supply secrets from Kubernetes Secrets (or External Secrets / AWS Secrets Manager) and the rest from a ConfigMap.

| Variable | Required | Notes |
|---|---|---|
| `APP_KEY` | **yes** (secret) | `php artisan key:generate --show`. Must be identical on every pod |
| `APP_URL` | yes | Public URL, e.g. `https://hub.example.com` |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | yes | `pgsql` (RDS / Aurora Postgres). Password is a secret |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | recommended | ElastiCache. Needed when using the next three settings |
| `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` | recommended | `redis`. (`database` also works and needs no Redis) |
| `MAIL_*` | when email ships | e.g. SES via SMTP |
| `TRUSTED_PROXIES` | no | Defaults to `*` (trust the ALB). Narrow it to the VPC CIDR if pods are reachable by other routes |
| `LOG_LEVEL` | no | Defaults to `info` |

## Kubernetes notes

- **Stateless:** sessions and cache in Redis (or the database), and uploads (when they exist) in S3. Pods can be killed and rescheduled at any time.
- **Read-only root filesystem (optional):** mount writable `emptyDir` volumes at `/var/www/html/storage`, `/var/www/html/bootstrap/cache` and `/tmp`.
- **Security context:** `runAsNonRoot: true`, `runAsUser: 33`, `allowPrivilegeEscalation: false`, drop all capabilities. Port 8080 needs no privileges.
- **Graceful shutdown:** give workers a `terminationGracePeriodSeconds` longer than your longest job.
- **Seeding a test environment:** `php artisan db:seed --class=LarkspurScenarioSeeder --force` loads the benchmark scenario. `DatabaseSeeder` needs dev-only factories, so don't run it in production images.

## Not done yet

- Kubernetes manifests or a Helm chart.
- Publishing the image to ECR. CI builds and smoke-tests it but doesn't push it yet.
- Trimming image size (currently about 840 MB, mostly the base image).
