# Ajouter des tests PHPUnit pour le filtre de modération
<!-- labels: tests,good first issue -->

## Contexte
`src/Service/ContentModerator.php` bloque les insultes, menaces, haine et données personnelles. Ses cas de test sont aujourd'hui dans un script (`tests/e2e/e2e_moderation.php`), pas dans PHPUnit.

## À faire
Créer `tests/Service/ContentModeratorTest.php` avec des *data providers* :
- des phrases qui **doivent être bloquées** (variantes : lettres espacées, chiffres à la place des lettres, lettres répétées) ;
- des phrases qui **doivent passer** (désaccord vif mais respectueux, mots légitimes comme « Mongols », sigles comme « PD » dans un autre sens).

Instanciez `new ContentModerator(['connard'])` sans dépendance : le service s'utilise sans le conteneur.

## Résultat attendu
`vendor/bin/phpunit tests/Service/ContentModeratorTest.php` passe, avec au moins 10 cas bloqués et 10 cas acceptés.

## Où
`tests/Service/` (nouveau dossier), `src/Service/ContentModerator.php` (à lire).

## Test
Lancer PHPUnit. Ajouter un faux positif volontaire pour vérifier que le test échoue bien.
