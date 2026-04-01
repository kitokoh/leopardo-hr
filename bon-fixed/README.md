# BON — Facebook Groups Publisher

Module autonome de publication automatisée dans des groupes Facebook.  
**Version 5.0** — Architecture Moderne & Robuste — Playwright · Anti-Détection · Multi-langue · Dashboard

## 🚀 Quoi de neuf en v5.0 ?

### Architecture Modernisée (Recommandations Ami)

- **Couche d'abstraction unifiée** (`automation/engine.py`) : Interface unique pour toutes les actions
  ```python
  engine.open_group()
  engine.write_post()
  engine.upload_image()
  engine.click_publish()
  ```
  
- **Sélecteurs centralisés multi-langue** (`config/selectors/*.json`) :
  - `facebook_fr.json` — Interface française
  - `facebook_en.json` — Interface anglaise
  - `facebook_ar.json` — Interface arabe
  
- **Fallback automatique intelligent** : Pour chaque élément
  - Essai 1 → role/aria-label
  - Essai 2 → texte visible
  - Essai 3 → XPath sémantique
  - Essai 4 → CSS de secours
  
- **Selector Tester** (`python -m automation.selector_tester`) : Outil de débogage interactif
  - Testez un sélecteur sur n'importe quelle URL
  - Capture d'écran automatique
  - Snippet HTML sauvegardé
  
- **Santé des sélecteurs** (`logs/selector_health.json`) :
  - Taux de succès par sélecteur
  - Détection automatique des sélecteurs morts
  - Alternatives proposées
  
- **Anti-blocage intelligent** (`automation/anti_block.py`) :
  - Maximum 5 groupes par heure
  - Pause aléatoire 30–120 secondes
  - Pause longue après 3 publications
  - Pas de même texte deux fois de suite
  - Pas de même image plus de 2 fois
  
- **Séparation contenu/moteur** (`data/`):
  - `campaigns/campaigns.json` — Textes multi-variantes (Farmoos, Doorika, etc.)
  - `groups/groups.json` — Groupes par catégorie/langue
  - Variation automatique du contenu
  
- **Profils navigateurs persistants** (`profiles/`) :
  - Cookies et sessions conservés
  - Un profil par compte Facebook

---

## Changelog v4.1 (Version précédente)

- **Migration Selenium → Playwright terminée** : Le fichier `test.py` a été entièrement réécrit pour utiliser Playwright au lieu de Selenium (obsolète)
- **Code modernisé** : Utilisation de `pathlib`, `press_sequentially()`, et des API natives Playwright
- **Meilleure gestion des erreurs** : Timeouts configurables, retries automatiques, screenshots sur erreur
- **Interface interactive** : Menu interactif dans `test.py` pour tester facilement les différentes fonctionnalités

---

## Changelog v4.0 — Distribution Ready

- **Fix C-08** : `check_license.py` réécrit — MAC lue dynamiquement (plus de valeur hardcodée), `get_serial_number()` utilise PowerShell en priorité (compatible Windows 11 22H2+), fallback UUID universel macOS/Linux.
- **Fix C-06** : `SELECTORS_CDN_URL` configurable via variable d'environnement `BON_SELECTORS_CDN_URL`. Alerte automatique si les sélecteurs dépassent `BON_SELECTORS_MAX_AGE_DAYS` (défaut : 30 jours).
- **Fix C-09** : `examples/pyqt_integration.py` ajouté — exemple complet de lancement subprocess, lecture logs JSONL temps réel, arrêt propre SIGTERM.
- **Fix C-07** : `tests/test_smoke.py` ajouté — 41 tests unitaires (resolve_media_path, DEFAULT_SESSION_CONFIG, timing limits, data files, license parsing). Sans Playwright ni réseau.
- **Fix C-02** : Avertissement automatique si `storage_state` manquant au démarrage (session expirée) avec hint de commande.
- **Fix C-06b** : Check Playwright au démarrage dans `__main__.py` — message d'erreur clair + redirection vers `install.py`.

## Changelog v3.1

