#!/bin/bash
# Récupère un token Sanctum par profil du tenant de recette RestaurantManager.
# Le token est renvoyé à la RACINE de la réponse (`token`), pas dans `data`.
API=${API:-http://127.0.0.1:8000/api/v1}
OUT=${OUT:-eval/resto/tokens.txt}
: > "$OUT"
for p in principal gerant comptable superviseur chef serveur caisse livreur; do
  R=$(curl -s -X POST "$API/auth/login" -H "Content-Type: application/json" -H "Accept: application/json" \
    -d "{\"email\":\"$p@example.com\",\"password\":\"recette123\"}")
  T=$(printf '%s' "$R" | python3 -c "import sys,json;d=json.load(sys.stdin);print(d.get('token') or d.get('data',{}).get('token') or '')" 2>/dev/null)
  if [ -z "$T" ]; then
    echo "$p => ECHEC $(printf '%s' "$R" | head -c 160)"
    continue
  fi
  echo "$p|$T" >> "$OUT"
  echo "$p => ${T:0:24}..."
done
