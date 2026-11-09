#!/usr/bin/env bash
# Lance tous les scripts de tests/e2e, un par un, en vidant les limiteurs de débit entre chaque.
# Usage : bash tests/e2e/run_all.sh        (depuis la racine du projet)
export MAIL_FROM="${MAIL_FROM:-noreply@example.test}" PUBLISHER_ADDRESS="${PUBLISHER_ADDRESS:-1 rue Test, 75001 Paris}"
fail=0
for f in tests/e2e/e2e_*.php; do
  rm -rf var/cache/test/pools
  out=$(APP_ENV=test timeout 120 php "$f" 2>&1); rc=$?
  if [ $rc -ne 0 ] || echo "$out" | grep -q '^KO'; then echo "ECHEC  $f"; echo "$out" | grep -E '^KO|Fatal' | head -3 | sed 's/^/         /'; fail=1; else echo "ok     $f"; fi
done
exit $fail