- **Fix critique** : `resolve_media_path()` gère maintenant correctement les chemins Windows legacy (antislash) sur Linux et macOS — les images des utilisateurs Windows ne sont plus perdues silencieusement sur les autres OS.
- **Fix** : `DEFAULT_SESSION_CONFIG` complété avec les champs manquants (`add_comments`, `comments`, `marketplace`, `cooldown_between_runs_s`).
- **Fix** : `run_login()` délègue désormais à `SessionManager.create_session()` — suppression du code dupliqué.
- **Sécurité** : `data.json` et `data1.json` remplacés par des exemples neutres — suppression des chemins personnels Windows exposés.
- **Ajout** : `check_license.py` réintégré (absent de v3).

---

---

## Architecture

Le module tourne **en autonome** dans son propre venv.  
L'app PyQt ne l'intègre pas directement — elle le **configure, le planifie, et lit ses logs**.

```
PyQt App
  └─ subprocess.run("python -m bon post --session compte1")
  └─ tail(LOGS_DIR/activity.jsonl)   ← logs JSON Lines temps réel
  └─ os.kill(pid, SIGTERM)           ← arrêt propre après groupe en cours
```

---

## Installation

```bash
python install.py
```

Crée un venv `.venv/`, installe les dépendances, et télécharge Chromium via Playwright (~300MB).

---

## Utilisation

### 1. Créer une session (login manuel une fois)

```bash
python -m bon login --session compte1
```

Une fenêtre Chrome s'ouvre. Connectez-vous à Facebook manuellement, puis appuyez sur Entrée.  
La session est sauvegardée dans `~/.config/bon/sessions/compte1_state.json`.

### 2. Configurer les posts et groupes

Éditez `~/.config/bon/sessions/compte1.json` :

```json
{
    "session_name": "compte1",
    "storage_state": "~/.config/bon/sessions/compte1_state.json",
    "max_groups_per_run": 10,
    "delay_between_groups": [60, 120],
    "max_runs_per_day": 2,
    "posts": [
        {
            "text": "Votre texte de publication",
            "image": "mon_image.jpg",
            "weight": 1
        }
    ],
    "groups": [
        "https://www.facebook.com/groups/123456789/"
    ]
}
```

Placez vos images dans : `~/.config/bon/media/compte1/`

### 3. Publier

```bash
python -m bon post --session compte1
# ou en mode invisible :
python -m bon post --session compte1 --headless
```

### 4. Rechercher des groupes

```bash
python -m bon save-groups --session compte1 --keyword "machines hydrauliques"
```

### 5. Lister les sessions

```bash
python -m bon list-sessions
```

---

## Migration depuis l'ancienne version

Si vous avez un ancien `data.json`, utilisez l'outil de migration :

```bash
python migrate_data.py --data data.json --session mon_compte
python migrate_data.py --data data1.json --session mon_compte
```

---

## Structure du projet

```
bon/
├── __main__.py              # Point d'entrée CLI
├── install.py               # Script d'installation venv + Playwright
├── migrate_data.py          # Migration depuis l'ancien format
├── requirements.txt         # Dépendances avec versions fixées
│
├── libs/
│   ├── playwright_engine.py # Moteur Playwright (remplace Selenium)
│   ├── scraper.py           # Logique métier (publication, sauvegarde groupes)
│   ├── session_manager.py   # Gestion des sessions par compte
│   ├── selector_registry.py # Sélecteurs multi-fallback + CDN
│   ├── config_manager.py    # Chemins cross-platform
│   ├── config_validator.py  # Validation config au démarrage
│   ├── timing_humanizer.py  # Délais humains, limites de fréquence
│   ├── log_emitter.py       # Logs JSON Lines lisibles par PyQt
│   └── error_handlers.py    # Retry, détection états bloquants
│
├── config/
│   └── selectors.json       # Sélecteurs multi-fallback versionnés
│
└── conception/
    ├── analyse_bon_module.pdf
    ├── plan_action_bon_v2.pdf
    └── robustness_ideas.md
```

---

## Logs — Interface PyQt

Les logs sont écrits en JSON Lines dans `~/.config/bon/logs/activity.jsonl`.

Format de chaque ligne :
```json
{"ts": "2026-03-31T14:32:11", "level": "INFO", "event": "SESSION_START", "compte": "compte1"}
{"ts": "2026-03-31T14:32:22", "level": "SUCCESS", "event": "POST_PUBLISHED", "compte": "compte1", "groupe": "https://..."}
{"ts": "2026-03-31T14:33:01", "level": "ERROR", "event": "SESSION_EXPIRED", "compte": "compte1"}
```

