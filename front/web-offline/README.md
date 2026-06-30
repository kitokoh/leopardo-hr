# Leopardo Edge — Web Offline (PWA)

Interface web locale accessible via `http://leopardo.local` quand le nœud Edge
est actif sur le réseau LAN. Conçue pour les managers/superviseurs sans
application mobile.

## Fonctionnalités

- ✅ Pointage manuel (check-in / check-out) via QR-code ou saisie badge
- ✅ Liste des présences du jour (temps réel depuis SQLite Edge)
- ✅ Fonctionne **100 % offline** — service worker cache toutes les assets
- ✅ Sync status badge : indique si le Cloud est accessible ou non
- ✅ Responsive (mobile + desktop)

## Stack

- HTML5 + CSS (Tailwind CDN)
- Vanilla JS (pas de bundler — zéro dépendance build)
- Service Worker (Cache API) pour le offline-first
- Fetch vers `http://leopardo.local/api/v1/edge/*`

## Démarrage local

```bash
# Depuis n'importe quel HTTP server :
cd front/web-offline
npx serve .
# ou
python3 -m http.server 8080
```

## Production

L'image Docker Edge (`leopardo/edge-api`) sert ce dossier statiquement via
Caddy sur `http://leopardo.local/`.

## Structure

```
front/web-offline/
├── index.html          # App shell (PWA)
├── manifest.json       # Web App Manifest
├── sw.js               # Service Worker
├── src/
│   ├── js/
│   │   ├── app.js      # Logique principale
│   │   ├── api.js      # Client HTTP Edge API
│   │   └── ui.js       # Composants UI
│   └── css/
│       └── app.css     # Styles custom (complément Tailwind)
└── public/
    └── icons/          # Icônes PWA (192, 512)
```
