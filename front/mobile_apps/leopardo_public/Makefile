# Leopardo RH — Mobile Build Commands
# Usage: make <target>
# Requires: Flutter SDK installed and in PATH
#
# ⚠️ Les recettes Make DOIVENT être indentées par une TABULATION, pas des
# espaces. Le fichier utilisait 8 espaces sur plusieurs cibles : `make` refuse
# alors la cible entière avec « missing separator ». Normalisé ici.

.PHONY: help deps run-dev run-staging build-staging build-prod build-web clean \
        e2e-restaurant e2e-restaurant-smoke e2e-check

API_BASE_URL ?= https://api.leopardo.africa/api/v1

help: ## Afficher l'aide
	@echo ""
	@echo "  🐆 LEOPARDO RH — Commandes de build Flutter"
	@echo ""
	@echo "  make run-dev        → Lancer en dev (émulateur local)"
	@echo "  make run-staging    → Lancer en staging (téléphone → backend Render)"
	@echo "  make build-staging  → Compiler APK pour testeurs (Render)"
	@echo "  make build-prod     → Compiler AAB pour Play Store"
	@echo "  make build-web      → Compiler la cible web grand public"
	@echo "  make e2e-restaurant → Tests E2E Maestro du parcours commande"
	@echo "  make e2e-check      → Vérifier que Maestro est installé"
	@echo "  make clean          → Nettoyer le projet"
	@echo "  make deps           → Installer les dépendances"
	@echo ""

deps: ## Installer les dépendances Flutter
	flutter pub get

run-dev: ## Lancer en mode développement (émulateur Android)
	flutter run --dart-define-from-file=.env.local

run-staging: ## Lancer en mode staging (téléphone connecté → backend Render)
	flutter run --dart-define-from-file=.env.staging

build-staging: deps ## Compiler l'APK pour les testeurs (backend Render)
	@echo "🔨 Compilation APK staging → backend Render..."
	flutter build apk --release \
		--dart-define-from-file=.env.staging \
		--build-name=1.0.0 \
		--build-number=1
	@echo ""
	@echo "✅ APK prêt !"
	@echo "📦 Fichier : build/app/outputs/flutter-apk/app-release.apk"
	@echo "📤 Uploader sur Firebase App Distribution ou partager directement"
	@echo ""

build-prod: deps ## Compiler l'AAB pour le Play Store (production)
	@echo "🚀 Compilation AAB production → Play Store..."
	flutter build appbundle --release \
		--dart-define-from-file=.env.production \
		--build-name=1.0.0 \
		--build-number=1
	@echo ""
	@echo "✅ AAB prêt !"
	@echo "📦 Fichier : build/app/outputs/bundle/release/app-release.aab"
	@echo "📤 À uploader sur Google Play Console"
	@echo ""

# ── Cible web grand public (verticale restaurant) ──
#
# `--pwa-strategy=none` : on ne sert PAS de service worker ici. Un worker mis
# en cache par une version précédente continuerait à servir l'ancien
# `main.dart.js` après un déploiement, alors que le contrat restaurant évolue
# (nouveaux champs, nouveaux statuts). Pour un site public dont le HTML doit
# rester à jour, un cache navigateur mal invalidé coûte plus qu'il ne rapporte.
#
# `--base-href=/` : les routes sont des chemins réels (`/restaurants/{slug}`),
# donc l'app doit être servie à la racine du domaine.
build-web: deps ## Compiler la cible web grand public (build/web)
	@echo "🌍 Compilation de la cible web → build/web..."
	flutter build web --release \
		--base-href=/ \
		--pwa-strategy=none \
		--no-wasm-dry-run \
		--dart-define=API_BASE_URL=$(API_BASE_URL) \
		--build-name=$(shell sed -n 's/^version:[[:space:]]*\([^+]*\).*/\1/p' pubspec.yaml | head -1) \
		--build-number=$(shell sed -n 's/^version:[[:space:]]*[^+]*+\([0-9]*\).*/\1/p' pubspec.yaml | head -1)
	@echo ""
	@echo "✅ Cible web prête !"
	@echo "📦 Dossier : build/web"
	@echo "🔗 Routes publiques : /restaurants/{slug}"
	@echo ""
	@echo "⚠️  Le serveur doit renvoyer index.html sur toute route inconnue"
	@echo "    (SPA fallback), sinon /restaurants/x répond 404 au rechargement."
	@echo ""

clean: ## Nettoyer le projet Flutter
	flutter clean
	flutter pub get
	@echo "✅ Projet nettoyé"

# ── Tests E2E Maestro (parcours commande restaurant) ──
#
# Ces cibles pilotent l'app INSTALLÉE sur un émulateur ou un appareil branché.
# Elles ne remplacent pas `flutter test` : les tests widget vérifient la logique
# avec un serveur simulé, Maestro vérifie ce que voit le client (clavier qui
# masque un bouton, liste qui ne défile pas jusqu'au champ).
#
# `RESTO_SLUG` et `RESTO_PRODUCT_CODE` sont passés en variables d'environnement
# Maestro : aucun identifiant d'établissement n'est codé dans les flows, sinon
# ils casseraient au premier changement de la base de démonstration.
RESTO_SLUG ?= le-bistrot
RESTO_PRODUCT_CODE ?= demo-1
MAESTRO ?= maestro

e2e-check: ## Vérifier que Maestro est installé
	@command -v $(MAESTRO) >/dev/null 2>&1 || { \
		echo "❌ Maestro introuvable."; \
		echo "   Installer : curl -Ls \"https://get.maestro.mobile.dev\" | bash"; \
		echo "   Puis : export PATH=\"\$$PATH:\$$HOME/.maestro/bin\""; \
		exit 1; \
	}
	@$(MAESTRO) --version

e2e-restaurant: e2e-check ## Tests E2E Maestro — parcours commande restaurant
	@echo "🧪 Parcours commande restaurant (slug=$(RESTO_SLUG))..."
	RESTO_SLUG=$(RESTO_SLUG) \
	RESTO_PRODUCT_CODE=$(RESTO_PRODUCT_CODE) \
		$(MAESTRO) test maestro/restaurant/
	@echo "✅ Parcours restaurant vert"

e2e-restaurant-smoke: e2e-check ## Tests E2E Maestro — parcours nominal seulement
	@echo "🧪 Parcours nominal restaurant (slug=$(RESTO_SLUG))..."
	RESTO_SLUG=$(RESTO_SLUG) \
	RESTO_PRODUCT_CODE=$(RESTO_PRODUCT_CODE) \
		$(MAESTRO) test --include-tags smoke maestro/restaurant/
	@echo "✅ Parcours nominal vert"
