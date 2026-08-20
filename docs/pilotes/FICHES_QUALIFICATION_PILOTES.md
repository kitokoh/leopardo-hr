# 📋 Fiches de qualification pilotes DZ (issue #5154)

**Version** : 1.0 · **Date** : 2026-08-20
**Usage** : 1 fiche par prospect cible — pré-remplie par l'équipe (segments), à compléter lors du 1er contact.
**Objectif** : qualifier les 3 pilotes DZ (taille, secteur, douleurs, faisabilité du parcours < 30 min).

---

## Critères de qualification (rappel)

| Critère | Seuil pilote |
|---|---|
| Effectif | 5–250 employés |
| Secteur | Industrie / services / BTP / logistique (priorité : paie mensuelle régulière) |
| Logiciel actuel | Excel pur, ou outil non conforme DZ, ou cabinet comptable seul |
| Date de paie | Fixe chaque mois (ex. 5 du mois) |
| Douleurs | Erreurs IRG/CNAS, pointage papier, retards de paie, absence de bulletins conformes |
| Volonté feedback | Oui (carnet + 1 cas réel de paie/mois) |

---

## Fiche 1 — PME industrielle (profil cible)

| Champ | Valeur |
|---|---|
| **Nom** | [À CONFIRMER — cible : PME industrielle, zone Alger] |
| **Contact** | [Nom, email, téléphone] |
| **Taille** | ~80 employés (60 ouvriers + 20 cadres) |
| **Secteur** | Production / fabrication |
| **Logiciel actuel** | Excel + cabinet comptable externe |
| **Date de paie** | 5 du mois |
| **Douleurs déclarées** | Calcul manuel IRG/CNAS, erreurs récurrentes, pointage papier (feuilles) |
| **Cas réel prévu** | 1 bulletin industriel (heures sup 25 %/50 %, primes) |
| **Faisabilité < 30 min** | Import CSV des 80 employés → pointage kiosque web → paie simulée |
| **Statut** | ☐ Contacté ☐ Qualifié ☐ Signé ☐ Onboardé |

## Fiche 2 — Entreprise de services / BTP (profil cible)

| Champ | Valeur |
|---|---|
| **Nom** | [À CONFIRMER — cible : services/BTP, Oran] |
| **Contact** | [Nom, email, téléphone] |
| **Taille** | ~40 employés, chantiers multiples |
| **Secteur** | BTP / services |
| **Logiciel actuel** | Outil RH générique non conforme DZ |
| **Date de paie** | Fin de mois |
| **Douleurs déclarées** | Pointage multi-sites, géofencing absent, bulletins non conformes demandés par les clients |
| **Cas réel prévu** | 1 bulletin BTP (indemnités chantier, géofence) |
| **Faisabilité < 30 min** | Géofence + kiosque web + paie simulée |
| **Statut** | ☐ Contacté ☐ Qualifié ☐ Signé ☐ Onboardé |

## Fiche 3 — Startup / logistique (profil cible)

| Champ | Valeur |
|---|---|
| **Nom** | [À CONFIRMER — cible : startup/logistique, Constantine] |
| **Contact** | [Nom, email, téléphone] |
| **Taille** | ~25 employés |
| **Secteur** | Logistique / e-commerce |
| **Logiciel actuel** | Excel + paie externalisée |
| **Date de paie** | 5 du mois |
| **Douleurs déclarées** | Pas de visibilité temps réel, onboarding employés lent, coût du cabinet |
| **Cas réel prévu** | 1 bulletin logistique (primes variables) |
| **Faisabilité < 30 min** | Onboarding complet + app mobile employé |
| **Statut** | ☐ Contacté ☐ Qualifié ☐ Signé ☐ Onboardé |

---

## Checklist de vérification du parcours « essai gratuit » (avant envoi du pitch)

- [ ] Le parcours trial guidé aboutit à un statut `ready` (pas `pending` — worker de queue actif, #5172)
- [ ] Le parcours trial OTP envoie bien l'email (pas de 503 — #5162)
- [ ] Le bouton « Continue with Google » répond 302 (pas 500/503 — #5170)
- [ ] La landing vitrine « Pilotes DZ » est publiée (staging au minimum) avec formulaire de contact fonctionnel
- [ ] L'onboarding complet a été chronométré < 30 min (checklist #5151)

---

*Kit de prospection — issues du plan 60 jours (Batch 2). Envoi/emails : action humaine uniquement (hors périmètre agent).*