Niveaux : `DEBUG` · `INFO` · `SUCCESS` · `WARN` · `ERROR`

### Lecture temps réel depuis PyQt

```python
class LogWatcher(QThread):
    new_line = pyqtSignal(dict)

    def run(self):
        log_path = pathlib.Path.home() / ".config/bon/logs/activity.jsonl"
        with open(log_path, "r") as f:
            f.seek(0, 2)  # aller à la fin
            while self.running:
                line = f.readline()
                if line.strip():
                    self.new_line.emit(json.loads(line))
                else:
                    time.sleep(0.3)
```

### Savoir si le module tourne

```python
import os, pathlib, signal

pid_file = pathlib.Path.home() / ".config/bon/logs/running.pid"
if pid_file.exists():
    pid = int(pid_file.read_text())
    # Arrêt propre :
    os.kill(pid, signal.SIGTERM)  # Linux/Mac
```

---

## Sélecteurs — Mise à jour

Les sélecteurs Facebook changent régulièrement. Pour mettre à jour :

1. **Automatiquement** : le module vérifie le CDN à chaque démarrage (configurer `SELECTORS_CDN_URL` dans `selector_registry.py`)
2. **Manuellement** : éditer `config/selectors.json` — format v2 avec liste de fallbacks :

```json
{
    "version": "2026-03",
    "selectors": {
        "submit": {
            "selectors": [
                "[aria-label*=\"Post\"][role=\"button\"]",
                "[data-testid='react-composer-post-button']"
            ]
        }
    }
}
```

---

## Limites recommandées (anti-détection)

| Paramètre | Valeur recommandée |
|---|---|
| Groupes par session | 10–15 max |
| Délai entre groupes | 60–120 secondes |
| Sessions par jour | 2–3 max |
| Cooldown entre sessions | 2 heures minimum |

---

## Compatibilité

| OS | Statut |
|---|---|
| Windows 10/11 | ✓ Supporté |
| Ubuntu 20+ | ✓ Supporté |
| macOS 12+ | ✓ Supporté |

---

## Dépendances principales

| Package | Version | Rôle |
|---|---|---|
| playwright | 1.42.0 | Moteur navigateur |
| python-dotenv | 1.0.1 | Variables d'environnement |
| requests | 2.31.0 | Mise à jour sélecteurs CDN |
| pyarmor | 8.4.0 | Obfuscation code |
| pycryptodome | 3.20.0 | Cryptographie |

---

*BON v2.0 — Mars 2026 — Document de travail interne*

---

## 📁 Nouvelle Structure du Projet

```
/workspace/
├── automation/              # NOUVEAU — Couche d'abstraction moderne
│   ├── __init__.py
│   ├── engine.py            # Interface unifiée (open_group, write_post, etc.)
│   ├── playwright_engine.py # Wrapper Playwright
│   ├── selenium_engine.py   # Fallback Selenium (obsolète)
│   ├── selector_tester.py   # Outil de test de sélecteurs
│   ├── selector_health.py   # Santé des sélecteurs
│   └── anti_block.py        # Gestion anti-blocage
│
├── config/
│   ├── selectors.json       # Sélecteurs principaux (existant)
│   └── selectors/           # NOUVEAU — Sélecteurs par langue
│       ├── facebook_fr.json
│       ├── facebook_en.json
│       └── facebook_ar.json
│
├── data/                    # NOUVEAU — Données séparées du moteur
│   ├── campaigns/
│   │   └── campaigns.json   # Campagnes multi-variantes
│   ├── groups/
│   │   └── groups.json      # Groupes par catégorie
│   ├── texts/
│   │   ├── fr/
│   │   ├── en/
│   │   └── ar/
│   └── images/
│
├── logs/
│   ├── screenshots/         # Captures d'erreur automatiques
│   ├── html/                # Snippets HTML pour débogage
│   └── selector_health.json # Santé des sélecteurs
│
├── profiles/                # NOUVEAU — Profils navigateurs persistants
│   ├── account_1/
│   ├── account_2/
│   └── ...
│
├── libs/                    # Bibliothèque existante (inchangée)
│   ├── playwright_engine.py
│   ├── selector_registry.py
│   ├── scraper.py
│   └── ...
│
└── tools/                   # NOUVEAU — Outils utilitaires
    └── dashboard.py         # Dashboard minimal (à venir)
```

