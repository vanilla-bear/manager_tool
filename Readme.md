# Manager Tools

Application Symfony pour la gestion d'équipe et le suivi des sprints.

## Prérequis

- Docker et Docker Compose
- PHP 8.2+
- Symfony CLI
- Composer

## Installation

1. Cloner le projet
```bash
git clone [URL_DU_REPO]
cd manager_tools
```

2. Lancer l'environnement Docker
```bash
make up
```

3. Installer les dépendances
```bash
make install
```

4. Accéder à l'application
- URL: https://localhost:8443/

## Commandes disponibles

Utiliser `make help` pour voir toutes les commandes disponibles.

Commandes principales :
```bash
make up        # Démarrer l'environnement
make down      # Arrêter l'environnement
make exec      # Se connecter au conteneur
make logs      # Voir les logs
```

## Base de données

### Commandes Doctrine

Création/mise à jour des entités :
```bash
symfony console doctrine:migrations:diff
symfony console doctrine:migrations:migrate
```

Vérification des migrations :
```bash
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:version --add --all
php bin/console doctrine:schema:update --dump-sql
```

## Structure du projet

```
src/
├── Controller/
│   ├── TimePeriodController.php
│   └── TeamController.php
├── Entity/
│   ├── TimePeriod.php
│   └── TeamMember.php
├── Repository/
│   ├── TimePeriodRepository.php
│   └── TeamMemberRepository.php
├── Service/
│   ├── GoogleCalendarService.php
│   └── TimePeriodCapacityCalculator.php
└── Form/
    └── TeamMemberType.php
    └── TimePeriodType.php
```

## Documentation

- [Best Practices Symfony](https://symfony.com/doc/current/best_practices.html#use-environment-variables-for-infrastructure-configuration)
- [Gestion des secrets](https://symfony.com/doc/current/configuration/secrets.html)
- [Documentation des bundles](https://symfony.com/doc/current/bundles.html)

## TODO

- [x] Initier le projet frankenphp + docker
- [x] Récupérer la data du calandar google
- [x] Pouvoir avoir des info par dates mais muliple
- [x] Historique des points de sprint
- [x] Afficher form gestion date calendar
- [x] Edit/delete nom team user
- [x] Mobile
- [x] Homepage
- [ ] Comprendre bootstrap