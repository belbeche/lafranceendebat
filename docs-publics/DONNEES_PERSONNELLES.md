# Données personnelles : ce qui est collecté

Version du 8 novembre 2026. La version destinée aux utilisateurs est la page « Politique de confidentialité » du site ; en cas de différence, la page du site fait foi.

| Donnée | Pourquoi | Durée | Où dans le code |
|---|---|---|---|
| E-mail, nom, mot de passe haché | Compte | Jusqu'à suppression ; avis à 24 mois d'inactivité, suppression à 30 jours sans réponse | `User`, `ComptesInactifsCommand` |
| Journal de sécurité : date, événement, IP complète, pays, pages | Sécurité du service | 12 mois | `UserEvent`, `UserJournal` |
| Lectures : empreinte quotidienne non réversible | Intérêt public | 13 mois | `ContentView` |
| Temps de lecture et défilement | Intérêt public (consentement) | 13 mois | `ContentView`, `ConsentManager` |
| Preuve de consentement : identifiant aléatoire, choix, date | Conformité | 13 mois | `ConsentRecord` |
| Réponses aux enquêtes | Selon l'enquête annoncée | Annoncée dans l'enquête | `SurveyResponse` |
| Pièces de paiement | Obligation comptable | 10 ans | `Donation`, `ServiceOrder` |

## Ce qui n'est pas fait
Aucun traceur tiers. Aucun pixel de suivi dans les e-mails. Aucune revente. Aucun profilage publicitaire. Aucune géolocalisation précise (le pays vient de l'en-tête de l'hébergeur).

## Contribuer sans risque
N'utilisez jamais de vraies données dans les tests ou les tickets. Les jeux de test créent leurs propres utilisateurs fictifs.

## Sous-traitant : Gemini (Google), modération, facultatif

Texte soumis uniquement (sans identifiant), à la soumission d'un membre connecté, si `GEMINI_API_KEY` est configurée. L'éditeur doit utiliser une offre dont les conditions excluent l'entraînement sur les données envoyées, et vérifier l'encadrement du transfert hors Union européenne.
