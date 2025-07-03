# Nom du conteneur (remplace par le nom de ton conteneur)
CONTAINER_NAME=symfony_frankenphp

# UID et utilisateur par défaut pour la connexion
USER_ID=1000
USER_NAME=localuser

# Chemin vers le dossier web
WEB_DIR=web

.PHONY: help up down restart logs exec exec-root clean rebuild status chown install test lint sync sync-all sync-sprints sync-last-month sync-last-3-months sync-custom reset-metrics reset-metrics-force reset-sprints reset-bugs reset-and-sync

# Afficher l'aide
help:
	@echo "Usage:"
	@echo "  make up              Démarrer les conteneurs"
	@echo "  make down            Arrêter les conteneurs"
	@echo "  make restart         Redémarrer les conteneurs"
	@echo "  make logs           Afficher les logs"
	@echo "  make exec           Se connecter au conteneur en tant qu'utilisateur"
	@echo "  make exec-root      Se connecter au conteneur en tant que root"
	@echo "  make clean          Nettoyer Docker"
	@echo "  make rebuild        Reconstruire les conteneurs"
	@echo "  make status         Afficher le status des conteneurs"
	@echo "  make chown          Corriger les permissions"
	@echo "  make install        Installer les dépendances"
	@echo "  make test           Lancer les tests"
	@echo "  make lint           Lancer le linting"
	@echo "  make sync           Synchroniser les données"
	@echo "  make sync-all       Synchroniser toutes les données depuis 6 mois"
	@echo "  make sync-sprints   Synchroniser uniquement les sprints depuis 6 mois"
	@echo "  make sync-last-month Synchroniser les données du dernier mois"
	@echo "  make sync-last-3-months Synchroniser les données des 3 derniers mois"
	@echo "  make sync-custom    Synchroniser les données pour une période personnalisée (FROM=YYYY-MM-DD TO=YYYY-MM-DD)"
	@echo "  make reset-metrics  Réinitialiser les données"
	@echo "  make reset-metrics-force Réinitialiser toutes les données des métriques (sans confirmation)"
	@echo "  make reset-sprints Réinitialiser uniquement les données des sprints"
	@echo "  make reset-bugs Réinitialiser uniquement les données des bugs"
	@echo "  make reset-and-sync Réinitialiser toutes les données et resynchroniser"

# Commandes Docker Compose
up:
	docker-compose up -d

down:
	docker-compose down

restart:
	docker-compose down && docker-compose up -d

logs:
	docker-compose logs -f

# Commande pour se connecter en tant qu'utilisateur spécifique dans le conteneur
exec:
	docker exec -ti --user $(USER_ID) $(CONTAINER_NAME) /bin/bash

exec-root:
	docker exec -ti $(CONTAINER_NAME) /bin/bash

# Nettoyer les images, volumes et conteneurs inutilisés
clean:
	docker system prune -af --volumes

# Recréer le conteneur sans utiliser le cache pour s'assurer que les changements sont pris en compte
rebuild:
	docker-compose down
	docker-compose build --no-cache
	docker-compose up -d

# Afficher l'état des conteneurs en cours d'exécution
status:
	docker-compose ps

# Appliquer chown pour localuser dans le conteneur
chown:
	docker exec -ti $(CONTAINER_NAME) chown -R $(USER_NAME):$(USER_NAME) /app

# Installation des dépendances
install:
	docker exec -ti --user $(USER_ID) $(CONTAINER_NAME) composer install

# Lancer les tests
test:
	docker exec -ti --user $(USER_ID) $(CONTAINER_NAME) php bin/phpunit

# Lancer le linting
lint:
	docker exec -ti --user $(USER_ID) $(CONTAINER_NAME) php -l src/
	docker exec -ti --user $(USER_ID) $(CONTAINER_NAME) composer run cs-fixer

# Synchroniser les données
sync:
	cd $(WEB_DIR) && php bin/console app:sync-jira --all

# Synchroniser toutes les données depuis 6 mois
sync-all:
	cd $(WEB_DIR) && php bin/console app:sync-jira --all

# Synchroniser uniquement les sprints depuis 6 mois
sync-sprints:
	cd $(WEB_DIR) && php bin/console app:sync-jira --sprints

# Synchroniser les données du dernier mois
sync-last-month:
	cd $(WEB_DIR) && php bin/console app:sync-jira --all --from=$(shell date -d "1 month ago" +%Y-%m-%d)

# Synchroniser les données des 3 derniers mois
sync-last-3-months:
	cd $(WEB_DIR) && php bin/console app:sync-jira --all --from=$(shell date -d "3 months ago" +%Y-%m-%d)

# Synchroniser les données pour une période personnalisée
sync-custom:
	@if [ -z "$(FROM)" ] || [ -z "$(TO)" ]; then \
		echo "Erreur: Les paramètres FROM et TO sont requis."; \
		echo "Usage: make sync-custom FROM=YYYY-MM-DD TO=YYYY-MM-DD"; \
		exit 1; \
	fi
	cd $(WEB_DIR) && php bin/console app:sync-jira --all --from=$(FROM) --to=$(TO)

# Réinitialiser les données
reset-metrics: ## Réinitialise toutes les données des métriques (avec confirmation)
	cd $(WEB_DIR) && php bin/console app:reset-metrics

reset-metrics-force: ## Réinitialise toutes les données des métriques (sans confirmation)
	cd $(WEB_DIR) && php bin/console app:reset-metrics --force

reset-sprints: ## Réinitialise uniquement les données des sprints
	cd $(WEB_DIR) && php bin/console app:reset-metrics --sprints

reset-bugs: ## Réinitialise uniquement les données des bugs
	cd $(WEB_DIR) && php bin/console app:reset-metrics --bugs

# Commande pratique pour réinitialiser et resynchroniser
reset-and-sync: reset-metrics-force sync-all ## Réinitialise toutes les données et resynchronise

.DEFAULT_GOAL := help