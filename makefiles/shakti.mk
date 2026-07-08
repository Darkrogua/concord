# OS3 — деплой на Shakti VPS

.PHONY: deploy shakti-bootstrap shakti-deploy shakti-status

deploy: shakti-deploy ## Деплой на Shakti

shakti-bootstrap: ## Первичная настройка Shakti (Traefik, ufw, /opt/os3)
	@./bin/shakti bootstrap

shakti-deploy: ## Сборка фронта + деплой на Shakti (RESET_ACTS=1 — сброс актов на проде)
	@RESET_ACTS="$(RESET_ACTS)" ./bin/shakti deploy $(ARGS)

shakti-status: ## Статус Shakti VPS
	@./bin/shakti status
