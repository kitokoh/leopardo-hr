# Page de vente — « Paie + Pointage DZ »

> **Statut** : livrable de la phase **P0 — Gel du message** · **Date** : 2026-11-27 · **Issue** : #10017
> **Décision de référence** : `../REPOSITIONNEMENT_2026.md` §4.1 — un seul pack, un seul pays, un seul message.
> **Cadre bloquant** : `docs/REFERENTIEL_PRODUIT/MESSAGE.md` (pitch canonique, promesses interdites), `METRIQUES_VITRINE.md` (chiffres datés uniquement).
> **Rappel de catégorie** : Leopardo est une **suite métier**. Jamais « logiciel RH », « HR SaaS » ni « Business OS » comme catégorie.

---

## 0. Comment utiliser ce document

Ce document est la **copie de référence** de la page de vente unique du lancement. Il se lit de haut en bas : chaque bloc correspond à une section de la page publique, dans l'ordre où le visiteur la voit.

Trois règles d'écriture, non négociables :

1. **Un seul message.** Si une phrase ne parle pas de paie, de pointage ou de preuve des heures, elle n'a rien à faire dans les six premiers écrans.
2. **Un seul pays.** L'Algérie est le pays du lancement. Les autres pays existent, mais en mention secondaire (« disponible sur demande »), jamais en argument d'entrée.
3. **Aucune promesse non prouvée.** Les référentiels de paie sont en statut **`pilot`**. On dit « le moteur calcule la paie algérienne », jamais « conformité validée ».

---

## 1. Hero — l'accroche

**Titre (H1)**

> # La paie et le pointage de vos équipes de terrain, dans une seule application.

**Sous-titre**

> Vos équipes pointent sur le terrain — mobile, GPS ou borne biométrique. Leopardo transforme ces heures en bulletins de paie algériens, calculés, validés et verrouillés. Sans tableur, sans double saisie, sans contestation que personne ne peut trancher.

**Boutons**

- **Primaire** : `Essayer 14 jours, sans carte bancaire`
- **Secondaire** : `Voir la démo de 20 minutes` (ancre vers §7)

**Bandeau de réassurance (4 items, une ligne chacun)**

| Item | Texte |
|---|---|
| 🇩🇿 | Paie algérienne : IRG, CNAS, cotisations, primes |
| 📱 | Pointage mobile, GPS et borne ZKTeco |
| 🔌 | Fonctionne quand le réseau tombe |
| 📄 | Bulletin PDF, run verrouillé après validation |

**Ce que le hero ne dit pas** — et c'est volontaire : ni « suite complète », ni « 41 modules », ni « 21 pays », ni « Business OS ». Ces éléments existent (§6), mais ils ne sont pas l'accroche.

---

## 2. Le problème — trois phrases, pas un paragraphe

> **Le papier ne prouve rien.**
> Un carnet de pointage se perd, se rature, se conteste. Le jour où un employé dit « j'ai fait deux heures de plus », personne n'a de preuve.

> **Le tableur ne calcule pas la loi.**
> Un tableur de paie reproduit ce qu'on lui a appris. Quand l'IRG change de tranche ou qu'une cotisation bouge, il faut le savoir avant, pas après le virement.

> **Et entre les deux, il y a vous.**
> Vous passez le début de chaque mois à recoller des heures, des absences et des avances — pour un résultat que personne ne peut vérifier.

**Transition**

> Leopardo ne vous demande pas de changer votre façon de travailler. Il vous demande d'arrêter de la documenter trois fois.

---

## 3. Les 3 modules en avant

Trois modules. Pas quatre. Pas « et aussi ». Le reste est au §6, en catalogue.

### 3.1 Paie — le moteur

**Ce que ça fait**

> Calcule la paie algérienne : IRG, CNAS, cotisations patronales et salariales, primes, retenues, avances. Produit le bulletin en PDF. Verrouille le run une fois validé — plus personne ne le modifie par accident.

**Ce que ça remplace** : le tableur de paie, la ressaisie mensuelle, le « je te renvoie le fichier corrigé ».

