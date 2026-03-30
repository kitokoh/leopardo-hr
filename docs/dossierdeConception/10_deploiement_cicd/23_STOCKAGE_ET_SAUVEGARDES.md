# STOCKAGE ET SAUVEGARDES — LEOPARDO RH
# Version 2.0 | Mars 2026 — DÉCISION TRANCHÉE MVP

---

## 0. DÉCISION STOCKAGE MVP — LECTURE OBLIGATOIRE

> **DÉCISION FINALE :** Stockage **local** pour la Phase 1 (MVP), migration vers **Cloudflare R2** en Phase 2.

| Phase | Stratégie | Raison |
|-------|-----------|--------|
| **Phase 1 — MVP** | `FILESYSTEM_DISK=local` (storage Laravel o2switch) | Simplicité, zéro coût, hébergement o2switch inclus |
| **Phase 2 — Scale** | Migration vers Cloudflare R2 (compatible S3, gratuit <10GB) | Scalabilité, CDN, backup automatique |

**Impact .env.example Phase 1 :**
```env
FILESYSTEM_DISK=local
# AWS_* non utilisés en Phase 1 — laisser vides
```

**Serveur des fichiers privés :** via contrôleur Laravel uniquement (JAMAIS d'URL directe `storage/`).
```
GET /api/v1/storage/payslips/{id}    → vérifie auth + permission → stream le fichier
GET /api/v1/storage/attachments/{id} → vérifie auth + permission → stream le fichier
```

**Taille estimée Phase 1 (50 clients, 18 mois) :**
- Photos de pointage : ~50 clients × 50 emp × 25 jours × 50KB = ~3 GB
- Bulletins PDF : ~50 clients × 50 emp × 18 mois × 100KB = ~4.5 GB
- **Total estimé : ~8 GB** — o2switch SSD inclus (largement suffisant pour Phase 1)

---



Les fichiers sont organisés par tenant pour faciliter les purges et la migration :

```
storage/app/public/
├── logos/                      # Logos entreprises (publics)
├── {tenant_uuid}/
│   ├── photos/                 # Photos pointage (privé)
│   ├── attachments/            # Justificatifs absences (privé)
│   ├── payslips/               # Bulletins PDF (privé, crypté)
│   └── exports/                # Exports bancaires CSV/XML (temporaire)
```

**Visibilité :**
- Les fichiers privés ne sont **jamais** accessibles via une URL directe.
- Ils sont servis par un contrôleur Laravel qui vérifie les permissions : `GET /storage/payslips/{id}`.

---

## 2. STRATÉGIE DE SAUVEGARDE

| Type | Fréquence | Rétention | Destination |
|------|-----------|-----------|-------------|
| Base de données (DUMP) | Quotidien | 30 jours | Serveur Backup o2switch |
| Base de données (WAL) | Toutes les heures | 24h | Local + Cloud |
| Fichiers Storage | Quotidien | 90 jours | Object Storage (R2 / S3) |
| Logs (Audit) | Permanent | 24 mois | Base de données |

---

## 3. PLAN DE REPRISE D'ACTIVITÉ (DRP)

En cas de crash serveur o2switch :
1. Provisionnement d'un nouveau VPS.
2. Restauration de la dernière image Docker / Code via Git.
3. Restauration du dernier DUMP PostgreSQL.
4. Pointage DNS vers la nouvelle IP.
*Temps estimé de rétablissement (RTO) : 2 heures.*
