# PLAN D'ACTION : REMISE SUR RAILS
## Leopardo RH - Stratégie de Différenciation Réelle

**Date** : 6 mai 2026  
**Objectif** : Construire un produit qui apporte une **valeur réelle et mesurable**  
**Principe directeur** : Ne vendre que ce qui résout un vrai problème mieux que les alternatives

---

## 🎯 CONSTAT DE DÉPART

### Ce qui NE marchera PAS :
❌ Vendre "un SaaS RH de plus" face à Zoho/Factorial/Odoo  
❌ Promettre "tout en 1 clic" (c'est du marketing creux)  
❌ Cibler "toutes les PME" (trop large, pas de focus)  
❌ Construire encore plus de features (sur-ingénierie)

### Ce qui PEUT marcher :
✅ Résoudre **UN problème spécifique** mieux que quiconque  
✅ Cibler **UN segment précis** avec un besoin urgent  
✅ Apporter une valeur **mesurable en argent ou en temps**  
✅ Construire sur nos **vraies forces techniques**

---

## 🔍 PHASE 1 : DÉCOUVERTE TERRAIN (Semaines 1-4)
### Objectif : Trouver le VRAI problème à résoudre

### Semaine 1-2 : Interviews Exploratoires

**Action 1.1 : Identifier 30 PME cibles**
- Critères : 10-50 employés, secteurs BTP/Sécurité/Logistique/Industrie
- Géographie : Alger, Oran, Constantine
- Méthode : LinkedIn, annuaires professionnels, chambres de commerce

**Action 1.2 : Conduire 20 interviews (1h chacune)**

**Script d'interview** (ne PAS vendre, juste écouter) :

```
1. Comment gérez-vous la présence de vos employés aujourd'hui ?
   → Chercher : Excel ? Papier ? Borne biométrique ? Autre SaaS ?

2. Quel est le plus gros problème avec votre système actuel ?
   → Chercher : Temps perdu ? Erreurs ? Fraude ? Coût ?

3. Combien de temps passez-vous par mois sur la paie ?
   → Chercher : Heures réelles, qui le fait, combien ça coûte

4. Avez-vous déjà essayé un logiciel RH ? Pourquoi avez-vous arrêté ?
   → Chercher : Prix ? Complexité ? Manque de support ? Bugs ?

5. Si je pouvais résoudre UN seul problème RH pour vous, lequel ?
   → Chercher : Le pain point #1 réel

6. Combien seriez-vous prêt à payer pour résoudre ce problème ?
   → Chercher : Budget réel, pas théorique
```

**Livrables Semaine 1-2** :
- 20 interviews enregistrées (avec permission)
- Tableau de synthèse des pain points
- 3-5 problèmes récurrents identifiés

---

### Semaine 3-4 : Validation du Problème #1

**Action 1.3 : Analyser les interviews**

Créer un tableau de scoring :

| Problème identifié | Fréquence (sur 20) | Intensité (1-10) | Willingness to Pay | Score Total |
|--------------------|--------------------|------------------|-------------------|-------------|
| Fraude au pointage | 15 | 8 | 50 DZD/employé | 600 |
| Calcul paie complexe | 18 | 9 | 30 DZD/employé | 486 |
| Gestion congés | 12 | 6 | 20 DZD/employé | 144 |
| Documents RH | 8 | 5 | 10 DZD/employé | 40 |

**Formule** : Score = Fréquence × Intensité × Willingness to Pay

**Action 1.4 : Choisir le problème #1**

Critères de sélection :
1. Score total > 400
2. On a déjà la techno pour le résoudre (biométrie ZKTeco ?)
3. Pas bien résolu par la concurrence
4. Mesurable (gain de temps ou d'argent)

**Hypothèse probable** (à valider) :

> **Problème #1 : Fraude au pointage coûte 10-15% de la masse salariale**
> 
> - Pointage copain (un employé pointe pour un absent)
> - Arrondis manuels (15min → 30min)
> - Heures sup fictives
> - **Coût réel** : Pour 20 employés à 40 000 DZD/mois = **96 000 DZD/mois de perte**

**Livrables Semaine 3-4** :
- Problème #1 validé avec chiffres réels
- 5 PME qui ont dit "si vous résolvez ça, j'achète"
- Calcul du ROI client (combien ils économisent vs combien on coûte)

---

## 🎯 PHASE 2 : SOLUTION FOCALISÉE (Semaines 5-8)
### Objectif : Construire la solution MINIMALE qui résout le problème #1

### Semaine 5-6 : Définir le MVP Focalisé

**Action 2.1 : Réécrire la proposition de valeur**

**AVANT (flou)** :
> "Leopardo RH : Gérez vos employés en 1 clic"

**APRÈS (précis)** :
> "Leopardo RH : Éliminez la fraude au pointage et économisez 10-15% de votre masse salariale"

**Action 2.2 : Simplifier le produit à l'extrême**

**Garder UNIQUEMENT** :
1. ✅ Pointage biométrique (visage + empreinte)
2. ✅ Intégration borne ZKTeco
3. ✅ Dashboard manager : qui est là, qui est en retard, qui a pointé pour qui (détection anomalies)
4. ✅ Rapport mensuel : heures réelles vs heures déclarées, économies réalisées
5. ✅ Export Excel pour le comptable

**Supprimer TOUT le reste** :
- ❌ Admin dashboard (prédictions, analytics avancées)
- ❌ Calcul de paie (laisser ça au comptable)
- ❌ Gestion congés (Phase 2)
- ❌ Documents RH (Phase 2)
- ❌ Évaluations, tâches, projets (Phase 2)

**Action 2.3 : Construire le "Détecteur de Fraude"**

**Algorithme simple** :

```python
# Détection anomalie #1 : Pointage trop rapide
if (pointage_A.time - pointage_B.time) < 5_secondes:
    alert("Possible pointage copain")

# Détection anomalie #2 : Pointage hors site
if pointage.gps_distance > 500_metres:
    alert("Pointage hors zone autorisée")

# Détection anomalie #3 : Pattern suspect
if employé.pointe_toujours_à(8h00) pendant 30_jours:
    alert("Pattern trop régulier (suspect)")

# Détection anomalie #4 : Heures sup excessives
if employé.heures_sup > 20h/mois:
    alert("Heures sup anormalement élevées")
```

**Livrables Semaine 5-6** :
- Maquettes du nouveau MVP focalisé (3 écrans max)
- Algorithme de détection de fraude implémenté
- Dashboard manager avec alertes en temps réel

---

### Semaine 7-8 : Tester avec 5 Pilotes

**Action 2.4 : Recruter 5 clients pilotes**

**Critères** :
- PME 15-30 employés (sweet spot)
- Secteur BTP, sécurité ou logistique (pointage critique)
- Ont déjà une borne ZKTeco (ou acceptent d'en acheter une)
- Patron impliqué (pas déléguer à un RH junior)

**Offre pilote** :
```
✅ 3 mois GRATUITS
✅ Installation et formation incluses
✅ Support WhatsApp 7j/7
✅ Vous gardez vos données si vous arrêtez

En échange :
→ Feedback hebdomadaire (30min call)
→ Accès à vos données de pointage (anonymisées)
→ Témoignage vidéo si ça marche
```

**Action 2.5 : Mesurer le ROI réel**

**Métriques à tracker** (par client pilote) :

| Métrique | Avant Leopardo | Après 3 mois | Gain |
|----------|----------------|--------------|------|
| Heures déclarées/mois | 3 200h | 2 880h | -10% |
| Heures sup/mois | 240h | 180h | -25% |
| Retards >15min/mois | 45 | 12 | -73% |
| Coût masse salariale | 800 000 DZD | 720 000 DZD | **-80 000 DZD** |
| Temps admin RH/mois | 20h | 5h | -75% |

**Objectif** : Prouver que Leopardo économise **au minimum 50 000 DZD/mois** pour une PME de 20 employés.

**Livrables Semaine 7-8** :
- 5 clients pilotes actifs
- Données de ROI réel (avant/après)
- 3 témoignages vidéo si résultats positifs

---

## 💰 PHASE 3 : MODÈLE ÉCONOMIQUE VIABLE (Semaines 9-12)
### Objectif : Définir un pricing qui reflète la valeur créée

### Semaine 9-10 : Pricing Basé sur la Valeur

**Action 3.1 : Calculer le ROI client**

**Exemple PME 20 employés** :

```
Économies mensuelles avec Leopardo :
- Réduction fraude pointage : 60 000 DZD
- Réduction heures sup fictives : 30 000 DZD
- Gain temps admin (15h × 2000 DZD/h) : 30 000 DZD
TOTAL ÉCONOMIES : 120 000 DZD/mois

Coût Leopardo : ??? DZD/mois

ROI = (Économies - Coût) / Coût
```

**Action 3.2 : Définir le pricing**

**Règle** : Le client doit économiser **au moins 3x** ce qu'il paie.

```
Si économies = 120 000 DZD/mois
→ Prix max acceptable = 40 000 DZD/mois
→ Prix optimal = 20 000 DZD/mois (ROI de 6x)
```

**Nouvelle grille tarifaire** :

| Plan | Prix/mois | Inclus | ROI Client |
|------|-----------|--------|------------|
| **Anti-Fraude** | 15 000 DZD | Pointage biométrique + Détection anomalies + Dashboard manager + Export Excel | 6-8x |
| **Anti-Fraude + Borne** | 25 000 DZD | Tout ci-dessus + Borne ZKTeco (location) | 4-6x |
| **Enterprise** | Sur devis | Multi-sites + API + Support prioritaire | Variable |

**Comparaison avec la concurrence** :

| Solution | Prix/mois | Résout la fraude ? | ROI |
|----------|-----------|-------------------|-----|
| **Leopardo RH** | 15 000 DZD | ✅ OUI (biométrie + IA) | 6x |
| Zoho People | 3 000 DZD | ❌ NON (pointage manuel) | 0x |
| Factorial | 7 000 DZD | ⚠️ PARTIEL (GPS seulement) | 2x |
| Excel + Comptable | 10 000 DZD | ❌ NON | 0x |

**Action 3.3 : Tester le pricing**

Appeler les 15 PME interviewées en Phase 1 :

```
"Bonjour [Nom], vous m'aviez dit que la fraude au pointage 
vous coûtait environ 100 000 DZD/mois.

J'ai construit une solution qui élimine ce problème avec 
de la biométrie + détection automatique des anomalies.

Mes 5 clients pilotes économisent en moyenne 80 000 DZD/mois.

Le prix est 15 000 DZD/mois, soit un ROI de 5x.

Seriez-vous intéressé par une démo ?"
```

**Objectif** : 5 démos réservées, 2 ventes closes.

**Livrables Semaine 9-10** :
- Grille tarifaire validée
- 2 premiers clients payants
- MRR = 30 000 DZD (15 000 × 2)

---

### Semaine 11-12 : Automatiser l'Acquisition

**Action 3.4 : Créer le Sales Playbook**

**Étape 1 : Qualification (5min call)**
```
Questions :
1. Combien d'employés ? (15-50 = qualifié)
2. Secteur ? (BTP/Sécurité/Logistique = qualifié)
3. Comment gérez-vous le pointage aujourd'hui ?
4. Avez-vous une borne biométrique ?
5. Qui décide des achats logiciels ? (Patron = qualifié)
```

**Étape 2 : Démo (30min)**
```
1. Montrer le problème (5min)
   → "Voici comment un employé peut pointer pour un collègue absent"
   
2. Montrer la solution (10min)
   → "Avec Leopardo, la biométrie empêche ça"
   → "Le dashboard vous alerte en temps réel"
   
3. Montrer le ROI (10min)
   → "Client X économise 85 000 DZD/mois"
   → "Pour vous, ça ferait environ [calcul personnalisé]"
   
4. Closer (5min)
   → "On peut démarrer dès la semaine prochaine"
   → "Premier mois gratuit pour tester"
```

**Étape 3 : Onboarding (1 semaine)**
```
Jour 1 : Installation borne + formation manager (2h sur site)
Jour 2-3 : Enrôlement biométrique des employés (15min/employé)
Jour 4-5 : Période de test (support WhatsApp)
Jour 7 : Bilan + ajustements
```

**Action 3.5 : Lancer les premiers canaux d'acquisition**

**Canal #1 : Partenariat Distributeurs ZKTeco**
- Identifier les 3 distributeurs ZKTeco en Algérie
- Offre : "Pour chaque borne vendue, proposez Leopardo RH"
- Commission : 20% du MRR pendant 12 mois
- **Objectif** : 10 clients via ce canal en 3 mois

**Canal #2 : LinkedIn Outreach**
- Cibler les patrons de PME BTP/Sécurité sur LinkedIn
- Message : "J'ai aidé [Client X] à économiser 80K DZD/mois en éliminant la fraude au pointage. Intéressé par une démo de 15min ?"
- **Objectif** : 20 démos/mois, 4 ventes/mois (20% conversion)

**Canal #3 : Bouche-à-oreille**
- Programme de parrainage : "Recommandez Leopardo, gagnez 1 mois gratuit"
- **Objectif** : 30% des ventes via parrainage après 6 mois

**Livrables Semaine 11-12** :
- Sales Playbook documenté
- 2 canaux d'acquisition actifs
- 5 clients payants au total
- MRR = 75 000 DZD

---

## 📈 PHASE 4 : CROISSANCE CONTRÔLÉE (Mois 4-12)
### Objectif : Atteindre 50 clients et 750 000 DZD MRR

### Mois 4-6 : Optimiser le Produit

**Action 4.1 : Améliorer le taux de rétention**

**Métriques à tracker** :
- Churn mensuel (objectif : <5%)
- NPS (Net Promoter Score) (objectif : >50)
- Temps d'onboarding (objectif : <3 jours)

**Actions correctives si churn >5%** :
- Interviews de sortie (pourquoi ils partent ?)
- Améliorer le support (temps de réponse <2h)
- Ajouter des quick wins (alertes plus précises, rapports plus clairs)

**Action 4.2 : Construire les preuves sociales**

- 10 témoignages vidéo clients
- 5 études de cas détaillées (avant/après avec chiffres)
- Page "Clients" sur le site avec logos
- Présence sur les réseaux sociaux (LinkedIn, Facebook)

**Objectif Mois 4-6** :
- 20 clients payants
- MRR = 300 000 DZD
- Churn <5%
- NPS >50

---

### Mois 7-9 : Scaler l'Acquisition

**Action 4.3 : Recruter un Commercial**

**Profil** :
- Expérience vente B2B en Algérie (3+ ans)
- Réseau dans le BTP/Sécurité/Logistique
- Bilingue FR/AR
- Basé à Alger

**Rémunération** :
- Fixe : 60 000 DZD/mois
- Variable : 10% du MRR des clients signés (récurrent)
- Objectif : 10 clients/mois après 3 mois de ramp-up

**Action 4.4 : Investir dans le Marketing**

**Budget mensuel** : 50 000 DZD

| Canal | Budget | Objectif |
|-------|--------|----------|
| Google Ads (mots-clés "pointage biométrique algérie") | 20 000 DZD | 30 leads/mois |
| LinkedIn Ads (ciblage patrons PME) | 15 000 DZD | 20 leads/mois |
| Événements BTP (salons, conférences) | 10 000 DZD | 10 leads/mois |
| Content Marketing (blog, études de cas) | 5 000 DZD | SEO long-terme |

**Objectif Mois 7-9** :
- 35 clients payants
- MRR = 525 000 DZD
- CAC (Customer Acquisition Cost) <30 000 DZD
- LTV/CAC ratio >3

---

### Mois 10-12 : Préparer la Phase 2

**Action 4.5 : Valider les prochaines features**

**Méthode** : Demander aux 35 clients existants :

```
"Maintenant que la fraude au pointage est résolue,
quel est votre prochain plus gros problème RH ?"

Options :
A. Gestion des congés
B. Calcul de la paie
C. Documents RH (contrats, fiches de paie)
D. Autre (précisez)
```

**Construire UNIQUEMENT** la feature la plus demandée (>60% des clients).

**Action 4.6 : Lever des fonds (optionnel)**

**Si** :
- MRR >500 000 DZD
- Churn <5%
- NPS >50
- Croissance >15%/mois

**Alors** :
- Lever 5M DZD (≈40K€) en Seed
- Utilisation : 60% commercial, 30% produit, 10% ops
- Objectif : Atteindre 100 clients en 12 mois

**Objectif Mois 10-12** :
- 50 clients payants
- MRR = 750 000 DZD (≈6 000€)
- ARR = 9M DZD (≈72 000€)
- Équipe : 1 fondateur + 1 commercial + 1 dev + 1 support

---

## 🎯 MÉTRIQUES DE SUCCÈS

### Indicateurs Clés (à tracker chaque semaine)

| Métrique | Mois 3 | Mois 6 | Mois 12 | Commentaire |
|----------|--------|--------|---------|-------------|
| **Clients payants** | 5 | 20 | 50 | Croissance organique |
| **MRR** | 75K DZD | 300K DZD | 750K DZD | Revenu récurrent |
| **Churn mensuel** | <10% | <5% | <3% | Rétention |
| **CAC** | 0 DZD | 20K DZD | 30K DZD | Coût acquisition |
| **LTV** | 180K DZD | 300K DZD | 450K DZD | Valeur vie client |
| **LTV/CAC** | ∞ | 15x | 15x | Rentabilité |
| **NPS** | N/A | >40 | >50 | Satisfaction |
| **Temps onboarding** | 7j | 5j | 3j | Efficacité |

---

## 🚨 SIGNAUX D'ALERTE (Quand pivoter ou arrêter)

### 🔴 RED FLAGS (Arrêter si 2+ sont vrais après 6 mois)

1. **Churn >10%** pendant 3 mois consécutifs
   → Les clients ne voient pas la valeur

2. **CAC >LTV**
   → Le business n'est pas rentable

3. **Temps de vente >3 mois**
   → Le cycle de vente est trop long

4. **NPS <20**
   → Les clients ne recommandent pas

5. **Croissance MRR <5%/mois**
   → Pas de traction

### 🟡 YELLOW FLAGS (Pivoter si 2+ sont vrais)

1. **Conversion démo→vente <10%**
   → Le pitch ne fonctionne pas

2. **Temps d'onboarding >2 semaines**
   → Le produit est trop complexe

3. **Support >20h/semaine pour <20 clients**
   → Le produit n'est pas stable

4. **Demandes de features hors scope >50%**
   → On cible le mauvais segment

---

## 💡 PRINCIPES DIRECTEURS (À ne JAMAIS oublier)

### 1. **Valeur d'abord, features ensuite**
```
❌ "On va ajouter la gestion des congés"
✅ "3 clients ont demandé les congés et sont prêts à payer 5K DZD/mois de plus"
```

### 2. **Mesurer, pas supposer**
```
❌ "Je pense que les clients aiment cette feature"
✅ "87% des clients utilisent cette feature chaque semaine"
```

### 3. **Focus brutal**
```
❌ "On peut faire ça en plus, ça prend juste 2 jours"
✅ "Est-ce que ça aide à signer plus de clients ? Non ? Alors non."
```

### 4. **Vendre avant de construire**
```
❌ "On va construire X, puis on verra si ça se vend"
✅ "5 clients ont pré-payé pour X, maintenant on construit"
```

### 5. **ROI client >3x minimum**
```
❌ "Notre prix est compétitif"
✅ "Nos clients économisent 6x ce qu'ils nous paient"
```

---

## 📋 CHECKLIST DE DÉMARRAGE (Semaine 1)

### Jour 1-2 : Préparation
- [ ] Lire ce plan d'action en entier
- [ ] Identifier 30 PME cibles (nom, contact, secteur)
- [ ] Préparer le script d'interview
- [ ] Créer un Google Sheet pour tracker les interviews

### Jour 3-5 : Premiers contacts
- [ ] Appeler 10 PME pour demander un interview
- [ ] Réserver 5 interviews (objectif : 50% taux de conversion)
- [ ] Conduire les 5 premiers interviews
- [ ] Noter les pain points mentionnés

### Jour 6-7 : Synthèse
- [ ] Analyser les 5 interviews
- [ ] Identifier les 3 problèmes les plus mentionnés
- [ ] Calculer le score de chaque problème
- [ ] Décider si on continue (score >400) ou si on pivote

---

## 🎬 PROCHAINES ÉTAPES IMMÉDIATES

### Cette semaine :
1. **Valider ce plan** avec l'équipe (2h meeting)
2. **Identifier les 30 PME** cibles (1 jour)
3. **Appeler les 10 premières** PME (2 jours)
4. **Conduire 5 interviews** (1 semaine)

### Semaine prochaine :
5. **Analyser les interviews** et choisir le problème #1
6. **Simplifier le MVP** (supprimer tout sauf l'essentiel)
7. **Contacter 5 clients pilotes** potentiels

### Dans 1 mois :
8. **Avoir 5 clients pilotes** actifs
9. **Mesurer le ROI réel** (avant/après)
10. **Décider** : on continue (si ROI >3x) ou on pivote

---

## 📞 SUPPORT ET QUESTIONS

**Si vous êtes bloqué à n'importe quelle étape** :

1. **Relire la section "Principes Directeurs"**
2. **Se demander** : "Est-ce que ça apporte de la valeur réelle au client ?"
3. **Si oui** : Continuer
4. **Si non** : Arrêter et revenir au plan

**Règle d'or** :
> "Si tu ne peux pas mesurer la valeur que tu apportes en argent ou en temps économisé, tu n'apportes pas de valeur."

---

## 🏆 VISION DE SUCCÈS (Dans 12 mois)

**Leopardo RH sera** :

✅ **Le leader** de la lutte anti-fraude au pointage en Algérie  
✅ **50 clients** qui économisent chacun 60-100K DZD/mois  
✅ **750K DZD MRR** (9M DZD ARR)  
✅ **NPS >50** (clients satisfaits qui recommandent)  
✅ **Équipe de 4** personnes rentables  
✅ **Prêt à lever** 50M DZD pour scaler vers 500 clients

**Et surtout** :

✅ **Un produit qui résout un vrai problème**  
✅ **Des clients qui ne peuvent plus s'en passer**  
✅ **Une croissance organique par bouche-à-oreille**  
✅ **Un business rentable et durable**

---

**Prêt à démarrer ?**

**Première action** : Identifier les 30 PME cibles (aujourd'hui).

**Bonne chance ! 🚀**

---

*Document créé le 6 mai 2026*  
*Version 1.0*  
*Auteur : Kiro AI*
