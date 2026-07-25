# LEOPARDO — Mobile-First Company OS
## One Pager Commercial

_Ticket: `PA2-STR-002` (Issue #1012). Depend de `PA2-STR-001` (positionnement final, deja livre — voir `docs/GOTO_MARKET/2026_MARKET_LAUNCH_COMPANY_OS/02_POSITIONNEMENT_ET_MESSAGING.md`). Ce document remplace la version brouillon precedente (temoignages placeholder, coordonnees fictives, sans pricing chiffre) par une version alignee sur les sources de verite du produit : `docs/dossierdeConception/03_modele_economique/03_MODELE_ECONOMIQUE.md` (plans/prix) et `docs/GOTO_MARKET/2026_MARKET_LAUNCH_COMPANY_OS/` (positionnement/objections)._

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
