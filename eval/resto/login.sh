#!/bin/bash
API=http://127.0.0.1:8000/api/v1
for p in principal gerant comptable superviseur chef serveur caisse livreur; do
  R=$(curl -s -X POST $API/auth/login -H "Content-Type: application/json" -H "Accept: application/json" \
    -d "{\"email\":\"$p@example.com\",\"password\":\"recette123\"}")
  T=$(echo "$R" | python3 -c "import sys,json;d=json.load(sys.stdin);print(d.get('data',{}).get('token') or d.get('token') or '')" 2>/dev/null)
  if [ -z "$T" ]; then T=$(echo "$R" | python3 -c "import sys,json;d=json.load(sys.stdin);print(json.dumps(d)[:200])" 2>/dev/null); fi
  echo "$p|$T" >> eval/resto/tokens.txt
  echo "$p => ${T:0:40}"
done
