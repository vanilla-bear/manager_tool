# Nom du conteneur (remplace par le nom de ton conteneur)
CONTAINER_NAME=symfony_frankenphp

# UID et utilisateur par défaut pour la connexion
USER_ID=1000
USER_NAME=localuser

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