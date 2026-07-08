# OS3 — управление из корня репозитория
# make help | make up | make down | make restart | make bash

include makefiles/common.mk
include makefiles/docker.mk
include makefiles/shell.mk
include makefiles/vite.mk
include makefiles/shakti.mk

.PHONY: help

help: ## Список команд
	@echo "$(GREEN)OS3$(NC)"
	@echo ""
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  $(GREEN)%-12s$(NC) %s\n", $$1, $$2}' \
		$(MAKEFILE_LIST) | sort -u

.DEFAULT_GOAL := help
