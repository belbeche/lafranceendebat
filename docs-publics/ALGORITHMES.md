# Algorithmes et classements : paramètres publics

Ce document décrit **tout calcul automatique qui décide de ce qui est mis en avant**. Il suit l'esprit de l'article 27 du règlement européen sur les services numériques (DSA) : indiquer en langage clair les principaux paramètres des systèmes de recommandation et les moyens de les modifier. Il est tenu à jour avec le code : toute modification d'un classement doit modifier ce fichier dans la même demande de fusion.

> Ce document n'est pas un avis juridique. Le DSA ne soumet pas de la même façon toutes les structures (les micro et petites entreprises ont des allègements). Nous publions quand même, par principe.

## Principe général
- Aucun classement ne tient compte d'une **opinion**, d'un **parti**, d'un **paiement** ni de l'**identité** de l'auteur.
- Aucun profilage individuel : le même classement s'affiche pour tout le monde, aucune recommandation personnalisée.
- Le visiteur peut **changer le tri** (voir 1) ; il n'existe aucun classement caché.

## 1. Débats : tri « Tendance » (par défaut)
Code : `src/Service/Algo/TrendingService.php`. Choix du tri par l'utilisateur : « Tendance », « Les plus lus », « Récents » (`/debats?tri=`).

Pour chaque débat, sur les 14 derniers jours :

| Paramètre | Valeur |
|---|---|
| Poids d'une lecture | 1 |
| Poids d'une lecture approfondie | 3 |
| Poids d'un argument validé | 5 |
| Demi-vie (une activité compte moitié moins tous les…) | 3 jours |
| Échelle | logarithmique : `score = ln(1 + somme pondérée) × m` |
| Multiplicateur qualité `m` | `0,6 + 0,8 × W` (de 0,6 à 1,4) |

`W` est la borne basse de l'intervalle de Wilson (95 %) de la part de lectures approfondies. Il ne s'applique qu'à partir de **5 lectures mesurées** ; en dessous, `m = 1`. Cette méthode évite qu'un sujet lu deux fois par hasard dépasse un sujet très lu.

**Lecture approfondie** : au moins 20 secondes avec la moitié de la page parcourue, ou au moins 60 secondes. **Départ rapide** : moins de 10 secondes.

En cas d'égalité, le débat le plus récent passe devant.

**Ce qui n'entre pas dans le calcul** : le contenu des arguments (pour ou contre), le thème, l'auteur, les soutiens, l'argent, la langue.

## 2. Intérêt affiché sur chaque publication
Code : `src/Service/Audience/ViewTracker.php`. Affichage public, par débat et par proposition : nombre de lectures, temps de lecture moyen, temps cumulé, part de lectures approfondies, part de départs rapides.

| Étiquette | Condition |
|---|---|
| Très suivi | Wilson ≥ 0,5 |
| Suivi | Wilson ≥ 0,3 |
| Peu lu jusqu'au bout | en dessous |
| Pas encore assez de lectures mesurées | moins de 5 mesures |

Limites assumées : un lecteur est compté une fois par jour ; le temps de lecture ne vient que des visiteurs qui ont accepté la mesure (échantillon non représentatif).

## 3. Propositions : classement par soutiens
Les propositions sont triées par nombre de soutiens (un soutien par membre), puis par date. La vue « tendances » privilégie les soutiens récents. Code : `PropositionRepository`.

## 4. Propositions proches (détection de doublons)
Code : `src/Service/Algo/SimilarityService.php`. Méthode : TF-IDF et similarité cosinus sur titre (poids double), description et thème, avec retrait des mots vides. Seuil d'avertissement avant dépôt : **0,55** ; seuil d'affichage « propositions proches » : **0,18**. Sert uniquement à orienter vers une proposition existante ; ne bloque jamais un dépôt.

## 5. Obligations de sources d'un débat
Code : `src/Service/DebatObligations.php`. Six points, chacun « Respecté / En cours / Pas encore » : question claire (10 à 180 caractères), exposé (≥ 100 caractères), source officielle (domaine de la liste publique `DebatObligations::OFFICIAL`), faits datés ou chiffrés, source propre au thème, autorité compétente. Les quatre premiers sont requis avant relecture. La liste des domaines officiels est ouverte aux propositions de la communauté (demande de fusion).

## 6. Artisans de paix (classement de la qualité du dialogue)
Code : `src/Service/RankingService.php`. 1 point par remerciement d'une personne distincte (compte de plus de 7 jours, au plus 3 par couple, plafond 50) ; 2 points par réponse qui reformule la position de l'autre (plafond 20). Affiché seulement pour les personnes qui l'ont choisi. Un contenu masqué ne rapporte plus rien.

## 7. Modération automatique
- **Filtre de texte** (`ContentModerator`) : bloque l'envoi d'un texte contenant insulte, menace ou donnée personnelle ; la personne voit la raison et peut reformuler.
- **Relecture humaine** avant publication des propositions et débats ; toute sanction durable est décidée par un humain.
- **Fiabilité des sources** (`ReliabilityChecker`) : signale les formulations absolues, les affirmations non sourcées et les domaines non reconnus. C'est une aide à la relecture, jamais un refus automatique.
- Les personnes concernées reçoivent le motif d'une restriction, de l'e-mail d'avertissement à la désactivation.

## 8. Lutte contre les faux comptes
Refus des adresses jetables (liste `config/disposable_domains.txt`), contrôle du domaine e-mail, champ piège, limitation de débit, confirmation par lien signé. Le **principe** est public ; les **seuils exacts de production** (limites de débit, délais) ne sont pas publiés, pour ne pas faciliter le contournement. Aucun de ces contrôles ne classe un contenu.

## Comment proposer un changement
Ouvrez un ticket « algorithme » en expliquant le problème observé et les données, puis une demande de fusion qui modifie le code **et** ce fichier. Un changement qui avantagerait une opinion, un acteur ou un paiement est refusé.
