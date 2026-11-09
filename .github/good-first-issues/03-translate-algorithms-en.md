# Traduire le document des algorithmes en anglais
<!-- labels: documentation,good first issue -->

## Contexte
`docs-publics/ALGORITHMES.md` décrit les paramètres exacts des classements (obligation de transparence). Il n'existe qu'en français.

## À faire
Créer `docs-publics/ALGORITHMES.en.md`, traduction fidèle. **Ne changez aucun chiffre** (demi-vie, seuils, pondérations) ni aucun nom de classe ou de fichier. Ajoutez un lien vers la traduction en tête des deux fichiers.

## Résultat attendu
Un lecteur anglophone comprend chaque classement. Les valeurs sont identiques à celles du code.

## Où
`docs-publics/`, valeurs de référence dans `src/Service/Algo/`.

## Test
Relire chaque paramètre cité et le comparer à la constante correspondante dans `src/Service/Algo/TrendingService.php` et `SimilarityService.php`.
