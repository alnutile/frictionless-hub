# Frictionless Hub: Docker-first developer commands. Run `make` to list them.
# Everything runs in containers; the host only needs Docker (and make).

SHELL := /bin/sh
COMPOSE := docker compose
PROD := docker compose -f compose.production.yaml -p frictionless-prod
APP := $(COMPOSE) exec app
export UID := $(shell id -u)
export GID := $(shell id -g)
export PROD_PORT ?= 8090

.DEFAULT_GOAL := help

.PHONY: help
help: ## List available commands
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z_-]+:.*##/ { printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

## ---- Local development --------------------------------------------------

.PHONY: setup
setup: ## First run on a machine: build images, install deps, create .env, migrate and seed
	@test -f .env || cp .env.example .env
	$(COMPOSE) build
	$(COMPOSE) up -d --wait pgsql redis mailpit app
	$(APP) composer install --no-interaction
	@grep -q '^APP_KEY=base64' .env || $(APP) php artisan key:generate --no-interaction
	$(APP) php artisan migrate:fresh --seed --force
	$(COMPOSE) run --rm --no-deps vite sh -c "npm ci --no-audit --no-fund && npm run build"
	$(COMPOSE) up -d
	@echo ""
	@echo "Ready: http://localhost:8000  (sign in as priya@larkspur.test / password)"
	@echo "Mail:  http://localhost:8025"

.PHONY: up
up: ## Start the stack (app, queue, vite, postgres, redis, mailpit)
	$(COMPOSE) up -d
	@echo "http://localhost:8000"

.PHONY: down
down: ## Stop the stack (keeps the database)
	$(COMPOSE) down

.PHONY: destroy
destroy: ## Stop the stack and delete its volumes (database, redis, node_modules)
	$(COMPOSE) down -v

.PHONY: fresh
fresh: ## Reset the database to clean demo data
	$(APP) php artisan migrate:fresh --seed --force

.PHONY: logs
logs: ## Follow logs from all services
	$(COMPOSE) logs -f

.PHONY: shell
shell: ## Open a shell in the app container
	$(APP) bash

.PHONY: artisan
artisan: ## Run an artisan command, e.g. make artisan cmd="route:list"
	$(APP) php artisan $(cmd)

## ---- Quality --------------------------------------------------------------

.PHONY: test
test: ## Unit + feature tests (SQLite in memory, inside the container)
	$(APP) php vendor/bin/pest --testsuite=Unit,Feature --parallel

.PHONY: test-pgsql
test-pgsql: ## Unit + feature tests against Postgres (the separate "testing" database)
	$(APP) php vendor/bin/pest --configuration=phpunit.pgsql.xml --testsuite=Unit,Feature

.PHONY: lint
lint: ## Pint, Prettier, ESLint and TypeScript checks
	$(APP) vendor/bin/pint --test
	$(COMPOSE) run --rm --no-deps vite sh -c "npm run format:check && npm run lint:check && npm run types"

## ---- Production image -----------------------------------------------------

.PHONY: prod-build
prod-build: ## Build the production image (frictionless-hub:production)
	docker build --target production -t frictionless-hub:production .

.PHONY: prod-up
prod-up: prod-build ## Run the production image like Kubernetes would, on http://localhost:8090
	$(PROD) up -d --wait web worker scheduler
	@echo "http://localhost:$(PROD_PORT) (empty database; run: make prod-seed)"

.PHONY: prod-seed
prod-seed: ## Load the Larkspur demo scenario into the production stack
	$(PROD) exec web php artisan db:seed --class=LarkspurScenarioSeeder --force

.PHONY: prod-smoke
prod-smoke: prod-up ## Build, start and check the production stack responds, then stop it
	@curl -fsS -o /dev/null -w "/up     %{http_code}\n" http://localhost:$(PROD_PORT)/up
	@curl -fsS -o /dev/null -w "/login  %{http_code}\n" http://localhost:$(PROD_PORT)/login
	@test "$$($(PROD) exec -T web id -u)" != "0" && echo "runs as non-root: ok"
	$(PROD) down -v

.PHONY: prod-down
prod-down: ## Stop the production stack and delete its data
	$(PROD) down -v
