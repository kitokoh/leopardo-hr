# Core Features

## Vue d'Ensemble

Leopardo HR concentre **8 fonctionnalités cœur** qui forment un écosystème cohérent et interdépendant. Chaque feature est conçue pour être :

- ✅ **Mobile-first** : Interface optimisée smartphone
- ✅ **Offline-capable** : Fonctionne sans connexion permanente
- ✅ **Self-service** : Zéro formation requise
- ✅ **Intégrée nativement** : Les données circulent entre les modules

---

## 1. Présence & Pointage

### Description

Système de pointage biométrique et géolocalisé pour tracer avec précision le temps de travail des équipes terrain.

### Fonctionnalités Clés

| Feature | Description | Valeur |
|---------|-------------|--------|
| **Pointage biométrique** | Reconnaissance faciale + empreinte digitale | Anti-fraude garantie |
| **Géolocalisation** | GPS + WiFi + cellulaire pour précision maximale | Preuve de présence sur site |
| **Photo horodatée** | Selfie automatique avec timestamp et coordonnées | Audit trail complet |
| **Modes hybrides** | Online + offline avec synchronisation différée | Fonctionne partout |
| **Plages configurables** | Horaires fixes, variables, shifts tournants | Flexibilité totale |
| **Tolérance intelligente** | Retards autorisés, arrondis configurables | Réduction des conflits |
| **Alertes temps réel** | Notification si absence ou retard | Réaction immédiate du manager |

### User Stories

```
👷 En tant qu'employé terrain :
   → Je pointe en 3 secondes avec mon visage
   → Je vois mon solde d'heures en temps réel
   → Je suis alerté si j'oublie de pointer

👨‍💼 En tant que manager :
   → Je vois qui est présent/absent en un coup d'œil
   → Je valide les heures supplémentaires en 1 clic
   → Je reçois une alerte si un employé n'a pas pointé

📊 En tant que RH :
   → J'exporte les timesheets pour la paie en 1 clic
   → J'ai un audit trail complet en cas de litige
   → Je détecte les patterns d'absentéisme
```

### Métriques de Succès

- ⏱️ Temps moyen de pointage : < 5 secondes
- 🎯 Taux de reconnaissance biométrique : > 98%
- 📶 Taux de synchronisation offline→online : 100%
- 🚫 Taux de fraude détectée : > 95%

---

## 2. Paie & Rémunération

### Description

Moteur de paie automatisé, conforme aux législations locales, supportant multi-pays et multi-devises.

### Fonctionnalités Clés

| Feature | Description | Valeur |
|---------|-------------|--------|
| **Calcul automatique** | Heures normales, supp, nuit, weekend | Zéro erreur manuelle |
| **Conformité légale** | SMIC, cotisations, impôts à jour | Risque légal éliminé |
| **Multi-pays** | Sénégal, Côte d'Ivoire, France, Turquie, etc. | Scalabilité régionale |
| **Variables intégrés** | Primes, commissions, pourboires | Flexibilité métier |
| **Absences déduites** | Congés, maladies, retards automatiques | Précision garantie |
| **Paiement intégré** | Virement bancaire + mobile money | Distribution fluide |
| **Bulletins digitaux** | PDF sécurisé dans l'app employé | Zéro papier, accessible 24/7 |

### User Stories

```
👷 En tant qu'employé :
   → Je consulte ma fiche de paie anytime
   → Je comprends chaque ligne (détail clair)
   → Je télécharge mes bulletins pour mes démarches

👨‍💼 En tant que manager :
   → Je valide les variables (primes, heures supp)
   → Je simule l'impact d'une augmentation
   → Je vois la masse salariale de mon équipe

📊 En tant que RH :
   → Je lance la paie en 1 clic pour toute l'entreprise
   → Je génère les déclarations sociales automatiquement
   → J'exporte vers la comptabilité (format standard)
```

### Métriques de Succès

- ⚡ Temps de calcul de paie : < 30 secondes pour 100 employés
- ✅ Taux de conformité légale : 100%
- 🔄 Taux d'erreur corrigée : < 0.1%
- 📄 Taux d'adoption fiches digitales : > 90%

