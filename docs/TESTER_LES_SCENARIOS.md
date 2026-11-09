# Tester les scénarios, pas à pas

## 1. Préparer
1. `php bin/console doctrine:schema:update --force` en local (en production : `doctrine:migrations:diff` puis `migrate`).
2. `php bin/console app:seed:civic` : crée les revendications de départ, dont les 8 de la lettre au Sénat (non publiées).
3. Créez 4 comptes via `/inscription?profil=...` : citoyen, élu, association, expert. Dans l'administration (`/admin/candidatures-droits`) approuvez les droits des trois acteurs après avoir ouvert le lien de preuve.

## 2. Scénarios à jouer
| # | Qui | Action | Résultat attendu |
|---|---|---|---|
| 1 | Visiteur | Ouvrir `/`, `/debats`, `/revendications` | Lecture seule, bouton de création de compte |
| 2 | Citoyen | S'inscrire avec « Citoyen » | Arrive sur `/bienvenue/citoyen`, aucun droit d'acteur |
| 3 | Élu | S'inscrire avec « Élu », sans organisation | Refus du formulaire, message clair |
| 4 | Élu | S'inscrire avec organisation | Redirigé vers `/candidater?role=ROLE_ELU`, lien de preuve et téléphone demandés |
| 5 | Admin | Valider la demande | L'élu reçoit notification et e-mail, rôle actif |
| 6 | Citoyen | `/missions` puis « Candidater auprès des acteurs » | Choix des acteurs, e-mail et téléphone obligatoires |
| 7 | Association | `/espace-acteur/candidatures` | Voit e-mail, téléphone, message, niveau au classement ; accepte ou décline |
| 8 | Citoyen | `/mes-candidatures` | Voit la réponse, peut écrire à l'acteur |
| 9 | Citoyen | Écrire à un autre citoyen | Refusé : un acteur public au moins doit être dans l'échange |
| 10 | Admin | `/admin/import-revendications`, publier une revendication, puis `php bin/console app:contenu:traduire --type=revendication` | Fiche lisible dans les 12 langues |
| 11 | Tous | `/classement` | Podium, progression personnelle, bloc « Pourquoi figurer » |

## 3. Temps réel (deux navigateurs)
Ouvrez deux profils de navigateur, connectés avec deux comptes. Écrivez un message dans l'un : la pastille de l'autre se met à jour sous 15 secondes, le fil ouvert sous 5 secondes.

## 4. Responsive
`node tests/e2e/responsive.js` teste 7 tailles d'écran (320 à 1920 px) : pas de défilement horizontal, menu sur une ligne.

## 5. Scripts automatiques
Chaque script se lance avec `php tests/e2e/e2e_xxx.php` (variables `MAIL_FROM` et `PUBLISHER_EMAIL` définies, base de test) :
`e2e_scenarios`, `e2e_roles`, `e2e_reg` (inscription par profil), `e2e_rr` (demande de droits), `e2e_apply` (candidatures aux missions), `e2e_live`, `e2e_actions`, `e2e_rank`, `e2e_mail`.

## 6. Traductions
- Contenus : `php bin/console app:contenu:traduire --limit=30` (ajoutez `--tout` pour inclure les contenus non publiés). Reprend où il s'est arrêté en cas de quota.
- Interface : `php bin/console app:traductions:completer --liste` montre les clés manquantes par langue ; sans `--liste` il les traduit. Déclarez `MYMEMORY_EMAIL` pour passer de 5 000 à 50 000 caractères par jour.
