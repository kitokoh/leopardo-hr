# FAQ — Leopardo RH (F-25)

> Programme FOCUS — foire aux questions clients (RH, paie, employés, sécurité/RGPD).
> Complément des guides : [Guide RH](GUIDE_RH.md), [Guide paie](GUIDE_PAIE.md), [Guide employé](GUIDE_EMPLOYE.md).
> Mise à jour à chaque évolution du cycle de paie et au fil des retours pilotes (F-24).

## 💰 Paie

**Quand la clôture mensuelle est-elle disponible ?**
La clôture suit le cycle documenté dans le [Guide paie](GUIDE_PAIE.md) : préparation → calcul → validation RH → validation comptable → verrouillage → diffusion des bulletins. Le calendrier exact des 3 premiers cycles est défini avec chaque pilote (checklist F-24).

**Que se passe-t-il si une erreur est trouvée après la clôture ?**
La clôture est **verrouillée** : aucune modification silencieuse. Toute correction passe par un déverrouillage motivé, est tracée dans l'audit trail (qui, quoi, quand, pourquoi, montant avant/après) et produit un diff visible (F-11).

**Comment sont calculés le brut, les cotisations et le net ?**
Le moteur applique les règles pays DZ (règles de calcul IRG par tranches, cotisations CNAS, primes, heures supplémentaires 25 %/50 %, prorata) — voir `docs/payroll/DZ_COMPLIANCE.md`. Les totaux de chaque export (journal, CNAS, virement) égalent les totaux de la clôture (contrôle automatique).

**Comment sont gérés les arrondis ?**
Le calcul suit la convention monétaire DZD (2 décimales), appliquée aux cotisations et au net à payer. Les cas limites (prorata, HS, absences) sont couverts par des tests golden (F-03/F-05).

**Un employé peut-il être payé sans IBAN/RIB ?**
Le fichier de virement exige un compte bancaire valide par employé ; les employés sans RIB sont listés dans le rapport d'anomalies avant clôture (F-28) pour régularisation.

## 🏖 Congés & absences

**Comment les congés payés sont-ils acquis ?**
Acquisition de 2,5 jours/mois, plafond annuel, avec prise en compte des absences (F-07). L'indemnité de congés retient la méthode la plus favorable à l'employé (1/10ᵉ des salaires de référence vs maintien de salaire).

**Un congé sans solde impacte-t-il le bulletin ?**
Oui : déduction conforme et tracée sur le bulletin (lien module Absence → paie, F-07/F-20).

**Qui valide une absence ?**
Le workflow d'approbation du module Absence (manager/RH), puis l'absence approuvée alimente la paie. Toute heure payée est traçable à sa source (F-20).

## 📱 Employés & pointage

**Le pointage fonctionne-t-il sans connexion ?**
Oui : mode hors-ligne avec file d'attente locale et synchronisation automatique à la reconnexion (règle : 1er pointage gagne, F-21). Le géofencing par site peut être activé par l'employeur.

**Un pointage peut-il être corrigé ?**
Oui, via le workflow de correction de pointage (approbation manager/RH) — la correction est tracée et reliée à la paie (F-20).

**Où trouver mon bulletin de paie ?**
Dans le dossier employé du Cabinet (archivage automatique, horodaté — F-09) et re-éditable à tout moment ; diffusion par email/push selon les préférences de l'entreprise.

## 🔒 Sécurité & RGPD

**Quelles données sont protégées ?**
Les données d'identification sensibles (IBAN/RIB, identifiants nationaux, références de paiement, notes personnelles) sont **chiffrées au repos** (AES-256, F-17). Les montants restent en clair pour permettre agrégation et rapports — conformément à la matrice RGPD (`docs/security/MATRICE_CONFORMITE_RGPD_LOI_18_07.md`).

**Qui peut voir les données de paie ?**
Accès par rôle (comptable vs RH vs manager vs employé) avec isolation multi-tenant stricte (search_path Postgres + tests adversarial, F-19). Les accès aux données sensibles sont journalisés.

**Un employé peut-il demander l'effacement de ses données ?**
Oui — droit à l'effacement (RGPD) : purge effective d'un employé/tenant via l'outillage d'audit (F-18), testée de bout en bout, avec respect des durées de rétention légales documentées.

**Où sont hébergées les données ?**
Hébergeurs identifiés dans le registre des traitements (`docs/security/REGISTRE_TRAITEMENTS_DONNEES_RH.md`) et le DPA (`docs/security/DPA.md`).

**La biométrie (pointage par visage/empreinte) est-elle conservée ?**
Politique de conservation des templates documentée (F-18) ; les données biométriques ne sont jamais utilisées hors pointage.
