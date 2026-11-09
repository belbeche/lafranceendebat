# Ajouter la balise noindex aux pages privées
<!-- labels: seo,good first issue -->

## Contexte
`public/robots.txt` demande aux moteurs de ne pas explorer les pages privées, mais cela n'empêche pas leur indexation si un lien existe ailleurs. Il faut aussi une balise `noindex`.

## À faire
Dans `templates/base.html.twig`, ajouter un bloc Twig `robots` (valeur par défaut : `index,follow`). Le redéfinir en `noindex,nofollow` pour la connexion, l'inscription, la réinitialisation du mot de passe, le profil, les comptes, la messagerie et les pages de remerciement.

## Résultat attendu
`curl -s http://localhost:8000/login` contient `<meta name="robots" content="noindex,nofollow">`, et la page d'accueil contient `index,follow`.

## Où
`templates/base.html.twig` et les gabarits des pages concernées.

## Test
Un script `tests/e2e/` qui vérifie la balise sur 3 pages privées et 2 pages publiques.
