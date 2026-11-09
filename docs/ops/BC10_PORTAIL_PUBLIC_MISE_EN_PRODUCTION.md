# Mise en production — Portail public d'offres d'emploi (BC-10)

> Ce document liste **tout ce qui doit être fait côté exploitation** pour que le
> portail public d'offres d'emploi soit réellement exploitable. Il ne décrit pas
> le code (voir `docs/specifications/MODULE_RECRUITMENT_PUBLIC_PORTAL.md`) mais
> les **gestes d'exploitation** : variables à définir, services à déployer,
> accréditations à obtenir, tâches à activer.
>
> **Tous les noms de variables ci-dessous sont ceux réellement lus par le code**
> (`api/config/recruitment.php`, `api/config/security.php`) et présents dans
> `api/.env.example`. Aucun n'est inventé.

---

## 1. Variables d'environnement

### 1.1 Portail public et limitation de débit

| Variable | Rôle | Défaut |
|---|---|---|
| `RATE_LIMIT_PUBLIC_CAREERS_PER_MINUTE` | Requêtes/minute par IP sur la liste et le détail des offres | `60` |
| `RATE_LIMIT_PUBLIC_CAREERS_APPLY_PER_MINUTE` | Requêtes/minute par IP sur le **dépôt de candidature** | `5` |

> Le second est volontairement bien plus bas : c'est la seule route publique qui
> **écrit** et qui accepte un fichier. C'est aussi la cible naturelle d'un spam
> de candidatures.

> ⚠️ **Il n'existe pas d'interrupteur `*_PORTAL_ENABLED`.** Le portail est actif
> dès que le déploiement l'est. Pour le rendre invisible, il faut retirer les
> routes ou filtrer en amont (WAF / reverse proxy) — ne pas chercher une
> variable qui n'existe pas.

### 1.2 Conservation des candidatures (RGPD)

| Variable | Rôle | Défaut |
|---|---|---|
| `RECRUITMENT_APPLICANT_RETENTION_DAYS` | Durée de conservation, **en jours**, à compter du consentement | `730` (24 mois) |

> Cette valeur renseigne `applicants.retention_until` à la candidature. C'est
> elle qui rend la purge calculable **sans interprétation** : la tâche planifiée
> ne fait que comparer une date. Configurable parce qu'une durée codée en dur
> serait impossible à ajuster par pays.

### 1.3 Synchronisation des agrégateurs

| Variable | Rôle | Défaut |
|---|---|---|
| `RECRUITMENT_SYNC_MAX_OFFERS` | Nombre maximal d'offres importées par synchronisation | `500` |
| `RECRUITMENT_FEED_CACHE_MINUTES` | Durée de cache du flux XML (minutes) | `15` |

> `RECRUITMENT_SYNC_MAX_OFFERS` borne la mémoire et la durée du job : un flux de
> 50 000 offres ne doit pas faire tomber le worker. Le flux XML est lu par les
> robots de Google Jobs / Indeed — il doit rester frais sans être recalculé à
> chaque requête.

### 1.4 Analyse antivirus des CV

| Variable | Rôle | Défaut |
|---|---|---|
| `RECRUITMENT_AV_DRIVER` | Moteur d'analyse : `clamav` ou `none` | `none` |
| `RECRUITMENT_AV_POLICY` | Sort d'un CV infecté : `reject` ou `quarantine` | `reject` |
| `RECRUITMENT_AV_HOST` | Hôte du démon clamd | `127.0.0.1` |
| `RECRUITMENT_AV_PORT` | Port clamd | `3310` |
| `RECRUITMENT_AV_TIMEOUT` | Délai max d'analyse (secondes) | `10` |

> ⚠️ **Trois états, pas deux.** Le service rend `clean`, `infected` **ou**
> `unavailable`. Un scanner injoignable ou non configuré rend `unavailable` —
> **jamais** `clean`. Un driver inconnu retombe sur `none`, pas sur `clean` :
> une faute de frappe dans `RECRUITMENT_AV_DRIVER` ne doit pas désactiver la
> sécurité en prétendant que les fichiers sont sains.
>
> **Un scanner indisponible n'est jamais un motif de rejet** : une panne
> d'infrastructure ne doit pas empêcher un candidat de postuler. Le verdict est
> persisté, donc auditable.
>
> **Conséquence opérationnelle** : tant que `RECRUITMENT_AV_DRIVER=none`, les CV
> sont acceptés et marqués `unavailable`. C'est un choix assumé (ne pas bloquer
> le recrutement), mais il doit être **conscient**. Déployer clamd est la seule
> façon d'obtenir un verdict `clean` réel.