---

## 3. Workflows & Automatisations

### Description

Moteur de workflows personnalisables pour digitaliser les processus métier sans code.

### Fonctionnalités Clés

| Feature | Description | Valeur |
|---------|-------------|--------|
| **Builder visuel** | Drag-and-drop pour créer des workflows | Zéro développement requis |
| **Templates prédéfinis** | Congés, achats, notes de frais, incidents | Démarrage rapide |
| **Conditions dynamiques** | Si X alors Y, escalade automatique | Intelligence embarquée |
| **Validations multi-niveaux** | Manager → RH → Direction selon montant/type | Contrôle granulaire |
| **Notifications contextuelles** | Push, email, SMS selon urgence | Engagement garanti |
| **Historique complet** | Qui a fait quoi, quand, pourquoi | Traçabilité totale |
| **API webhooks** | Intégration avec outils externes (Slack, Zapier) | Écosystème ouvert |

### Exemples de Workflows

```
📅 Demande de congé :
   Employé → Manager (validation) → RH (enregistrement) → Employé (notification)

💰 Note de frais :
   Employé → Manager (< 100€) / DG (> 100€) → Comptabilité → Paiement

⚠️ Incident sécurité :
   Employé → Manager HSE → RH → Action corrective → Clôture

📦 Demande d'achat :
   Employé → Manager → Acheteur → Fournisseur → Réception → Facture
```

### Métriques de Succès

- 🚀 Temps de création d'un workflow : < 10 minutes
- 📉 Réduction du temps de traitement : > 70%
- ✅ Taux de complétion des workflows : > 95%
- 🔁 Nombre moyen de workflows actifs par client : > 5

---

## 4. Documents & Conformité

### Description

Coffre-fort numérique centralisé pour tous les documents légaux et administratifs de l'entreprise.

### Fonctionnalités Clés

| Feature | Description | Valeur |
|---------|-------------|--------|
| **Stockage sécurisé** | Chiffrement AES-256, backup redondant | Confidentialité garantie |
| **Catégorisation auto** | Contrats, fiches de paie, attestations, EPI | Recherche instantanée |
| **Génération automatique** | Contrats types, lettres, certificats | Gain de temps massif |
| **Signature électronique** | eIDAS compliant, valeur légale | Zéro déplacement |
| **Alertes d'échéance** | Renouvellement CDD, visite médicale, EPI | Conformité proactive |
| **Partage contrôlé** | Permissions granulaires par document | Sécurité maximale |
| **Audit trail** | Qui a vu/téléchargé/modifié chaque document | Preuve légale |

### Types de Documents Supportés

```
📄 Contrats : CDI, CDD, Stage, Alternance, Prestataire
💰 Paie : Bulletins, attestations salaire, certificats travail
🏥 Santé : Visites médicales, arrêts maladie, accidents du travail
🛡️ Sécurité : EPI, formations, habilitations
📜 Légal : Registre du personnel, DPAE, déclarations URSSAF
📋 Interne : Règlements, procédures, chartes
```

### Métriques de Succès

- 📁 % de documents digitaux vs papier : > 95%
- ⏰ Temps de recherche d'un document : < 10 secondes
- 🔔 Taux de renouvellement avant échéance : 100%
- 🔐 Nombre d'incidents de sécurité : 0

---

## 5. Tâches & Exécution

### Description

Système de gestion de tâches pour coordonner les équipes terrain et suivre l'exécution opérationnelle.

### Fonctionnalités Clés

| Feature | Description | Valeur |
|---------|-------------|--------|
| **Création rapide** | Texte, voice-to-text, photo, localisation | Capture contexte riche |
| **Assignation ciblée** | Individu, équipe, rôle, shift | Flexibilité d'organisation |
| **Échéances intelligentes** | Dates, récurrence, dépendances | Planification réaliste |
| **Suivi temps réel** | Statut, progression, commentaires | Visibilité totale |
| **Preuves d'exécution** | Photo, signature, géolocalisation | Qualité garantie |
| **Escalade automatique** | Si retard → notification manager | Résolution accélérée |
| **Rapports automatiques** | Tâches complétées, KPIs par équipe | Performance mesurable |

