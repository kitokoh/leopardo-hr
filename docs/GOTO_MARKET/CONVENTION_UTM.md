# Convention UTM — Leopardo (registre commun, source de vérité)

> Tout lien sortant publié par Leopardo (posts, fiches annuaires, articles republiés,
> signatures, kits `platform-submissions/`) pointe vers la vitrine **avec UTM conformes à
> ce document**. Canaux et valeurs canoniques par canal : `REGISTRE_CANAUX.md`.
> Issue de référence : #7877. Stratégie : `STRATEGIE_ACQUISITION_2026.md` §10.

## 1. Principes

1. **kebab-case strict** : minuscules ASCII, mots séparés par `-`. Jamais d'espaces,
   de majuscules, d'accents, de `_` dans les *valeurs* (les *clés* `utm_source` etc.
   gardent leur underscore standard).
2. **Vocabulaire fermé** : `utm_source` et `utm_medium` ne prennent que les valeurs
   listées ci-dessous. Une nouvelle valeur = une PR sur ce fichier + une ligne dans
   `REGISTRE_CANAUX.md`. Pas de valeur inventée à la volée.
3. **Stabilité** : une valeur publiée ne change jamais (sinon les séries analytics
   cassent). En cas d'erreur, on corrige les liens mais on documente l'ancienne valeur
   dans « Historique » en bas de ce fichier.
4. **3 paramètres obligatoires** (`utm_source`, `utm_medium`, `utm_campaign`) ;
   `utm_content` optionnel ; `utm_term` **non utilisé** (réservé au paid, hors périmètre).
5. Les UTM ne s'appliquent qu'aux liens **entrants vers nos propriétés** (vitrine, docs).
   Jamais d'UTM sur les liens internes de la vitrine (ça écraserait la source réelle).

## 2. Paramètres

### `utm_source` — d'où vient le clic (la plateforme)

Vocabulaire fermé (aligné sur `REGISTRE_CANAUX.md`) :

| Famille | Valeurs autorisées |
|---|---|
| Social | `linkedin`, `x`, `youtube` |
| Communautés | `facebook`, `whatsapp`, `telegram`, `reddit`, `hn` |
| Annuaires open source | `alternativeto`, `openalternative`, `libhunt`, `awesome-selfhosted`, `selfhst`, `stackshare` |
| Listings B2B / startup | `capterra`, `getapp`, `g2`, `producthunt`, `crunchbase`, `f6s`, `vc4a`, `wellfound`, `betalist`, `saashub` |
| Contenu republié | `devto`, `hashnode` |
| Dev / code | `github` |
| E-mail sortant | `newsletter` |

### `utm_medium` — la nature du canal

Vocabulaire fermé (6 valeurs) :

| Valeur | Usage |
|---|---|
| `social` | Réseaux sociaux (posts, threads, vidéos) |
| `community` | Forums, groupes, communautés (HN, Reddit, groupes FB/WhatsApp/Telegram) |
| `listing` | Fiches annuaires, marketplaces, plateformes startup |
| `referral` | Liens depuis contenus tiers ou republiés (dev.to, README GitHub, articles invités) |
| `press` | Presse et médias (Disrupt Africa, TechCabal, Webrazzi…) |
| `email` | E-mails sortants (newsletter, pitchs presse avec lien traqué) |

### `utm_campaign` — la campagne ou l'initiative

Kebab-case, format `<theme>-<annee>` ou `<evenement>-<annee-mois>` :

- Campagne fil rouge par défaut : `growth-2026`.
- Lancements one-shot : `launch-ph-2026-11`, `show-hn-2026-11` (date réelle à la
  planification).
- Clusters de contenu : `paie-dz-2026`, `paie-sn-2026`, `selfhost-2026`.
- Campagnes d'avis : `avis-capterra-2026`.

### `utm_content` — optionnel, distingue les variantes

Kebab-case libre mais descriptif, pour différencier plusieurs liens d'une même
source/campagne : emplacement (`bio`, `post`, `article-footer`, `fiche-cta`,
`readme-badge`) ou variante créative (`video-pointage`, `thread-multi-tenant`).

## 3. Exemples canoniques par canal

Base vitrine (domaine live actuel — cf. `docs/ops/DOMAINS.md`) :
`https://gestionemployer-backend.vercel.app`

