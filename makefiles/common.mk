# OS3 — общие переменные

COMPOSE_FILE     ?= docker/docker-compose.local.yaml
COMPOSE_ENV_FILE ?= docker/.env
NETWORK_NAME     ?= web

PHP_CONTAINER    ?= os3-php
NGINX_CONTAINER  ?= os3-nginx
DB_CONTAINER     ?= os3-db
NODE_CONTAINER   ?= os3-vite

# docker compose v2; при необходимости: make … DOCKER_COMPOSE="docker-compose"
DOCKER_COMPOSE   ?= docker compose --env-file $(COMPOSE_ENV_FILE) -f $(COMPOSE_FILE)

APP_URL          ?= http://os3.megan:8083

GREEN  = \033[0;32m
YELLOW = \033[1;33m
NC     = \033[0m
