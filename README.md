# La France en Débat

**Français** | [English](README.en.md)

![La France en Débat](public/img/lfed-banner-1000.jpg)

[Site en ligne](https://lafranceendebat.fr) · [Code source (AGPL-3.0)](https://github.com/belbeche/lafranceendebat) · [Premiers tickets](https://github.com/belbeche/lafranceendebat/labels/good%20first%20issue)

Plateforme citoyenne de débat apaisé, de propositions sourcées et de suivi des engagements. Symfony 6.4, PHP 8.2+, Bootstrap 5.

Ce code est ouvert pour que chacun puisse **vérifier comment la plateforme fonctionne** (classements, collecte de données, modération) et **contribuer**. Les secrets, données de membres et réglages de production ne sont pas dans ce dépôt.

## Démarrer en 5 minutes

```bash
git clone <url-du-depot> && cd la-france-en-debat
composer install
cp .env.example .env.local        # puis changez APP_SECRET
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:update --force
symfony serve                      # ou: php -S localhost:8000 -t public
```

Par défaut : base SQLite locale, aucun e-mail réellement envoyé (`MAILER_DSN=null://null`), aucun paiement (pas de clé Stripe). Vous n'avez besoin d'aucun compte externe pour contribuer.

## Tests

```bash
APP_ENV=test php bin/console doctrine:schema:create
APP_ENV=test MAIL_FROM=no-reply@example.org php tests/e2e/e2e_audience.php
```

Chaque fichier `tests/e2e/e2e_*.php` est un scénario autonome qui affiche `OK` ou `KO`. Videz `var/cache/test` entre deux exécutions.

## Comprendre le projet

- [`docs-publics/FONCTIONNALITES.md`](docs-publics/FONCTIONNALITES.md) : ce que fait chaque fonction, en langage simple, et où se trouve le code.
- [`docs-publics/ALGORITHMES.md`](docs-publics/ALGORITHMES.md) : les paramètres exacts de chaque classement et calcul automatique.
- [`docs-publics/DONNEES_PERSONNELLES.md`](docs-publics/DONNEES_PERSONNELLES.md) : ce qui est collecté, pourquoi, combien de temps.
- [`docs-publics/ARCHITECTURE.md`](docs-publics/ARCHITECTURE.md) : la carte du code, les règles à ne jamais casser, où ajouter quoi.
- [`docs-publics/CONVENTIONS_DE_CODE.md`](docs-publics/CONVENTIONS_DE_CODE.md) : comment documenter, écrire et proposer du code.
- [`CONTRIBUTING.md`](CONTRIBUTING.md) : comment proposer un changement.
- [`SECURITY.md`](SECURITY.md) : comment signaler une faille (merci de ne pas ouvrir de ticket public).

## Contribuer

Contribuer ne coûte rien, il n'y a pas de contrat à signer. Vos contributions restent sous la même licence que le projet (AGPL-3.0). Les tickets débutants sont marqués [`good first issue`](https://github.com/belbeche/lafranceendebat/labels/good%20first%20issue) (liste dans [`docs-publics/TICKETS_DEBUTANTS.md`](docs-publics/TICKETS_DEBUTANTS.md)).

## Licence

GNU AGPL v3 : vous pouvez lire, modifier et réutiliser le code, à condition de publier vos modifications si vous proposez le service en ligne. Voir `LICENSE`.
