#!/bin/bash
API=http://127.0.0.1:8000/api/v1
declare -A T
while IFS='|' read -r p t; do T[$p]=$t; done < eval/resto/tokens.txt
EPS=(
 "GET /restaurant/ping"
 "GET /restaurant/branches"
 "GET /restaurant/zones"
 "GET /restaurant/tables"
 "GET /restaurant/products"
 "GET /restaurant/ingredients"
 "GET /restaurant/menus"
 "GET /restaurant/orders"
 "GET /restaurant/kitchen/orders"
 "GET /restaurant/pos-sessions/current"
 "GET /restaurant/stock-levels"
 "GET /restaurant/stock/alerts"
 "GET /restaurant/purchase-orders"
 "GET /restaurant/reservations"
 "GET /restaurant/reviews"
 "GET /restaurant/delivery-riders"
 "GET /restaurant/deliveries"
 "GET /restaurant/loyalty-programs"
 "GET /restaurant/promotions"
 "GET /restaurant/reports/sales"
 "GET /restaurant/reports/kpis"
 "GET /restaurant/dashboard/kpis"
 "GET /restaurant/health/metrics"
 "GET /restaurant/suppliers"
 "GET /restaurant/units"
 "GET /restaurant/tax-rates"
 "GET /restaurant/categories"
 "GET /restaurant/hours"
 "GET /restaurant/delivery-zones"
 "GET /restaurant/inventory-movements"
 "GET /restaurant/inventory-counts"
 "GET /restaurant/receivings"
 "GET /restaurant/print-jobs"
 "GET /restaurant/sync/status"
 "GET /restaurant/mobile/server/orders"
 "GET /restaurant/mobile/rider/deliveries"
 "GET /restaurant/mobile/manager/kpis"
 "GET /restaurant/mobile/manager/stock-alerts"
 "GET /restaurant/shop/token"
 "GET /restaurant/payments/configuration"
)
printf "%-42s" "ENDPOINT"
for p in principal gerant comptable superviseur chef serveur caisse livreur; do printf "%-12s" "$p"; done; echo
for e in "${EPS[@]}"; do
  M=${e%% *}; U=${e#* }
  printf "%-42s" "$U"
  for p in principal gerant comptable superviseur chef serveur caisse livreur; do
    C=$(curl -s -o /dev/null -w "%{http_code}" -X $M "$API$U" -H "Authorization: Bearer ${T[$p]}" -H "Accept: application/json")
    printf "%-12s" "$C"
  done
  echo
done
