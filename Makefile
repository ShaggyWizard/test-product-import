DC = docker compose
APP = $(DC) exec app

.PHONY: help init up down build logs migrate seed test stan cs ps

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

test: ## Тесты бэкенда
	$(APP) vendor/bin/phpunit

stan: ## PHPStan
	$(APP) vendor/bin/phpstan analyse --memory-limit=512M

cs: ## Проверка код-стайла
	$(APP) vendor/bin/php-cs-fixer fix --dry-run --diff
