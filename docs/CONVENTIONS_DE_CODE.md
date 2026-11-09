# Conventions de code

Ces règles viennent des pratiques courantes des projets ouverts (Symfony, Contributor Covenant, ARCHITECTURE.md des projets Rust) adaptées à ce dépôt. Elles servent à ce que n'importe qui puisse reprendre le travail d'un autre sans lui poser de question.

## Documenter le code

- **Chaque classe a un docblock** : à quoi elle sert, dans quel cas on l'utilise, ce qu'elle ne fait pas. Pour un contrôleur, les routes et l'accès ; pour une entité, la table.
- **Expliquer le pourquoi, pas le comment.** Le code dit ce qu'il fait ; le commentaire dit pourquoi on le fait ainsi (règle de la CNIL, limite de Stripe, piège connu).
- **Une règle métier ou légale cite sa source** (article, recommandation, page de documentation) dans le commentaire, pour que le relecteur puisse la vérifier.
- **Pas de commentaire personnel** : on n'écrit ni prénom, ni « à la demande de ». L'historique Git garde l'auteur ; le code garde la raison.
- **Les TODO** sont écrits `TODO(sujet): ce qu'il reste à faire et pourquoi`, ou mieux, deviennent un ticket.

## Écrire le code

- PHP 8.2, types déclarés partout, `readonly` quand c'est possible, injection par constructeur.
- Contrôleurs fins, logique dans les services, requêtes dans les dépôts.
- Noms en anglais pour le code (classes, méthodes), français pour les textes affichés et les docblocks.
- Un texte affiché à l'utilisateur est écrit à la deuxième personne du pluriel, sans jargon, avec une piste pour avancer (pas seulement un refus).
- Pas de nouvelle dépendance sans l'expliquer dans la demande de fusion (licence compatible AGPL, maintenance, poids).

## Sécurité et vie privée en bref

- Entrées validées côté serveur, sorties échappées par Twig (pas de `|raw` sans relecture).
- Pas de requête SQL construite par concaténation.
- Un secret n'apparaît ni dans le code, ni dans un test, ni dans un journal.
- Une vulnérabilité se signale en privé : voir `SECURITY.md`.

## Messages de commit et demandes de fusion

- Commit : une phrase à l'impératif qui dit le changement (« Bloquer les lettres espacées dans le filtre »). Le corps explique le pourquoi.
- Demande de fusion : un seul sujet, le test qui la protège, la documentation mise à jour (`docs-publics/` si un algorithme ou une donnée change).
- Respecter `CODE_OF_CONDUCT.md` dans les relectures : on critique le code, jamais la personne.
