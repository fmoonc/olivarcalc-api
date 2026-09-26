# Local workflow for the OlivarCalc API.
#
# Assumes Laravel Herd (PHP 8.5) plus DBNGIN's MySQL 8 on 127.0.0.1:3306.
# See DECISIONS.md ADR-0001 for why this deviates from spec §13.2's Docker
# Compose setup. `docker/Dockerfile` is kept for production on Coolify (§13.3).

.DEFAULT_GOAL := help
.PHONY: help setup migrate migrate-fresh seed db-create test test-feature test-unit lint lint-fix analyse ci openapi serve routes

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

setup: ## Install deps, refresh .env from .env.example, generate app key
	composer install --no-interaction
	@test -f .env || (cp .env.example .env && echo "created .env from .env.example")
	@grep -q '^APP_KEY=base64:' .env || php artisan key:generate
	@echo "Now run 'make migrate' and 'make seed'."

migrate: ## Run migrations
	php artisan migrate

migrate-fresh: ## Drop all tables, re-migrate, and seed
	php artisan migrate:fresh --seed

seed: ## Load seed data
	php artisan db:seed

db-create: ## Create the olivarcalc databases and user (needs MySQL root)
	@php -r 'new PDO("mysql:host=127.0.0.1;port=3306","root","");' 2>/dev/null \
		&& echo "MySQL reachable" \
		|| echo "Start MySQL 8 in DBNGIN first."

test: ## Run the whole Pest suite (MySQL 8, database olivarcalc_test)
	php artisan test

test-feature: ## Run only tests/Feature
	php artisan test --testsuite=Feature

test-unit: ## Run only tests/Unit
	php artisan test --testsuite=Unit

lint: ## Check code style (Pint)
	@php vendor/bin/pint --test

lint-fix: ## Fix code style (Pint)
	@php vendor/bin/pint

analyse: ## Static analysis (Larastan level 6)
	@php vendor/bin/phpstan analyse --memory-limit=1G

ci: ## lint -> analyse -> test, the same order as GitHub Actions
	composer lint
	composer analyse
	composer test

openapi: ## Export the OpenAPI contract to storage/app/openapi.json
	php artisan scramble:export --path=storage/app/openapi.json

serve: ## Serve the API on http://localhost:8000
	php artisan serve --host=0.0.0.0 --port=8000

routes: ## List registered routes
	php artisan route:list
