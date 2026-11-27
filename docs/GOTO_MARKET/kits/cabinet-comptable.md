# Kit de vente grands comptes

> **Statut** : kit d'exécution · **Version** : v2 (grand compte) · **Date** : 2026-11-28 · **Issue** : #10025
> **Remplace** : le kit « cabinet comptable » (v1, cible PME). Le chemin du fichier est conservé pour ne pas casser les références ni le budget documentaire.
> **Décision de référence** : `../REPOSITIONNEMENT_2026.md` §4-5.
> **Cadre bloquant** : `docs/REFERENTIEL_PRODUIT/MESSAGE.md` (promesses interdites), `METRIQUES_VITRINE.md` (chiffres datés uniquement).

---

## 0. Pourquoi ce kit existe

Un cycle grand compte se perd sur trois choses, jamais sur le produit : on parle au mauvais interlocuteur, on promet ce qu'on n'a pas, et on n'a pas de réponse préparée à la question de sécurité.

Ce kit tient en une page par sujet, et s'utilise tel quel en rendez-vous. Il ne remplace pas la démonstration : il l'encadre.

**Règle d'or :** on entre par le **DAF**, jamais par la DRH. Dans un groupe, la DRH gère les personnes ; le DAF paie et subit les erreurs.

---

## 1. Le cycle de décision — les 7 étapes

| # | Étape | Durée | Interlocuteur principal | Ce qu'il faut obtenir pour passer à la suivante |
|---|---|---|---|---|
| 1 | **Prise de contact** | 1-2 sem. | DAF | Un rendez-vous de 45 minutes |
| 2 | **Découverte** | 2-3 sem. | DAF + DRH | La cartographie des entités, conventions et systèmes |
| 3 | **Démonstration** | 1 sem. | DAF + DRH + DSI | L'accord pour une revue technique |
| 4 | **Revue technique** | 3-6 sem. | DSI + RSSI | La validation de l'architecture et de la sécurité |
| 5 | **Pilote** | 12 sem. | DAF + DSI | Les 3 critères de succès atteints |
| 6 | **Négociation** | 4-8 sem. | Achats + DAF | La signature |
| 7 | **Déploiement** | 8-16 sem. | DSI + DRH | L'extension aux autres entités |

**Durée totale : 6 à 12 mois.** Toute promesse de « mise en route en 30 minutes » est un mensonge qui se paie à l'étape 4.

**Point de non-retour :** l'étape 4. Si le DSI n'est pas convaincu, le reste ne sert à rien. C'est pourquoi le dossier technique se prépare **avant** la première démonstration.

---

## 2. Les interlocuteurs — qui veut quoi, qui bloque quoi

| Rôle | Ce qu'il veut entendre | Ce qu'il peut bloquer | Argument à lui servir |
|---|---|---|---|
| **DAF** | « Votre paie consolidée sort sans tableur, et elle est juste. » | Le budget | Le coût d'une paie fausse : redressement, heures payées à tort, temps de correction |
| **DRH groupe** | « Vos équipes RH arrêtent la ressaisie. » | L'adoption | Le dossier employé saisi une fois, le self-service, les congés |
| **DSI** | « Ça s'intègre, ça s'héberge chez nous, c'est documenté. » | **Techniquement, tout** | API versionnée, 994 endpoints, Docker, auto-hébergement |
| **RSSI** | « Voici l'architecture, le chiffrement, les rôles, l'audit. » | La revue de sécurité | Le dossier de sécurité, avec ses écarts assumés |
| **Achats** | « Le prix est défendable, le contrat est clair. » | La signature | Fourchette 30-80 k€/an, contrat type, SLA |
| **DG / famille** | « Le risque est maîtrisé. » | Tout, en dernier recours | Un pilote borné, une sortie possible |

**Erreur classique :** convaincre le DAF puis découvrir à l'étape 4 que le RSSI exige SAML. C'est pourquoi l'écart SAML s'annonce **dès l'étape 2**, pas à l'étape 4.

---

## 3. Argumentaire — les trois gains, dans l'ordre où un DAF les entend

### 3.1 « Vous arrêtez de consolider à la main »

Aujourd'hui : une paie par entité, un tableur de consolidation, une ressaisie mensuelle, et un total que personne ne vérifie vraiment.

Demain : une paie par entité dans le même moteur, un total groupe disponible sans manipulation.

**Ce que ça vaut :** 2 à 5 jours-homme par mois dans un groupe de 6 entités, et la fin d'une source d'erreur qui ne se voit qu'à l'audit.

### 3.2 « Vos heures deviennent opposables »

Aujourd'hui : des badgeuses dont les données sortent en fichier texte, des feuilles papier sur les chantiers, et « on va vérifier » comme réponse aux contestations.

Demain : une heure rattachée à une personne, un site, un horodatage — y compris en coupure réseau.

**Ce que ça vaut :** la fin des heures payées sans preuve, et une position défendable en cas de contentieux.