**Preuve vérifiée en exécution** (2026-11-27, environnement local PHP 8.4 / PostgreSQL 15) :

| Test | Résultat observé |
|---|---|
| Run de paie DZ, 9 salariés | **9 bulletins**, 842 059,10 DZD brut, 626 085,54 DZD net, 1 060 994,47 DZD coût employeur |
| `/validate` puis `/lock` | Statuts `validated` → `locked` confirmés |
| `GET /api/v1/pay-slips/1/pdf` | **PDF de 879 730 octets** généré |
| `/payroll-runs/1/anomalies` | 0 anomalie sur le run de test |

**Ce que ça ne prouve pas** : la conformité légale. Les référentiels sont en statut **`pilot`** et doivent être validés par un conseil fiscal algérien avant toute promesse contractuelle. C'est un engagement de la phase P1, pas un argument de vente.

### 3.2 Pointage — la preuve

**Ce que ça fait**

> Enregistre les arrivées et départs depuis le mobile, le web ou une borne biométrique ZKTeco, avec GPS et photo. En cas de coupure réseau, la borne continue de fonctionner et resynchronise au retour du lien.

**Ce que ça remplace** : le carnet, la badgeuse isolée, le « il m'a dit qu'il était là ».

**Pourquoi c'est décisif** : c'est le module qui rend la paie défendable. Sans preuve d'heures, la paie est une opinion. Avec, c'est un calcul.

**Limite assumée** : le mode hors-ligne est présent dans le code (kiosque + EdgeSync) mais n'a pas été testé en coupure réseau réelle dans cette mission. À valider en pilote.

### 3.3 RH & absences — le référentiel

**Ce que ça fait**

> Tient le dossier employé complet : identité, contrat, poste, horaire, documents, historique. Gère les demandes de congés et d'absences avec circuit de validation et soldes calculés.

**Ce que ça remplace** : le classeur de contrats, le fichier Excel des congés, la relance par WhatsApp.

**Pourquoi il est là et pas ailleurs** : c'est le module qui alimente les deux autres. Un employé saisi une fois est visible dans la paie, le pointage et les congés. C'est ce qui supprime la triple saisie — et c'est la raison pour laquelle il est dans le pack, pas au catalogue.

---

## 4. La boucle fermée — le schéma qui vend

```
   POINTAGE                RH & ABSENCES              PAIE
   ─────────               ─────────────              ────
   Mobile / GPS            Dossier employé            IRG, CNAS
   Borne ZKTeco     ───▶   Contrat, horaire    ───▶   Primes, retenues
   Hors-ligne              Congés, soldes             Bulletin PDF
        │                                                  │
        └────────────── heures prouvées ───────────────────┘
                              │
                        Run verrouillé
```

**La phrase à retenir** : *« Une heure pointée devient une ligne de paie, sans ressaisie. »*

C'est cette boucle — et rien d'autre — qui se démontre en 20 minutes. C'est aussi ce qu'aucune suite internationale ne peut montrer en Algérie, faute de référentiel de paie local.

---

## 5. Le canal cabinet comptable

**Bloc dédié, placé après la boucle fermée et avant le catalogue.**

> ### Vous êtes un cabinet comptable ?
>
> Vos clients vous demandent déjà de produire leur paie. Leopardo vous donne un espace multi-dossiers : chaque PME cliente a son dossier, ses écritures, ses clôtures — et sa paie calculée avec les règles algériennes.
>
> Vous ne revendez pas un logiciel. Vous industrialisez votre production de paie, et vous gardez une marge sur chaque dossier.

**Trois arguments, dans l'ordre où un cabinet les entend**

| # | Argument | Ce qu'il répond |
|---|---|---|
| 1 | **Productivité** | « Combien de dossiers puis-je traiter sans embaucher ? » — la paie cesse d'être un travail de ressaisie |
| 2 | **Conformité** | « La paie DZ est dans l'outil, pas dans un tableur que je maintiens moi-même » |
| 3 | **Marge** | « Je facture le service, je garde une commission sur l'abonnement » — voir la grille dans le kit partenaire |