---

## 🛠️ Utilisation de la Nouvelle Architecture

### 1. Tester un sélecteur

```bash
# Mode interactif
python -m automation.selector_tester

# Mode commande
python -m automation.selector_tester --url "https://facebook.com" --selector "[role='button']"
```

### 2. Utiliser l'interface unifiée

```python
from automation import AutomationEngine

with AutomationEngine(headless=False) as engine:
    engine.open_group("https://facebook.com/groups/123/")
    engine.write_post("Mon texte de publication")
    engine.upload_image("/chemin/vers/image.jpg")
    engine.click_publish()
```

### 3. Vérifier la santé des sélecteurs

```python
from automation import get_health_manager

hm = get_health_manager()
print(hm.generate_report())

# Détecter les sélecteurs morts
dead = hm.detect_dead_selectors(threshold=30)
if dead:
    print(f"Sélecteurs morts: {dead}")
```

### 4. Gérer l'anti-blocage

```python
from automation import get_anti_block_manager

abm = get_anti_block_manager()

# Vérifier si on peut poster
can_post, reason = abm.can_post()
if can_post:
    abm.record_post(
        group_url="https://facebook.com/groups/123/",
        text="Mon texte",
        images=["image1.jpg"]
    )
else:
    print(f"Ne peut pas poster: {reason}")
```

---

## ⚠️ Ce qu'il ne faut PLUS faire

- ❌ Sélecteurs codés en dur dans les fichiers Python
- ❌ XPath Facebook très longs avec classes obfusquées (`x1lliihq`, `x78zum5`)
- ❌ `nth-child` dans les sélecteurs
- ❌ Même texte dans 20 groupes
- ❌ Même compte qui publie trop vite (> 5 groupes/heure)
- ❌ Ignorer les captures d'écran sur erreur

---

## 📊 Ordre de Développement (Statut)

| Étape | Description | Statut | Fichier |
|-------|-------------|--------|---------|
| 1 | Intégrer Playwright | ✅ Déjà fait (v4.1) | `libs/playwright_engine.py` |
| 2 | Centraliser tous les sélecteurs | ✅ Fait (v5.0) | `config/selectors/*.json` |
| 3 | Créer fallback automatique | ✅ Fait (v5.0) | `libs/selector_registry.py` |
| 4 | Ajouter captures + logs | ✅ Déjà fait + amélioré (v5.0) | `logs/screenshots/`, `logs/html/` |
| 5 | Profils persistants | ✅ Structure créée (v5.0) | `profiles/` |
| 6 | Multi-langue | ✅ Fait (v5.0) | `config/selectors/{fr,en,ar}.json` |
| 7 | Variation des textes/images | ✅ Fait via campaigns.json (v5.0) | `data/campaigns/campaigns.json` |
| 8 | Anti-blocage | ✅ Fait (v5.0) | `automation/anti_block.py` |
| 9 | Dashboard | ✅ Fait (v5.1) | `tools/dashboard.py` |
| 10 | Base de données SQLite | ✅ Fait (v5.1) | `libs/database.py` |
| 11 | Santé des comptes | ✅ Fait (v5.1) | `libs/database.py` (table `accounts`) |
| 12 | Scoring des groupes | ✅ Fait (v5.1) | `libs/database.py` (table `groups`) |
| 13 | Test sur plusieurs comptes | 🔄 À tester | - |

---

## 🎯 Analyse des Recommandations Ami (Statut d'Implémentation)

### ✅ Implémenté dans v5.1

| Recommandation | Statut | Détails |
|----------------|--------|---------|
| Rotation comportementale humaine | ✅ Partiel | Délais aléatoires dans `anti_block.py`, timing humanizer existant |
| Rotation de contenu intelligente | ✅ Oui | Variantes multiples dans `campaigns.json` |
| Architecture multi-comptes | ✅ Oui | Table `accounts` avec statuts et health scores |
| Selector health tracking | ✅ Oui | `selector_health.py` + table `selector_stats` |
| Base de données SQLite | ✅ Oui | `libs/database.py` avec 6 tables |
| Système d'analyse de performance | ✅ Oui | Dashboard + stats dans DB |
| Qualification des groupes | ✅ Partiel | Score de qualité dans table `groups` |
| Dashboard minimal | ✅ Oui | `tools/dashboard.py` CLI + JSON export |

