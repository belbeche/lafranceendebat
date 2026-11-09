# Propositions des groupes politiques et calculateur de budget

## Ce que fait le module

- `/programmes` : propositions de loi déposées par chaque groupe à l'Assemblée nationale (import officiel) et mesures de programme saisies avec le lien du site de la formation. Filtres : groupe, thème indicatif, mot-clé, « chiffrées ».
- `/programmes/{id}` : fiche avec source, chiffrage (si sourcé) et niveau de crédibilité, avis consultatifs des membres.
- `/calculateur-budget` : additionne le coût annuel estimé de propositions chiffrées, en séparant les niveaux A, B et C.
- Administration : `/admin/programmes` (thème, publication, chiffrage, mesures de programme).

## Règles de neutralité (à ne jamais contourner)

1. Même traitement pour tous les groupes : mêmes champs, mêmes filtres, mêmes exigences de source. Aucun classement, aucune note, aucun adjectif éditorial.
2. Pas de source officielle `https://`, pas de publication.
3. Pas de chiffrage sans niveau de crédibilité **et** source `https://` (`PartyProposal::setCost()` ignore le reste).
4. Une proposition de loi engage ses signataires, pas forcément tout leur parti : le rappel figure sur chaque page.
5. Le thème est indicatif : règle publique par mots-clés (`ThemeGuesser`), corrigeable en administration.

## Niveaux de crédibilité

| Niveau | Sens |
|---|---|
| A | Chiffrage officiel : étude d'impact, Cour des comptes, loi de finances, Assemblée ou Sénat |
| B | Chiffrage publié par un organisme indépendant, avec méthode |
| C | Estimation de l'auteur ou citoyenne, non vérifiée |

## Import des données officielles

```bash
php bin/console app:programmes:import --legislature=17            # télécharge ~16 Mo, idempotent
php bin/console app:programmes:import --from-dir=/chemin/extrait   # sans téléchargement
```

Sources : données ouvertes de l'Assemblée nationale (`data.assemblee-nationale.fr`, licence ouverte Etalab : mentionner la source et la date). Règle de rattachement : groupe du **premier signataire à la date de dépôt**. Les textes déposés par un organe qui n'est pas un groupe (par exemple l'Assemblée) ou par un auteur sans groupe identifiable ne sont jamais importés (pas de devinette). Un échantillon a été vérifié à la main contre les pages officielles (n° 500 : première signataire du groupe Écologiste et Social ; n° 729 : première signataire du groupe LFI-NFP).

Limites connues :
- Les **programmes de 2027** ne sont pas dans les données ouvertes : ils s'ajoutent à la main (`/admin/programmes/nouvelle`) avec le lien officiel du parti.
- Les propositions du **Sénat** (`data.senat.fr`, même licence) ne sont pas encore importées.
- Un chiffrage doit être saisi à la main avec sa source : aucun coût n'est estimé automatiquement.

## Tests

```bash
APP_ENV=test php tests/e2e/e2e_programmes.php   # import sur jeu de test (tests/fixtures/an), chiffrage, calculateur, avis, admin
```

## Ajouts de novembre 2026

### Propositions du Sénat
`php bin/console app:programmes:import-senat --since-session=2023` importe les propositions de loi du Sénat (données ouvertes
data.senat.fr, licence ouverte : source et date de mise à jour à citer). Le groupe est celui du **premier signataire**, mais le Sénat ne
publie que le groupe **actuel** des sénateurs : seuls les textes dont le premier signataire est en exercice sont importés, les autres sont
ignorés plutôt que mal attribués. Les groupes du Sénat portent le préfixe `SEN-`.

### Logique de chiffrage et hypothèses par IA
Le calculateur additionne d'abord les chiffrages **sourcés** (niveaux A, B, C). Pour les propositions sans chiffrage sourcé, Gemini peut proposer des
**paramètres** (`app:programmes:analyser`) : personnes concernées × coût annuel par personne × part de bénéficiaires, plus un coût fixe, moins une
contrepartie, chacun en valeur basse, centrale et haute avec la base publique citée. **Le résultat est calculé par le site** (`CostModel`), jamais par l'IA ;
toute valeur hors bornes fait rejeter l'analyse. La fiabilité annoncée est plafonnée à 70. Le texte envoyé à Gemini est public (titre, résumé, thème), sans
le nom du groupe. Une analyse reste **invisible tant qu'un modérateur ne l'a pas validée**, puis s'affiche étiquetée « hypothèse IA, non officielle »,
à part, jamais ajoutée aux chiffrages sourcés.

### Espace des partis
`/partis` présente toutes les formations de la même façon (ordre alphabétique, aucun classement) : propositions, thèmes, sommes sourcées par niveau, liens
officiels saisis par la modération. Nous ne reprenons pas les notations de sites tiers (par exemple MonVote2027) : ce sont des analyses éditoriales
dont la licence de réutilisation n'est pas précisée, et les afficher reviendrait à attribuer des positions aux partis. Nous renvoyons vers eux par un lien.

### Préremplir un débat depuis un article
Le formulaire « Ouvrir un débat » propose de contrôler une source avant tout remplissage : https, domaine reconnu (sites officiels ou médias d'une liste),
page lisible, date, **droits** (réserve de fouille de textes `tdm-reservation`, article payant). Si la source réserve ses droits, seule sa présentation publique
sert au brouillon (écrit en mots propres par Gemini), jamais le texte intégral. Le membre relit et corrige : rien n'est publié automatiquement.
