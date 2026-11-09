---
title: "La France en Débat : spécification technique"
subtitle: "Version du 8 novembre 2026"
---

# 1. Objet

Plateforme citoyenne de débat apaisé, de propositions sourcées et de suivi des engagements. Ce document décrit l'architecture réellement en place, les algorithmes, les données personnelles traitées et l'exploitation.

# 2. Pile technique

| Élément | Choix |
|---|---|
| Framework | Symfony 6.4, PHP 8.2 minimum (production en 8.4) |
| Base de données | MySQL 8 (production), SQLite (tests) ; schéma géré par Doctrine |
| Interface | Twig, Bootstrap 5, JavaScript sans framework (fichiers statiques sous `public/js`) |
| E-mail | Amazon SES via Symfony Mailer ; aucun pixel de suivi |
| Paiement | Stripe Checkout hébergé et Stripe Connect (API REST, sans SDK) |
| Sécurité HTTP | Politique CSP avec nonce, en-têtes de durcissement, limiteurs de débit |
| Hébergement | Shiftek IT (France) ; CDN et pays via en-tête `CF-IPCountry` si Cloudflare |

# 3. Modules

| Module | Rôle | Éléments principaux |
|---|---|---|
| Débats | Question neutre, faits sourcés, arguments pour/contre/nuancés | `Debat`, `Argument`, `DebatObligations` |
| Propositions | Dépôt en 3 étapes, soutiens, modération a priori | `Proposition`, `Vote`, `ContentModerator`, `SimilarityService` |
| Registre et classement | Artisans de paix, tendances | `RankingService`, `TrendingService` |
| Lectures | Compteur de vues, temps de lecture (consenti) | `ContentView`, `ViewTracker`, `read-meter.js` |
| Consentement | Cookie maison, preuve, retrait | `ConsentManager`, `ConsentRecord`, `ConsentController` |
| Sécurité du compte | E-mails jetables, honeypot, MX, confirmation signée, restriction, journal | `DisposableEmailPolicy`, `UserJournal`, `AdminAlerter`, `UserEvent` |
| Conservation | Avis et suppression des comptes inactifs | `ComptesInactifsCommand`, `InactivityController`, `AccountEraser` |
| Agenda | Import d'événements nationaux avec vérification automatique | `NationalEventImporter`, `EventVerifier` |
| Enquêtes | Création, aperçu obligatoire, envoi, résultats | `Survey`, `SurveyResponse`, `SurveyResults` |
| Statistiques | Tableau de bord d'administration | `AdminStats` |
| Paiements | Contributions de soutien, commandes de services, commission, remboursements | `StripeGateway`, `MarketplaceService`, `DonationService`, webhook signé |
| Lettre et contacts | Invitations d'acteurs publics, désinscription en un clic | `LettreMailer`, `ContactApplicationMailer` |

# 4. Algorithmes

## 4.1 Tendance des débats (`TrendingService`)

Pour chaque débat, sur une fenêtre de 14 jours :

- Poids : lecture 1, lecture engagée 3, argument validé 5.
- Décroissance exponentielle : poids multiplié par 0,5^(âge en jours / 3).
- Somme brute `R`, puis `score = ln(1 + R) × m`.
- Multiplicateur de qualité `m = 0,6 + 0,8 × W`, où `W` est la borne basse de Wilson (z = 1,96) de la part de lectures engagées. Appliqué seulement si au moins 5 lectures ont été mesurées, sinon `m = 1`.
- Une lecture est engagée si le lecteur a lu au moins 20 s en parcourant la moitié de la page, ou au moins 60 s.

Justification : la décroissance exponentielle (famille Hacker News / Reddit « hot ») fait suivre l'actualité ; l'échelle logarithmique limite l'effet des gros volumes ; la borne de Wilson évite qu'un sujet lu deux fois par deux curieux écrase un sujet lu cent fois par des lecteurs moyens. Aucune opinion n'entre dans le calcul.

