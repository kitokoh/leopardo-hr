# Leopardo RH — Mobile Build Commands
# Usage: make <target>
# Requires: Flutter SDK installed and in PATH

.PHONY: help run-dev build-staging build-prod clean deps

help: ## Afficher l'aide
	@echo ""
	@echo "  🐆 LEOPARDO RH — Commandes de build Flutter"
	@echo ""
	@echo "  make run-dev        → Lancer en dev (émulateur local)"
	@echo "  make build-staging  → Compiler APK pour testeurs (Render)"
	@echo "  make build-prod     → Compiler AAB pour Play Store"
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

clean: ## Nettoyer le projet Flutter
	flutter clean
	flutter pub get
	@echo "✅ Projet nettoyé"
