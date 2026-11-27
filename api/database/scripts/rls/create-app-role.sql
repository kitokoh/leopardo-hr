-- =============================================================================
-- #9896 / #9974 — ADR-9879, étape 2 : rôle applicatif NOSUPERUSER NOBYPASSRLS
-- =============================================================================
--
-- ## Pourquoi ce script existe
--
-- PostgreSQL fait TOUJOURS bypasser RLS aux rôles SUPERUSER et BYPASSRLS,
-- MÊME avec `FORCE ROW LEVEL SECURITY`. Mesure dans ce dépôt : le rôle
-- applicatif (`leopardo`) est SUPERUSER. Les politiques posées par #9879,
-- #9889, #9897 et #9924 existent donc, mais elles ne protègent RIEN tant que
-- l'application se connecte avec ce rôle.
--
-- Vérifier l'état courant avant toute chose :
--
--     php artisan tenant:rls-status
--
-- Sortie 1 = « RLS annoncé mais bypassé ». C'est l'état à corriger ici.
--
-- ## Défaut corrigé par #9974 (important)
--
-- La version précédente écrivait `:'app_password'` À L'INTÉRIEUR d'un bloc
-- `DO $$ … $$`. psql n'interpole PAS ses variables dans une chaîne
-- dollar-quotée : le script échouait en `syntax error at or near ":"`.
-- Autrement dit, la procédure de bascule documentée ne pouvait pas
-- s'exécuter. La création passe désormais par `\gexec`, où `:'app_password'`
-- est bien substitué (il est hors dollar-quotes).
--
-- ## Second défaut corrigé par #9974 (trouvé en CI)
--
-- La documentation demandait `-v app_password="'<secret>'"` — valeur DÉJÀ
-- entourée de quotes — alors que le script applique `%L`, qui quote à son
-- tour. Le mot de passe réellement stocké devenait `'<secret>'`, quotes
-- littérales comprises, et la connexion échouait en
-- `password authentication failed`. Le contrat est désormais : **valeur
-- brute, non quotée** ; `%L` fait le quoting et l'échappement.
--
-- ## Troisième défaut corrigé par #9974 (trouvé en CI parallèle)
--
-- Le nom du rôle était codé en dur. Or un rôle PostgreSQL est CLUSTER-WIDE :
-- deux bases de test sur la même instance partagent le même catalogue de
-- rôles. En CI parallèle (4 workers), un worker supprimait le rôle pendant
-- qu'un autre s'y connectait — `password authentication failed` aléatoire.
-- Le nom est désormais paramétrable (`-v app_role=…`), ce qui rend le script
-- utilisable pour plusieurs environnements sur une même instance et rend les
-- tests hermétiques.
--
-- ## Ce que ce script NE fait pas
--
-- Il ne bascule pas `DB_USERNAME`. C'est une décision d'exploitation, prise
-- après vérification, et réversible en changeant une variable d'environnement
-- (voir « Rollback »). Un script de migration ne doit pas pouvoir couper
-- l'accès à la base d'un déploiement en cours.
--
-- ## Exécution
--
--     psql "$DATABASE_URL" -v app_password='<secret>' -f create-app-role.sql
--
-- Le mot de passe est passé en variable : il ne doit jamais être écrit dans
-- ce fichier, qui est versionné.
--
-- Nom de rôle personnalisé (défaut : `leopardo_app`) :
--
--     psql "$DATABASE_URL" -v app_role=mon_role -v app_password='<secret>' \
--          -f create-app-role.sql
--
-- Idempotent : réexécutable sans erreur.
-- =============================================================================

\set ON_ERROR_STOP on

-- Nom du rôle : `leopardo_app` par défaut, surchargeable par `-v app_role=…`.
-- `\if` évite d'écraser une valeur déjà fournie sur la ligne de commande.
\if :{?app_role}
\else
    \set app_role leopardo_app
\endif

-- -----------------------------------------------------------------------------
-- 0) Garde : il faut un rôle capable de créer/altérer un rôle
-- -----------------------------------------------------------------------------
-- Sans ce garde, l'échec arrive au milieu du script, après des GRANT partiels.
-- Un message clair vaut mieux qu'un `permission denied` sur `CREATE ROLE`.

DO $guard$
BEGIN
    IF NOT (SELECT rolsuper OR rolcreaterole FROM pg_roles WHERE rolname = current_user) THEN
        RAISE EXCEPTION
            'create-app-role.sql : le rôle courant (%) n''a ni SUPERUSER ni CREATEROLE. '
            'Exécuter ce script avec le rôle propriétaire des migrations (DB_USERNAME actuel).',
            current_user;
    END IF;
END
$guard$;

-- -----------------------------------------------------------------------------
-- 1) Le rôle applicatif
-- -----------------------------------------------------------------------------
-- NOSUPERUSER  : sans quoi RLS est bypassé (le point de tout ce script).
-- NOBYPASSRLS  : idem — un rôle non-superuser peut quand même porter BYPASSRLS.
-- NOCREATEDB / NOCREATEROLE : moindre privilège, l'application n'en a pas besoin.
--
-- `\gexec` : la requête PRODUIT la commande, qui est ensuite exécutée. C'est
-- ce qui permet à `:'app_password'` d'être substitué par psql (hors
-- dollar-quotes) tout en gardant l'idempotence. `%I` quote l'identifiant,
-- `%L` quote le littéral.

SELECT format(
    'CREATE ROLE %I LOGIN NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE PASSWORD %L',
    :'app_role',
    :'app_password'
)
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'app_role')
\gexec

