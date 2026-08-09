# 🔐 Données au repos — inventaire & chiffrement (F-17)

> Programme FOCUS — état des lieux du chiffrement au repos et plan d'extension.

## État actuel
- **SensitiveDataEncryptor** (`api/app/Core/Auth/Infrastructure/Services/SensitiveDataEncryptor.php`) : chiffrement AES-256 des champs sensibles déjà identifiés (identifiants nationaux, banques — à confirmer la liste exacte).
- Chiffrement en transit : TLS (config API) ; base : PostgreSQL (chiffrement disque côté hébergeur).

## Inventaire des données sensibles paie (à chiffrer / à vérifier)
| Donnée | Colonne/modèle | Statut |
|---|---|---|
| Salaire de base | salary_structures.base_salary | ⚠️ à chiffrer ou documenter l'exception |
| RIB / IBAN employé | employees.iban / bank_account | ✅ SensitiveDataEncryptor (à vérifier) |
| Net / brut bulletins | pay_slips.* | ⚠️ à évaluer (nécessaire aux exports/recalculs) |
| Historique de paie | payroll_runs.* | ⚠️ à évaluer |
| Biométrie (templates) | kiosk/mobile | 🔴 politique de rétention à documenter |
| Identifiants nationaux | employees.* | ✅ SensitiveDataEncryptor (à vérifier) |

## Décisions à prendre (prochaine itération)
1. Chiffrer les colonnes de salaire au repos (impact : recherche/agrégats → clé de chiffrement par tenant + index dédiés ou chiffrement au niveau application).
2. Ne JAMAIS loguer de données sensibles (scan CI des logs — StructuredLogging).
3. Rétention biométrie : durée + purge automatique (lien #1474).

## Tests
- SensitiveDataEncryptor : tests existants ? (à compléter) — chiffrement/déchiffrement + round-trip + clés différentes par tenant.