**CTA du bloc** : `Recevoir le kit partenaire cabinet` → `docs/GOTO_MARKET/kits/cabinet-comptable.md`

**Pourquoi ce bloc est sur la page de vente et pas dans un PDF séparé** : le cabinet est un **prescripteur**, pas un prospect comme les autres. Il doit se reconnaître immédiatement, sans qu'on le lui dise deux fois.

---

## 6. Le catalogue — 38 modules activables

**Titre de section**

> ### Et quand vous en avez besoin : 38 modules déjà là.

**Chapeau**

> Leopardo n'est pas seulement une paie et un pointage. C'est une suite métier complète. Ces modules ne sont pas dans le pack de lancement — ils s'activent quand votre activité les réclame, sans changer d'outil ni ressaisir vos données.

**Règle d'affichage** : cette section est **atteignable**, jamais **mise en avant**. Elle vit sous le pli, après le CTA principal. Un visiteur qui cherche la paie ne doit jamais avoir à la traverser.

### 6.1 Les 3 modules du pack (rappel, non répétés au catalogue)

Paie · Pointage · RH & absences — voir §3.

### 6.2 Les 38 modules activables, par famille

**Socle & administration (8)**

| Module | Ce qu'il fait |
|---|---|
| Authentification & IAM | Connexion sécurisée : mot de passe, code à usage unique, double authentification |
| Multi-tenant & sécurité | Isole les données de chaque entreprise dans une base partagée et sécurisée |
| Plateforme & observabilité | Administre les entreprises clientes, leurs modules et leurs abonnements |
| Abonnements & facturation | Gère les abonnements, factures, encaissements et impayés |
| Encaissements | Encaisse par carte, mobile ou espèces et rapproche les paiements |
| Audit & conformité | Journalise les actions sensibles et produit les registres de conformité (RGPD) |
| Exports & rapports | Exporte en CSV, Excel et PDF ; rapports de paie, présence et comptabilité |
| Notifications | Envoie par push, e-mail ou SMS selon les préférences de chacun |

**Terrain & opérations (7)**

| Module | Ce qu'il fait |
|---|---|
| Kiosque & EdgeSync | Fait tourner la borne biométrique et synchronise les pointages avec le serveur |
| EdgeSync & hors-ligne | Pont local chez le client pour continuer à travailler sans internet |
| Géolocalisation | Localise pointages et interventions sur une carte, vérifie la présence sur site |
| Planning & tâches | Planifie les shifts, affecte les équipes, exige une preuve d'exécution |
| Notes de frais | Soumet une note de frais avec justificatif, la fait valider, suit le remboursement |
| Flotte | Gère véhicules, affectations, entretien, carburant et documents réglementaires |
| Livraison | Organise les tournées, suit les colis, capture la preuve de livraison |

**Finance & conformité (3)**

| Module | Ce qu'il fait |
|---|---|
| Comptabilité | Tient plan comptable, écritures et journaux ; génère les clôtures et exporte |
| Cabinet comptable | Gère un portefeuille de clients depuis un espace unique : dossiers, écritures, clôtures |
| Contrats | Génère et stocke les contrats, alerte avant échéances, gère les renouvellements |

**Commerce & relation client (5)**

| Module | Ce qu'il fait |
|---|---|
| CRM client | Suit prospects et clients, les échanges et les relances, avec pipeline par étape |
| Catalogue produits & services | Tient le catalogue avec tarifs et TVA, gère la disponibilité par établissement |
| Vitrine & acquisition | Publie la vitrine publique et capte les demandes entrantes |
| Pages & contenu | Crée et publie des pages publiques avec contenu éditorial et métadonnées |
| Marque & surfaces publiques | Applique logo, couleurs et identité sur toutes les surfaces publiques |

**Personnes & organisation (6)**

