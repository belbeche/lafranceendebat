#!/usr/bin/env bash
# Livraison sans coupure brutale : maintenance, sauvegarde du code et de la base, extraction, vérification, retour arrière automatique.
# Usage (depuis la racine du site) :  bash bin/deploy.sh ship45-cumul.tgz https://lafranceendebat.fr
set -uo pipefail
ARCHIVE="${1:?Usage: bash bin/deploy.sh archive.tgz https://votre-site}"
SITE="${2:?Adresse du site, ex. https://lafranceendebat.fr}"
PHP="${PHP_BIN:-php8.4}"
STAMP="$(date +%Y%m%d-%H%M%S)"
SAVE="var/releases/$STAMP"
mkdir -p "$SAVE"

say() { printf '\n== %s\n' "$*"; }
rollback() {
  say "ÉCHEC : retour à la version précédente"
  tar xzf "$SAVE/code.tgz" --overwrite 2>/dev/null
  "$PHP" bin/console cache:clear --env=prod --no-warmup >/dev/null 2>&1
  "$PHP" bin/console app:maintenance off
  echo "Ancienne version rétablie. La sauvegarde de la base est dans $SAVE/base.sql (non restaurée automatiquement)."
  exit 1
}

say "1/7 Maintenance ON"
"$PHP" bin/console app:maintenance on --message "Mise à jour en cours, retour dans quelques minutes." --minutes 15

say "2/7 Sauvegarde du code actuel"
tar czf "$SAVE/code.tgz" src templates public/css config translations 2>/dev/null || true
[ -d public/vendor ] && tar rzf "$SAVE/code.tgz" public/vendor 2>/dev/null || true

say "3/7 Sauvegarde de la base (si mysqldump est disponible)"
if command -v mysqldump >/dev/null 2>&1 && [ -n "${DB_NAME:-}" ]; then
  mysqldump --single-transaction -u "${DB_USER:-root}" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME" > "$SAVE/base.sql" && echo "Base sauvegardée." || echo "Sauvegarde base impossible : continuez seulement si une sauvegarde récente existe."
else
  echo "Non faite (définir DB_NAME, DB_USER, DB_PASS pour l'activer). Utilisez aussi la sauvegarde de l'administration."
fi

say "4/7 Extraction de la livraison"
tar xzf "$ARCHIVE" --overwrite || rollback
chmod -R a+rX public

say "5/7 Caches"
"$PHP" bin/console cache:clear --env=prod || rollback

say "6/7 Contrôle de la base (différences à appliquer, sans rien modifier)"
"$PHP" bin/console doctrine:schema:update --dump-sql --env=prod 2>&1 | head -30

say "7/7 Test de santé du site (la maintenance bloque les visiteurs, /sante reste ouvert)"
CODE="$(curl -s -o /dev/null -w '%{http_code}' "$SITE/sante" || echo 000)"
BODY="$(curl -s "$SITE/sante" || true)"
echo "Réponse /sante : $CODE $BODY"
case "$BODY" in *'"base":"ok"'*) ;; *) rollback ;; esac
HOME_CODE="$(curl -s -o /dev/null -w '%{http_code}' -H 'Cache-Control: no-cache' "$SITE/login" || echo 000)"
[ "$HOME_CODE" = "200" ] || rollback

"$PHP" bin/console app:maintenance off
say "Livraison terminée. Site rouvert. Retour arrière possible avec : tar xzf $SAVE/code.tgz --overwrite && $PHP bin/console cache:clear --env=prod"
