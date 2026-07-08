# OS3 — интерактивная оболочка в PHP-контейнере (artisan, composer)

.PHONY: bash

include makefiles/common.mk

bash: ## Bash в контейнере October (рабочая dir /var/www/html)
	@echo "$(YELLOW)Контейнер $(PHP_CONTAINER) — php artisan, composer …$(NC)"
	@docker exec -it -w /var/www/html $(PHP_CONTAINER) bash
