# OS3 — Docker Compose

.PHONY: up down restart ensure-network ensure-os3-resources shell-db db-dump db-restore

include makefiles/common.mk

OS3_INTERNAL_NETWORK ?= os3_internal
OS3_DB_VOLUME        ?= os3_db_data

ensure-network:
	@docker network inspect $(NETWORK_NAME) >/dev/null 2>&1 \
		|| docker network create $(NETWORK_NAME)

ensure-os3-resources: ensure-network
	@docker network inspect $(OS3_INTERNAL_NETWORK) >/dev/null 2>&1 \
		|| docker network create $(OS3_INTERNAL_NETWORK)
	@docker volume inspect $(OS3_DB_VOLUME) >/dev/null 2>&1 \
		|| docker volume create $(OS3_DB_VOLUME)

up: ensure-os3-resources ## Запустить проект
	@echo "$(GREEN)Запуск OS3…$(NC)"
	@$(DOCKER_COMPOSE) up -d
	@echo "$(GREEN)Готово.$(NC) $(APP_URL)/app · бэкенд $(APP_URL)/console"

down: ## Остановить проект
	@echo "$(YELLOW)Остановка OS3…$(NC)"
	@$(DOCKER_COMPOSE) down
	@echo "$(GREEN)Проект остановлен.$(NC)"

restart: ## Перезапустить контейнеры (без пересборки)
	@echo "$(YELLOW)Перезапуск OS3…$(NC)"
	@$(DOCKER_COMPOSE) restart
	@echo "$(GREEN)Готово.$(NC) $(APP_URL)/app · бэкенд $(APP_URL)/console"

shell-db: ## psql в PostgreSQL (через ./bin/db)
	@./bin/db

db-dump: ## Дамп БД в backups/ (./bin/db dump)
	@./bin/db dump

db-restore: ## Восстановить БД (make db-restore os3_db_….sql.gz)
	@backup="$(filter-out db-restore,$(MAKECMDGOALS))"; \
	if [ -z "$$backup" ]; then \
		echo "$(RED)Укажите файл: make db-restore os3_db_….sql.gz$(NC)"; \
		exit 1; \
	fi; \
	./bin/db restore "$$backup"

# make db-restore <file> — имя файла передаётся как доп. цель make
%:
	@:
