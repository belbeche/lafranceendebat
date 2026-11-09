---
title: "La France en Débat : cahier des charges"
subtitle: "Version du 8 novembre 2026"
---

# 1. Contexte et objectifs

Créer un espace de dialogue pacifié où chacun peut débattre sur des faits sourcés, proposer des mesures, suivre les engagements des élus et s'engager concrètement, sans haine ni violence, avec une modération stricte et une transparence totale sur l'usage des données.

Objectifs mesurables à 12 mois : (1) 5 000 membres inscrits ; (2) 150 débats ouverts dont 80 % avec source officielle respectée ; (3) 500 propositions validées ; (4) 0 contenu haineux visible plus de 24 h ; (5) 100 % des comptes inactifs traités selon la règle de conservation.

# 2. Publics et acteurs

| Acteur | Besoin | Fonctions clés |
|---|---|---|
| Citoyen | Comprendre, proposer, être entendu | Débats, propositions, soutiens, agenda, enquêtes |
| Modérateur | Garder le dialogue apaisé | File de relecture, verrous de modération, signalements |
| Administrateur | Piloter, sécuriser | Statistiques, journal de sécurité, alertes, import d'agenda |
| Élu, candidat | Écouter, répondre | Espace acteur, avis sur propositions, engagements suivis |
| Association (utilisatrice) | Mobiliser, financer | Espace acteur, missions, services |
| Expert, médiateur, indépendant | Offrir un service | Place de marché, onboarding Stripe Connect |
| Journaliste | Publier des tribunes | Tribunes relues |
| Collectivité | Consulter ses habitants | Consultations, enquêtes |
| Soutien | Contribuer en confiance | Contribution par carte, sans reçu fiscal |

# 3. Périmètre fonctionnel

## 3.1 Obligatoire (livré)

1. Inscription avec identité réelle : refus des e-mails jetables, contrôle MX, honeypot, confirmation par lien signé.
2. Débats : exposé neutre, faits sourcés, arguments pour/contre/nuancés, reformulation avant réponse, clôture avec compte rendu.
3. Obligations de sources officielles affichées et vérifiées en direct (Respecté / En cours / Pas encore), sur le formulaire et sur chaque débat.
4. Propositions : dépôt en 3 étapes, détection de doublons, modération a priori, soutiens uniques.
5. Classement tendance des débats et intérêt public affichés (lectures, temps de lecture, lectures approfondies).
6. Consentement maison : bandeau équilibré, page de choix, retrait en un clic, preuve conservée.
7. Journal de sécurité par utilisateur, restriction des comptes suspects avec avertissement préalable, alertes aux administrateurs.
8. Conservation limitée : avis d'inactivité à 24 mois, suppression à 30 jours sans réponse.
9. Agenda national avec vérification automatique, enquêtes avec aperçu obligatoire, statistiques détaillées.
10. Paiements : contributions de soutien, services, commission, remboursement, webhook signé.
11. Pages légales complètes, page « Comment ça marche ».

## 3.2 Souhaitable (à planifier)

- Notifications push et résumé hebdomadaire des débats suivis.
- Traduction automatique de masse validée par des relecteurs.
- Tableau de bord public de suivi des engagements des élus.
- Facturation et reçus automatiques pour les commandes de services.
- Application mobile installable (PWA déjà amorcée).

## 3.3 Hors périmètre

Publicité, revente de données, financement de campagne électorale ou de parti, vote officiel opposable, messagerie privée de masse.

# 4. Exigences non fonctionnelles

| Domaine | Exigence |
|---|---|
| Vie privée | Aucun traceur tiers, aucun pixel e-mail, consentement et retrait, durées de conservation publiées |
| Sécurité | CSRF, CSP à nonce, liens signés, webhooks signés, limitation de débit, mots de passe hachés |
| Accessibilité | Cibler RGAA : contrastes, navigation clavier, étiquettes de formulaire, regions ARIA |
| Performance | Page de liste sous 1 s à 1 000 visiteurs simultanés avec cache applicatif |
| Disponibilité | 99,5 % mensuel, page de maintenance, sauvegarde quotidienne |
| Éthique | Aucun contenu ne monte à cause d'une opinion ou d'un paiement |
| Modération | Texte hostile bloqué à l'envoi, relecture humaine avant publication, sanction décidée par un humain |

# 5. Contraintes

- Cadre légal : RGPD et recommandations CNIL, loi pour la confiance dans l'économie numérique (mentions légales), droit électoral (aucun financement ni propagande), règles de la plateforme de paiement (Stripe).
- Les services payants ne peuvent avoir d'objet politique ou électoral (garde-fou en place).
- Éditeur : micro-entreprise (Axis Humain). Aucun reçu fiscal ; contributions traitées comme chiffre d'affaires. À faire valider par un expert-comptable.

# 6. Livrables et recette

Livrables : code, tests e2e, documentation d'exploitation, pages légales, guide de test Stripe, ces quatre documents.
Recette : exécution des scripts `tests/e2e`, vérification manuelle des parcours (inscription, débat, proposition, consentement, contribution de test Stripe), contrôle des pages légales par un juriste.

# 7. Planning indicatif (hypothèse)

| Étape | Durée | Contenu |
|---|---|---|
| Lot 1 : fiabilité et mise en production | 2 semaines | Mise à jour du schéma, crons, clés Stripe de test puis réelles, relecture légale |
| Lot 2 : lancement doux | 4 semaines | 50 premiers membres, 10 débats amorcés, ajustement du classement |
| Lot 3 : ouverture publique | 6 semaines | Communication, partenariats associations, premières contributions |
| Lot 4 : acteurs et services | 8 semaines | Onboarding Connect, premiers prestataires, offre collectivités |