### 1.5 Fournisseurs d'offres externes (agrégateurs)

Chaque fournisseur est activé par sa propre paire de variables. **Un
fournisseur sans clé reste inactif et le dit** — il ne fait jamais semblant de
remonter des offres.

| Fournisseur | Variables | Accréditation requise |
|---|---|---|
| Adzuna | `RECRUITMENT_JOBBOARD_ADZUNA_APP_ID`, `RECRUITMENT_JOBBOARD_ADZUNA_APP_KEY` | Inscription développeur (self-service) |
| Jooble | `RECRUITMENT_JOBBOARD_JOOBLE_API_KEY` | Clé API (self-service) |
| Indeed | `RECRUITMENT_JOBBOARD_INDEED_PUBLISHER_ID`, `RECRUITMENT_JOBBOARD_INDEED_API_KEY` | Contrat partenaire Indeed |
| LinkedIn | `RECRUITMENT_JOBBOARD_LINKEDIN_CLIENT_ID`, `RECRUITMENT_JOBBOARD_LINKEDIN_CLIENT_SECRET`, `RECRUITMENT_JOBBOARD_LINKEDIN_ACCESS_TOKEN` | LinkedIn Talent Solutions |
| Glassdoor | `RECRUITMENT_JOBBOARD_GLASSDOOR_PARTNER_ID`, `RECRUITMENT_JOBBOARD_GLASSDOOR_PARTNER_KEY` | Programme partenaire Glassdoor |
| Flux personnalisé | *(aucune variable)* — `feed_url` + `api_key` saisis dans l'écran super admin | **Aucune** |

> **Ces variables sont un simple repli.** La saisie en base **prime** sur elles
> (`JobBoardCredentialResolver`) : un opérateur qui a explicitement configuré un
> connecteur dans l'écran super admin ne doit pas voir ses identifiants
> remplacés par un déploiement.
>
> Elles sont lues via `config()` et **non** `env()` : `env()` hors du répertoire
> `config/` renvoie `null` dès que la configuration est mise en cache
> (`php artisan config:cache`), ce qui **désactiverait silencieusement tous les
> connecteurs en production**.
>
> **Seul `custom_feed` fonctionne sans partenariat.** Les cinq autres exigent un
> contrat d'accès API. Le code des clients est écrit et testé avec des réponses
> HTTP simulées ; il ne reste qu'à fournir les clés.
>
> Les secrets sont **chiffrés au repos** et ne sont **jamais** renvoyés par
> l'API — l'écran super admin n'affiche qu'un indicateur « configuré / non
> configuré ».

### 1.6 Front web (portail public)

| Variable | Rôle |
|---|---|
| `API_PROXY_TARGET` | Cible du proxy Next `/api/v1/*` (prioritaire) |
| `BACKEND_API_URL` | Repli si `API_PROXY_TARGET` absent |
| `NEXT_PUBLIC_API_URL` | Dernier repli |

> Le portail public appelle l'API **via le proxy Next**, jamais en direct : la
> cible est lue au **runtime**, donc une bascule de stack ne demande qu'une
> variable Vercel + redéploiement du web.

---

## 2. Démon antivirus à déployer

1. Déployer un service clamd joignable depuis l'API (réseau privé).
2. Renseigner `RECRUITMENT_AV_DRIVER=clamav`, `RECRUITMENT_AV_HOST`,
   `RECRUITMENT_AV_PORT`.
3. Vérifier le verdict sur un fichier de test EICAR :

```bash
curl -s -o /tmp/eicar.txt https://secure.eicar.org/eicar.com.txt
# Déposer ce fichier via le formulaire de candidature, puis :
#   SELECT resume_scan_status, resume_scan_engine
#     FROM applicants ORDER BY created_at DESC LIMIT 1;
# Attendu : 'infected' (et l'engine 'clamav')
```

4. Surveiller le taux de `unavailable` : s'il est élevé, clamd est injoignable
   et **aucun** CV n'est réellement analysé.

---

