# Leopardo Edge UI — PWA web-offline (Next.js static export)
#
# Issue #6604 : l'image `leopardo/edge-ui` n'était JAMAIS publiée (fantôme) —
# le service edge-ui du compose la construisait depuis nulle part. Le service
# edge-ui construit maintenant localement via ce Dockerfile (docker compose up
# --build). Pour publier l'image : `docker build -t leopardo/edge-ui:<v> .`
FROM node:20-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build
# output: 'export' (next.config.js) → out/

FROM nginx:1.27-alpine
COPY --from=build /app/out /usr/share/nginx/html
COPY nginx.edge.conf /etc/nginx/conf.d/default.conf
EXPOSE 3000
