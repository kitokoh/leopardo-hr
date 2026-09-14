# Installation « 100 % gratuite » — assistant IA + commandes vocales

Ce guide monte l'assistant Leopardo (BC-23) sur des offres **gratuites** et vérifiées,
sans carte bancaire à l'étape Groq, avec une voix **française**.

> État vérifié le **2026-09-14** sur la documentation officielle Groq. Les offres
> évoluent : vérifie `console.groq.com/settings/limits` sur ton compte.

---

## 1. Pourquoi Groq, et pourquoi pas seulement Groq

| Brique | Fournisseur gratuit retenu | Pourquoi |
| :--- | :--- | :--- |
| **Modèle (raisonnement + outils)** | Groq `openai/gpt-oss-120b` | Gratuit, rapide, et **sait appeler des outils** (indispensable : sans tool-calling, l'assistant répond mais n'exécute rien) |
| **Transcription (écoute)** | Groq `whisper-large-v3` | Gratuit, multilingue (fr inclus) |
| **Synthèse vocale (voix)** | `edge-tts` | Gratuit **et français**. Groq propose aussi un TTS gratuit (`canopylabs/orpheus-*`) mais **anglais et arabe uniquement** — pas de français à ce jour |

⚠️ **Ne pas utiliser `llama-3.3-70b-versatile`** : ce modèle est passé « Enterprise /
Contact Sales » chez Groq. C'était le défaut historique du dépôt ; il a été corrigé
(issue #7379) mais un `.env` ancien peut encore le porter.

---

## 2. Obtenir la clé (gratuit, sans carte)

1. Créer un compte sur `console.groq.com`.
2. `API Keys` → `Create API Key` → copier la clé `gsk_...`.
3. Vérifier l'absence de carte bancaire requise (le free tier est ouvert).

**Limites du plan gratuit** (indicatives, à revérifier sur ton compte) :

| Modèle | RPM | RPD | TPM | TPD |
| :--- | ---: | ---: | ---: | ---: |
| `openai/gpt-oss-120b` | 30 | 1 000 | 8 K | 200 K |
| `whisper-large-v3` | 20 | 2 000 | 7,2 K ASH | 28,8 K ASD |
| `canopylabs/orpheus-*` (TTS) | 10 | 100 | 1,2 K | 3,6 K |

→ Largement suffisant pour un pilote. En usage intensif, l'assistant renverra une erreur
**429** explicite (pas un échec muet).

---

## 3. Installer la voix française (edge-tts)

`edge-tts` est un binaire Python. Il est **déjà installé dans l'image de production**
(`api/Dockerfile.prod`) ; en local :

```bash
pip install --break-system-packages edge-tts
command -v edge-tts   # doit renvoyer un chemin
```

Sans ce binaire, `/ai/voice/synthesize` répond **503 `TTS_UNAVAILABLE`** (fail-closed) —
jamais un faux succès.

---

## 4. Configuration (`api/.env`)

```dotenv
# --- Modèle + transcription (Groq, gratuit) ---
AI_ENABLED=true
AI_LLM_DRIVER=groq
GROQ_API_KEY=gsk_...
AI_GROQ_MODEL=openai/gpt-oss-120b

# --- Voix : entrée Groq (Whisper), sortie edge-tts (français, gratuit) ---
AI_STT_PROVIDER=whisper
AI_TTS_PROVIDER=edge_tts
# Voix forcée (optionnel) : fr-FR-DeniseNeural | ar-SA-ZariyahNeural | tr-TR-EmelNeural
# ELEVENLABS_API_KEY=        # laisser vide : le cloud payant ne doit pas prendre la main
```

Puis les **trois verrous** (sinon l'assistant reste éteint, c'est volontaire) :

```bash
# 1. registre d'outils (sinon le modèle ne reçoit AUCUN outil)
php artisan db:seed --class=AIToolRegistrySeeder

# 2. flags tenant : leo_ai (module) + ai_cloud_allowed (envoi vers un fournisseur cloud)
#    via l'admin plateforme, ou en SQL pour un tenant de test :
#    UPDATE public.companies SET features = features
#      || '{"leo_ai":true,"ai_cloud_allowed":true}'::jsonb WHERE slug = 'mon-tenant';
```

**Pourquoi `ai_cloud_allowed` ?** Groq est un fournisseur externe : la politique RGPD
(`AiCloudPolicy`) est *fail-closed* et refuse tout envoi tant que le tenant n'a pas
explicitement accepté le cloud. Une transcription vocale part chez Groq : c'est un
consentement à recueillir, pas une case technique.

---

## 5. Vérifier que ça marche

```bash
# 1. Le modèle répond et exécute un outil (le point qui compte vraiment)
curl -s -X POST "$API/api/v1/ai/chat" \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"message":"Combien avons-nous d employes actifs ?"}'
# → "tools_used" doit contenir get_headcount, pas un texte vague

# 2. La liste des outils exposés (doit être non vide)
curl -s "$API/api/v1/ai/tools" -H "Authorization: Bearer $TOKEN"
```

```bash
# 3. Une commande VOCALE de bout en bout (transcription → exécution → réponse audio)
curl -s -X POST "$API/api/v1/ai/voice/command" \
  -H "Authorization: Bearer $TOKEN" \
  -F "audio=@ordonnance.ogg" -F "language=fr"
```

---

## 6. Ce que l'assistant peut exécuter aujourd'hui

Un agent ne fait **que** ce que ses outils permettent. État réel du registre :

- **17 outils de lecture** — employés, pointages, absences, effectifs, paie, rapports…
- **8 outils d'écriture** — et **tous exigent une confirmation humaine** :
  - `create_absence`, `approve_absence`, `absence_decision`
  - `shift_assign`, `notify_team`
  - `create_employee` (nouveau, #7377)
  - `check_in_employee`, `check_out_employee` (nouveaux, #7378)

### Ce qui n'est PAS encore possible

`update_employee`, `delete_employee`, `create_salary_advance`, la validation de paie,
et toute action dont le handler n'existe pas. **Ce n'est pas une limite du modèle** :
il faut ajouter l'outil (garde `ToolRegistryCoverageTest`), sinon l'assistant ne fait
que *promettre* l'action — c'est précisément ce que le dépôt interdit depuis #5625.

### Confirmation humaine sur commande vocale

Chaque écriture rend `confirmation_required` avec un `pending_action_id` ; elle ne
s'exécute qu'après `POST /api/v1/ai/actions/{id}/confirm`. Pour un usage mains libres,
l'application mobile doit donc proposer une **confirmation vocale** (« dois-je
confirmer ? » → « oui ») : le backend ne l'autorise pas encore automatiquement.

---

## 7. Passer à un modèle auto-hébergé plus tard

Aucun verrou fournisseur : `App\AI\LLMClient` est une interface. Le plus direct est de
pointer le client OpenAI sur un serveur local **compatible OpenAI** (Ollama, vLLM,
LM Studio) :

```dotenv
AI_LLM_DRIVER=openai
OPENAI_BASE_URL=http://localhost:11434/v1
OPENAI_API_KEY=ollama
OPENAI_MODEL=gpt-oss:20b      # ou qwen2.5:14b, etc.
```

Deux points de vigilance :
1. le modèle local **doit** savoir appeler des outils (sinon l'assistant ne fait rien) ;
2. `openai` est classé « cloud » par `AiCloudPolicy` → le flag tenant `ai_cloud_allowed`
   reste exigé même si rien ne quitte ton infrastructure. Pour lever cette ambiguïté,
   ajouter un driver `local` explicite est un lot à part.