| Canal | Lien type |
|---|---|
| LinkedIn (post) | `...?utm_source=linkedin&utm_medium=social&utm_campaign=growth-2026&utm_content=post` |
| X (thread) | `...?utm_source=x&utm_medium=social&utm_campaign=growth-2026&utm_content=thread-multi-tenant` |
| Groupe WhatsApp DZ | `...?utm_source=whatsapp&utm_medium=community&utm_campaign=growth-2026` |
| Groupe Facebook SN | `...?utm_source=facebook&utm_medium=community&utm_campaign=growth-2026` |
| AlternativeTo (fiche) | `...?utm_source=alternativeto&utm_medium=listing&utm_campaign=growth-2026&utm_content=fiche-cta` |
| Capterra | `...?utm_source=capterra&utm_medium=listing&utm_campaign=growth-2026` |
| Product Hunt (launch) | `...?utm_source=producthunt&utm_medium=listing&utm_campaign=launch-ph-2026-11` |
| Show HN | `...?utm_source=hn&utm_medium=community&utm_campaign=show-hn-2026-11` |
| dev.to (footer article) | `...?utm_source=devto&utm_medium=referral&utm_campaign=selfhost-2026&utm_content=article-footer` |
| README GitHub (badge) | `...?utm_source=github&utm_medium=referral&utm_campaign=growth-2026&utm_content=readme-badge` |
| Pitch presse (TechCabal) | `...?utm_source=newsletter&utm_medium=email&utm_campaign=growth-2026` *(lien traqué dans l'e-mail)* — l'article publié utilise `utm_medium=press` si le média accepte le lien traqué |

**Cas particulier — SEO organique (vitrine `/alternatives`, blog)** : pas d'UTM. Le
trafic organique est mesuré via Google Search Console + analytics (canal `organic`).
Taguer du trafic organique casserait l'attribution.

## 4. Anti-patterns (interdits)

| ❌ Interdit | ✅ Correct | Pourquoi |
|---|---|---|
| `utm_source=LinkedIn`, `utm_source=Twitter` | `utm_source=linkedin`, `utm_source=x` | Casse (analytics sensible à la casse → séries dupliquées) ; `x` est la valeur canonique |
| `utm_source=twitter` | `utm_source=x` | Une seule valeur par plateforme |
| `utm_medium=social-media`, `utm_medium=post` | `utm_medium=social` | Vocabulaire fermé — le format va dans `utm_content` |
| `utm_source=facebook&utm_medium=social` pour un groupe | `utm_medium=community` | Les groupes FB/WhatsApp sont des communautés, pas du social organique de page |
| `utm_campaign=test`, `utm_campaign=campagne1` | `growth-2026`, `launch-ph-2026-11` | Nom descriptif + daté, sinon inexploitable dans 6 mois |
| `utm_term=...` | *(omettre)* | Réservé au paid — hors périmètre 2026 |
| UTM sur liens internes vitrine → vitrine | Lien nu | Écrase la vraie source de session |
| Accents/espaces/underscores dans les valeurs (`paie_dz`, `paie dz`) | `paie-dz-2026` | kebab-case strict |
| Nouvelle valeur `utm_source` inventée dans un post | PR sur ce fichier d'abord | Vocabulaire fermé |
| Raccourcisseur d'URL qui strippe les paramètres | Lien complet ou raccourcisseur qui les préserve | Perte d'attribution silencieuse |

## 5. Procédure d'application aux liens existants

À exécuter une fois (chantier de mise en conformité), puis à chaque publication :

1. **Inventaire** : lister tous les liens sortants publiés vers la vitrine/docs —
   sources : `REGISTRE_CANAUX.md` (registre des fiches), kits
   `platform-submissions/*.md`, `SOCIAL_MEDIA_PITCHES.md`, README du repo, bios de
   profils sociaux.
2. **Audit** : pour chaque lien, vérifier la présence et la conformité des 3 paramètres
   obligatoires (vocabulaire fermé, kebab-case). Dans le repo :
   `rg -o 'utm_[a-z]+=[^&"\s)]+' --no-filename | sort | uniq -c` puis comparer aux
   tableaux §2.
3. **Correction** :
   - Liens éditables (fiches annuaires, bios, README, kits) → remplacer par le lien
     conforme immédiatement.
   - Liens non éditables (vieux posts sociaux) → ne pas retoucher ; noter la valeur
     historique dans « Historique » ci-dessous pour l'interprétation analytics.
4. **Traçabilité** : chaque lien corrigé/publié → mettre à jour la ligne correspondante
   dans `REGISTRE_CANAUX.md` (registre des fiches).
5. **Garde à la publication** : les kits `platform-submissions/` doivent inclure le lien
   UTM final prêt à copier ; relecture UTM incluse dans la checklist de publication
   (validation fondateur).

## 6. Historique des valeurs (migrations)

| Ancienne valeur | Nouvelle valeur | Depuis | Note |
|---|---|---|---|
| *(aucune migration à ce jour)* | | | |

---

*Toute évolution de ce fichier passe par une PR `docs:` (comme `MESSAGE.md`).*
