# Ajouter une intégration continue (GitHub Actions)
<!-- labels: ci,good first issue -->

## Contexte
Aucune vérification automatique ne tourne sur les demandes de fusion.

## À faire
Créer `.github/workflows/ci.yml` qui, à chaque `push` et `pull_request` :
1. installe PHP 8.2 et Composer ;
2. lance `composer validate` et `composer install --no-interaction` ;
3. lance `php bin/console lint:container`, `lint:twig templates/` (en excluant `templates/_archive` si présent) et `lint:yaml config/` ;
4. lance PHPUnit s'il existe des tests.

## Résultat attendu
Une demande de fusion affiche une coche verte ou rouge. Le badge est ajouté en tête du README.

## Où
`.github/workflows/ci.yml`, `README.md`.

## Test
Ouvrir une demande de fusion de test avec une erreur Twig volontaire : la CI doit échouer.