| Module | Ce qu'il fait |
|---|---|
| Recrutement | Publie les offres et une page carrières, recueille et fait avancer les candidatures |
| Onboarding & provisioning | Démarre une nouvelle entreprise en quelques minutes : inscription, essai, création |
| Communication interne | Diffuse annonces et messages ciblés, suit les accusés de lecture |
| Formation | Organise les formations internes, suit la progression, délivre les attestations |
| Programme partenaires | Gère les partenaires et apporteurs d'affaires, suit commissions et paiements |
| Recherche & intelligence | Cherche dans toutes les données de l'entreprise en une seule barre |

**Verticales métier (9)**

| Module | Ce qu'il fait |
|---|---|
| Restauration & POS | Encaisse sur le POS, gère le plan de salle, envoie en cuisine via le KDS |
| Commerce de détail | Encaisse sur le POS retail, gère catalogue et stock, imprime les tickets |
| Hotellerie | Gère chambres, séjours et housekeeping ; applique les tarifs et prend les réservations |
| Pharmacie | Tient catalogue, stock et ordonnances ; encaisse et publie la fiche officine |
| Santé | Gère patients, rendez-vous et dossiers de soin ; rédige les ordonnances |
| École & formation | Gère élèves, classes, notes et présences ; facture la scolarité |
| Station-service | Suit pompes et index, enregistre les ventes de carburant, clôture la journée |
| VTC | Affecte les courses aux chauffeurs, applique la grille tarifaire, consolide |
| Agence de voyage | Publie une boutique de trajets, gère réservations et devis |

**Extensions (4)**

| Module | Ce qu'il fait |
|---|---|
| Assistant IA | Répond aux questions sur les données et prépare des actions (brouillon de bulletin) |
| API & intégrations | Expose une API documentée (OpenAPI) et des webhooks |
| Applications mobiles | Apps distinctes pour l'employé, le manager et l'administrateur |
| Poste de travail (desktop) | Application de bureau pour les postes fixes et la saisie intensive |

**Deux modules hors catalogue de lancement** — `Agriculture` (Farm OS) et `Négoce & import-export` (Trade OS) : ils exigent des environnements de démonstration qui n'existent pas encore. Ils restent dans le produit, hors du discours public (voir `REPOSITIONNEMENT_2026.md` §3.3).

**Total** : 3 modules en pack + 38 en catalogue + 2 hors lancement = 43 entrées produit. Le décompte « 41 modules API » du dépôt compte les modules techniques ; le décompte commercial ci-dessus compte les modules vendables. Les deux sont vrais, ils ne mesurent pas la même chose.

---

## 7. La démo de 20 minutes — script de vente

**Titre**

> ### Vingt minutes, et vous savez si ça marche chez vous.

**Déroulé, minute par minute**

| Temps | Ce qu'on montre | Ce que le prospect doit penser |
|---|---|---|
| 0-2 | Le problème, avec ses mots : « combien de temps pour recoller les heures ce mois-ci ? » | « Il connaît mon problème » |
| 2-6 | Création d'un employé, puis le même employé visible dans paie, pointage et congés | « Je ne saisis qu'une fois » |
| 6-11 | Pointage depuis le mobile, puis la borne ; coupure réseau simulée | « Mes heures sont prouvées » |
| 11-16 | Simulation de paie DZ, bulletin PDF, verrouillage du run | « Ma paie est calculée, pas recopiée » |
| 16-19 | Le catalogue, en 3 minutes, sans insister | « Il y a de la marge pour plus tard » |
| 19-20 | Le prix, le pilote, la prochaine étape | « Je sais ce que ça coûte » |

**Règle** : on ne montre jamais le catalogue avant la minute 16. Un prospect qui voit 41 modules avant de voir la paie repart en se disant « c'est trop gros pour moi ».

---

## 8. Prix

**Grille publique existante** (`front/web/src/modules/vitrine/data/pricing.ts`, consultée le 2026-11-27) :

| Offre | Prix | Pour qui |
|---|---|---|
| **Free** | 0 € | Découverte, un site, équipe réduite |
| **Pilot** | 29 €/mois | PME qui démarre : paie + pointage + RH |
| **Operations** | 79 €/mois | PME multi-sites, modules opérationnels |
| **Enterprise** | Sur devis | Multi-entités, auto-hébergement, engagements |

