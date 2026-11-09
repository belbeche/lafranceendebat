# Créer une commande qui détecte les traductions manquantes
<!-- labels: i18n,good first issue -->

## Contexte
Le site est traduit en plusieurs langues (`translations/messages.*.yaml`). Rien ne signale les clés présentes en français mais absentes ailleurs.

## À faire
Créer `src/Command/TranslationsCheckCommand.php` (`app:translations:check`) : lit `translations/messages.fr.yaml`, compare chaque langue, affiche les clés manquantes par langue, et retourne un code de sortie 1 s'il en manque. Option `--locale=en` pour une seule langue.

## Résultat attendu
La commande liste les manques et se termine en erreur s'il y en a.

## Où
`src/Command/`, `translations/`. Suivre le style de `src/Command/ComptesInactifsCommand.php`.

## Test
Supprimer une clé dans `messages.en.yaml` : la commande doit la signaler.
