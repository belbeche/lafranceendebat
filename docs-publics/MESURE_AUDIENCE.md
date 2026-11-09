# Mesure d'audience : fonctionnement, anonymisation et opposition

Ce document prouve, code à l'appui, comment la plateforme mesure sa fréquentation sans outil tiers et sans cookie de suivi. Il sert de pièce technique en cas de contrôle (RGPD, CNIL) et de guide pour les contributeurs : **toute modification de ces fichiers doit mettre à jour ce document et la politique de confidentialité** (`templates/legal/privacy_policy.html.twig`, section « Mesure d'audience interne »).

## 1. Principe

La mesure se fait **côté serveur**, à partir de la requête HTTP reçue. Elle n'utilise ni cookie, ni `localStorage`, ni identifiant persistant, ni script externe. Les statistiques sont des totaux, jamais rattachés à un compte.

| Mesure | Code | Données écrites | Conservation |
|---|---|---|---|
| Visites par page | `src/Service/VisitorTrackerService.php::trackVisit()`, appelé par `src/EventSubscriber/VisitorTrackingSubscriber.php` | date, nom de route (pas l'URL), User-Agent (255 car. max), **IP anonymisée** | 90 jours (`cleanupOldVisits(90)`, commande `app:cleanup-visits`) |
| Lectures de débats et propositions | `src/Service/Audience/ViewTracker.php::recordView()` | empreinte du jour (`sha1(IP | User-Agent | secret | date)`), type et id du contenu | 13 mois (`app:journal:purger`) |
| Temps de lecture et défilement | `ViewTracker::recordRead()`, appelé par `POST /mesure/lecture` | secondes et % de défilement, rattachés à l'empreinte du jour | 13 mois, **uniquement avec consentement** (`ConsentManager::hasAudience()`) |

## 2. Anonymisation de l'adresse IP

Fonction `VisitorTrackerService::anonymize()` :

- IPv4 : le 4e octet est mis à zéro (`203.0.113.57` devient `203.0.113.0`) ;
- IPv6 : seuls les 48 premiers bits sont conservés ;
- adresse invalide : `0.0.0.0`.

L'adresse complète n'est **jamais** écrite dans la table des visites. Elle n'est utilisée qu'en mémoire pour deux choses : (a) calculer une clé anti-doublon de 10 minutes, `HMAC-SHA256(IP + page, secret du serveur)`, gardée dans le cache applicatif 10 minutes (un simple hachage d'IPv4 se retrouverait par force brute, d'où le secret) ; (b) calculer l'empreinte quotidienne des lectures. Aucune géolocalisation, aucune IP envoyée à un service tiers.

## 3. Ce qui n'est jamais mesuré

- les robots connus (liste `botPatterns`), les requêtes d'administration, les API, les fichiers statiques, les requêtes non-GET ;
- les adresses locales et privées ;
- **toute personne qui s'est opposée** (section 4).

## 4. Droit d'opposition (RGPD art. 21)

Classe `src/Service/Audience/AudienceOptOut.php`. Une personne n'est mesurée nulle part si :

1. elle a cliqué sur **« Désactiver le suivi »** (politique de confidentialité, ancre `#opposition`) : cookie technique `lfed_notrack=1`, 13 mois, sans donnée personnelle, route `POST /confidentialite/suivi` (CSRF obligatoire, sans connexion, sans motif) ;
2. son navigateur envoie **`Sec-GPC: 1`** (Global Privacy Control) ;
3. son navigateur envoie **`DNT: 1`** (Do Not Track).

Le test `tests/e2e/e2e_audience_optout.php` vérifie les trois cas pour les visites, les lectures et le temps de lecture, ainsi que la réactivation.

## 5. Stockage dans le navigateur (inventaire complet)

| Nom | Rôle | Durée | Nécessite un consentement |
|---|---|---|---|
| Cookie de session Symfony | connexion, jeton CSRF | session | non (nécessaire) |
| `remember_me` | rester connecté si la case est cochée | 7 jours | non |
| `lfed_lang` | langue choisie | 1 an | non |
| `lfed_consent` | mémorise le choix sur le temps de lecture | 6 mois | non (preuve du choix) |
| `lfed_notrack` | mémorise l'opposition à la mesure | 13 mois | non |
| `lfed_welcome` | message d'accueil déjà vu | 1 an | non |
| `lfed_sv` | une réponse par enquête (identifiant aléatoire haché côté serveur) | 1 an | non, déposé seulement à l'ouverture d'une enquête |
| `localStorage lfed_pwa_dismiss` (`public/js/pwa.js`) | ne pas reproposer l'installation 14 jours | local | non (préférence d'interface, jamais envoyée) |
| `sessionStorage` (`public/js/live.js`, membres connectés) | éviter de répéter des notifications | onglet | non (jamais envoyé) |

Aucun de ces éléments ne sert à suivre une personne entre sites ou à de la publicité.

## 6. Pourquoi cette mesure est exemptée de consentement

Les lignes directrices de la CNIL sur les traceurs exemptés réunissent : finalité limitée à la mesure d'audience, résultats anonymes et agrégés, aucun recoupement avec d'autres traitements, aucun suivi inter-sites, information claire et **possibilité de s'opposer facilement**, durées de conservation limitées (13 mois maximum). Le point d'attention est la table `site_visitor` : elle garde l'User-Agent et une IP tronquée pendant 90 jours, sans identifiant personnel. Si vous ajoutez une colonne, relisez cette liste.

> Ce document décrit un fonctionnement technique ; il ne remplace pas l'avis d'un juriste ou du délégué à la protection des données. Vérifiez les lignes directrices en vigueur de la CNIL avant toute évolution.

## 7. Vérifier soi-même

```bash
APP_ENV=test php tests/e2e/e2e_audience_optout.php
php bin/console app:cleanup-visits          # purge des visites de plus de 90 jours
php bin/console app:journal:purger          # purge des lectures de plus de 13 mois
```

## 5. Mesure de conversion

Code : `src/Service/Audience/ConversionTracker.php`, `src/EventListener/ConversionListener.php`, page `/admin/conversions`.

On compte, par jour, combien de fois une étape a eu lieu : inscription, contribution, première contribution d'un membre, candidature à une mission, demande de contact. La table `conversion_count` ne contient que `jour`, `nom de l'étape` et `total`. Aucun compte, aucune adresse IP, aucun identifiant : il est impossible de savoir qui a fait quoi. Ce sont des totaux anonymes, conservés sans limite.

Pour ajouter une étape : l'inscrire dans `ConversionTracker::EVENTS` puis la déclencher depuis `ConversionListener` (création d'une entité) ou en appelant `record()`. Test : `tests/e2e/e2e_conversions.php`.
