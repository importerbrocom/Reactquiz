# QuizPath — Development Commands
# Usage: make <target>

.PHONY: dev test fresh lint build clean help

# ─── Development ─────────────────────────────────────────────────────────────

dev: ## Start development environment (docker compose)
	docker compose -f deploy/docker/compose.dev.yml up -d
	@echo "API:      http://localhost:8000"
	@echo "Mailpit:  http://localhost:8025"
	@echo "MinIO:    http://localhost:9001"

down: ## Stop development environment
	docker compose -f deploy/docker/compose.dev.yml down

# ─── Testing ─────────────────────────────────────────────────────────────────

test: ## Run all tests (API + Web)
	cd api && vendor/bin/pest --compact
	cd web && npm run build

test-api: ## Run API tests only
	cd api && vendor/bin/pest --compact

test-web: ## Run web type check + build
	cd web && npx tsc -b && npm run build

# ─── Code Quality ────────────────────────────────────────────────────────────

lint: ## Run all linters
	cd api && vendor/bin/pint --quiet
	cd api && vendor/bin/phpstan analyse --memory-limit=1G --no-progress

format: ## Format PHP code
	cd api && vendor/bin/pint

# ─── Database ────────────────────────────────────────────────────────────────

fresh: ## Fresh migration with seeding
	cd api && php artisan migrate:fresh --seed

migrate: ## Run migrations
	cd api && php artisan migrate

seed: ## Run seeders
	cd api && php artisan db:seed

# ─── Build ───────────────────────────────────────────────────────────────────

build: ## Build web frontend for production
	cd web && npm ci && npm run build

build-api: ## Build API Docker image
	docker build -f deploy/docker/Dockerfile.api -t quizpath-api .

build-worker: ## Build worker Docker image
	docker build -f deploy/docker/Dockerfile.worker -t quizpath-worker .

# ─── Utilities ───────────────────────────────────────────────────────────────

clean: ## Remove build artifacts
	rm -rf web/dist web/node_modules/.vite
	cd api && php artisan cache:clear config:clear route:clear view:clear

ide-helpers: ## Regenerate IDE helper annotations
	cd api && php artisan ide-helper:models --write --reset --no-interaction

# ─── Help ────────────────────────────────────────────────────────────────────

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

.DEFAULT_GOAL := help
