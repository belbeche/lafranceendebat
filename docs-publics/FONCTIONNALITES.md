# Les fonctionnalités, expliquées simplement

Pour chaque fonction : ce que voit l'utilisateur, ce que fait le code, où le trouver.

| Fonction | Ce que voit l'utilisateur | Où dans le code |
|---|---|---|
| Ouvrir un débat | Un formulaire avec une liste de contrôle en direct des obligations de sources (Respecté / En cours / Pas encore) | `DebatController::open`, `DebatObligations`, `public/js/debat-obligations.js` |
| Débattre | Arguments pour, contre ou nuancés, reformulation avant réponse | `DebatController`, `Argument`, `templates/debat/` |
| Intérêt public | Nombre de lectures, temps moyen, lectures approfondies sur chaque débat et proposition | `ViewTracker`, `templates/_read_stats.html.twig` |
| Tri des débats | Tendance, plus lus, récents | `DebatController::index`, `TrendingService` |
| Déposer une proposition | Trois étapes, propositions proches pendant la saisie | `PropositionController::new`, `public/js/proposal-wizard.js`, `SimilarityService` |
| Soutenir | Un soutien par membre et par proposition | `VoteController`, `PropositionSupportService` |
| Artisans de paix | Classement de la qualité du dialogue, sur option | `RankingController`, `RankingService` |
| Consentement | Bandeau, page de choix, retrait en un clic | `ConsentController`, `ConsentManager`, `templates/legal/cookie_preferences.html.twig` |
| Mesure de lecture | Compteur sans cookie, temps de lecture avec accord | `ContentView`, `public/js/read-meter.js` |
| Inscription sûre | Identité réelle, adresses jetables refusées, confirmation par lien | `RegistrationController`, `DisposableEmailPolicy` |
| Comptes suspects | Avertissement par e-mail avant restriction | `ComptesSuspectsCommand`, `UserJournal` |
| Comptes inactifs | E-mail à 24 mois : garder ou supprimer ; suppression à 30 jours | `ComptesInactifsCommand`, `InactivityController`, `AccountEraser` |
| Agenda national | Événements importés et vérifiés automatiquement | `NationalEventImporter`, `EventVerifier` |
| Enquêtes | Création, aperçu obligatoire, envoi, résultats | `Survey*`, `templates/survey/` |
| Contributions de soutien et services | Paiement sur la page sécurisée de Stripe (aucune carte ne passe par nous) | `StripeGateway`, `DonationService`, `MarketplaceService` |
| Modération | Relecture avant publication, signalements, verrous | `ModerationService`, `ModerationController` |

Chaque classe d'algorithme commence par un commentaire qui explique son objectif en français courant : lisez-le avant le code.

## Modération et signalement

- Le filtre bloque avant publication les insultes (y compris lettres espacées, remplacements par des chiffres, petites fautes volontaires), les menaces, les propos de haine visant un groupe et les données personnelles de tiers. Il ne juge jamais une opinion.
- Chaque blocage d'un membre connecté est enregistré (membre, page, motif, extrait de 300 caractères maximum, 12 mois), l'équipe de modération est prévenue par e-mail, et l'auteur reçoit un rappel courtois (1 par jour au maximum).
- Trois blocages en 30 jours constituent une récidive signalée à l'équipe. Toute sanction est décidée par une personne, jamais automatiquement.
- Deuxième avis par IA (Gemini), facultatif : actif seulement si `GEMINI_API_KEY` est renseignée. Texte seul envoyé, uniquement à la soumission d'un membre connecté ; l'IA peut ajouter un blocage (confiance 85 % ou plus), jamais en lever un ; un doute est seulement signalé aux modérateurs. Code : `src/Service/GeminiModerator.php`.
