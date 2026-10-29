#!/usr/bin/env bash
# ============================================================================
# backup-store.sh — abstraction de stockage objet pour les sauvegardes (#7657)
# ============================================================================
#
# Pourquoi ce script existe
# -------------------------
# `database-backup.yml` exigeait AWS S3 (`BACKUP_S3_BUCKET` +
# `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY`). Ces secrets n'ont jamais été
# posés : la sauvegarde quotidienne de la base de production n'a donc JAMAIS
# eu lieu, et le job échouait chaque jour en fail-loud (#6836, #7253).
#
# Or le dépôt possède DÉJÀ des identifiants Backblaze B2 opérationnels
# (`B2_KEY_ID`, `B2_APP_KEY`, `B2_BUCKET_NAME`), utilisés par
# `db-standby-failover.yml`. Refuser de s'en servir revenait à préférer une
# panne bruyante à une sauvegarde réelle.
#
# Ce script expose donc UNE interface, deux fournisseurs :
#
#   put <fichier> <clé>     téléverse un objet
#   latest <préfixe>        imprime « <horodatage ISO> <clé> <taille> » du
#                           dernier objet sous <préfixe>, ou rien
#
# Sélection du fournisseur (déterministe, jamais de mélange) :
#   1. `BACKUP_STORE=s3|b2` force le fournisseur (utile en test) ;
#   2. sinon S3 si `BACKUP_S3_BUCKET` + `AWS_ACCESS_KEY_ID` +
#      `AWS_SECRET_ACCESS_KEY` sont tous posés ;
#   3. sinon B2 si `B2_BUCKET_NAME` + `B2_KEY_ID` + `B2_APP_KEY` sont posés ;
#   4. sinon erreur explicite (exit 2) — jamais de no-op silencieux.
#
# Décision (DevOps) : B2 est utilisé via son API NATIVE, pas via sa passerelle
# S3. La passerelle exige un endpoint régional (`s3.<region>.backblazeb2.com`)
# qu'aucun secret du dépôt ne porte ; le deviner produirait un échec obscur.
# L'API native est déjà éprouvée dans `db-standby-failover.yml`.
#
# Usage :
#   dev-hub/scripts/backup-store.sh put    /tmp/dump.age postgres/daily/2026/10/29/dump.age
#   dev-hub/scripts/backup-store.sh latest postgres/daily/
#   dev-hub/scripts/backup-store.sh provider
# ============================================================================

set -euo pipefail

PROVIDER_OVERRIDE="${BACKUP_STORE:-}"

die() { echo "::error::$*" >&2; exit 1; }

# `::add-mask::` est une commande de workflow lue sur STDOUT par GitHub Actions.
# Or `latest` doit rendre une sortie PARSABLE (une seule ligne) : un masquage
# sur stdout la corromprait. On l'émet donc sur STDERR — GitHub ne le traite
# pas, mais le token n'est jamais imprimé nulle part (curl -fsS n'affiche ni
# en-têtes ni corps d'erreur), donc le masquage est une défense en profondeur,
# pas la seule barrière.
mask() { echo "::add-mask::$1" >&2; }

detect_provider() {
  if [[ -n "${PROVIDER_OVERRIDE}" ]]; then
    case "${PROVIDER_OVERRIDE}" in
      s3|b2) echo "${PROVIDER_OVERRIDE}" ;;
      *) die "BACKUP_STORE='${PROVIDER_OVERRIDE}' inconnu (attendu : s3 ou b2)." ;;
    esac
    return
  fi

  if [[ -n "${BACKUP_S3_BUCKET:-}" && -n "${AWS_ACCESS_KEY_ID:-}" && -n "${AWS_SECRET_ACCESS_KEY:-}" ]]; then
    echo "s3"
    return
  fi

  if [[ -n "${B2_BUCKET_NAME:-}" && -n "${B2_KEY_ID:-}" && -n "${B2_APP_KEY:-}" ]]; then
    echo "b2"
    return
  fi

  die "Aucun fournisseur de stockage configuré : poser soit BACKUP_S3_BUCKET + AWS_ACCESS_KEY_ID + AWS_SECRET_ACCESS_KEY, soit B2_BUCKET_NAME + B2_KEY_ID + B2_APP_KEY (issue #7657)."
}

