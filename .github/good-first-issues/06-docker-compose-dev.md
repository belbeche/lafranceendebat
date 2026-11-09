# Fournir un environnement de développement Docker
<!-- labels: dx,good first issue -->

## Contexte
Démarrer le projet demande PHP, Composer et les extensions. Un conteneur simplifierait l'arrivée des contributeurs.

## À faire
Ajouter `docker-compose.yml` (et un `Dockerfile` si besoin) : PHP 8.2 avec les extensions requises, SQLite, `php -S 0.0.0.0:8000 -t public`. Aucun secret dans les fichiers (valeurs de `.env.example` seulement). Documenter dans le README en trois lignes.

## Résultat attendu
`docker compose up` démarre le site sur http://localhost:8000 après `composer install` et la création du schéma.

## Où
Racine du dépôt, `README.md`.

## Test
Sur une machine propre, suivre le README : la page d'accueil répond en 200.
