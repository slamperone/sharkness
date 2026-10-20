# Atajos para el día a día de Sharkness.  Escribe `make` para ver la ayuda.
SHELL := /bin/bash
include .env
export

.DEFAULT_GOAL := help

help: ## Muestra esta ayuda
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36mmake %-12s\033[0m %s\n", $$1, $$2}'

up: ## Enciende la tienda
	docker compose up -d
	@echo ""
	@echo "  Tienda:      http://localhost:$(PS_PORT)"
	@echo "  Back office: http://localhost:$(PS_PORT)/$(PS_FOLDER_ADMIN)"
	@echo "  phpMyAdmin:  http://localhost:$(PMA_PORT)"
	@echo "  Mailpit:     http://localhost:$(MAILPIT_PORT)"

down: ## Apaga la tienda (los datos se conservan)
	docker compose down

restart: ## Reinicia los contenedores
	docker compose restart

logs: ## Muestra los logs en vivo (Ctrl+C para salir)
	docker compose logs -f prestashop

shell: ## Abre una terminal dentro del contenedor de PrestaShop
	docker compose exec prestashop bash

cache: ## Limpia la caché de PrestaShop
	docker compose exec prestashop rm -rf var/cache/dev var/cache/prod
	@echo "Caché limpia"

backup-db: ## Respalda la BD en backups/
	@mkdir -p backups
	docker compose exec -T mariadb mariadb-dump -u root -p$(DB_ROOT_PASSWORD) $(DB_NAME) | gzip > backups/$(DB_NAME)-$$(date +%Y%m%d-%H%M%S).sql.gz
	@ls -lh backups | tail -1

restore-db: ## Restaura un respaldo: make restore-db FILE=backups/xxx.sql.gz
	@test -n "$(FILE)" || (echo "Uso: make restore-db FILE=backups/xxx.sql.gz" && exit 1)
	gunzip -c $(FILE) | docker compose exec -T mariadb mariadb -u root -p$(DB_ROOT_PASSWORD) $(DB_NAME)
	@echo "BD restaurada"

.PHONY: help up down restart logs shell cache backup-db restore-db
