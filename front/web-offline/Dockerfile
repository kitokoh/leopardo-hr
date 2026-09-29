# ============================================================
# Leopardo Edge Web UI — PWA web-offline (Next.js static export)
# ============================================================
# #6595 (audit Vague 2) : image `leopardo/edge-ui` construite et publiée
# par edge/publish.sh (service `edge-ui` du compose Edge). La PWA est un
# export statique (next.config.js → output: 'export') : l'URL de l'API est
# inlinée au BUILD via l'ARG NEXT_PUBLIC_EDGE_API (les variables d'env
# runtime ne sont PAS relues par un export statique).
# ============================================================
FROM node:22-alpine AS build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .

# Même valeur par défaut que next.config.js (fallback local Edge).
ARG NEXT_PUBLIC_EDGE_API=http://leopardo.local:7878
ENV NEXT_PUBLIC_EDGE_API=$NEXT_PUBLIC_EDGE_API

RUN npm run build

# ── Serveur statique ───────────────────────────────────────
FROM nginx:1.27-alpine

# #8267 — la base publiée dérive du repo Alpine entre deux reconstructions
# upstream (scan hebdo image-scan.yml ROUGE : 40 CVE CRITICAL/HIGH
# corrigibles au 2026-09-28, correctifs tous publiés en v3.21). Monter les
# paquets OS au build rend l'image auto-cicatrisante.
RUN apk upgrade --no-cache

COPY --from=build /app/out /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 3000

CMD ["nginx", "-g", "daemon off;"]
