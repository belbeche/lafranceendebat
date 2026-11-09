# Scénarios utilisateurs (testés automatiquement, 54 vérifications)

Chaque scénario indique ce que fait la personne, ce qu'elle voit et quels e-mails partent.
Les e-mails ne partent que si MAIL_FROM est renseigné. Les alertes admin vont à PUBLISHER_EMAIL.

## S1. Visiteur non connecté
1. Il lit l'accueil, le Plan Marshall, l'Agora, les débats, propositions, scrutins, services, acteurs, la charte, la marche à suivre et les contacts utiles.
2. S'il tente d'agir (proposer, candidater, commander, ouvrir Mon espace), il est envoyé vers la connexion.
3. L'administration lui est fermée.

## S2. Citoyen : proposer une idée
1. Il ouvre Mon espace, clique « Proposer une idée », remplit le formulaire.
2. La proposition est enregistrée « En attente ».
3. E-mails : alerte à l'admin ("Nouvelle proposition à relire") et accusé de réception à l'auteur.
4. Il suit l'avancement dans « Ma proposition » (Déposée, Analyse, Publiée, Portée aux élus, Suivi).

## S3. Candidature à un rôle (médiateur, acteur, élu, association)
1. Un compte tout neuf peut candidater (aucune ancienneté exigée).
2. E-mails : alerte admin ("Nouvelle candidature") et accusé de réception au candidat.
3. L'admin approuve ou refuse dans « Candidatures aux droits » ; le candidat reçoit un e-mail de décision.
4. Le rôle accordé ouvre son espace : /espace/elu, /espace/association, /espace/mediateur ou /espace-acteur. Les autres espaces restent fermés.

## S4. Prestataire et client : une commande
1. Le client commande un service, paie via Stripe.
2. Paiement confirmé : e-mail au prestataire, au client et alerte admin.
3. Ils échangent dans la messagerie de la commande ; chaque message déclenche un e-mail de notification (sans le contenu). Les e-mails, numéros et liens sont refusés.
4. Le client confirme la prestation : le net est reversé au prestataire, qui reçoit un e-mail.
5. Sans confirmation, reversement automatique après 14 jours. L'admin peut tout consulter, en lecture seule.

## S5. Lecteur d'une autre langue
1. Il choisit sa langue ; le menu, l'accueil, le bandeau Plan Marshall et les marches à suivre s'affichent dans sa langue (fr, en, es, de, it, pt, ar ; les autres retombent sur l'anglais).
2. « Ce qui a été dénoncé et ne doit pas être oublié » : chaque revendication a une langue. Il voit d'abord celles de sa langue ; s'il n'y en a aucune, toutes s'affichent. Le lien « Voir toutes les langues » les montre toutes.
3. Les lettres (envoi en masse) ont une langue : seuls les destinataires de cette langue la reçoivent. Une lettre par langue.
4. L'import des acteurs publics accepte une colonne `langue` (fr, en, es...).

## S6. Modérateur / admin
1. Le modérateur voit la file de relecture dans son espace médiateur.
2. L'admin gère revendications (champ langue), piliers, services, candidatures, lettres, acteurs publics.

## Ajouts du 24 octobre

7. **Arrivée sur le site** : bannière, `/bienvenue`, choix du profil, 3 à 4 premières actions, cookie pour ne plus afficher la bannière. Après inscription, redirection vers le portail.
8. **Acteur qui donne un avis** : élu, association, prestataire ou médiateur ouvre une proposition publiée, choisit approuve, réserves ou ne soutient pas, explique en 30 caractères minimum. Un seul avis par acteur, modifiable.
9. **Personne qui cherche une mission** : `/missions` (bénévole ou rémunérée), `/tutoriels` par profil.
10. **Contribution d'une revendication** : `/revendications/proposer`, source https obligatoire, publication après relecture.
11. **Import d'acteurs consentants** : CSV avec `consentement` et `preuve_consentement`, modèle dans `docs/modele_acteurs_consentants.csv`.

## Principe de simplicité : une action concrète par écran (24 octobre 2026)
Chaque personne voit en haut de l'accueil et dans le bouton « Que voulez-vous faire ? » une seule carte « Prochaine étape », calculée sur son état réel (`next_step()`), puis quatre intentions : proposer, soutenir, déposer une demande, aider ou être rémunéré.

| État de la personne | Prochaine étape affichée | Action |
|---|---|---|
| Visiteur | Créer mon compte | `/inscription` |
| Compte sans soutien | Soutenir une première proposition | `/propositions` |
| A soutenu, n'a rien proposé | Écrire une proposition | `/propositions/nouvelle` |
| Demande de droits en cours | Voir ma demande | `/candidater` |
| Acteur avec candidatures à traiter | N candidatures à traiter | `/espace-acteur/candidatures` |
| Acteur sans candidature en attente | Donner un avis motivé | `/propositions` |
| Citoyen actif | Aider sur une mission | `/missions` |
