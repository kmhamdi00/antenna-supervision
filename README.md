# Antenna Supervision — API + Dashboard

API REST de pilotage du statut des antennes du réseau mobile (100 000+ antennes),
et interface légère de supervision pour les techniciens.

## Sommaire

- [Choix technique](#choix-technique)
- [Note technique : concurrence sur l'intervention active](#note-technique--concurrence-sur-lintervention-active)
- [Lancer le projet](#lancer-le-projet)
- [Lancer les tests](#lancer-les-tests)
- [Exemples curl](#exemples-curl)
- [Arbitrages et limites connues](#arbitrages-et-limites-connues)

## Choix technique

Symfony a été retenu pour sa capacité à structurer une API métier robuste, maintenable
et facilement testable, avec une séparation claire des responsabilités.

Les principaux choix sont les suivants :

- **Doctrine ORM + Migrations** : permet de conserver une maîtrise fine du modèle
  relationnel et du schéma PostgreSQL. Dans ce projet, la contrainte métier
  « une seule période active par antenne » est renforcée au niveau de la base par
  un **index unique partiel** sur `antenna_id` avec `ended_at IS NULL`.
  Cette garantie est volontairement portée par la base de données afin qu'elle
  reste vraie quel que soit le point d'entrée de l'application.

- **Symfony Validator** : les règles de validation sont séparées de la logique
  métier, ce qui permet de rejeter les requêtes invalides de manière cohérente
  et de maintenir un code métier lisible.

- **Gestion centralisée des exceptions** : un subscriber sur
  `kernel.exception` permet de transformer les exceptions applicatives en
  réponses HTTP JSON homogènes. Les erreurs métier, de validation et les erreurs
  inattendues suivent ainsi un contrat d'API explicite.

- **Security Component** : la gestion de l'authentification et des autorisations
  s'appuie sur les mécanismes natifs de Symfony. Les règles d'accès restent
  explicites et découplées de la logique métier.

- **Architecture orientée services** : la logique métier n'est pas concentrée
  dans les contrôleurs. Les contrôleurs restent minces et délèguent les
  opérations aux services applicatifs, ce qui facilite les tests unitaires,
  l'évolution fonctionnelle et la maintenance.

Le choix de Symfony n'est donc pas uniquement lié au framework lui-même, mais
à sa capacité à fournir une architecture structurée tout en laissant la maîtrise
des contraintes métier critiques à PostgreSQL.

## Note technique : concurrence sur l'intervention active

La règle « une seule intervention active par antenne » est garantie à deux niveaux :

1. **Côté application** : avant de créer une intervention, on vérifie qu'il n'en existe
   pas déjà une active pour l'antenne.

2. **Côté base de données** : un index unique partiel PostgreSQL garantit qu'une seule
   intervention avec `ended_at IS NULL` peut exister pour une même antenne.

Le contrôle applicatif permet de retourner rapidement un message explicite dans le cas
normal. Cependant, il ne suffit pas en cas de requêtes simultanées : deux requêtes
pourraient effectuer le contrôle en même temps et toutes les deux passer.

L'index PostgreSQL constitue donc la **garantie finale contre cette race condition**.
Si deux requêtes concurrentes tentent de créer une intervention active pour la même
antenne, PostgreSQL n'en autorise qu'une et la seconde génère une violation de contrainte.

Cette erreur est ensuite transformée en réponse **HTTP 409** avec le code
`ACTIVE_INTERVENTION_EXISTS`.

Pour la clôture d'une intervention, un **verrou optimiste Doctrine** basé sur le champ
`version` permet également d'éviter qu'une même intervention soit clôturée
simultanément par deux requêtes. En cas de modification concurrente, l'API retourne
un **HTTP 409** avec le code `CONCURRENT_MODIFICATION`.

## Lancer le projet

Prérequis : Docker + Docker Compose V2.

```bash
docker compose up --build
```

Ceci va :

1. builder l'image PHP 8.3,
2. installer les dépendances (`composer install`),
3. démarrer le serveur PHP intégré sur `http://localhost:8000`.

Les migrations ne sont **pas** lancées automatiquement (voir plus bas) — appliquez-les manuellement après le premier démarrage :

```bash
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

Le dashboard est accessible sur **http://localhost:8000/**.
L'API est accessible sur **http://localhost:8000/api/...**.

La clé API par défaut (dev uniquement, à changer en production) est
`super-secret-api-key-change-me` (variable d'environnement `API_KEY`).

## Lancer les tests

```bash
docker compose exec app php bin/console doctrine:database:create --env=test --if-not-exists
docker compose exec app php bin/console doctrine:migrations:migrate --env=test --no-interaction
docker compose exec app php bin/phpunit
```

Les tests couvrent :

- la création nominale d'une intervention (bascule de l'antenne à `DOWN`),
- le rejet d'une deuxième intervention active sur la même antenne (`409`),
- la clôture nominale (bascule de l'antenne à `UP`),
- le rejet d'une double clôture (`409`),
- l'absence de clé API (`401`),
- un payload de création invalide (`422`).

## Exemples curl

Voir [`docs/API.md`](docs/API.md) pour la documentation complète.

```bash
# Créer une antenne de test (via psql, pas d'endpoint de création d'antenne dans ce périmètre)
docker compose exec database psql -U app -d antenna_supervision -c "INSERT INTO antenna (name, city, status, created_at) VALUES ('Antenne Test', 'Paris', 'UP', now());"

# Lister les antennes
curl "http://localhost:8000/api/antennas?city=Paris"

# Créer une intervention
curl -X POST http://localhost:8000/api/interventions -H "Content-Type: application/json" -H "X-API-KEY: super-secret-api-key-change-me" -d '{"antenna_id":1,"description":"Panne.","technician_identity":"mkhaled","priority":"HIGH"}'

# Clôturer l'intervention n°1
curl -X PATCH http://localhost:8000/api/interventions/1/close -H "X-API-KEY: super-secret-api-key-change-me"
```

## Arbitrages et limites connues

- Les antennes sont supposées provisionnées par un système externe.
- Une clé API statique est utilisée pour simplifier l'authentification.
- Le dashboard utilise cette clé pour appeler l'API protégée ; en production,
  une authentification utilisateur serait préférable.
- L'API des antennes est paginée avec un maximum de 100 éléments par page,
  compte tenu du volume attendu (> 100 000 antennes).
- Le filtre par ville utilise une égalité stricte ; une recherche partielle
  pourra être ajoutée selon les besoins.
- La suppression n'est pas implémentée afin de préserver l'historique.
