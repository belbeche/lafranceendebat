# Cadre légal de La France en Débat, point par point et sourcé

Ce document recense les règles qui s'appliquent à la plateforme, ce que le code fait déjà, et ce qui reste à faire de votre côté. Les liens renvoient aux textes officiels (Légifrance, CNIL, EUR-Lex). Vérifiez la version en vigueur à la date de mise en ligne : les textes évoluent. Ce n'est pas un avis d'avocat : c'est une grille de conformité construite sur les sources officielles.

## 1. Qui est responsable de quoi

| Rôle | Règle | Source |
|---|---|---|
| Éditeur du site | Mentions légales obligatoires : identité, adresse, contact, directeur de publication, hébergeur | Loi n° 2004-575 (LCEN), art. 6 III, https://www.legifrance.gouv.fr/ |
| Hébergeur des contributions | Pas d'obligation de surveillance générale. Responsabilité engagée seulement si un contenu manifestement illicite est signalé et n'est pas retiré promptement | LCEN art. 6 I 2 et I 7 ; règlement (UE) 2022/2065 (DSA) art. 6, https://eur-lex.europa.eu/eli/reg/2022/2065/oj |
| Signalement | Mécanisme de notification accessible, décision motivée envoyée à l'auteur | DSA art. 16 et 17 |
| Point de contact | Adresse électronique publique pour les autorités et les utilisateurs | DSA art. 11 et 12 |

Déjà en place : pages légales, signalement de contenu, modération avant publication, décisions motivées par e-mail. À faire : renseigner dans les mentions légales le nom réel de l'éditeur et du directeur de publication (variables `PUBLISHER_*`).

## 2. Expression, débat et modération

- Injure, diffamation, provocation à la haine : loi du 29 juillet 1881, art. 23, 24, 29, 32, 33. Prescription de 3 mois pour la presse en ligne (art. 65). Source : https://www.legifrance.gouv.fr/loi/id/JORFTEXT000000877119
- Menaces : Code pénal art. 222-17. Atteinte à la vie privée : art. 226-1. Usurpation d'identité en ligne : art. 226-4-1.
- Droit de réponse en ligne : LCEN art. 6 IV et décret n° 2007-1527. Quiconque est nommé ou désigné peut demander à répondre. La plateforme doit publier la réponse dans les 3 jours de la réception.
- Règle de la plateforme, identique pour toute partie, institution ou parti : source publique en https, faits vérifiables, aucune insulte, menace ni donnée personnelle, droit de réponse, relecture avant publication. Aucune accusation nominative sans source.

Déjà en place : filtre insultes et menaces à chaque dépôt, source obligatoire, statut « en relecture », fiche « droit de réponse ». À faire : désigner la personne qui traite les demandes de droit de réponse et fixer le délai de 3 jours dans votre routine.

## 3. Données personnelles (RGPD)

Texte : règlement (UE) 2016/679, https://eur-lex.europa.eu/eli/reg/2016/679/oj ; loi n° 78-17 modifiée. Guides : https://www.cnil.fr/

- Base légale (art. 6) : exécution du service pour le compte, consentement (art. 7) pour la lettre d'information et les cookies non essentiels, intérêt légitime pour la sécurité et la lutte contre les abus.
- Information des personnes (art. 13) : page de confidentialité avec finalités, durées, droits, contact, réclamation à la CNIL.
- Droits (art. 15 à 22) : accès, rectification, effacement, portabilité, opposition. Délai de réponse : 1 mois.
- Registre des traitements (art. 30), sécurité (art. 32), notification d'une violation à la CNIL sous 72 h (art. 33).
- Mineurs : consentement numérique à partir de 15 ans en France (loi n° 78-17, art. 45).
- Candidatures aux missions : l'e-mail et le téléphone du candidat ne sont transmis qu'aux acteurs qu'il a choisis, pour répondre à sa candidature. À indiquer dans la page de confidentialité.
- Annuaire des acteurs publics : n'importer que des personnes ayant donné un consentement avec preuve (colonne `preuve_consentement`). Sans preuve, ne pas importer.
- Prospection par e-mail : consentement préalable pour les particuliers, droit d'opposition pour les professionnels si le message est en rapport avec leur fonction (Code des postes et des communications électroniques, art. L34-5).
- Cookies et traceurs : consentement avant dépôt de tout traceur non essentiel, refus aussi simple que l'acceptation (loi n° 78-17, art. 82 ; lignes directrices CNIL du 17 septembre 2020).
- Traduction automatique : le texte des contributions publiques est envoyé à MyMemory (ou DeepL, LibreTranslate). Ne traduire que du contenu public, jamais de message privé. À mentionner dans la page de confidentialité comme sous-traitant.
- Analyse d'impact (art. 35) : à faire si vous traitez des opinions politiques à grande échelle. Les votes consultatifs liés à un compte sont des données révélant des opinions politiques (art. 9) : ne pas les publier de façon nominative et conserver la base sécurisée.

## 4. Votes, consultations et candidats

- Les votes sont consultatifs et le disent clairement : pas de valeur de scrutin officiel. Un référendum d'initiative partagée relève de l'article 11 de la Constitution et de la loi organique n° 2013-1114, avec contrôle du Conseil constitutionnel : la plateforme ne peut que relayer, pas organiser. Source : https://www.legifrance.gouv.fr/
- Période électorale : la présentation des candidats doit rester neutre et gratuite pour tous. Évitez toute publicité commerciale de promotion d'une collectivité ou d'un candidat (Code électoral, art. L52-1) et tout financement ou avantage en nature à un candidat (art. L52-8).
- Commons et contributions : les textes déposés par les citoyens restent leur œuvre. Précisez la licence de réutilisation dans les conditions (Code de la propriété intellectuelle, art. L111-1 et suivants). Pour un bien commun, une licence ouverte du type CC BY-SA 4.0 est cohérente avec votre déclaration que le travail appartient à tous.

## 5. Services payants (marketplace)

- Information loyale sur les prix, la commission et les avis : Code de la consommation, art. L111-7 et D111-7 (opérateurs de plateforme en ligne).
- Droit de rétractation de 14 jours pour les services à distance (art. L221-18), avec exceptions pour un service pleinement exécuté avec accord préalable (art. L221-28).
- Conditions générales de vente, médiation de la consommation (art. L612-1) et mentions de TVA selon votre statut.
- Paiement : le prestataire de paiement (Stripe) encadre les fonds. Ne jamais stocker de numéro de carte.

## 6. Sécurité et conservation

- Mots de passe hachés, CSRF, limiteurs de débit, CSP : en place.
- Conservation limitée : fixer des durées (comptes inactifs, messages, journaux de connexion de 12 mois côté hébergeur, LCEN art. 6 II et décret n° 2011-219).
- Clés sensibles hors dépôt : déplacer les identifiants SMTP du `.env` vers `.env.local` et les renouveler.

## 7. Checklist avant ouverture

1. Mentions légales complètes (éditeur, directeur de publication, hébergeur).
2. Page de confidentialité à jour (finalités, destinataires dont MyMemory, durées, droits).
3. Bandeau cookies conforme, refus aussi simple que l'acceptation.
4. Registre des traitements rempli (modèle CNIL).
5. Adresse de contact DSA et procédure de droit de réponse affectées à une personne.
6. Licence des contributions écrite dans les conditions.
7. Conditions générales de vente et commission affichées avant paiement.
8. Durées de conservation décidées et appliquées.

Points où une relecture humaine reste prudente, sans être obligatoire : la licence des contributions, les CGV de la marketplace et l'analyse d'impact sur les votes. Le reste est couvert par cette grille et les sources ci-dessus.
