# Leopardo RH — Developer Makefile
# Usage: make <target>
# Run `make help` to see all available targets.

.DEFAULT_GOAL := help
SHELL := /bin/bash

DC := docker compose
API := $(DC) exec api
ARTISAN := $(API) php artisan

## — Setup ————————————————————————————————————————————

.PHONY: install
install: ## First-time setup: build, start, migrate, seed
	$(DC) build
	$(DC) up -d
	@echo "Waiting for PostgreSQL..."
	@sleep 5
	$(API) composer install
	$(ARTISAN) key:generate --force
	$(ARTISAN) migrate --seed --force
	@echo "Leopardo RH is running at http://localhost:8000"

.PHONY: up
up: ## Start all core services (api, postgres, redis, queue, scheduler)
	$(DC) up -d

.PHONY: up-full
up-full: ## Start all services including dashboard, web, and mailpit
	$(DC) --profile full up -d

.PHONY: down
down: ## Stop all services
	$(DC) down

.PHONY: restart
restart: down up ## Restart all core services

.PHONY: destroy
destroy: ## Stop services and remove volumes (WARNING: deletes database)
	$(DC) down -v

## — Development ——————————————————————————————————————

.PHONY: migrate
migrate: ## Run database migrations
	$(ARTISAN) migrate --force

.PHONY: migrate-fresh
migrate-fresh: ## Reset database and re-run all migrations with seeders
	$(ARTISAN) migrate:fresh --seed --force

.PHONY: seed
seed: ## Run database seeders
	$(ARTISAN) db:seed --force

.PHONY: tinker
tinker: ## Open Laravel Tinker REPL
	$(ARTISAN) tinker

.PHONY: routes
routes: ## List all API routes
	$(ARTISAN) route:list

.PHONY: logs
logs: ## Tail all container logs
	$(DC) logs -f

.PHONY: logs-api
logs-api: ## Tail API container logs
	$(DC) logs -f api

.PHONY: shell
shell: ## Open bash shell in API container
	$(API) bash

## — Testing ——————————————————————————————————————————

.PHONY: test
test: ## Run all backend tests
	$(ARTISAN) test

.PHONY: test-unit
test-unit: ## Run unit tests only
	$(ARTISAN) test --testsuite=Unit

.PHONY: test-feature
test-feature: ## Run feature tests only
	$(ARTISAN) test --testsuite=Feature

.PHONY: test-coverage
test-coverage: ## Run tests with coverage report
	$(ARTISAN) test --coverage

## — Code Quality —————————————————————————————————————

.PHONY: lint
lint: ## Run Laravel Pint code style fixer
	$(API) ./vendor/bin/pint

.PHONY: lint-check
lint-check: ## Check code style without fixing
	$(API) ./vendor/bin/pint --test

.PHONY: analyze
analyze: ## Run PHPStan static analysis
	$(API) ./vendor/bin/phpstan analyse

.PHONY: quality
quality: lint-check analyze test ## Run all quality checks (lint, analyze, test)

## — Queue & Scheduler ————————————————————————————————

.PHONY: queue-restart
queue-restart: ## Restart queue worker
	$(DC) restart queue

.PHONY: queue-status
queue-status: ## Show queue status
	$(ARTISAN) queue:monitor

## — Help —————————————————————————————————————————————

.PHONY: help
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'