### 🔄 À Améliorer (Recommandé)

| Fonctionnalité | Priorité | Effort | Notes |
|----------------|----------|--------|-------|
| Mouvements souris réels | Moyenne | Moyen | Nécessite integration avec `playwright` mouse API |
| IA de réécriture automatique | Faible | Élevé | Nécessite API externe (OpenAI, etc.) |
| Publication par fuseau horaire | Moyenne | Faible | Ajouter scheduler avec timezone support |
| Warm-up des comptes | Moyenne | Moyen | Automatiser visites/likes avant publication |
| Interface PyQt complète | Élevée | Élevé | Dashboard existe en CLI, reste à intégrer PyQt |

### ❌ Non Opportun (Pour l'instant)

| Fonctionnalité | Raison |
|----------------|--------|
| Migration totale depuis JSON | JSON reste utile pour config, DB pour suivi |
| CDN pour sélecteurs | Déjà implémenté mais optionnel |
| Système de commentaires auto | Risque de détection trop élevé |

---

*BON v5.1 — Mars 2026 — Architecture moderne et robuste avec SQLite & Dashboard*

---

## 🚀 Utilisation du Dashboard

### Mode CLI (texte)

```bash
# Affichage unique
python -m tools.dashboard --no-refresh

# Mode interactif avec rafraîchissement automatique (5s)
python -m tools.dashboard

# Rafraîchissement toutes les 10 secondes
python -m tools.dashboard --interval 10
```

### Mode JSON (pour intégration PyQt)

```bash
python -m tools.dashboard --json
```

Sortie exemple:
```json
{
  "timestamp": "2026-03-31T12:22:59",
  "general_stats": {
    "total_accounts": 3,
    "healthy_accounts": 2,
    "blocked_accounts": 0,
    "posts_today": 15,
    "success_rate_today": 93.3
  },
  "accounts": [...],
  "recent_errors": [...],
  "selector_health": {...},
  "dead_selectors": []
}
```

### Intégrer dans PyQt

```python
import subprocess
import json

def get_dashboard_data():
    result = subprocess.run(
        ["python", "-m", "tools.dashboard", "--json"],
        capture_output=True,
        text=True
    )
    return json.loads(result.stdout)

# Utiliser dans votre interface
data = get_dashboard_data()
print(f"Posts aujourd'hui: {data['general_stats']['posts_today']}")
```

---

## 📚 Nouvelle Structure Complète (v5.1)

```
/workspace/
├── automation/              # Couche d'abstraction moderne
│   ├── __init__.py
│   ├── engine.py            # Interface unifiée
│   ├── playwright_engine.py # Wrapper Playwright
│   ├── selenium_engine.py   # Fallback Selenium
│   ├── selector_tester.py   # Outil de test
│   ├── selector_health.py   # Santé des sélecteurs
│   └── anti_block.py        # Anti-blocage
│
├── libs/                    # Bibliothèque principale
│   ├── database.py          # NOUVEAU: SQLite DB ⭐
│   ├── playwright_engine.py
│   ├── selector_registry.py
│   ├── scraper.py
│   ├── session_manager.py
│   └── ...
│
├── tools/                   # Outils utilitaires
│   ├── dashboard.py         # NOUVEAU: Dashboard ⭐
│   └── ...
│
├── config/selectors/        # Sélecteurs par langue
│   ├── facebook_fr.json
│   ├── facebook_en.json
│   └── facebook_ar.json
│
├── data/                    # Données séparées
│   ├── campaigns/campaigns.json
│   ├── groups/groups.json
│   └── texts/{fr,en,ar}/
│
├── logs/
│   ├── bon.db               # NOUVEAU: Base SQLite ⭐
│   ├── screenshots/
│   ├── html/
│   └── selector_health.json
│
└── profiles/                # Profils navigateurs
```
