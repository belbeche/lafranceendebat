# Architecture de La France en Débat

Ce document est la carte du code. Il répond à trois questions : où est quoi, qu'est-ce qui ne doit jamais casser, et par où commencer pour contribuer. Il reste volontairement court : quand un détail change souvent, il vit dans le code (docblocks en tête de chaque classe), pas ici.

## Vue d'ensemble

Application Symfony 6.4 (PHP 8.2+), rendu côté serveur avec Twig et Bootstrap 5, JavaScript léger sans framework. Une base SQL via Doctrine, des e-mails via Symfony Mailer, les paiements via Stripe (pages hébergées, aucune carte ne passe par nous).

Le principe : un débat contient des arguments et des propositions sourcées ; les membres soutiennent, la modération garde le ton respectueux, et tout algorithme de classement est public (voir `docs-publics/ALGORITHMES.md`).

## Carte du code

| Dossier | Contenu | Pour y toucher |
|---|---|---|
| `src/Controller` | Une classe par domaine (débats, propositions, comptes...). Les routes sont listées dans le docblock de chaque classe. | Garder les contrôleurs fins : la logique va dans un service. |
| `src/Controller/Admin` | Espace modérateurs et administrateurs. | Toujours `#[IsGranted]`. |
| `src/Entity` | Modèle de données (une entité = une table). | Voir « Modifier la base » plus bas. |
| `src/Service` | La logique métier. | C'est ici qu'on ajoute les règles. |
| `src/Service/Algo` | Classement par tendance, similarité de textes. | Toute modification doit être reportée dans `docs-publics/ALGORITHMES.md`. |
| `src/Service/Audience` | Mesure d'audience sans cookie tiers et consentement maison. | Respecter `docs-publics/DONNEES_PERSONNELLES.md`. |
| `src/Command` | Tâches planifiées (purges, comptes inactifs, imports). | Voir `docs/CRON_ET_EXPLOITATION.md`. |
| `src/Form`, `src/Twig` | Formulaires et extensions Twig. | |
| `templates/` | Vues Twig. `emails/transac` pour les e-mails de service, `legal` pour les pages juridiques. | |
| `public/js`, `public/css` | JavaScript et styles. Pas de build obligatoire. | |
| `tests/e2e` | Scénarios de bout en bout, un fichier par thème. | Voir « Tester ». |
| `src/Archive`, `templates/_archive` | Ancien code d'un autre projet, conservé pour mémoire et exclu du dépôt public. | Ne pas modifier, ne pas importer. |

## Les règles qui ne doivent jamais casser

1. **Tout texte public passe par `ContentModerator`** avant d'être enregistré (insultes, menaces, haine, données personnelles). Un contrôleur qui accepte du texte d'un membre sans cette étape est un bogue.
2. **Les propos bloqués sont signalés** : `ModerationReporter` enregistre un `ModerationIncident`, prévient les modérateurs et envoie un rappel courtois à l'auteur. Il se déclenche tout seul quand le filtre bloque un texte soumis en POST par un membre connecté.
3. **Aucune action qui modifie des données en GET**, et un jeton CSRF sur chaque formulaire.
4. **Mesure d'audience** : les vues sont comptées sans cookie (empreinte quotidienne non réversible). Le temps de lecture n'est mesuré qu'avec le consentement du visiteur.
5. **Pas de traceur dans les e-mails** (ni pixel, ni lien redirigé).
6. **Données personnelles** : durées de conservation fixées et appliquées par `app:journal:purger` et `app:comptes:inactifs`. Toute nouvelle donnée personnelle doit être ajoutée à la politique de confidentialité et à `docs-publics/DONNEES_PERSONNELLES.md`.
7. **Secrets** : jamais dans le dépôt. Variables d'environnement ou `.env.local` (ignoré par Git). Modèle : `.env.example`.
8. **Les classements sont explicables** : aucune pondération cachée, aucun réglage manuel d'un score.

## Parcours principaux

- **Proposer** : `PropositionController::new` (formulaire en 3 étapes, `public/js/proposal-wizard.js`) → modération → alerte doublon (`SimilarityService`) → enregistrement en attente → validation par un médiateur → publication.
- **Soutenir** : `VoteController` / `PropositionSupportService` : un soutien par membre et par proposition (contrainte d'unicité en base).
- **Ouvrir un débat** : `DebatController::new` → `DebatObligations` vérifie les six obligations de sources officielles (Respecté, En cours, Pas encore).
- **Lecture** : `ViewTracker` compte la vue côté serveur ; `read-meter.js` envoie durée et défilement seulement si le consentement est donné.
- **Signaler un propos** : `ContentModerator::analyze` → `ModerationReporter::report` → table `moderation_incident`, e-mails, page `/admin/signalements-filtre`.

## Modifier la base

Le projet n'utilise pas les migrations pour l'instant : en développement, `php bin/console doctrine:schema:update --dump-sql` pour voir le changement, puis `--force`. En production, relire le SQL avant de l'exécuter et faire une sauvegarde. Ne jamais supprimer une table qui existe encore dans une autre branche sans en parler dans la demande de fusion.

## Tester

```bash
APP_ENV=test MAIL_FROM=no-reply@lafranceendebat.fr php tests/e2e/e2e_moderation.php
```

Chaque fichier de `tests/e2e` est un scénario autonome qui affiche `OK` ou `KO` par vérification et se termine en erreur s'il y a un `KO`. Quand vous ajoutez une fonction, ajoutez le scénario qui la protège. Vider `var/cache/test/pools` entre deux exécutions si un test dépend du cache.

## Où ajouter quoi

- **Un mot interdit** : `config/services.yaml`, clé `forbidden_words` (les `_` valent des espaces). Éviter les mots qui ont un sens légitime dans un débat, et vérifier avec le test de faux positifs de `tests/e2e/e2e_moderation.php`.
- **Une règle du filtre** (menace, haine) : `src/Service/ContentModerator.php`, tableaux `THREAT_PATTERNS` et `HATE_PATTERNS`. Un motif doit viser une formulation, jamais une opinion.
- **Un e-mail de service** : une méthode dans `TransactionalMailer` et un gabarit dans `templates/emails/transac`.
- **Une page admin** : un contrôleur dans `src/Controller/Admin` avec `#[IsGranted]` et un lien dans `templates/admin/_nav.html.twig`.
- **Une nouvelle donnée collectée** : voir la règle 6.

## Pour commencer

Lisez `CONTRIBUTING.md`, installez le projet avec le `README.md`, puis cherchez les tickets marqués « bon premier ticket ». Une demande de fusion petite, avec son test et sa documentation, est relue plus vite qu'une grosse.