## 3. Accréditations partenaires à obtenir

| Fournisseur | Démarche | Délai typique |
|---|---|---|
| Adzuna | Inscription développeur (self-service) | Immédiat |
| Jooble | Clé API (self-service) | Immédiat |
| Indeed | Contrat partenaire (Job Sync API) | Semaines |
| LinkedIn | LinkedIn Talent Solutions | Semaines |
| Glassdoor | Programme partenaire | Semaines |

> **Recommandation** : commencer par **Adzuna** et **Jooble** (self-service,
> immédiats) pour valider la chaîne d'agrégation de bout en bout, puis ouvrir
> les négociations Indeed/LinkedIn/Glassdoor en parallèle.

---

## 4. Tâche planifiée de purge RGPD

**Elle est déjà planifiée dans le code** (`api/routes/console.php`) :

```php
Schedule::command('recruitment:purge-expired-applications')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();
```

Quotidienne et non horaire : la purge n'a aucune contrainte de fraîcheur (une
candidature échue depuis un jour de plus ne change rien), et un passage nocturne
évite de concurrencer les écritures de candidatures en journée.

**Ce qui reste à faire côté exploitation : s'assurer que le scheduler tourne.**

```bash
# Cron système (ou supervisor) — indispensable, sinon la purge ne s'exécute jamais
* * * * * cd /var/www/leopardo && php artisan schedule:run >> /dev/null 2>&1
```

### Ordre d'opérations — important

Le **fichier CV est supprimé avant la ligne**. Supprimer la ligne en laissant le
document personnel sur le disque serait le pire des deux mondes : la personne
croit ses données effacées, le document reste lisible. Et si le fichier n'est
**pas** supprimable, la ligne est **conservée** pour que la prochaine exécution
retente, au lieu d'orpheliner le CV.

### Vérification

```bash
# Liste ce qui SERAIT supprimé, sans rien toucher — à lancer avant la 1re exécution
php artisan recruitment:purge-expired-applications --dry-run

# Marge de sécurité optionnelle (jours après l'échéance)
php artisan recruitment:purge-expired-applications --grace-days=7
```

### Journalisation

Chaque exécution journalise le nombre de candidatures purgées, de fichiers
supprimés et d'échecs. **Un échec de suppression de fichier doit alerter** : il
signale un problème de permissions sur le bucket.

---

## 5. Checklist de mise en production

- [ ] `RECRUITMENT_APPLICANT_RETENTION_DAYS=730` (ou la durée retenue)
- [ ] `RATE_LIMIT_PUBLIC_CAREERS_PER_MINUTE` / `..._APPLY_PER_MINUTE` ajustés
- [ ] Démon clamd déployé + `RECRUITMENT_AV_DRIVER=clamav`
- [ ] Test EICAR → verdict `infected`
- [ ] Au moins un fournisseur configuré (Adzuna ou Jooble recommandé)
- [ ] `API_PROXY_TARGET` défini côté Vercel
- [ ] **Scheduler actif** (`schedule:run` en cron) — sinon pas de purge
- [ ] `recruitment:purge-expired-applications --dry-run` exécuté une fois
- [ ] Un tenant réel avec au moins une offre **publiée**
- [ ] `GET /api/v1/public/careers/{slug}` → 200 avec des offres
- [ ] `GET /api/v1/public/careers/{slug}/feed.xml` → XML valide
- [ ] Soumettre une candidature de test → visible dans l'espace RH

---

## 6. Points de vigilance

| Risque | Symptôme | Action |
|---|---|---|
| Aucun CV analysé | `resume_scan_status = 'unavailable'` partout | clamd injoignable ou `driver=none` |
| Aucune offre agrégée | Écran « offres importées » vide | Fournisseur non configuré ou sync en échec |
| Connecteurs muets en prod | Toutes les clés semblent absentes | `config:cache` actif + variables lues via `env()` — vérifier `config/recruitment.php` |
| Fuite entre tenants | Offres d'une autre entreprise visibles | Vérifier la correspondance de pays (stricte) |
| Purge silencieuse | Candidatures anciennes toujours présentes | Scheduler inactif ou permissions bucket |
| Spam de candidatures | Volume anormal sur `/apply` | Baisser `RATE_LIMIT_PUBLIC_CAREERS_APPLY_PER_MINUTE` |