### 3.3 « Vous passez une revue de sécurité sans improviser »

Aujourd'hui : un questionnaire de 60 questions qui remonte à la direction, et des réponses bricolées.

Demain : un dossier de sécurité, une architecture documentée, un hébergement que vous choisissez.

**Ce que ça vaut :** 3 à 6 semaines gagnées sur le cycle, et un DSI qui devient un allié au lieu d'un obstacle.

### 3.4 Le pitch en 30 secondes (à savoir par cœur)

> « Vous avez six sociétés, trois pays, et la paie consolidée se fait sur un tableur. Nous mettons la paie de chaque entité et le pointage de chaque site dans un seul moteur : une paie par société, un total groupe, et des heures qu'on peut prouver. On commence par une entité, douze semaines, avec trois critères écrits. Si ça ne marche pas, vous ne signez pas. »

---

## 4. Les chiffres à utiliser — et ceux à ne pas utiliser

### 4.1 Chiffres vérifiés (utilisables tels quels)

| Fait | Valeur | Vérifié le |
|---|---|---|
| Run de paie DZ | 9 bulletins, 842 059,10 DZD brut, 626 085,54 DZD net | 2026-11-27 |
| Coût employeur du run DZ | 1 060 994,47 DZD | 2026-11-27 |
| Bulletin PDF généré | 879 730 octets | 2026-11-27 |
| Run de paie Maroc | 6 bulletins, 53 614,27 MAD brut | 2026-11-27 |
| Moteurs pays résolus en exécution | 21 pays | 2026-11-27 |
| Modules API | 42 | 2026-11-28 |
| Endpoints | 994 | 2026-11-28 |
| Licence | MIT | 2026-09-20 |

### 4.2 Chiffres interdits

- « X clients », « X utilisateurs », « X entreprises nous font confiance » — **aucune référence client publiable**.
- « Conformité validée », « certifié », « 100 % conforme » — référentiels en statut `pilot`.
- « SSO SAML », « SCIM » — non implémentés (voir §6).
- « Testé à 10 000 utilisateurs » — aucun test de charge publié.
- Tout prix par salarié présenté comme ferme.

---

## 5. Script du premier rendez-vous (45 minutes)

### 5.1 Avant le rendez-vous — 15 minutes de préparation

