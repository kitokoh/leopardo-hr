# 12 — Analyse marketing et positionnement

**Règle de ce document :** aucun prix, aucune fonctionnalité concurrente n'est inventé.
Chaque chiffre renvoie à une source publique datée, listée au §8. Quand une donnée n'est
pas publiée par l'éditeur, c'est écrit « non publié » — jamais estimé.

**Date de relevé :** 2026-10-17.

---

## 1. Positionnement

**Formulation retenue :**

> Leopardo Business OS est la suite de gestion qui fait tourner une entreprise de bout en
> bout — du premier jour d'activité au pilotage multi-établissements — dans une seule
> plateforme, en français, arabe et turc, et qui continue de fonctionner quand le réseau
> tombe.

**Ce que ce positionnement n'est pas :** un ERP à paramétrer. La différence n'est pas la
liste des modules, c'est le **temps jusqu'à la première opération réussie**. Un ERP se
déploie ; Leopardo démarre.

**Promesse mesurable :** « votre première vente encaissée le jour de l'inscription », avec
l'onboarding universel comme mécanisme, pas comme argument.

---

## 2. Bénéfices par profil

| Profil | Douleur dominante | Bénéfice Leopardo | Preuve nécessaire |
|---|---|---|---|
| Restaurateur indépendant | caisse + cuisine désynchronisées, coupures réseau | caisse et cuisine sur la même file, rejeu sans doublon | scénario OL-2 vert |
| Groupe de restaurants | aucune vue consolidée, données mélangées entre sites | établissements séparés + consolidation | gap T-01/T-10 à combler |
| Gérant de station-service | marges opaques, clôture de caisse manuelle | ventes par mode de paiement, clôture ventilée, annulation tracée | tests Fuel existants |
| Agence de voyage | surbooking, billets falsifiables | verrou de siège, QR vérifiable | tests Travel existants |
| Grossiste / détaillant | stock et ventes dans deux outils | catalogue + stock + vente dans un tenant | gap T-03 (E2E) |
| Importateur / exportateur | devises, documents, partenaires éclatés | multi-devises, documents, rôles dédiés | dépend de la PR #8487 |
| Dirigeant multi-activités | un outil par métier | un compte, plusieurs solutions, un seul tenant | catalogue 12 verticales |
| RH / paie | pointage et paie déconnectés | pointage terrain → paie, mêmes données | modules existants |

---

## 3. Segments et priorités

| Segment | Taille du besoin | Facilité d'acquisition | Priorité |
|---|---|---|---|
| Restauration (mono + groupe) | élevée | élevée (réseau local, démonstration visuelle) | **1** |
| Station-service | moyenne | moyenne (décideur unique) | **2** |
| Retail / grossiste | élevée | moyenne | **3** |
| Agence de voyage | moyenne | faible (cycle long) | 4 |
| École / santé / pharmacie | moyenne | faible (appels d'offres) | 5 |
| Trade international | élevée | faible (confiance, documents) | 6 — dépend de #8487 |

**Justification du choix n°1 :** la restauration est la seule verticale où la valeur est
démontrable en 10 minutes, sur le matériel du client, avec une coupure réseau provoquée
volontairement. C'est aussi celle où les concurrents sont les plus chers par terminal.

---

## 4. Différenciation

| Axe | Leopardo | Constat marché (sourcé) |
|---|---|---|
| **Multi-verticale réelle** | 12 verticales, un seul tenant, un seul socle | KiboERP couvre boutique/resto/école/pharmacie/hôtel sur une base commune (§8) |
| **Hors-ligne assumé** | PWA + Edge LAN, file visible, rejeu idempotent | KiboERP propose un déploiement on-premise pour les zones à connectivité instable (§8) |
| **Onboarding en minutes** | parcours universel 8 étapes, établissement créé | Odoo exige une implémentation payante de 5 000 à 150 000 $ (§8) |
| **Prix lisible** | abonnement par tenant, pas par terminal | Square/Toast facturent par appareil et par module (§8) |
| **Multilingue FR/AR/TR** | 4 locales complètes en CI | rare chez les éditeurs régionaux |
| **Honnêteté du statut** | aucune app annoncée sans canal réel | pratique peu répandue |

**Ce qui n'est PAS un différenciateur (à ne pas revendiquer) :**

- L'IA : présente mais non différenciante sur ce marché aujourd'hui.
- L'intégration matérielle pompes/TPE : **non faite** (le §`11_integrations_catalogue.md`
  le documente).
- Les stores mobiles : aucun canal iOS, distribution Android en échec sur `main`.

---

## 5. Analyse concurrentielle par verticale

### 5.1 Restauration

