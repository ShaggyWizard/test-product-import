DC = docker compose
APP = $(DC) exec app

.PHONY: help init up down build logs migrate seed deps test e2e stan cs ps

help: ## Список команд
	@grep -E '^[a-z-]+:.*##' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  %-10s %s\n", $$1, $$2}'

init: ## Первый запуск: .env, сборка, миграции
	@test -f .env || cp .env.example .env
	$(DC) up -d --build
	$(APP) php bin/console migrations:migrate --no-interaction
	@echo "Готово: http://localhost:$${HTTP_PORT:-8080}"

up: ## Поднять все сервисы
	$(DC) up -d

down: ## Остановить
	$(DC) down

build: ## Пересобрать образы
	$(DC) build

logs: ## Логи всех сервисов
	$(DC) logs -f --tail=100

ps: ## Состояние сервисов
	$(DC) ps

migrate: ## Применить миграции
	$(APP) php bin/console migrations:migrate --no-interaction

seed: ## Заполнить БД тестовыми данными
	$(APP) php bin/console app:seed

# Боевой образ без dev-зависимостей, поэтому тесты и анализаторы идут в одноразовом контейнере
# с примонтированным кодом: composer ставит dev-пакеты, БД — тот же сервис db (база products_test).
DEV = $(DC) run --rm --no-deps -u root -v ./backend:/var/www/app app

deps: ## Поставить dev-зависимости бэкенда (для тестов и анализаторов)
	$(DEV) composer install --no-interaction --no-progress

test: ## Тесты бэкенда (unit + интеграционные на products_test)
	$(DEV) vendor/bin/phpunit

e2e: ## E2E в браузере (Playwright) против поднятого стека; нужен Node.js
	cd e2e && npm ci && npx playwright install chromium && npx playwright test

stan: ## PHPStan
	$(DEV) vendor/bin/phpstan analyse --memory-limit=512M

cs: ## Проверка код-стайла
	$(DEV) vendor/bin/php-cs-fixer fix --dry-run --diff
