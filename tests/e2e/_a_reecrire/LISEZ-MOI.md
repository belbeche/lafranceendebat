# Scripts à réécrire (ne tournent plus dans la suite)

Ces 10 anciens scripts échouaient sans que le code de production soit en cause : ils dépendent d'un état de base de données
laissé par d'autres scripts, de textes qui ont changé (pied de page, libellés), d'un constructeur de commande modifié
(`annuaire`) ou d'un ordre d'exécution (`lettre2` doit suivre `lettre`). Ils sont rangés ici plutôt que supprimés :
chacun décrit un scénario utile à reprendre quand la fonction concernée évolue.

Pour en réactiver un : le déplacer dans `tests/e2e/`, le faire passer seul (`APP_ENV=test php tests/e2e/e2e_xxx.php`), puis relancer `bash tests/e2e/run_all.sh`.