# ── S3 (AWS ou compatible, endpoint explicite optionnel) ─────────────────────
s3_args() {
  local args=(--region "${AWS_REGION:-eu-west-3}")
  if [[ -n "${BACKUP_S3_ENDPOINT:-}" ]]; then
    args+=(--endpoint-url "${BACKUP_S3_ENDPOINT}")
  fi
  printf '%s\n' "${args[@]}"
}

s3_put() {
  local file="$1" key="$2"
  mapfile -t args < <(s3_args)
  # #7676 — pas de classe de stockage AWS-spécifique sur un fournisseur
  # S3-compatible (Backblaze B2 ne connaît que STANDARD).
  if [[ -n "${BACKUP_S3_ENDPOINT:-}" ]]; then
    aws s3 cp "${file}" "s3://${BACKUP_S3_BUCKET}/${key}" --sse AES256 "${args[@]}"
  else
    aws s3 cp "${file}" "s3://${BACKUP_S3_BUCKET}/${key}" --sse AES256 --storage-class STANDARD_IA "${args[@]}"
  fi

  # #7253 — un `aws s3 cp` peut sortir 0 sans objet exploitable : on relit.
  local remote_size local_size
  mapfile -t args < <(s3_args)
  remote_size="$(aws s3api head-object --bucket "${BACKUP_S3_BUCKET}" --key "${key}" "${args[@]}" --query 'ContentLength' --output text)"
  local_size="$(stat -c %s "${file}")"
  [[ "${remote_size}" == "${local_size}" ]] \
    || die "Sauvegarde incohérente (#7253) : distant=${remote_size} octets, local=${local_size} octets."
  echo "Backup verified: s3://${BACKUP_S3_BUCKET}/${key} (${local_size} octets)"
}

s3_latest() {
  local prefix="$1"
  mapfile -t args < <(s3_args)
  # La clé contient la date (postgres/daily/YYYY/MM/DD/...) : un tri
  # lexicographique suffit à isoler la sauvegarde la plus récente.
  local line
  line="$(aws s3 ls "s3://${BACKUP_S3_BUCKET}/${prefix}" --recursive "${args[@]}" | sort | tail -1 || true)"
  [[ -z "${line}" ]] && return 0
  # Format `aws s3 ls` : "2026-10-29 02:15:03   12345678 postgres/daily/..."
  local date_part time_part size key
  date_part="$(awk '{print $1}' <<<"${line}")"
  time_part="$(awk '{print $2}' <<<"${line}")"
  size="$(awk '{print $3}' <<<"${line}")"
  key="$(awk '{print $4}' <<<"${line}")"
  echo "${date_part}T${time_part}Z ${key} ${size}"
}

# ── Backblaze B2 (API native v2) ─────────────────────────────────────────────
# Les identifiants d'autorisation sont mis en cache dans des variables
# globales : `put` et `latest` d'un même run ne ré-authentifient pas.
B2_API_URL=""
B2_ACCOUNT_ID=""
B2_TOKEN=""
B2_BUCKET_ID=""

b2_auth() {
  [[ -n "${B2_TOKEN}" ]] && return 0

  local auth
  auth="$(curl -fsS -u "${B2_KEY_ID}:${B2_APP_KEY}" \
    "https://api.backblazeb2.com/b2api/v2/b2_authorize_account")" \
    || die "Authentification Backblaze B2 impossible (B2_KEY_ID / B2_APP_KEY invalides ?)."

  B2_API_URL="$(jq -r .apiUrl <<<"${auth}")"
  B2_ACCOUNT_ID="$(jq -r .accountId <<<"${auth}")"
  B2_TOKEN="$(jq -r .authorizationToken <<<"${auth}")"
  mask "${B2_TOKEN}"

  B2_BUCKET_ID="$(curl -fsS -H "Authorization: ${B2_TOKEN}" -H "Content-Type: application/json" \
    "${B2_API_URL}/b2api/v2/b2_list_buckets" \
    -d "$(jq -nc --arg a "${B2_ACCOUNT_ID}" --arg n "${B2_BUCKET_NAME}" '{accountId:$a, bucketName:$n}')" \
    | jq -r '.buckets[0].bucketId // empty')"

  [[ -n "${B2_BUCKET_ID}" ]] || die "Bucket Backblaze introuvable : ${B2_BUCKET_NAME}"
}

