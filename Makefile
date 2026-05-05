COMPOSE := docker compose -f .docker/compose.yml

.PHONY: up down destroy build ensure-up test analyse test-cs fix-cs flush dev-build sh

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

## Run PHPStan static analysis
analyse: ensure-up
	$(COMPOSE) exec app vendor/bin/phpstan analyse -c phpstan.neon.dist --memory-limit=512M

## Run php-cs-fixer dry-run (was: `make test` in the old setup)
test-cs: ensure-up
	$(COMPOSE) exec app sh -c 'cd /module && /app/vendor/bin/php-cs-fixer fix --diff --dry-run --config=.php-cs-fixer.php'

## Auto-fix code style
fix-cs: ensure-up
	$(COMPOSE) exec app sh -c 'cd /module && /app/vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php'

## Clear SilverStripe cache
flush: ensure-up
	$(COMPOSE) exec app vendor/bin/sake flush

## Run dev/build to rebuild the database and manifest
dev-build: ensure-up
	$(COMPOSE) exec app vendor/bin/sake dev/build flush=1

## Open shell in the app container
sh: ensure-up
	$(COMPOSE) exec app sh