## 4.2 Intérêt affiché publiquement (`ViewTracker::stats`)

Lectures, temps de lecture moyen (sur les lecteurs consentants), temps cumulé, part de lectures approfondies, part de départs en moins de 10 s. Étiquette : « Très suivi » (Wilson ≥ 0,5), « Suivi » (≥ 0,3), « Peu lu jusqu'au bout », ou « pas assez de lectures mesurées » sous 5 mesures.

## 4.3 Propositions proches (`SimilarityService`)

TF-IDF (titre pondéré double, description, thème) et similarité cosinus, avec retrait des mots vides, normalisation des accents et racinisation légère. Seuil « doublon » 0,55 : avertissement avant dépôt, une fois par titre. Seuil « proche » 0,18 : liste sous la fiche et suggestions en direct pendant la saisie. Calcul à la volée sur les 600 propositions validées les plus soutenues.

## 4.4 Obligations d'un débat (`DebatObligations`)

Six points évalués en direct, chacun « Respecté / En cours / Pas encore » : question claire, exposé d'au moins 100 caractères, source officielle (liste blanche de domaines, sous-domaines acceptés, domaines piégés refusés), faits datés ou chiffrés, source officielle propre au thème, autorité compétente. Les quatre premiers conditionnent la relecture.

## 4.5 Artisans de paix (`RankingService`)

1 point par remerciement d'une personne distincte (compte de plus de 7 jours, plafond 50), 2 points par reformulation de la position adverse (plafond 20). Participation sur option. Mesure la qualité du dialogue, pas la popularité.

## 4.6 Détection de comptes suspects

Domaine jetable (liste locale), MX absent, honeypot, limiteurs de débit, tentatives de connexion répétées : avertissement par e-mail avant restriction, restriction avec paiements bloqués, désactivation après délai, alerte aux administrateurs à chaque action.

# 5. Données personnelles et durées

| Donnée | Finalité | Durée |
|---|---|---|
| Compte (e-mail, nom, mot de passe haché) | Service | Jusqu'à suppression ; avis après 24 mois d'inactivité, suppression à 30 jours sans réponse |
| Journal de sécurité (IP, pays, pages) | Sécurité | 12 mois |
| Lectures (empreinte quotidienne) | Intérêt public | 13 mois |
| Temps de lecture | Intérêt public, sur consentement | 13 mois |
| Preuve de consentement | Conformité | 13 mois |
| Pièces comptables, reçus | Obligation légale | 10 ans |

Aucun traceur tiers. Aucun pixel dans les e-mails. Cookie de consentement propre au site, 6 mois.

# 6. Sécurité

CSRF sur tous les formulaires, liens signés avec expiration (`UriSigner`), webhooks Stripe et SES vérifiés par signature, mots de passe hachés, sessions invalidables à distance, journal par utilisateur, CSP à nonce, limiteurs de débit, modération a priori des textes. Aucune donnée bancaire n'est reçue ni stockée.

# 7. Exploitation

Tâches planifiées (voir `docs/CRON_ET_EXPLOITATION.md`) : import de l'agenda (05:00), comptes suspects (04:30), purge du journal, des lectures et des preuves (04:45), comptes inactifs (03:15), envoi des enquêtes (toutes les 5 minutes). Mise à jour de schéma : `php bin/console doctrine:schema:update --force`. Tests : scripts `tests/e2e/e2e_*.php` (exécuter en `APP_ENV=test`).

# 8. Limites connues

- Les lecteurs sont uniques par jour, pas sur la durée (conséquence volontaire du respect de la vie privée).
- L'import de l'agenda dépend des flux ICS déclarés ; sans flux, seul le calendrier national intégré est utilisé.
- La page d'administration des articles historiques (`/admin/posts`) est à réparer.
- Les montants et seuils de l'algorithme de tendance sont des réglages à ajuster avec l'usage réel.
