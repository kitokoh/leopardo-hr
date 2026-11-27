# Assets production — documents archivés le 2026-11-27

> ⚠️ **NON MAINTENU.** Ces trois documents décrivent le positionnement d'AVANT le
> repositionnement 2026 : catégorie « Mobile-First Company OS » (périmée depuis #7428),
> pitch « suite complète », 41 modules en accroche, 6 pays en argument.
>
> **Références à jour** : `docs/GOTO_MARKET/REPOSITIONNEMENT_2026.md` (périmètre de
> lancement) et `docs/GOTO_MARKET/PACK_LANCEMENT/04_PAGE_VENTE_PAIE_POINTAGE_DZ.md`
> (page de vente du lancement).
>
> Archivés pour le budget documentaire (#9981) : leurs décisions sont tranchées dans le
> repositionnement, et les garder vivants créait une source de vérité concurrente.
> Conservés ici pour traçabilité uniquement.

---

## 1. LEOPARDO_ONE_PAGER.md (ex `docs/GOTO_MARKET/ASSETS_PRODUCTION/DOCS/`)

# LEOPARDO — Mobile-First Company OS
## One Pager Commercial

_Ticket: `PA2-STR-002` (Issue #1012). Depend de `PA2-STR-001` (positionnement final, deja livre — voir `docs/GOTO_MARKET/REPOSITIONNEMENT_2026.md`). Ce document remplace la version brouillon precedente (temoignages placeholder, coordonnees fictives, sans pricing chiffre) par une version alignee sur les sources de verite du produit : `docs/dossierdeConception/03_modele_economique/03_MODELE_ECONOMIQUE.md` (plans/prix) et `docs/GOTO_MARKET/REPOSITIONNEMENT_2026.md` (positionnement/objections)._

---

### **L'OS mobile qui fait fonctionner votre entreprise terrain.**

Presence, taches, paie, documents et pilotage RH — dans une seule plateforme mobile-first, pour employes, managers et direction.

---

### **LE PROBLEME**

Les PME terrain (securite, BTP, logistique, nettoyage, restauration, retail, maintenance — 20 a 250 employes) sont coincees entre :

- **Excel et WhatsApp** — pratique au quotidien, mais aucune preuve, aucun audit, aucune vue consolidee.
- **ERP/SIRH lourds** — penses desktop, deploiement long, hors budget PME.
- **Outils RH deconnectes du terrain** — pas d'offline, pas de mobile natif, adoption faible par les equipes terrain.

**Resultat concret** : paie contestee, retards non suivis, absences gerees par appel telephonique, direction sans visibilite temps reel sur qui travaille, ou, et sur quoi.

---

### **LA SOLUTION LEOPARDO**

Un **Company OS mobile-first**, deja en production, qui couvre le cycle complet presence → validation → paie :

- **Pointage intelligent** — QR code, biometrie faciale, geolocalisation (mode tolerant : jamais de blocage si le GPS est indisponible), kiosque tablette partage.
- **Absences et corrections** — demande, validation manager, historique, workflow de correction en cas d'oubli de pointage.
- **Paie multi-pays** — cycles journalier/hebdomadaire/mensuel configurables, avances sur salaire a double validation (demande → approbation manager → paiement declare → confirmation employe), bulletins PDF generes en asynchrone.
- **Taches et communication** — assignation d'equipe, annonces entreprise, notifications push/email/SMS avec preferences et heures silencieuses.
- **Documents** — placard numerique par employe, telechargement mobile.
- **Multi-tenant et securite** — isolation par entreprise, roles employe/manager/RH/admin plateforme, audit trail sur les operations sensibles (avances, confirmations de paiement).

---

### **PROPOSITION PAR PERSONA**

_(reprise du positionnement final — `02_POSITIONNEMENT_ET_MESSAGING.md`, PA2-STR-001)_

- **Dirigeant** — visibilite quotidienne sur presences, alertes, couts et documents, sans consultant ni fichier Excel.
- **RH** — moins de relances et de doubles saisies ; les demandes des employes et les validations des managers alimentent directement une paie basee sur des donnees propres.
- **Manager terrain** — qui est present, qui est en retard, quelle tache est terminee, quelle demande attend une validation.
- **Employe** — son espace personnel dans la poche : pointage, absences, bulletins, notifications, documents.
- **Partenaire integrateur (cabinet RH/comptable)** — API documentee, produit multi-tenant, offre standardisable pour plusieurs clients PME.

---

### **OFFRE ET PRICING**

_Source de verite : `docs/dossierdeConception/03_modele_economique/03_MODELE_ECONOMIQUE.md`. Facturation Phase 1 en EUR uniquement ; prix locaux (DZD/MAD/TND) affiches a titre indicatif sur la landing page, facture emise en EUR au taux du jour._

| | **Trial** | **Starter** | **Business** | **Enterprise** |
|---|---|---|---|---|
| **Prix mensuel** | Gratuit | 29 EUR | 79 EUR | 199 EUR |
| **Prix annuel** | — | 290 EUR (-17%) | 790 EUR (-17%) | 1990 EUR (-17%) |
| **Employes max** | 5 | 20 | 200 | Illimite |
| **Duree** | 14 jours | — | — | 30 jours d'essai |
| **Biometrie / photo pointage** | — | — | ✅ | ✅ |
| **Taches et evaluations** | — | — | ✅ | ✅ |
| **Rapports avances / export bancaire** | — | Export Excel seul | ✅ | ✅ |
| **API publique** | — | — | — | ✅ |
| **Isolation schema dediee** | — | — | — | ✅ |

**Prix indicatifs multi-pays** (a titre d'affichage, facturation reelle en EUR) :

| Plan | EUR | DZD | MAD | TND |
|---|---|---|---|---|
| Starter | 29€ | ~4 200 DA | ~315 MAD | ~96 TND |
| Business | 79€ | ~11 500 DA | ~855 MAD | ~261 TND |
| Enterprise | 199€ | ~29 000 DA | ~2 150 MAD | ~657 TND |

---

### **ROI — CE QUE LA PME RECUPERE**

_Aligne sur `03_DIRECTION_COMMERCIALE_ET_OFFRES.md` (KPI business et resultat promis)._

- **Moins d'erreurs de paie** — donnees de presence sourcees directement du pointage, pas ressaisies.
- **Moins de temps perdu en relances** — demandes d'absence et d'avance auto-routees au bon manager.
- **Meilleure visibilite terrain** — qui travaille, ou, sur quoi, en temps reel plutot qu'en fin de mois.
- **Onboarding plus rapide** — nouvel employe operationnel via QR/invitation, sans formation lourde.
- **Conformite et audit** — chaque validation d'avance/paiement laisse une trace horodatee.

**Critere de conversion pilote** (7 jours, methode deja definie) : au moins 70% des employes invites se connectent, au moins 5 jours de donnees de presence, au moins 1 workflow valide de bout en bout, rapport ROI remis au dirigeant.

---

### **CAS D'USAGE PME TERRAIN**

- **Securite / gardiennage multi-sites** — pointage QR par site, verification de presence en temps reel pour la direction, export pour les rondes.
- **BTP / chantier** — pointage geolocalise sur chantier temporaire, heures supplementaires suivies pour la paie de fin de mois, corrections manuelles tracees en cas d'oubli sur site sans reseau.
- **Logistique / entrepot** — kiosque partage a l'entree du depot, plusieurs employes pointent sur le meme appareil, alertes manager en cas de pointage hors zone autorisee.
- **Restauration / retail multi-boutiques** — cycles de paie hebdomadaires, avances sur salaire encadrees par validation manager, annonces d'equipe (changement d'horaire, rappel consignes) en un envoi.
- **Cabinet RH/comptable multi-clients** — un tenant par client final, meme produit, standardisation de l'onboarding et des exports pour plusieurs PME a la fois.

---

### **OBJECTIONS ET REPONSES**

_Reprise integrale de `02_POSITIONNEMENT_ET_MESSAGING.md` (PA2-STR-001), qui reste la source de verite pour ce contenu._

| Objection | Reponse |
|---|---|
| "On a deja Excel/WhatsApp" | Leopardo garde la simplicite du mobile mais ajoute preuve, audit, paie et pilotage. |
| "Un SIRH est trop lourd" | Leopardo commence par les parcours quotidiens : presence, demandes, validations, documents. |
| "Nos employes ne sont pas tech" | Le produit est mobile-first, role-based et concu pour limiter la formation. |
| "Et si internet tombe ?" | Le pointage kiosque et mobile reste utilisable meme sans reseau permanent ; la priorite produit est de ne jamais bloquer l'ecran presence. |
| "Combien de temps pour demarrer ?" | Objectif commercial : demo en 15 minutes, pilote en 48h, valeur visible en 7 jours. |
| "Pourquoi pas un ERP existant ?" | Un ERP est concu desktop-first et facture les modules RH/paie a part ; Leopardo est mobile natif et inclut paie + presence + communication dans un seul abonnement. |

---

### **DEMANDEZ UNE DEMONSTRATION**

📧 **Email** : contact@leopardo.com
🌐 **Site** : www.leopardo.com

_Coordonnees commerciales finales (telephone/WhatsApp direct, liens de demo en ligne) a completer par l'equipe commerciale au moment de la mise en marche — hors scope technique de ce ticket._

---

### **CALL TO ACTION**

**"Essai gratuit 14 jours, sans carte bancaire. Pilote 7 jours avec rapport ROI."**

---

*Leopardo HR — Mobile-First Company OS pour PME terrain.*

---

## 2. LEOPARDO_BROCHURE_PLAN.md (ex `docs/GOTO_MARKET/ASSETS_PRODUCTION/DOCS/`)

# PLAN DE PRODUCTION BROCHURE COMMERCIALE LEOPARDO

## Spécifications

- **Format :** PDF 8-12 pages, A4
- **Public cible :** PME, usines, agriculture, construction, logistique, sécurité, restauration
- **Ton :** Professionnel, pragmatique, orienté résultats
- **Objectif :** Convaincre en moins de 5 minutes

---

## STRUCTURE DE LA BROCHURE

### PAGE 1 — COUVERTURE

**Visuel :** Manager africain/européen utilisant Leopardo sur smartphone, interface dashboard visible  
**Titre :**  
```
L'OS mobile qui fait fonctionner votre entreprise.
```
**Sous-titre :**  
```
Présence, tâches, paie, documents et workflows dans une seule plateforme.
```
**Logo :** Leopardo HR  
**Tagline :** Mobile-First Company OS

---

### PAGE 2 — LE PROBLÈME

**Titre :**  
```
Votre entreprise mérite mieux qu'Excel et WhatsApp.
```

**Contenu :**
- ❌ Feuilles de présence perdues ou falsifiées
- ❌ Calculs de paie manuels et erreurs coûteuses
- ❌ Absences non suivies, retards non justifiés
- ❌ Communication éparpillée (WhatsApp, SMS, papier)
- ❌ ERP trop lourds, trop chers, pas mobiles

**Statistique choc :**  
*"Les PME perdent en moyenne 12h/mois en gestion administrative manuelle."*

**Visuel :** Collage montrant chaos actuel (feuilles papier, écrans Excel, messages WhatsApp)

---

### PAGE 3 — LA SOLUTION LEOPARDO

**Titre :**  
```
Tout votre entreprise dans votre poche.
```

**Présentation produit :**
Leopardo est le premier **Company OS Mobile-First** conçu pour les équipes terrain.

**Fonctionnalités clés :**
- 📍 **Pointage intelligent** — QR Code, biométrie faciale, géolocalisation
- 📋 **Gestion des absences** — Demandes et validations instantanées
- ✅ **Tâches & Workflows** — Assignation, suivi, notifications
- 💰 **Paie automatisée** — Calculs multi-pays, avances, bulletins
- 📄 **Documents & Kiosque** — Placard numérique, signatures électroniques
- 📶 **Offline-First** — Fonctionne sans connexion

**Visuel :** Mockup smartphone avec interface Leopardo (dashboard manager)

---

### PAGE 4 — POINTAGE INTELLIGENT

**Titre :**  
```
Fini les feuilles de présence. Bonjour la précision.
```

**Fonctionnalités :**
- Pointage en 1 clic via smartphone
- QR Code unique par entreprise
- Reconnaissance faciale anti-fraude
- Géolocalisation optionnelle
- Mode kiosque tablette pour entrée unique
- Historique complet et exportable

**Bénéfices :**
- ⏱️ Gain de temps : 80%
- 🔒 Zéro fraude possible
- 📊 Données temps réel

**Visuel :** Employé scannant un QR Code + écran de validation

---

### PAGE 5 — GESTION DES ÉQUIPES

**Titre :**  
```
Une équipe synchronisée, même sans connexion.
```

**Fonctionnalités :**
- Absences : demandes et validations en temps réel
- Tâches : assignation, priorités, échéances
- Notifications push instantanées
- Chat intégré par équipe
- Tableau de bord manager

**Bénéfices :**
- 🚀 Réactivité immédiate
- 📱 100% mobile, offline-first
- 👥 Collaboration simplifiée

**Visuel :** Notification de validation d'absence + liste de tâches

---

### PAGE 6 — PAIE AUTOMATISÉE

**Titre :**  
```
La paie en 2 clics. Sans erreur.
```

**Fonctionnalités :**
- Calcul automatique des salaires
- Primes, heures supplémentaires, retenues
- Avances sur salaire validées par workflow
- Bulletins de salaire numériques
- Export vers systèmes comptables
- Multi-pays (Afrique, Europe, Turquie, Moyen-Orient)

**Bénéfices :**
- 💰 Réduction des erreurs de 95%
- ⏱️ Traitement en minutes vs jours
- 🌍 Conformité locale garantie

**Visuel :** Bulletin de salaire mobile + écran de validation paie

---

### PAGE 7 — DOCUMENTS & KIOSQUE

**Titre :**  
```
Vos documents RH, accessibles partout.
```

**Fonctionnalités :**
- Placard numérique (contrats, attestations, règlements)
- Signature électronique intégrée
- Kiosque tablette avec reconnaissance faciale
- Archivage sécurisé et conforme RGPD
- Accès employé 24/7

**Bénéfices :**
- 📄 Zéro papier
- 🔐 Sécurité maximale
- ✅ Conformité légale

**Visuel :** Tablette en mode kiosque + employé signant un document

---

### PAGE 8 — POURQUOI LEOPARDO ?

**Titre :**  
```
La différence qui change tout.
```

**Tableau comparatif :**

| Critère | Leopardo | ERP Traditionnels | Outils RH |
|---------|----------|-------------------|-----------|
| Mobile-First Natif | ✅ Oui | ❌ Non | ⚠️ Adapté |
| Mode Offline | ✅ Complet | ❌ Aucun | ⚠️ Limité |
| Onboarding | ✅ 30 secondes | ❌ Jours/semaines | ⚠️ Heures |
| Paie Incluse | ✅ Oui | ❌ Module payant | ❌ Non |
| Prix PME | ✅ Abordable | ❌ Cher | ⚠️ Variable |
| Open Core | ✅ Transparent | ❌ Fermé | ❌ Fermé |

**Visuel :** Infographie comparative

---

### PAGE 9 — TÉMOIGNAGES

**Titre :**  
```
Ils ont transformé leur gestion avec Leopardo.
```

**Témoignage 1 :**  
*"Leopardo a révolutionné notre gestion de 200 employés sur chantiers. Fini les feuilles perdues et les erreurs de paie."*  
**— PDG, Entreprise BTP, Sénégal**  
🏗️ Secteur : Construction | 👥 200 employés

**Témoignage 2 :**  
*"La paie se fait maintenant en 2 clics. On a gagné 80% de temps administratif."*  
**— DRH, Chaîne de restaurants, Turquie**  
🍽️ Secteur : Restauration | 👥 150 employés

**Témoignage 3 :**  
*"L'onboarding par QR Code est bluffant. Nos équipes agricoles sont opérationnelles en 30 secondes."*  
**— Directeur, Exploitation agricole, Maroc**  
🌾 Secteur : Agriculture | 👥 80 employés

**Visuel :** Photos des témoignages (si disponibles) ou icônes secteurs

---

### PAGE 10 — OFFRE DE LANCEMENT

**Titre :**  
```
Commencez aujourd'hui. Transformez-vous en 14 jours.
```

**Offre spéciale lancement :**
- 🎁 **Essai gratuit 14 jours** — Aucune carte requise
- 🎁 **Onboarding offert** pour les 50 premières entreprises (valeur 500€)
- 🎁 **Formation équipe incluse**
- 🎁 **Support prioritaire 3 mois**

**Plans tarifaires :** *(référer à PRICING.md)*
- **Gratuit** — Jusqu'à 5 employés
- **Starter** — 5-50 employés
- **Business** — 50-250 employés
- **Enterprise** — 250+ employés, personnalisé

**Visuel :** Badge "Offre de lancement" + captures des plans

---

### PAGE 11 — PROGRAMME PARTENAIRE

**Titre :**  
```
Devenez partenaire Leopardo.
```

**Cibles :**
- Agences marketing digital
- SSII et intégrateurs
- Consultants RH
- Cabinets comptables
- Fournisseurs de logiciels

**Avantages :**
- 💰 **25% de commission récurrente** sur les abonnements
- 🎓 Formation et certification offerte
- 📦 Kit marketing complet (brochures, vidéos, démos)
- 🤝 Support technique dédié
- 🌟 Visibilité sur notre annuaire partenaires

**Visuel :** Icônes partenaires + graphique commission

---

### PAGE 12 — CONTACT & CTA

**Titre :**  
```
Prêt à transformer votre entreprise ?
```

**Call to Action :**
👉 **Demandez votre démonstration gratuite**

**Coordonnées :**
- 📧 Email : contact@leopardo.com
- 🌐 Site : www.leopardo.com
- 📱 WhatsApp : +XX XXX XXX XXXX
- 📍 Siège : [Adresse]

**QR Codes :**
- QR Code 1 → Démo live
- QR Code 2 → Essai gratuit 14 jours
- QR Code 3 → Programme partenaire

**Réseaux sociaux :**
- LinkedIn : @LeopardoHR
- Twitter : @LeopardoHQ
- Facebook : /LeopardoCompanyOS

**Footer :**  
*Leopardo HR — Le Système d'Exploitation Mobile de votre Entreprise*  
© 2025 Leopardo. Tous droits réservés. | RGPD Compliant | ISO 27001

---

## INSTRUCTIONS GRAPHIQUES

### Charte visuelle
- **Couleurs principales :** Bleu nuit (#1a365d), Blanc, Accent orange (#ed8936)
- **Typographie :** Inter (titres), Roboto (corps)
- **Style :** Premium, épuré, style Apple/Stripe
- **Images :** Photos authentiques de professionnels africains/européens, interfaces mockup haute qualité

### Format de sortie
- PDF haute résolution (300 DPI)
- Version web optimisée (72 DPI)
- Fichier source modifiable (Figma/Canva/InDesign)

---

## PROCHAINES ÉTAPES

1. ✅ Valider le contenu texte (ce document)
2. 🎨 Créer maquettes graphiques (Figma)
3. 📸 Produire/sélectionner visuels
4. 🖨️ Générer PDF final
5. 📤 Diffuser aux équipes commerciales

---

## 3. LANDING_PAGE_SPEC.md (ex `docs/GOTO_MARKET/ASSETS_PRODUCTION/DOCS/`)

# PLAN DE PRODUCTION LANDING PAGE — LEOPARDO HR

## Spécifications complètes pour site web de lancement

---

## OBJECTIFS

- ✅ Convertir les visiteurs en demandes de démo
- ✅ Présenter le produit en moins de 30 secondes
- ✅ Rassurer sur la crédibilité (témoignages, sécurité)
- ✅ SEO optimisé pour mots-clés RH/PME/mobile
- ✅ Mobile-first responsive (évidemment !)

---

## STRUCTURE DE LA PAGE

### SECTION 1 — HERO (Above the Fold)

**Layout :** Split screen ou centré selon device

**Éléments :**

```
Logo Leopardo (en haut gauche)
Navigation : Fonctionnalités | Tarifs | Témoignages | Contact (en haut droite)

[TITRE H1]
L'OS mobile qui fait fonctionner votre entreprise.

[SOUS-TITRE]
Présence, tâches, paie, documents et workflows dans une seule plateforme.
Conçu pour les PME et équipes terrain. 100% mobile. Offline-first.

[BOUTON CTA PRINCIPAL - GRAND]
→ Demander une démonstration gratuite

[BOUTON CTA SECONDAIRE]
▶ Voir la vidéo (2 min)

[VISUEL HERO]
Mockup smartphone géant montrant dashboard Leopardo
OU
Vidéo loop 15s montrant fonctionnalités clés

[PREUVE SOCIALE]
"Déjà adopté par 200+ entreprises en Afrique, Europe et Moyen-Orient"
Logos 5-6 entreprises pilotes (si disponibles)
```

**Couleurs :**
- Background : Gradient bleu nuit (#1a365d → #2d3748)
- Texte : Blanc
- CTA Principal : Orange (#ed8936) avec hover plus foncé
- CTA Secondaire : Transparent avec bordure blanche

**Animations :**
- Fade-in titre + sous-titre
- Slide-up boutons
- Float léger mockup smartphone
- Particules subtiles en background

---

### SECTION 2 — PROBLÈME (Pain Points)

**Layout :** 3 colonnes ou carousel mobile

**Titre Section :**
```
Votre entreprise mérite mieux qu'Excel et WhatsApp.
```

**Cartes Douleurs :**

```
CARTE 1 : ❌ Feuilles de présence perdues
"30% des feuilles papier sont perdues ou falsifiées chaque mois"
Icône : Document barré rouge

CARTE 2 : ❌ Paie manuelle error-prone
"12h/mois perdues en calculs manuels, erreurs coûteuses"
Icône : Calculatrice avec warning

CARTE 3 : ❌ Communication éparpillée
"WhatsApp, SMS, papier... personne ne sait où est l'info"
Icône : Messages éclatés

CARTE 4 : ❌ ERP trop lourds
"Trop chers, trop complexes, pas mobiles"
Icône : Éléphant (lourdeur)
```

**Style :**
- Background : Blanc
- Cartes : Ombres légères, hover lift
- Icônes : Rouge doux pour problèmes
- Animation : Stagger fade-in

---

### SECTION 3 — SOLUTION (Fonctionnalités)

**Layout :** Grid alternée (texte-gauche/image-droite puis inverse)

**Titre Section :**
```
Tout votre entreprise dans votre poche.
```

**Sous-titre :**
```
Leopardo est le premier Company OS Mobile-First conçu pour les équipes terrain.
```

**Fonctionnalités (6 blocs) :**

```
BLOC 1 : 📍 Pointage Intelligent
[Visuel : Employé scannant QR code]
"Pointage en 1 clic via QR Code, reconnaissance faciale ou géolocalisation.
Zéro fraude, 100% fiable, même sans connexion."
→ En savoir plus (lien vers page dédiée)

BLOC 2 : 📋 Gestion des Absences
[Visuel : Notification validation absence]
"Demandes et validations en temps réel. Historique complet.
Finis les retards non justifiés."
→ En savoir plus

BLOC 3 : ✅ Tâches & Workflows
[Visuel : Liste tâches mobile]
"Assignez, suivez, notifiez. Une équipe synchronisée même offline.
Productivité boostée de 40%."
→ En savoir plus

BLOC 4 : 💰 Paie Automatisée
[Visuel : Bulletin salaire digital]
"Calculs automatiques multi-pays. Primes, heures sup, avances.
Bulletins générés en 2 clics."
→ En savoir plus

BLOC 5 : 📄 Documents & Kiosque
[Visuel : Tablette kiosque + signature]
"Placard numérique, signatures électroniques, archivage RGPD.
Zéro papier, 100% sécurisé."
→ En savoir plus

BLOC 6 : 📶 Offline-First
[Visuel : Smartphone avec badge offline]
"Fonctionne sans connexion. Synchronisation automatique.
Idéal chantiers, zones rurales, sous-sols."
→ En savoir plus
```

**Style :**
- Background : Gris très clair (#f7fafc)
- Alternance : White cards / transparent
- Visuels : Mockups smartphones haute qualité
- Animation : Scroll-triggered fade-in

---

### SECTION 4 — DÉMONSTRATION VIDÉO

**Layout :** Pleine largeur, centré

**Éléments :**
```
[TITRE]
Découvrez Leopardo en action

[VIDÉO EMBED]
Lecteur vidéo personnalisé (Vimeo/Wistia/YouTube)
Durée : 2 minutes maximum
Contenu : Démo produit complète avec voiceover

[BOUTON SOUS VIDÉO]
→ Essayer gratuitement (14 jours, sans carte)
```

**Style :**
- Background : Bleu nuit (#1a365d)
- Lecteur vidéo : Bordures arrondies, ombre portée
- Thumbnail vidéo : Dashboard Leopardo avec bouton play

---

### SECTION 5 — DIFFÉRENCIATION (Pourquoi Leopardo)

**Layout :** Tableau comparatif ou checklist

**Titre Section :**
```
La différence qui change tout.
```

**Tableau Comparatif :**

```
| Critère              | Leopardo ✅ | ERP Traditionnels ❌ | Outils RH ❌     |
|----------------------|-------------|---------------------|------------------|
| Mobile-First Natif   | ✅ Oui      | ❌ Non              | ⚠️ Adapté        |
| Mode Offline         | ✅ Complet  | ❌ Aucun            | ⚠️ Limité        |
| Onboarding           | ✅ 30 sec   | ❌ Jours/semaines   | ⚠️ Heures        |
| Paie Incluse         | ✅ Oui      | ❌ Module payant    | ❌ Non           |
| Prix PME             | ✅ Abordable| ❌ Cher              | ⚠️ Variable      |
| Open Core            | ✅ Transparent| ❌ Fermé          | ❌ Fermé         |
| Support Terrain      | ✅ Expert   | ❌ Générique        | ❌ Aucun         |
```

**Alternative : Checklist Avantage**
```
✅ Conçu mobile dès le jour 1 (pas adapté du desktop)
✅ Offline-first natif (vrai mode hors-ligne)
✅ Onboarding par QR Code (30 secondes chrono)
✅ Paie multi-pays incluse (pas de module payant)
✅ Tarif PME abordable (à partir de 49€/mois)
✅ Open Core transparent (code auditables)
✅ Support expert terrain (pas de call-center)
```

**Style :**
- Background : Blanc
- Tableau : Lignes alternées grises claires
- Check/Cross : Vert/Rouge vifs
- Animation : Count-up si chiffres clés

---

### SECTION 6 — TÉMOIGNAGES (Preuve Sociale)

**Layout :** Carousel ou grid 3 cartes

**Titre Section :**
```
Ils ont transformé leur gestion avec Leopardo.
```

**Témoignages :**

```
CARTE 1 : 🏗️ BTP
"Leopardo a révolutionné notre gestion de 200 employés sur chantiers. 
Fini les feuilles perdues et les erreurs de paie."
— Mamadou D., PDG Entreprise BTP, Sénégal
Photo + Logo entreprise + Secteur + Nombre employés

CARTE 2 : 🍽️ Restauration
"La paie se fait maintenant en 2 clics. On a gagné 80% de temps administratif.
Mes équipes adorent la simplicité."
— Ayşe K., DRH Chaîne Restaurants, Turquie
Photo + Logo + Secteur + Nombre employés

CARTE 3 : 🌾 Agriculture
"L'onboarding par QR Code est bluffant. Nos équipes agricoles sont 
opérationnelles en 30 secondes. Incroyable."
— Hassan M., Directeur Exploitation, Maroc
Photo + Logo + Secteur + Nombre employés

CARTE 4 : 🚚 Logistique
"Enfin un outil qui comprend les réalités terrain. Offline, rapide, 
fiable. Mes chauffeurs sont conquis."
— Jean-Pierre L., DG Transport, France
Photo + Logo + Secteur + Nombre employés
```

**Éléments additionnels :**
- Note moyenne : ⭐⭐⭐⭐⭐ 4.8/5 (basé sur XX avis)
- Logos entreprises (si autorisés)
- Photos authentiques (pas de stock)

**Style :**
- Background : Gris clair (#f7fafc)
- Cartes : Ombres douces, hover lift
- Citations : Guillemets stylisés
- Animation : Carousel auto ou manual

---

### SECTION 7 — TARIFICATION

**Layout :** 4 cartes alignées (responsive : stack mobile)

**Titre Section :**
```
Des tarifs simples, transparents, adaptés aux PME.
```

**Sous-titre :**
```
Essai gratuit 14 jours. Aucune carte requise. Annulable anytime.
```

**Plans :**

```
CARTE 1 : 🎁 GRATUIT
0€ / mois
Jusqu'à 5 employés

✅ Pointage QR Code illimité
✅ Gestion des absences
✅ 1GB stockage documents
✅ Support communauté
❌ Paie automatisée
❌ Workflows avancés
❌ API accès

[BOUTON] → Commencer gratuitement


CARTE 2 : 🚀 STARTER (POPULAIRE)
49€ / mois
5-50 employés

Tout du Gratuit +
✅ Paie automatisée (1 pays)
✅ Tâches & workflows
✅ 10GB stockage
✅ Support email prioritaire
✅ Exports comptables
❌ Paie multi-pays
❌ Analytics avancés
❌ SSO/SAML

[BOUTON] → Essai gratuit 14 jours
Badge : "Le plus choisi"


CARTE 3 : 💼 BUSINESS
149€ / mois
50-250 employés

Tout du Starter +
✅ Paie multi-pays (5 pays)
✅ Analytics & rapports
✅ 100GB stockage
✅ Support téléphone dédié
✅ API accès complet
✅ Formations équipe
❌ SSO/SAML
❌ Custom developments

[BOUTON] → Essai gratuit 14 jours


CARTE 4 : 🏢 ENTERPRISE
Sur devis
250+ employés

Tout du Business +
✅ SSO/SAML
✅ Audit logs
✅ Stockage illimité
✅ Support 24/7 dédié
✅ SLA garanti 99.9%
✅ Custom developments
✅ Onboarding white-glove

[BOUTON] → Contacter les ventes
```

**Éléments rassurants :**
- ✅ 14 jours gratuits, sans carte
- ✅ Annulable anytime
- ✅ Migration assistée offerte
- ✅ Données exportables anytime

**Style :**
- Background : Blanc
- Carte populaire : Mise en avant (bordure orange, shadow plus forte, badge)
- Check/Cross : Vert/Gris
- Animation : Hover scale léger

---

### SECTION 8 — SÉCURITÉ & CONFORMITÉ

**Layout :** 2 colonnes (texte + badges)

**Titre Section :**
```
Sécurité enterprise, conformité garantie.
```

**Contenu Colonne Gauche :**
```
Vos données sont précieuses. Nous les protégeons comme les nôtres.

🔒 Chiffrement AES-256 de bout en bout
🔐 Authentification multi-facteurs (MFA)
🛡️ Backups quotidiens automatiques
📍 Data centers UE (RGPD compliant)
📋 Audit logs complets
⏱️ SLA 99.9% garanti
🔍 Pen-tests trimestriels
👥 RBAC granulaire (rôles personnalisables)

Certifications en cours : ISO 27001, SOC 2
```

**Contenu Colonne Droite :**
```
[Badges/Certificats]
- RGPD Compliant
- AES-256 Encryption
- Cloud Secured
- [Logo Data Center]
- [Logo Hébergeur]
```

**Style :**
- Background : Bleu nuit (#1a365d)
- Texte : Blanc
- Icônes : Blanc/orange
- Badges : Couleurs officielles

---

### SECTION 9 — CTA FINAL (Dernière Chance)

**Layout :** Centré, impact maximum

**Éléments :**
```
[TITRE FORT]
Prêt à transformer votre entreprise ?

[SOUS-TITRE]
Rejoignez les 200+ entreprises qui font confiance à Leopardo.

[BOUTON CTA GIGANTESQUE]
→ Demander votre démonstration gratuite

[SOUS-BOUTON]
Ou commencer l'essai gratuit 14 jours (sans carte)

[PREUVES FINALES]
⚡ Installation en 30 minutes
🎯 Onboarding offert (50 premières entreprises)
💬 Support dédié inclus
📞 +XX XXX XXX XXXX
```

**Style :**
- Background : Gradient orange/bleu dynamique
- Texte : Blanc
- Bouton : Blanc avec texte orange, hover orange
- Animation : Pulse léger sur bouton

---

### SECTION 10 — FOOTER

**Layout :** 4 colonnes + newsletter

**Colonne 1 : Marque**
```
Logo Leopardo
"L'OS mobile qui fait fonctionner votre entreprise."

Réseaux sociaux :
[LinkedIn] [Twitter] [Facebook] [YouTube]
```

**Colonne 2 : Produit**
```
Fonctionnalités
Tarification
Roadmap
Changelog
API Documentation
Open Source
```

**Colonne 3 : Ressources**
```
Blog
Guides & Tutoriels
Webinaires
Études de cas
Centre d'aide
Communauté
```

**Colonne 4 : Entreprise**
```
À propos
Carrières (nous recrutons !)
Presse
Partenaires
Contact
Mentions légales
```

**Newsletter Signup :**
```
Restez informé
[Email input] → [S'inscrire]
"1 email/mois. Pas de spam. Désinscription anytime."
```

**Bas de Footer :**
```
© 2025 Leopardo. Tous droits réservés.
RGPD Compliant | ISO 27001 in progress
Fabriqué avec ❤️ pour les PME mondiales
```

**Style :**
- Background : Gris très foncé (#1a202c)
- Texte : Gris clair
- Liens : Hover blanc
- Séparateurs : Subtils

---

## SPÉCIFICATIONS TECHNIQUES

### Stack Recommandée

**Option 1 : Next.js (Recommandé)**
- Framework : Next.js 14+ (App Router)
- Styling : Tailwind CSS
- Animations : Framer Motion
- Forms : React Hook Form + Zod
- Hosting : Vercel
- Analytics : Plausible (privacy-first)

**Option 2 : WordPress (Rapide)**
- Thème : Elementor Pro ou Divi
- Forms : Gravity Forms
- Caching : WP Rocket
- Hosting : SiteGround ou Kinsta

**Option 3 : Webflow (No-code)**
- Design : Webflow natif
- Forms : Webflow Forms
- Hosting : Webflow
- Custom code : Embed possible

### SEO Requirements

```html
<!-- Meta tags essentiels -->
<title>Leopardo HR — L'OS mobile pour PME et équipes terrain</title>
<meta name="description" content="Présence, paie, tâches et workflows dans une seule app mobile. Offline-first. Essai gratuit 14j.">
<meta name="keywords" content="logiciel RH, gestion présence, pointeuse mobile, paie PME, company OS, mobile-first">

<!-- Open Graph -->
<meta property="og:title" content="Leopardo HR — Mobile-First Company OS">
<meta property="og:description" content="Transformez votre gestion RH en 14 jours.">
<meta property="og:image" content="/og-image.jpg">
<meta property="og:url" content="https://www.leopardo.com">

<!-- Schema.org -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "Leopardo HR",
  "description": "Mobile-First Company OS for SMEs",
  "offers": {
    "@type": "Offer",
    "price": "49.00",
    "priceCurrency": "EUR"
  }
}
</script>
```

### Performance Targets

- ⚡ LCP (Largest Contentful Paint) : < 2.5s
- 🎯 FID (First Input Delay) : < 100ms
- 📊 CLS (Cumulative Layout Shift) : < 0.1
- 📱 Mobile Score Lighthouse : > 90
- 💻 Desktop Score Lighthouse : > 95

### Accessibilité (A11y)

- ✅ Contrastes AA minimum
- ✅ Navigation clavier complète
- ✅ Labels ARIA sur tous éléments interactifs
- ✅ Focus states visibles
- ✅ Alt text sur toutes images
- ✅ Transcripts vidéos

---

## CONTENUS À PRODUIRE

### Textes
- ✅ Hero headline + subhead
- ✅ 6 blocs fonctionnalités détaillés
- ✅ 4 témoignages complets
- ✅ FAQ (10 questions/réponses)
- ✅ Pages légales (CGV, Confidentialité)

### Visuels
- 📸 Mockups smartphones (5 angles)
- 📸 Screenshots interface (15+)
- 📸 Photos équipe (si dispo)
- 📸 Logos clients (autorisations)
- 🎨 Icônes custom (pack 50+)

### Vidéos
- 🎥 Hero video loop (15s)
- 🎥 Démo complète (2 min)
- 🎥 Témoignages clients (3 x 1 min)

### Documents
- 📄 Brochure PDF (téléchargeable)
- 📄 One Pager (téléchargeable)
- 📄 Étude de cas (3 exemples)

---

## CHECKLIST LANCEMENT

### Avant Publication

- [ ] Validation juridique (mentions légales, CGV, confidentialité)
- [ ] Tests cross-browser (Chrome, Firefox, Safari, Edge)
- [ ] Tests responsive (mobile, tablette, desktop)
- [ ] Optimisation images (WebP, compression)
- [ ] Configuration analytics (Plausible/Google Analytics)
- [ ] Setup forms (CRM integration: HubSpot/Pipedrive)
- [ ] Email automation (welcome sequence)
- [ ] SEO on-page (meta tags, schema, sitemap)
- [ ] Speed optimization (cache, CDN, lazy loading)
- [ ] Accessibility audit (WAVE, axe DevTools)

### Jour J

- [ ] DNS configuration
- [ ] SSL certificate activé
- [ ] Monitoring setup (UptimeRobot, Sentry)
- [ ] Backup automatique configuré
- [ ] Redirections anciennes URLs (si migration)

### Post-Lancement (J+1 à J+7)

- [ ] Soumission Google Search Console
- [ ] Soumission Bing Webmaster Tools
- [ ] Indexation check
- [ ] Performance monitoring
- [ ] A/B testing setup (Google Optimize)
- [ ] Heatmaps (Hotjar/Microsoft Clarity)
- [ ] Feedback collection (Typeform/Tally)

---

## MÉTRIQUES DE SUCCÈS

### Mois 1

- 👁️ **Visiteurs uniques** : Objectif 5 000+
- 📊 **Taux de rebond** : < 40%
- ⏱️ **Temps moyen page** : > 2 min
- 📲 **Taux conversion mobile** : > 3%
- 💻 **Taux conversion desktop** : > 5%
- 🎯 **Total démos demandées** : 150+
- 🆓 **Essais démarrés** : 300+

### Mois 3

- 👁️ **Visiteurs mensuels** : 20 000+
- 🎯 **Conversion globale** : > 4%
- 💰 **Coût par lead** : < 20€
- 📈 **Croissance organique** : +30%/mois

---

## BUDGET ESTIMATIF

### Option DIY (Interne)

- Domaine + Hosting : 50€/mois
- Thème/Template : 100-300€ (one-time)
- Plugins/Outils : 100€/mois
- **Total : ~250€/mois**

### Option Agence

- Design UX/UI : 3 000-5 000€
- Développement : 5 000-10 000€
- Contenu (textes + visuels) : 2 000-4 000€
- **Total : 10 000-19 000€ (one-time)**

### Option Freelance

- Designer : 1 500-3 000€
- Développeur : 2 000-5 000€
- Copywriter : 500-1 500€
- **Total : 4 000-9 500€ (one-time)**

---

## PROCHAINES ÉTAPES

1. ✅ Valider structure et contenus (ce document)
2. 🎨 Maquettes design (Figma)
3. ✍️ Rédaction contenus définitifs
4. 📸 Production visuels/vidéos
5. 💻 Développement landing page
6. 🧪 Tests QA complets
7. 🚀 Publication et monitoring

---

## ANNEXE : WIREFRAMES

*[Insérer liens vers wireframes Figma ici]*

- Wireframe Desktop : [Lien]
- Wireframe Mobile : [Lien]
- Wireframe Tablette : [Lien]

---

**Document créé pour :** Phase 4 — Semaine 2 du Plan GTM  
**Responsable :** [Nom PM/Lead Dev]  
**Deadline :** [Date]  
**Statut :** ✅ En attente de validation
