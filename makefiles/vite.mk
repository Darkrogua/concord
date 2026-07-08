# OS3 — Zen.Act Vite (контейнер os3-vite)

.PHONY: vite-install vite-build vite-serve vite-serve-stop vite-check

include makefiles/common.mk

ACT_VITE_DIR     ?= plugins/zen/act
ACT_ASSETS_DIR   ?= php/plugins/zen/act/assets
ACT_VITE_ORIGIN  ?= http://localhost:5174

vite-install: ## npm install в php/plugins/zen/act (os3-vite)
	@docker exec $(NODE_CONTAINER) sh -c 'cd $(ACT_VITE_DIR) && npm install'
	@echo "$(GREEN)Готово:$(NC) зависимости zen/act установлены"

vite-build: ## npm run build — prod-сборка act_app в assets/ + проверка
	@docker exec $(NODE_CONTAINER) sh -c 'cd $(ACT_VITE_DIR) && npm run build'
	@ACT_ASSETS_DIR="$(CURDIR)/$(ACT_ASSETS_DIR)" APP_URL="$(APP_URL)" ./bin/vite-check
	@echo "$(GREEN)Готово:$(NC) сборка в $(ACT_ASSETS_DIR)/"

vite-check: ## Проверить prod-сборку act_app (manifest, PWA, entry JS)
	@ACT_ASSETS_DIR="$(CURDIR)/$(ACT_ASSETS_DIR)" APP_URL="$(APP_URL)" ./bin/vite-check

vite-serve: ## npm run serve — dev-сервер Vite (фон, порт 5174 на хосте)
	@docker exec $(NODE_CONTAINER) sh -c 'pkill -f "vite.*plugins/zen/act" 2>/dev/null || true'
	@docker exec -d $(NODE_CONTAINER) sh -c 'cd $(ACT_VITE_DIR) && ACT_VITE_ORIGIN=$(ACT_VITE_ORIGIN) npm run serve'
	@echo "$(GREEN)Vite serve$(NC) запущен в $(NODE_CONTAINER) → $(ACT_VITE_ORIGIN)"

vite-serve-stop: ## Остановить dev-сервер Vite в os3-vite
	@docker exec $(NODE_CONTAINER) sh -c 'pkill -f "vite.*plugins/zen/act" 2>/dev/null || true'
	@echo "$(GREEN)Vite serve$(NC) остановлен"
