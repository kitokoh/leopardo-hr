# STOCKAGE ET SAUVEGARDES — LEOPARDO RH
# Version 1.0 | Mars 2026

---

## 1. ORGANISATION DU STOCKAGE (S3 / LOCAL)

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
