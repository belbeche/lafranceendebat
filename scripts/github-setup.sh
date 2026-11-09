#!/usr/bin/env bash
# Prépare le dépôt GitHub pour l'ouverture aux contributeurs : sujets, étiquettes, signalement privé, tickets débutants.
# Prérequis : GitHub CLI (gh) installée et connectée (`gh auth login`), exécuter à la racine du dépôt.
# Usage : bash scripts/github-setup.sh belbeche/lafranceendebat
set -euo pipefail
REPO="${1:?Usage : bash scripts/github-setup.sh <proprietaire/depot>}"

echo "1) Sujets (topics)"
gh repo edit "$REPO" --add-topic civic-tech --add-topic democracy --add-topic symfony --add-topic php --add-topic open-source --add-topic france \
  --description "Open-source civic platform for peaceful, source-based public debate and citizen proposals (Symfony). Interface in French. AGPL-3.0." --homepage "https://lafranceendebat.fr"

echo "2) Étiquettes"
for l in "good first issue:7057ff:Pour démarrer" "tests:0e8a16:Tests" "ci:1d76db:Intégration continue" "documentation:0075ca:Documentation" \
         "seo:fbca04:Référencement" "accessibility:d93f0b:Accessibilité" "dx:c5def5:Expérience développeur" "i18n:bfdadc:Traductions"; do
  IFS=: read -r name color desc <<<"$l"
  gh label create "$name" --repo "$REPO" --color "$color" --description "$desc" --force >/dev/null
done

echo "3) Signalement privé des failles et analyse des secrets"
gh api -X PUT "repos/$REPO/private-vulnerability-reporting" >/dev/null || echo "   (à activer à la main : Settings > Code security)"
gh api -X PATCH "repos/$REPO" -f 'security_and_analysis[secret_scanning][status]=enabled' \
  -f 'security_and_analysis[secret_scanning_push_protection][status]=enabled' >/dev/null || echo "   (secret scanning : à activer à la main)"

echo "4) Tickets débutants"
for f in .github/good-first-issues/*.md; do
  title="$(head -n1 "$f" | sed 's/^# //')"
  labels="$(sed -n '2s/<!-- labels: \(.*\) -->/\1/p' "$f")"
  body="$(tail -n +3 "$f")"
  args=(); IFS=, read -ra arr <<<"$labels"; for x in "${arr[@]}"; do args+=(--label "$x"); done
  gh issue create --repo "$REPO" --title "$title" --body "$body" "${args[@]}"
done
echo "Terminé. Vérifiez aussi : Settings > Branches (protéger main) et le dépôt en Public."
