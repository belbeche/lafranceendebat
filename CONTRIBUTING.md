# Contribuer

Merci. Quelques règles simples, pour que le débat reste apaisé jusque dans le code.

## Avant de commencer
1. Lisez le [code de conduite](CODE_OF_CONDUCT.md).
2. Ouvrez un ticket pour discuter d'un gros changement avant d'écrire du code.
3. **Failles de sécurité** : jamais de ticket public, voir [SECURITY.md](SECURITY.md).

## Ce qui est bienvenu
Corrections de bugs, accessibilité (RGAA), traductions, tests, documentation, performance, nouvelles sources officielles par thème, amélioration des textes d'aide.

## Ce qui demande une discussion préalable
- Tout changement des **classements ou algorithmes** : il doit être justifié, documenté dans `docs-publics/ALGORITHMES.md` et ne jamais favoriser une opinion, un parti ou un paiement.
- Tout **nouveau traitement de données personnelles** : finalité, durée et base légale à documenter dans `docs-publics/DONNEES_PERSONNELLES.md`.
- Lire d'abord `docs-publics/ARCHITECTURE.md` et `docs-publics/CONVENTIONS_DE_CODE.md` : ils expliquent où ajouter quoi et comment documenter.
- Tout ce qui touche à la modération ou aux paiements.

## Règles non négociables
- Aucun traceur tiers, aucun pixel de suivi dans les e-mails, aucune publicité.
- Aucun secret, jeton, mot de passe ni donnée réelle dans un commit (même pour un test).
- Les textes visibles sont en français clair, sans jargon.
- Un changement = un test. Les scénarios sont dans `tests/e2e/`.

## Faire une proposition de changement
1. Forkez, créez une branche : `git checkout -b ma-fonctionnalite`.
2. Codez, lancez les tests concernés (voir README).
3. Messages de commit clairs, à l'impératif : « Ajoute le filtre par thème ».
4. Ouvrez une demande de fusion en décrivant **le problème résolu**, **ce qui change pour l'utilisateur** et **l'impact sur les données** (s'il y en a).

## Style de code
PSR-12, types stricts quand c'est possible, commentaires en français qui expliquent le *pourquoi*, pas le *comment*. Les classes d'algorithmes commencent par un commentaire lisible par un non-informaticien.
