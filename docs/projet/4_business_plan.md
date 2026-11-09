---
title: "La France en Débat : business plan"
subtitle: "Version du 8 novembre 2026. Chiffres chiffrés dans le prévisionnel (hypothèses)."
---

# 1. Résumé

La France en Débat est un espace citoyen où le débat est cadré par des faits sourcés, la modération est stricte, et où chaque mesure du comportement des lecteurs est publique et consentie. Le modèle économique repose sur la confiance : pas de publicité, pas de revente de données. Les revenus viennent de dons, d'une commission sur des services utiles au débat civique, d'abonnements pour les organisations et de missions de consultation pour les collectivités.

# 2. Problème et réponse

| Problème | Réponse de la plateforme |
|---|---|
| Les débats en ligne dégénèrent | Charte de paix, reformulation avant réponse, modération a priori, texte hostile bloqué |
| Les affirmations ne sont pas vérifiables | Source officielle obligatoire, obligations affichées (Respecté / En cours / Pas encore) |
| Les mêmes idées se dispersent | Détection de propositions proches, soutiens regroupés |
| On ignore ce qui intéresse vraiment | Lectures, temps de lecture et lectures approfondies affichés publiquement |
| Méfiance sur les données | Aucun traceur tiers, aucun pixel e-mail, consentement et retrait en un clic, conservation limitée |

# 3. Marché et cibles

Cibles principales : citoyens engagés (membres), associations et collectifs, collectivités et institutions qui consultent, indépendants du dialogue civique (médiateurs, formateurs, juristes, journalistes), fondations et mécènes de la démocratie participative. Les parts de marché ne sont pas chiffrées ici faute de source vérifiée ; une étude d'une demi-journée sur les budgets de participation citoyenne des collectivités serait la première validation.

# 4. Acteurs ciblés pour les paiements Stripe

| Acteur | Ce qu'il paie ou encaisse | Solution Stripe | Priorité | Précautions |
|---|---|---|---|---|
| Citoyens soutiens | Contribution ponctuelle (carte), 5 à 100 euros | Checkout hébergé (en place) | 1 | Aucun reçu fiscal ni réduction d'impôt : l'éditeur est une micro-entreprise (`DONATION_RECEIPTS_ENABLED=0`) |
| Soutiens réguliers | Contribution mensuelle | Abonnement Stripe Billing | 2 | Page de gestion et résiliation en un clic |
| Indépendants et experts (médiateurs, formateurs, juristes) | Encaissent le prix d'une prestation | Connect, comptes connectés, commission 5 % (en place) | 1 | Vérification d'identité par Stripe ; pas d'objet politique ni électoral |
| Associations et collectifs | Cotisations, billetterie d'événements, services | Connect ou compte propre ; abonnement « espace acteur » | 2 | Vérifier qu'il ne s'agit pas de financement de parti ou de campagne |
| Collectivités et institutions | Paient des consultations et enquêtes | Facture avec virement ou prélèvement SEPA (mandats publics) plutôt que carte | 2 | Respect des règles de commande publique et des délais de paiement |
| Fondations et mécènes | Soutien annuel | Facture, virement, ou paiement en ligne | 3 | Convention de mécénat, pas de contrepartie éditoriale |
| Journalistes et médias | Abonnement ou tribune sponsorisée | Billing | 3 | Séparation nette éditorial/commercial ; aucune tribune achetée |
| Organisateurs d'événements de l'agenda | Billetterie | Checkout + Connect | 3 | Frais plateforme à définir |

Règle d'or : aucun paiement ne donne de visibilité ni d'avantage dans le classement. Stripe interdit certaines activités et le droit électoral encadre strictement le financement politique ; faire valider chaque nouveau cas par un juriste avant ouverture.

# 5. Offre et tarification (hypothèses)

| Offre | Public | Prix |
|---|---|---|
| Accès citoyen | Tous | Gratuit |
| Contribution de soutien | Citoyens | Libre, à partir de 5 euros |
| Espace acteur | Associations, élus, organisations | 29 euros par mois |
| Place de marché | Indépendants | Commission de 5 % par commande |
| Consultation ou enquête sur mesure | Collectivités | Environ 4 000 euros par mission |
| Mécénat | Fondations | Convention annuelle |

# 6. Avantages concurrentiels

- Transparence radicale et vérifiable : le code applique ce que disent les pages légales (pas de pixel, retrait du consentement en un clic, suppression automatique).
- Méthode de débat éprouvée : exposé neutre, sources officielles obligatoires et visibles.
- Classement qui mesure l'intérêt et la qualité du dialogue, pas l'opinion ni l'argent.
- Coûts très faibles : hébergement léger, aucun service tiers de suivi, outils maison.

# 7. Risques et parades

| Risque | Parade |
|---|---|
| Récupération politique ou accusation de partialité | Charte, méthode publique, source officielle obligatoire, aucun avantage payant, comité de modération |
| Contenus haineux ou désinformation | Blocage à l'envoi, relecture humaine, signalements, verrous de modération |
| Atteinte à la vie privée | Minimisation, consentement, durées courtes, aucun transfert vers des traceurs |
| Revenus trop faibles | Plan prudent avec réduction des charges, subventions, bénévolat de développement |
| Dépendance à Stripe | Garder la possibilité de virement et de facture ; documents comptables exportables |
| Fraude et faux comptes | Refus des adresses jetables, restriction, paiements bloqués, journal de sécurité |
| Droit électoral et financement politique | Garde-fou technique sur l'objet des services, relecture juridique |

# 8. Feuille de route

1. Mois 0 à 1 : mise en production du lot actuel, relecture juridique des pages légales, test Stripe de bout en bout, amorçage de 10 débats.
2. Mois 2 à 3 : lancement doux (50 membres), réglage de l'algorithme de tendance sur données réelles, premières contributions de soutien.
3. Mois 4 à 6 : ouverture publique, partenariats avec 5 associations, premier mécénat.
4. Mois 7 à 12 : espace acteur payant, premiers prestataires Connect, première consultation de collectivité, rapport de transparence public.

# 9. Indicateurs de réussite

Membres actifs mensuels, taux de lecture approfondie, propositions validées et soutenues, part de débats avec toutes les obligations respectées, nombre de contenus hostiles bloqués, don moyen, volume Connect, abonnements actifs, satisfaction mesurée par enquête semestrielle.

# 10. À valider avant lancement commercial

- Statut de l'éditeur : micro-entreprise (Axis Humain). Les contributions sont du chiffre d'affaires soumis au régime micro (plafonds de chiffre d'affaires et de TVA à surveiller) et ne donnent pas droit à des reçus fiscaux. Faire valider par un expert-comptable, et s'il le faut créer plus tard une structure à but non lucratif distincte.
- Conformité du modèle d'abonnement et de la commission avec les règles de la plateforme de paiement.
- Revue des pages légales par un juriste ou un délégué à la protection des données.