b2_put() {
  local file="$1" key="$2"
  b2_auth

  local up up_url up_token sha1 result file_id size
  up="$(curl -fsS -H "Authorization: ${B2_TOKEN}" \
    "${B2_API_URL}/b2api/v2/b2_get_upload_url?bucketId=${B2_BUCKET_ID}")"
  up_url="$(jq -r .uploadUrl <<<"${up}")"
  up_token="$(jq -r .authorizationToken <<<"${up}")"
  mask "${up_token}"

  sha1="$(sha1sum "${file}" | cut -d' ' -f1)"

  result="$(curl -fsS -X POST "${up_url}" \
    -H "Authorization: ${up_token}" \
    -H "X-Bz-File-Name: ${key}" \
    -H "Content-Type: application/octet-stream" \
    -H "X-Bz-Content-Sha1: ${sha1}" \
    --data-binary "@${file}")" \
    || die "Téléversement Backblaze échoué pour ${key}."

  file_id="$(jq -r .fileId <<<"${result}")"
  size="$(jq -r .contentLength <<<"${result}")"
  echo "Backup verified: b2://${B2_BUCKET_NAME}/${key} (${size} octets, fileId ${file_id:0:24}…)"
}

b2_latest() {
  local prefix="$1"
  b2_auth

  # b2_list_file_names trie par nom croissant : on demande le DERNIER nom
  # sous le préfixe en partant de la fin. L'API n'offre pas de tri descendant,
  # on pagine donc jusqu'à épuisement (les préfixes datés restent petits).
  local start="" names="" page next
  while :; do
    page="$(curl -fsS -H "Authorization: ${B2_TOKEN}" -H "Content-Type: application/json" \
      "${B2_API_URL}/b2api/v2/b2_list_file_names" \
      -d "$(jq -nc --arg b "${B2_BUCKET_ID}" --arg p "${prefix}" --arg s "${start}" \
        '{bucketId:$b, prefix:$p, startFileName:$s, maxFileCount:1000}')")" \
      || die "Listing Backblaze échoué sous ${prefix}."

    names+="$(jq -r '.files[] | "\(.fileName) \(.uploadTimestamp) \(.contentLength)"' <<<"${page}")"$'\n'
    next="$(jq -r '.nextFileName // empty' <<<"${page}")"
    [[ -z "${next}" ]] && break
    start="${next}"
  done

  local last
  last="$(printf '%s' "${names}" | sed '/^$/d' | sort | tail -1)"
  [[ -z "${last}" ]] && return 0

  local key ts_ms size
  key="$(awk '{print $1}' <<<"${last}")"
  ts_ms="$(awk '{print $2}' <<<"${last}")"
  size="$(awk '{print $3}' <<<"${last}")"
  # uploadTimestamp est en millisecondes epoch → ISO 8601 UTC.
  echo "$(date -u -d "@$(( ts_ms / 1000 ))" +%Y-%m-%dT%H:%M:%SZ) ${key} ${size}"
}

# ── Dispatch ─────────────────────────────────────────────────────────────────
main() {
  local cmd="${1:-}"
  case "${cmd}" in
    provider)
      detect_provider
      ;;
    put)
      [[ $# -eq 3 ]] || die "Usage : $0 put <fichier> <clé>"
      local file="$2" key="$3"
      [[ -f "${file}" ]] || die "Fichier introuvable : ${file}"
      case "$(detect_provider)" in
        s3) s3_put "${file}" "${key}" ;;
        b2) b2_put "${file}" "${key}" ;;
      esac
      ;;
    latest)
      [[ $# -eq 2 ]] || die "Usage : $0 latest <préfixe>"
      case "$(detect_provider)" in
        s3) s3_latest "$2" ;;
        b2) b2_latest "$2" ;;
      esac
      ;;
    *)
      die "Commande inconnue '${cmd}' (attendu : put | latest | provider)."
      ;;
  esac
}

main "$@"
