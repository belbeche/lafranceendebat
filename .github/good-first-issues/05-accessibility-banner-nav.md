# Auditer l'accessibilité du bandeau de consentement et de la navigation
<!-- labels: accessibility,good first issue -->

## Contexte
Le bandeau de consentement et la barre de navigation (`templates/base.html.twig`) doivent être utilisables au clavier et avec un lecteur d'écran (RGAA / WCAG 2.1 AA).

## À faire
1. Tester au clavier : l'ordre de tabulation, le focus visible, la fermeture du bandeau.
2. Vérifier les rôles et libellés ARIA (`role`, `aria-label`, `aria-live`).
3. Vérifier le contraste du bleu `#002395` et du rouge `#ED2939` sur leurs fonds.
4. Corriger ce qui est cassé, dans `templates/base.html.twig` et `public/css/lfed-community.css`.

## Résultat attendu
Aucune erreur critique avec l'extension axe DevTools ou Lighthouse (score d'accessibilité au moins 95) sur la page d'accueil, avec et sans bandeau.

## Test
Joindre à la demande de fusion les scores avant et après, et la liste des corrections.