1. Combien d'entités juridiques ? (registre de commerce, site du groupe, LinkedIn)
2. Combien de pays ? (implantations déclarées)
3. Quel ERP ? (offres d'emploi, communiqués)
4. Combien de salariés, et quelle proportion en horaire ? (rapport annuel, presse)
5. Qui est le DAF, et depuis quand ? (LinkedIn)

**But :** ne poser aucune question dont la réponse est publique. Un DAF perd patience en 3 minutes face à un fournisseur mal préparé.

### 5.2 Déroulé

| Min | Ce qu'on fait | Ce qu'on dit |
|---|---|---|
| 0-3 | Cadrage | « Je ne vais pas vous présenter un logiciel. Je vais vous poser trois questions, et si la troisième ne vous parle pas, on s'arrête là. » |
| 3-12 | Découverte | « Combien de systèmes pour sortir votre masse salariale consolidée ? » · « Qui consolide, et avec quoi ? » · « Combien de temps par mois ? » |
| 12-18 | Reformulation | « Si je comprends : six entités, trois systèmes, un tableur, et deux à trois jours par mois. C'est ça ? » |
| 18-28 | La boucle fermée | Pointage → heures → paie → bulletin → consolidation. Sur un cas réel, pas une démo générique. |
| 28-35 | Les écarts, annoncés | « Trois choses ne sont pas encore prêtes : SAML, SCIM, et l'audit immuable généralisé. Voilà notre calendrier. » |
| 35-42 | Le pilote | « Une entité, douze semaines, trois critères écrits, 8 à 15 k€, déduits si vous signez. » |
| 42-45 | La suite | « Je vous envoie le dossier technique. Qui, chez vous, doit le lire ? » |

**La minute 28-35 est la plus importante du rendez-vous.** Annoncer ses écarts avant que le client ne les découvre est ce qui transforme un fournisseur en partenaire.

### 5.3 Après le rendez-vous — dans les 24 heures

- Envoyer le dossier technique (architecture, sécurité, hébergement).
- Envoyer le contrat de pilote type, avec les trois critères.
- Nommer le prochain interlocuteur (DSI ou RSSI) et proposer une date.
- Consigner dans le CRM : entités, pays, ERP, nombre de salariés, blocages identifiés.

---

## 6. Objections — et les réponses

**« Vous êtes trop petits. »**
« C'est vrai, nous n'avons pas de référence dans votre secteur. C'est pourquoi nous ne demandons pas un déploiement groupe : une entité, douze semaines, trois critères. Si ça échoue, vous avez perdu douze semaines. »

**« Pourquoi pas Workday / SAP ? »**
« Ils ne font pas la paie algérienne de façon native, et ils coûtent 95 à 180 $ par salarié et par an avec 9 à 18 mois d'intégration. Si vous êtes déjà standardisé sur SAP, nous ne sommes pas le bon choix — et je vous le dis maintenant. »

**« Avez-vous SOC 2 / ISO 27001 ? »**
« Non. Nous avons un dossier de sécurité complet, un hébergement que vous choisissez, et une architecture documentée. La certification est au programme, pas au catalogue. »

**« Faites-vous du SAML ? »**
« Pas encore. OIDC est opérationnel — Entra ID, Okta, Google. SAML est en cours, deux à trois semaines. Si c'est bloquant pour votre revue, dites-le maintenant et nous planifions le pilote après. »

**« Et le multi-entités ? »**
« C'est le chantier en cours, un à trois mois. Aujourd'hui le produit gère une entreprise par tenant. Nous ne vendons pas la consolidation groupe avant qu'elle soit livrée. »

**« Combien ? »**
« Un groupe de 1 000 salariés sur 6 entités : 30 000 à 80 000 € par an selon le périmètre. Le pilote est à 8 à 15 k€, déduits si vous signez. »

**« On a déjà un ERP. »**
« Nous ne le remplaçons pas. Nous remplaçons la paie et le pointage, et nous exportons vers votre comptabilité. L'intégration est un point du pilote. »

**« Pourquoi un pilote payant ? »**
« Parce qu'un POC gratuit ne se termine jamais. Un pilote payant a une date de fin, des critères écrits, et une décision à la sortie — pour vous comme pour nous. »

---

## 7. Onboarding du pilote — checklist 12 semaines

### Semaines 1-2 — Cadrage
- [ ] Entité pilote et site pilote désignés par le client.
- [ ] Périmètre écrit : nombre de salariés, conventions, variables de paie.
- [ ] Les 3 critères de succès signés par le DAF.
- [ ] Accès : environnement, réseau, matériel de pointage.

### Semaines 3-5 — Données et pointage
- [ ] Import du dossier employé (fichier client, anonymisé si besoin).
- [ ] Paramétrage de la convention et du pays.
- [ ] Installation du pointage (mobile, GPS ou borne) sur le site.
- [ ] Test de coupure réseau et resynchronisation.

### Semaines 6-9 — Première paie réelle
- [ ] Run de paie en parallèle de l'existant (double calcul).
- [ ] Écart expliqué ligne par ligne avec le comptable du client.
- [ ] Validation par le conseil fiscal local si le client l'exige.
- [ ] Bulletin PDF et exports comptables vérifiés.

### Semaines 10-12 — Consolidation et décision
- [ ] Deuxième et troisième paie sans erreur imputable à l'outil.
- [ ] Revue technique finale avec le DSI.
- [ ] Bilan écrit contre les 3 critères.
- [ ] Proposition de contrat groupe, ou décision d'arrêt documentée.

### Critère de succès du pilote — les 3, écrits d'avance
1. **Trois paies consécutives** sans erreur imputable à l'outil.
2. **100 % des heures du site** prouvées et rattachées à une personne.
3. **Validation du DSI** sur l'authentification et l'audit, dans son environnement.

---

## 8. Ce qu'il ne faut jamais dire

| Interdit | Pourquoi |
|---|---|
| « SSO SAML » | Non implémenté — `SSOService.php` refuse la connexion SAML |
| « SCIM » | Aucune implémentation |
| « Certifié SOC 2 / ISO 27001 » | Aucune certification détenue |
| « Conformité légale validée » | Référentiels en statut `pilot` |
| « Multi-entités disponible » | Chantier en cours (mois 1-3) |
| « Testé à 10 000 utilisateurs » | Aucun test de charge publié |
| « Mise en route en 30 minutes » | Vrai pour une PME, faux pour un groupe |
| « Nous avons X clients » | Aucune référence publiable |

**Une seule de ces phrases prononcée en rendez-vous peut coûter le compte — et la réputation sur le marché, qui est petit.**

---

## 9. Résumé en une page (à imprimer)

**Cible :** groupe privé multi-entités, 500-5 000 salariés, 4-15 entités, main-d'œuvre horaire.

**Message :** « Une seule paie pour toutes vos entités, et la preuve des heures de chaque site. »

**Entrée :** le DAF. **Décision :** DAF + DSI + RSSI + achats.

**Offre :** pilote payant, 12 semaines, une entité, 150-400 salariés, 8-15 k€.

**Critères de succès :** 3 paies sans erreur · 100 % des heures prouvées · validation DSI.

**Écarts annoncés d'emblée :** SAML, SCIM, audit immuable généralisé, multi-entités, SOC 2.

**Prix cible :** 30-80 k€/an pour 1 000 salariés sur 6 entités.

**Durée du cycle :** 6-12 mois. Ne jamais promettre moins.
