COMPOSE := docker compose -f .docker/compose.yml

.PHONY: up down destroy build ensure-up test coverage analyse rector rector-dry flush dev-build sh

## Generate .docker/.env with deterministic ports (auto-runs if missing)
.docker/.env:
	.docker/env.sh

## Start services (build if needed)
up: .docker/.env
	$(COMPOSE) up -d --build
	@echo "\n  Testbed running at https://localhost:$$(grep WEB_PORT .docker/.env | cut -d= -f2)\n"

## Stop services
down:
	$(COMPOSE) down

## Stop services and remove volumes
destroy:
	$(COMPOSE) down -v

## Build images without starting
build:
	$(COMPOSE) build

## Ensure services are running and ready
ensure-up: .docker/.env
	@$(COMPOSE) exec app true 2>/dev/null || $(COMPOSE) up -d --build --wait

## Run PHPUnit
test: ensure-up
	$(COMPOSE) exec app vendor/bin/phpunit

## Run PHPUnit with code coverage (text summary on stdout, HTML at coverage/html, clover XML for CI)
coverage: ensure-up
	$(COMPOSE) exec app vendor/bin/phpunit \
		--coverage-text \
		--coverage-html /app/coverage/html \
		--coverage-clover /app/coverage/clover.xml

## Run PHPStan static analysis
analyse: ensure-up
	$(COMPOSE) exec app vendor/bin/phpstan analyse -c phpstan.neon.dist --memory-limit=512M

## Run Rector refactoring (applies changes)
rector: ensure-up
	$(COMPOSE) exec app vendor/bin/rector process

## Run Rector in dry-run mode (preview only)
rector-dry: ensure-up
	$(COMPOSE) exec app vendor/bin/rector process --dry-run

## Clear SilverStripe cache
flush: ensure-up
	$(COMPOSE) exec app vendor/bin/sake flush

## Run dev/build to rebuild the database and manifest
dev-build: ensure-up
	$(COMPOSE) exec app vendor/bin/sake dev/build flush=1

## Open shell in the app container
sh: ensure-up
	$(COMPOSE) exec app sh