### Cas d'Usage Sectoriels

```
🏗️ Construction :
   → Inspection quotidienne de chantier
   → Réception de matériaux
   → Signalement de non-conformité

🚚 Logistique :
   → Tournée de livraisons
   → Chargement/déchargement
   → Incident véhicule

🌾 Agriculture :
   → Récolte par parcelle
   → Traitement phytosanitaire
   → Maintenance équipement

🍽️ Restauration :
   → Checklist ouverture/fermeture
   → Contrôle hygiène HACCP
   → Inventaire quotidien

👮 Sécurité :
   → Ronde de surveillance
   → Incident/sinistre
   → Relève de poste
```

### Métriques de Succès

- ✅ Taux de complétion des tâches : > 90%
- ⏱️ Délai moyen de résolution : -50% vs avant
- 📸 % de tâches avec preuve photo : > 80%
- 📊 Nombre de tâches actives par employé/jour : 5-15

---

## 6. Notifications & Communication

### Description

Système de communication interne unifié pour garder toutes les équipes alignées, même dispersées géographiquement.

### Fonctionnalités Clés

| Feature | Description | Valeur |
|---------|-------------|--------|
| **Notifications push** | Instantanées, ciblées, priorisées | Engagement immédiat |
| **Annonces entreprise** | Messages direction, actualités, célébrations | Culture renforcée |
| **Messages ciblés** | Par équipe, rôle, localisation, shift | Pertinence garantie |
| **Accusés de lecture** | Qui a lu quoi, quand | Accountability |
| **Urgences critiques** | Alarme, évacuation, incident majeur | Sécurité assurée |
| **Multi-canaux** | Push + SMS + email selon criticité | Reach maximal |
| **Archivage searchable** | Historique complet des communications | Mémoire institutionnelle |

### Types de Communications

```
📢 Annonces officielles :
   → Fermeture exceptionnelle
   → Nouveauté produit/service
   → Résultats trimestriels

⚠️ Alertes opérationnelles :
   → Changement de planning urgent
   → Incident sécurité
   → Météo défavorable

🎉 Engagements positifs :
   → Félicitations individuelles
   → Anniversaires
   → Promotions

📋 Rappels administratifs :
   → Visite médicale à planifier
   → Document à signer
   → Formation obligatoire
```

### Métriques de Succès

- 📖 Taux d'ouverture des notifications : > 85%
- ⏱️ Temps moyen de lecture : < 5 minutes
- 📣 Reach des annonces importantes : > 95%
- 😊 Satisfaction communication (survey) : > 4/5

---

## 7. Performance & Objectifs

### Description

Module de suivi de performance individuelle et collective pour aligner les efforts sur les objectifs stratégiques.

### Fonctionnalités Clés

| Feature | Description | Valeur |
|---------|-------------|--------|
| **Objectifs SMART** | Spécifiques, Mesurables, Atteignables, Réalistes, Temporels | Clarté stratégique |
| **KPIs personnalisés** | Par rôle, secteur, niveau hiérarchique | Pertinence métier |
| **Check-ins réguliers** | Hebdo, mensuel, trimestriel selon besoin | Feedback continu |
| **Évaluations 360°** | Manager, pairs, collaborateurs, self | Vision complète |
| **Plans de développement** | Formations, mentoring, compétences à acquérir | Growth individuel |
| **Reconnaissance sociale** | Badges, félicitations publiques, leaderboard | Motivation intrinsèque |
| **Analytics prédictifs** | Détection risques turnover, underperformance | Action proactive |

### Cycle de Performance

```
🎯 Fixation (Janvier) :
   → Objectifs individuels alignés sur stratégie entreprise
   → KPIs définis et partagés

📊 Suivi (Mensuel) :
   → Check-in manager-employé
   → Ajustements si nécessaire

💬 Feedback (Continu) :
   → Reconnaissance en temps réel
   → Corrections constructives

📈 Évaluation (Juin + Décembre) :
   → Revue formelle des résultats
   → Bonus/promotions liés à la performance

🚀 Développement (Post-évaluation) :
   → Plan de formation personnalisé
   → Objectifs nouvelle période
```