**Positionnement du pack de lancement** : le pack « Paie + Pointage DZ » se vend sur l'offre **Pilot**, avec les 3 modules inclus. Les 38 modules du catalogue s'activent au niveau **Operations** ou au-delà.

**Ce qu'on ne fait pas** : inventer un prix algérien en dinars sur cette page. Le prix en DZD dépend du taux, de la fiscalité locale et du mode de facturation — il se fixe avec le premier pilote, pas avant. La page publique affiche l'euro ; la facturation locale se traite au contrat.

---

## 9. Objections — les six qui reviennent

| Objection | Réponse |
|---|---|
| **« On a déjà un tableur qui marche. »** | Il marche jusqu'au premier contrôle ou à la première contestation. Leopardo ne remplace pas votre façon de travailler, il remplace la ressaisie. |
| **« C'est trop gros pour nous. »** | Vous n'achetez pas 41 modules. Vous achetez la paie et le pointage. Le reste s'active plus tard, si besoin. |
| **« Et si internet tombe ? »** | La borne continue de pointer hors-ligne et resynchronise au retour du réseau. *(À valider en pilote : non testé en coupure réelle.)* |
| **« Est-ce que c'est conforme ? »** | Le moteur applique les règles algériennes (IRG, CNAS) et calcule réellement. Les référentiels sont en statut **pilote** : la validation par un conseil fiscal local fait partie du pilote, et on ne vous vend pas une conformité qu'on n'a pas fait vérifier. |
| **« On est multi-pays. »** | Le moteur couvre 21 pays. Le lancement est centré sur l'Algérie ; les autres pays sont disponibles sur demande, avec un référentiel à valider pays par pays. |
| **« Qui va le mettre en place ? »** | Mise en route en moins de 30 minutes pour le socle. Pour la paie, on accompagne le premier run — c'est aussi comme ça qu'on valide le référentiel. |

---

## 10. CTA final

> ## Prouvez vos heures. Payez juste.
>
> Quatorze jours, sans carte bancaire. Vous branchez le pointage, vous importez vos employés, vous lancez une simulation de paie. Si le bulletin ne tombe pas juste, vous arrêtez — et vous nous dites pourquoi.

- **Primaire** : `Commencer l'essai`
- **Secondaire** : `Parler à quelqu'un` (formulaire, réponse sous 24 h ouvrées)
- **Tertiaire** : `Je suis un cabinet comptable` → kit partenaire

---

## 11. Ce que cette page change, et ce qu'elle ne change pas

| Avant | Après |
|---|---|
| « Le Business OS des entreprises de terrain » | « La paie et le pointage de vos équipes de terrain » |
| 41 modules en accroche | 3 modules en accroche, 38 en catalogue |
| 21 pays en argument | 1 pays en argument, 20 sur demande |
| 32 profils ciblés | 2 cibles : dirigeant PME, cabinet comptable |
| Vente directe | Vente directe + canal cabinet |

**Ce qui ne change pas** : le produit. Aucun module n'est supprimé, aucune fonctionnalité n'est retirée. Seul l'ordre de lecture change.

---

## 12. Checklist de publication

- [ ] Le H1 ne contient ni « suite », ni « Business OS », ni un nombre de modules.
- [ ] Les 3 modules du pack sont au-dessus du pli ; le catalogue est en dessous.
- [ ] Chaque chiffre de la page est daté et traçable (`METRIQUES_VITRINE.md` ou test d'exécution du 2026-11-27).
- [ ] Aucune occurrence de « conformité validée » — uniquement « statut pilote ».
- [ ] Le bloc cabinet comptable est présent et pointe vers le kit.
- [ ] Les 4 locales (fr, en, tr, ar) portent la même accroche ; l'arabe est en RTL.
- [ ] Le CTA primaire mène à l'inscription d'essai, pas à une page de contact.
