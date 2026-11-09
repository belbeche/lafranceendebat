# Ajouter les propositions validées au sitemap.xml
<!-- labels: seo,good first issue -->

## Contexte
`/sitemap.xml` (`src/Controller/SeoController.php`) liste les débats, tribunes et dossiers, mais pas les propositions publiées.

## À faire
Ajouter les propositions au statut `Proposition::STATUS_VALIDATED` (route `app_proposition_show`, paramètre `id`), avec `lastmod` si une date de mise à jour existe. Ne jamais lister une proposition en attente, rejetée ou retirée. Limiter à 1000 entrées.

## Résultat attendu
Le sitemap contient `/propositions/{id}` pour les propositions validées uniquement.

## Où
`src/Controller/SeoController.php`, `src/Entity/Proposition.php`.

## Test
Créer un script dans `tests/e2e/` (modèle : `e2e_moderation.php`) qui crée une proposition validée et une en attente, puis vérifie la présence de la première et l'absence de la seconde dans `/sitemap.xml`.