### Métriques de Succès

- 🎯 % d'employés avec objectifs documentés : > 95%
- 💬 % de check-ins réalisés dans les temps : > 90%
- 📈 Corrélation performance → rétention : Positive
- 😊 Satisfaction processus évaluatif : > 4/5

---

## 8. Kiosque Employé

### Description

Portail self-service où chaque employé gère autonomement ses informations, demandes et documents.

### Fonctionnalités Clés

| Feature | Description | Valeur |
|---------|-------------|--------|
| **Profil personnel** | Coordonnées, RIB, contacts d'urgence à jour | Autonomie employé |
| **Demandes en 1-clic** | Congés, attestations, modifications | Réduction charge RH |
| **Solde en temps réel** | Congés, RTT, heures récup | Transparence totale |
| **Documents personnels** | Téléchargement fiches de paie, contrats | Accessible 24/7 |
| **Historique complet** | Toutes les demandes, validations, paiements | Mémoire personnelle |
| **Préférences** | Langue, notifications, thème | Expérience personnalisée |
| **Support intégré** | FAQ, chatbot, ticket support | Résolution autonome |

### Ce que l'Employé Peut Faire Seuls

```
✅ Modifier ses coordonnées personnelles
✅ Télécharger ses 12 derniers bulletins de paie
✅ Demander un congé et voir le solde restant
✅ Consulter son planning et ses horaires
✅ Voir ses heures travaillées et heures supp
✅ Demander une attestation de travail/travail
✅ Mettre à jour son RIB pour le paiement
✅ Contacter le support RH en cas de question
✅ Accéder aux formations obligatoires
✅ Donner son feedback (survey, évaluation)
```

### Métriques de Succès

- 📱 MAU (Monthly Active Users) kiosque : > 80%
- 🎫 Réduction des tickets RH routiniers : > 60%
- ⏱️ Temps gagné RH/mois : > 20 heures
- 😊 NPS Employé : > 50

---

## Matrice d'Interdépendance

| Feature | Présence | Paie | Workflow | Docs | Tâches | Notif | Perf | Kiosque |
|---------|----------|------|----------|------|--------|-------|------|---------|
| **Présence** | — | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Paie** | ✅ | — | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ |
| **Workflow** | ✅ | ✅ | — | ✅ | ✅ | ✅ | ❌ | ✅ |
| **Docs** | ✅ | ✅ | ✅ | — | ❌ | ✅ | ❌ | ✅ |
| **Tâches** | ✅ | ❌ | ✅ | ❌ | — | ✅ | ✅ | ❌ |
| **Notif** | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ | ✅ |
| **Perf** | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ | — | ✅ |
| **Kiosque** | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ✅ | — |

**Légende :**
- ✅ = Intégration forte (données partagées)
- ❌ = Intégration faible ou inexistante

---

## Roadmap d'Adoption

### Phase 1 : Foundation (Mois 1-3)
```
🎯 Objectif : Time-to-value rapide
📦 Features : Présence + Paie + Kiosque
📈 KPI : 80% des employés pointent quotidiennement
```

### Phase 2 : Engagement (Mois 4-6)
```
🎯 Objectif : Adoption quotidienne
📦 Features : Notifications + Tâches + Workflows
📈 KPI : 70% MAU sur fonctions non-paie
```

### Phase 3 : Mastery (Mois 7-12)
```
🎯 Objectif : Transformation digitale complète
📦 Features : Documents + Performance + Analytics
📈 KPI : 90% des processus RH digitaux
```

---

## Conclusion

Les 8 core features de Leopardo ne sont pas des produits isolés. Elles forment un **écosystème vertueux** où chaque module renforce la valeur des autres.

> **La somme est greater than the parts.**

C'est cette synergie qui fait de Leopardo un **Company OS**, pas un simple assemblage de fonctionnalités.