| Acteur | Modèle tarifaire publié | Forces | Faiblesses face à Leopardo |
|---|---|---|---|
| Toast (US) | 0 $ à ~165 $/mois + matériel + commission | écosystème complet | pas d'ancrage Afrique, prix par terminal + modules |
| Square (US) | forfait par appareil, ~20–30 $/appareil/mois | simplicité | paiement carte dominant, hors-ligne limité |
| KoriPOS (Afrique de l'Ouest) | paiement unique 99 000 / 299 000 / 599 000 FCFA | prix unique, OHADA, PC hors ligne en option payante | pas de multi-verticale, pas de RH/paie |
| KiboERP | 9 900 à 149 900 FCFA/mois | Mobile Money natif, on-premise | ERP à configurer, pas d'onboarding guidé métier |
| NexoPOS | 45 $ une fois + add-ons | coût d'entrée très bas | écosystème limité, pas de gestion RH |

### 5.2 Station-service

| Acteur | Modèle | Forces | Faiblesses |
|---|---|---|---|
| Stationnex | non publié (devis) | FCC cloud, jaugeage, RFID flotte | orienté réseau pétrolier, pas PME |
| Station Maroc | non publié | ancrage local Maroc, prix carburant | mono-pays, mono-verticale |
| StationPlus | essai gratuit, prix non publié | multi-stations | périmètre caisse carburant |

**Écart à combler :** aucun acteur consulté ne publie un prix — Leopardo peut se
différencier par la **transparence tarifaire**, pas par le prix.

### 5.3 RH / paie

| Acteur | Modèle publié | Note |
|---|---|---|
| Gestion RH Africa | à partir de 5 000 FCFA/mois + 700 FCFA/employé actif | modèle par employé, lisible |
| Ngavix | dès 5 000 FCFA/mois | positionnement francophone |
| Odoo | 24,90–49 $/utilisateur/mois + implémentation | puissant, coût d'entrée élevé |

**Conséquence :** le modèle « par employé actif » est la norme régionale lisible. Un
abonnement par tenant sans limite d'employés serait un argument fort — **à valider
commercialement, non décidé ici**.

---

## 6. Objections et réponses

| Objection | Réponse honnête |
|---|---|
| « On a déjà Excel, ça marche » | « Jusqu'au premier doublon de stock. Voici ce que coûte un écart de caisse sur un mois. » — calcul à partir de **leurs** chiffres, pas d'une moyenne inventée. |
| « Et si Internet tombe ? » | démonstration en coupure réelle, avec la file visible et le rejeu. **Ne pas** promettre tant que OL-1..OL-4 ne sont pas verts. |
| « C'est trop cher » | comparer au coût du terminal + modules chez les concurrents (§4), pas au prix d'Excel. |
| « On est au Maroc / en Algérie / au Tchad, vous connaissez ? » | multi-devises + multi-fuseaux + multi-langues ; conformité fiscale locale **non revendiquée** au-delà de ce qui est implémenté. |
| « On veut nos données chez nous » | déploiement Edge LAN documenté ; hébergement souverain **non proposé** aujourd'hui. |
| « Combien de temps pour démarrer ? » | le parcours d'onboarding, chronométré devant eux. |
| « Vous êtes une petite structure » | ne pas répondre sur la taille ; répondre sur la réversibilité (export des données) et sur le référentiel de tests. |

---

## 7. Plan de contenu

### 7.1 Site

| Page | Objectif | Contenu clé | Statut |
|---|---|---|---|
| Accueil | qualifier en 5 s | promesse + constellation des 12 verticales + CTA « démarrer » | existant (refonte #8588–#8592) |
| `/packs/<verticale>` (×12) | capter l'intention métier | bénéfices par profil, capture d'écran réelle, CTA avec `?sector=` | existant partiel |
| `/trade` | parcours international/domestique | les deux marchés, sous-profils, cas d'usage | existant, à enrichir (gap O-01) |
| `/mon-logiciel` (produit) | transparence | statuts réels, aucune promesse non tenue | existant #8597 |
| Tarifs | lever le doute | grille par tenant, pas par terminal | **à créer** |
| Sécurité & données | rassurer | multi-tenant, isolation, exports, rétention | **à créer** |

### 7.2 Démos et vidéos (prompts de production)

| # | Vidéo | Durée | Script (plan par plan) |
|---|---|---|---|
| V1 | « De l'inscription à la première vente » | 90 s | 0–10 s : vitrine, choix métier. 10–30 s : inscription, 3 champs. 30–50 s : établissement + 3 produits. 50–70 s : vente encaissée, ticket imprimé. 70–90 s : Mon Logiciel, statut réel des apps. Voix off factuelle, aucun superlatif. |
| V2 | « Le réseau tombe, la caisse continue » | 60 s | 0–10 s : Wi-Fi coupé à l'écran (icône réelle). 10–25 s : 2 ventes enregistrées, bandeau « 2 en attente ». 25–45 s : reconnexion, envoi, **vérification qu'il n'y a pas de doublon** à l'écran. 45–60 s : phrase de conclusion sur la preuve, pas sur la promesse. |
| V3 | « Un groupe, deux restaurants, zéro mélange » | 75 s | 0–15 s : 2 établissements créés. 15–40 s : vente sur le site B. 40–60 s : vue du site A, aucune donnée de B. 60–75 s : consolidation (si livrée — sinon ne pas tourner). |
| V4 | « La station, de la pompe à la clôture » | 75 s | 0–15 s : vente comptant. 15–35 s : vente carte. 35–55 s : clôture ventilée par mode. 55–75 s : annulation d'une vente erronée, historique conservé. |
| V5 | « Le billet qu'on ne peut pas falsifier » | 60 s | 0–20 s : réservation, siège bloqué en temps réel. 20–40 s : scan du QR au contrôle. 40–60 s : second scan refusé, premier opérateur conservé. |

**Règle de production :** chaque plan montre une action réelle à l'écran. Aucune
reconstitution, aucun montage suggérant une fonctionnalité absente. Si une séquence dépend
d'un gap non comblé (V3 consolidation), elle n'est pas tournée.

### 7.3 Articles

| Titre | Intention | Angle |
|---|---|---|
| « Combien coûte vraiment une caisse de restaurant en Afrique de l'Ouest » | comparatif | chiffres publics, calcul du coût total incluant matériel et modules |
| « Ce qui se passe quand le réseau tombe pendant le service » | technique + rassurance | mécanique de file, rejeu, idempotence, expliquée simplement |
| « Multi-établissements : les trois erreurs qui mélangent vos données » | expertise | séparation par site, permissions, consolidation |
| « Pourquoi nous refusons d'afficher un bouton de téléchargement sans canal réel » | confiance | politique produit assumée |

### 7.4 E-mails d'onboarding

| # | Déclencheur | Objet | Contenu |
|---|---|---|---|
| E1 | immédiat | « Votre espace est prêt » | 1 action : terminer la configuration (lien direct à l'étape) |
| E2 | J+1 sans première vente | « La première vente prend 2 minutes » | 3 étapes numérotées, capture |
| E3 | J+3 avec première vente | « Invitez votre équipe » | rôles, permissions, un clic |
| E4 | J+7 inactif | « Qu'est-ce qui vous a bloqué ? » | une seule question ouverte, réponse humaine |
| E5 | J+14 actif | « Vos chiffres de la semaine » | 3 indicateurs réels, aucun remplissage |

**Interdit :** e-mail de « relance commerciale » déguisé en aide ; toute séquence s'arrête
dès la première réponse humaine.

### 7.5 Présentations

| Deck | Usage | Contenu |
|---|---|---|
| Deck investisseur | levée / partenariat | problème, preuve terrain, architecture, traction réelle, modèle |
| Deck partenaire intégrateur | réseau de revendeurs | périmètre, marges, support, certification |
| Deck client (par verticale) | rendez-vous commercial | 8 slides : douleur, démonstration, périmètre réel, prix, mise en route, preuves |

**Règle :** tout chiffre de traction dans un deck doit être reproductible depuis une
requête ou un rapport daté. Aucun chiffre arrondi « vers le haut ».

---

## 8. Sources publiques consultées (2026-10-17)

| Source | Donnée utilisée |
|---|---|
| `tcang.net` — Restaurant POS Cost 2026 | logiciel 0–300 $/mois, matériel 600–7 200 $ |
| `deelo.ai` — Toast POS Pricing 2026 | paliers Toast, frais par module |
| `warocol.com` — POS Buyer's Guide 2026 | WARO 30 $/mois, comparaison Square/Toast |
| `koripos.com` | 99 000 / 299 000 / 599 000 FCFA paiement unique, PC hors ligne 50 000 F/an |
| `kiboerp.com` (caisse, comptable, école) | 9 900–149 900 FCFA/mois, Mobile Money, on-premise, SYSCOHADA |
| `my.nexopos.com` | 45 $ une fois + add-ons |
| `stationnex.com`, `stationmaroc.com`, `stationplus.pro` | périmètre fonctionnel station, prix non publiés |
| `gestionrhafrica.com`, `ngavix.com` | 5 000 FCFA/mois + 700 FCFA/employé actif |
| `octurasolutions.com`, `swell.is`, `whizzbridge.com` | Odoo 24,90–49 $/utilisateur/mois, implémentation 5 000–150 000 $ |

**Aucune de ces sources n'a été vérifiée par un appel d'offres réel.** Les prix publiés
peuvent différer des prix pratiqués ; ce document ne les présente pas comme des vérités
contractuelles.

---

## 9. Ce qui est interdit sans autorisation explicite

- Publier une page, un article ou une vidéo.
- Lancer une campagne payante (aucune dépense externe).
- Contacter un prospect ou un concurrent.
- Annoncer une date de disponibilité.
- Revendiquer une conformité fiscale (SYSCOHADA, DGI, CNAS…) non implémentée et non testée.
