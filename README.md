# Todofony — API REST Symfony 7.4

API REST de gestion de tâches (todo list) construite avec **Symfony 7.4**, **Doctrine ORM**, **PostgreSQL** et authentification **JWT** (cookies HttpOnly).

## Table des matières

- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Docker](#docker)
- [Base de données](#base-de-données)
- [Authentification JWT](#authentification-jwt)
- [Endpoints API](#endpoints-api)
- [Structure du projet](#structure-du-projet)

## Prérequis

- **PHP** >= 8.2
- **Composer**
- **PostgreSQL** 16+
- **Symfony CLI** (optionnel, recommandé)
- **Docker & Docker Compose** (optionnel)

## Installation

```bash
# Cloner le dépôt
git clone <url-du-repo>
cd todofony

# Installer les dépendances
composer install

# Copier le fichier d'environnement
cp .env .env.local
```

## Configuration

Configurer les variables d'environnement dans `.env.local` :

```dotenv
# Base de données PostgreSQL
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/todofony?serverVersion=16&charset=utf8"

# CORS — origines autorisées (regex)
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'

# Clés JWT (voir section Authentification)
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=votre_passphrase
```

## Docker

Le projet inclut une configuration Docker Compose pour PostgreSQL et Mailpit (dev) :

```bash
# Démarrer les services
docker compose up -d

# Services disponibles :
# - PostgreSQL : localhost:5432
# - Mailpit (web UI) : http://localhost:8025
# - Mailpit (SMTP) : localhost:1025
```

| Service    | Image                | Port(s)       |
|------------|----------------------|---------------|
| `database` | `postgres:16-alpine` | `5432`        |
| `mailer`   | `axllent/mailpit`    | `1025`, `8025`|

## Base de données

```bash
# Créer la base de données
php bin/console doctrine:database:create

# Exécuter les migrations
php bin/console doctrine:migrations:migrate
```

### Schéma

**Table `user`**
| Colonne      | Type              | Contraintes       |
|--------------|-------------------|-------------------|
| `id`         | integer (auto)    | PK                |
| `email`      | varchar(180)      | unique, not null  |
| `roles`      | json              | not null          |
| `password`   | varchar           | not null          |
| `created_at` | datetime_immutable| not null          |
| `updated_at` | datetime_immutable| not null          |

**Table `task`**
| Colonne      | Type              | Contraintes                        |
|--------------|-------------------|------------------------------------|
| `id`         | integer (auto)    | PK                                 |
| `title`      | varchar(255)      | not null                           |
| `status`     | string (enum)     | `todo`, `doing`, `done`            |
| `person_id`  | integer           | FK → user(id), not null            |
| `created_at` | datetime_immutable| not null                           |
| `updated_at` | datetime_immutable| not null                           |

> Contrainte d'unicité sur `(title, person_id)` — un utilisateur ne peut pas avoir deux tâches avec le même titre.

## Authentification JWT

Le projet utilise **LexikJWTAuthenticationBundle** avec des tokens RS256 stockés dans des cookies HttpOnly.

```bash
# Générer les clés JWT
php bin/console lexik:jwt:generate-keypair
```

| Paramètre        | Valeur          |
|-------------------|-----------------|
| Algorithme        | RS256           |
| Durée du token    | 3600s (1 heure) |
| Stockage          | Cookie `BEARER` (HttpOnly, SameSite=Lax) |
| Extracteurs       | Cookie + Header `Authorization: Bearer` |

## Endpoints API

### Authentification (`/api`)

| Méthode | Route           | Auth requise | Description                        |
|---------|-----------------|--------------|------------------------------------|
| `POST`  | `/api/register` | Non          | Inscription (email, password, confirmPassword) |
| `POST`  | `/api/login`    | Non          | Connexion (email, password) → cookie JWT |
| `POST`  | `/api/logout`   | Oui          | Déconnexion (supprime le cookie)   |
| `GET`   | `/api/me`       | Oui          | Profil de l'utilisateur connecté   |

### Tâches (`/api/tasks`) — `ROLE_USER` requis

| Méthode  | Route                   | Description                                  |
|----------|-------------------------|----------------------------------------------|
| `GET`    | `/api/tasks`            | Liste des tâches de l'utilisateur (triées par date de mise à jour) |
| `POST`   | `/api/tasks/create`     | Créer une tâche (title)                      |
| `PATCH`  | `/api/tasks/edit/{id}`  | Modifier une tâche (title, status)           |
| `DELETE` | `/api/tasks/remove/{id}`| Supprimer une tâche                          |

### Exemples de requêtes

```bash
# Inscription
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "secret", "confirmPassword": "secret"}'

# Connexion
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -c cookies.txt \
  -d '{"email": "user@example.com", "password": "secret"}'

# Lister les tâches
curl http://localhost:8000/api/tasks -b cookies.txt

# Créer une tâche
curl -X POST http://localhost:8000/api/tasks/create \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"title": "Ma nouvelle tâche"}'

# Modifier une tâche
curl -X PATCH http://localhost:8000/api/tasks/edit/1 \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"title": "Titre modifié", "status": "doing"}'

# Supprimer une tâche
curl -X DELETE http://localhost:8000/api/tasks/remove/1 -b cookies.txt
```

### CORS

Les requêtes cross-origin sont configurées via **NelmioCorsBundle** :

- **Origines** : regex configurable (`CORS_ALLOW_ORIGIN`)
- **Méthodes** : GET, POST, PUT, PATCH, DELETE, OPTIONS
- **Headers** : Content-Type, Authorization
- **Credentials** : activé (envoi des cookies)

## Structure du projet

```
todofony/
├── config/
│   ├── packages/          # Configuration des bundles
│   ├── routes/            # Routes framework, sécurité, profiler
│   └── jwt/               # Clés JWT (private.pem, public.pem)
├── migrations/            # Migrations Doctrine
├── src/
│   ├── Controller/API/    # AuthController, TaskController
│   ├── DTO/               # AuthDTO, TaskCreateDTO, TaskPatchDTO
│   ├── Entity/            # User, Task
│   ├── EventListener/     # EntityListener, JWT listeners
│   ├── Model/             # TaskStatusEnum (todo, doing, done)
│   ├── Repository/        # UserRepository, TaskRepository
│   └── Service/           # AuthService, TaskService
├── tests/                 # Tests PHPUnit
├── compose.yaml           # Docker Compose (PostgreSQL)
├── compose.override.yaml  # Docker Compose override (dev: Mailpit)
└── composer.json
```
