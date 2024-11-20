# commandes utiles
Création de projet symfony
````
symfony new --webapp web --no-git
````

TODO 

- [x] Initier le projet frankenphp + docker
- [x] Récupérer la data du calandar google
- [x] Pouvoir avoir des info par dates mais muliple
- [x] Historique des points de sprint
- [x] Afficher form gestion date calendar
- [x] Edit/delete nom team user
- [x] Mobile
- [x] Homepage
- [ ] Comprendre bootstrap

# Infos utiles

Organisation projet
https://symfony.com/doc/current/best_practices.html#use-environment-variables-for-infrastructure-configuration

Variables secretes
https://symfony.com/doc/current/configuration/secrets.html

Bundles
https://symfony.com/doc/current/bundles.html
Plus de bundles !!
A priori utiliser les namesspace suffit.

Create/update entity
````
symfony console doctrine:migrations:diff
symfony console doctrine:migrations:migrate
````
~~symfony console doctrine:schema:validate~~

si souci 
````
php bin/console doctrine:schema:update --force
````

check des migrations faites:
````
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:version --add --all
php bin/console doctrine:schema:update --dump-sql
````

# structure projet
````
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
````