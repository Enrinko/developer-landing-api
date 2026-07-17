.PHONY: up down build restart sh composer install test stan cs cs-fix logs cache-clear

up: ## Start the whole stack
	docker compose up -d --build

down: ## Stop the stack
	docker compose down

build:
	docker compose build

restart: down up

sh: ## Shell into the PHP container
	docker compose exec app sh

install: ## Install PHP dependencies
	docker compose exec app composer install

test: ## Run the test suite
	docker compose exec app php bin/phpunit

stan: ## Static analysis
	docker compose exec app vendor/bin/phpstan analyse

cs: ## Code style check (dry run)
	docker compose exec app vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix code style
	docker compose exec app vendor/bin/php-cs-fixer fix

logs:
	docker compose logs -f --tail=100

cache-clear:
	docker compose exec app php bin/console cache:clear