-- Réexécution : on RÉAFFIRME les attributs de sécurité. Un rôle créé à la main
-- puis promu SUPERUSER par erreur serait sinon laissé tel quel, et RLS
-- resterait bypassé sans que personne ne le voie.
SELECT format('ALTER ROLE %I NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE', :'app_role')
\gexec

-- Le mot de passe est réaffirmé aussi : une rotation se fait en rejouant ce
-- script avec un nouveau `-v app_password=…`, sans commande ad hoc.
SELECT format('ALTER ROLE %I PASSWORD %L', :'app_role', :'app_password')
\gexec

-- -----------------------------------------------------------------------------
-- 2) Droits sur les schémas et les tables
-- -----------------------------------------------------------------------------
-- `shared_tenants` porte les tables tenant ; `public` porte `companies` et
-- les tables plateforme dont l'application a besoin en lecture.

SELECT format('GRANT USAGE ON SCHEMA shared_tenants, public TO %I', :'app_role')
\gexec

SELECT format('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA shared_tenants TO %I', :'app_role')
\gexec
SELECT format('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO %I', :'app_role')
\gexec

SELECT format('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA shared_tenants TO %I', :'app_role')
\gexec
SELECT format('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO %I', :'app_role')
\gexec

-- Les tables créées PLUS TARD par le rôle propriétaire (celui des migrations)
-- doivent aussi être accessibles. Sans `ALTER DEFAULT PRIVILEGES`, chaque
-- nouvelle migration produirait une table que l'application ne peut pas lire —
-- une panne au déploiement suivant, pas au moment de la bascule.
SELECT format(
    'ALTER DEFAULT PRIVILEGES IN SCHEMA shared_tenants GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO %I',
    :'app_role'
)
\gexec
SELECT format(
    'ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO %I',
    :'app_role'
)
\gexec
SELECT format(
    'ALTER DEFAULT PRIVILEGES IN SCHEMA shared_tenants GRANT USAGE, SELECT ON SEQUENCES TO %I',
    :'app_role'
)
\gexec
SELECT format(
    'ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO %I',
    :'app_role'
)
\gexec

-- -----------------------------------------------------------------------------
-- 3) Vérification AVANT bascule — doit renvoyer 0 ligne
-- -----------------------------------------------------------------------------
-- Si cette requête renvoie des lignes, la bascule rendrait ces tables
-- ILLISIBLES (fail-closed) : la politique existe mais aucune ligne ne
-- satisfait la comparaison. Corriger AVANT de changer DB_USERNAME.

\echo ''
\echo '== Vérification 1 : le rôle ne doit PAS bypasser RLS (0 ligne attendue) =='
SELECT rolname, rolsuper, rolbypassrls
  FROM pg_roles
 WHERE rolname = :'app_role'
   AND (rolsuper OR rolbypassrls);

\echo ''
\echo '== Vérification 2 : tables attendues couvertes (ENABLE + FORCE + policy) =='
SELECT c.relname,
       c.relrowsecurity      AS rls_enabled,
       c.relforcerowsecurity AS rls_forced,
       (SELECT count(*) FROM pg_policy p WHERE p.polrelid = c.oid) AS policies
  FROM pg_class c
  JOIN pg_namespace n ON n.oid = c.relnamespace
 WHERE n.nspname = 'shared_tenants'
   AND c.relname IN ('bulk_payment_claims', 'pay_slips', 'payroll_runs', 'employees')
 ORDER BY c.relname;

-- -----------------------------------------------------------------------------
-- 4) Bascule (hors de ce script)
-- -----------------------------------------------------------------------------
--   DB_USERNAME=leopardo_app
--   DB_PASSWORD=<le secret>
--   puis redéploiement / redémarrage des workers.
--
-- Vérification APRÈS bascule :
--
--   php artisan tenant:rls-status --strict   # doit sortir 0
--   SELECT count(*) FROM shared_tenants.pay_slips;   -- sans tenant : 0
--
-- Le déploiement exécute désormais cette sonde automatiquement
-- (`docker-entrypoint.sh`, gate `RLS_GATE_MODE`, #9974) : en `DEPLOY_TIER=prod`
-- un rôle qui bypass RLS fait ÉCHOUER le déploiement. Render conserve alors la
-- version précédente en ligne — c'est le comportement voulu.
--
-- -----------------------------------------------------------------------------
-- Rollback
-- -----------------------------------------------------------------------------
--   DB_USERNAME=leopardo   (le rôle superuser d'origine)
--   puis redéploiement.
--
-- Aucune migration à défaire : les politiques restent en place et
-- redeviennent simplement inertes. Aucun risque de perte de données.
--
-- Le rôle peut rester créé (il ne gêne pas) ou être supprimé :
--
--   REASSIGN OWNED BY leopardo_app TO leopardo;   -- si des objets lui appartiennent
--   DROP OWNED BY leopardo_app;
--   DROP ROLE leopardo_app;
--
-- -----------------------------------------------------------------------------
-- Point d'attention : propriété des tables
-- -----------------------------------------------------------------------------
-- `leopardo_app` ne doit PAS être propriétaire des tables. S'il l'était,
-- `FORCE ROW LEVEL SECURITY` resterait indispensable (il est posé), et toute
-- table future créée par un autre rôle devrait recevoir les GRANT — d'où
-- `ALTER DEFAULT PRIVILEGES` ci-dessus, qui ne couvre que les objets créés
-- par le rôle qui exécute ce script. Si les migrations tournent sous un autre
-- rôle, rejouer la section 2 sous CE rôle.
