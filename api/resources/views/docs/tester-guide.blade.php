<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Guide testeur - Leopardo RH</title>
    <style>
        :root { --bg:#f6f7fb; --card:#fff; --text:#0f172a; --muted:#475569; --primary:#0f766e; --border:#dbe2ea; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font:16px/1.55 "Segoe UI", system-ui, sans-serif; }
        header { background:#0f172a; color:white; padding:32px 24px; }
        main { max-width:1120px; margin:0 auto; padding:28px 20px 48px; }
        h1,h2,h3 { margin:0 0 10px; line-height:1.2; }
        p { margin:0 0 12px; color:var(--muted); }
        a { color:var(--primary); font-weight:700; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:14px; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:18px; box-shadow:0 8px 20px rgba(15,23,42,.06); }
        .steps { margin:12px 0 0; padding-left:20px; color:#334155; }
        .steps li { margin:8px 0; }
        code { background:#e2e8f0; border-radius:6px; padding:2px 6px; }
        .badge { display:inline-block; margin:4px 6px 8px 0; border-radius:999px; background:#ccfbf1; color:#115e59; padding:4px 10px; font-size:12px; font-weight:800; }
        .warn { border-color:#fde68a; background:#fffbeb; }
    </style>
</head>
<body>
<header>
    <h1>Guide testeur Leopardo RH</h1>
    <p style="color:#cbd5e1">Parcours de validation pour le portail client web, l'application mobile, l'administration plateforme et les API.</p>
</header>
<main>
    <section class="grid">
        <article class="card">
            <h2>1. Portail client web</h2>
            <span class="badge">Managers</span><span class="badge">Employes</span>
            <ol class="steps">
                <li>Ouvrir la vitrine puis <code>/auth/login</code>.</li>
                <li>Cliquer <strong>Acces Demo</strong> et choisir un profil principal, RH, comptable, superviseur ou employe.</li>
                <li>Verifier la redirection dashboard, les modules visibles selon le role, le bouton notifications et les preferences.</li>
                <li>Tester une erreur volontaire avec un mauvais mot de passe : un message doit apparaitre sans spinner infini.</li>
            </ol>
        </article>
        <article class="card">
            <h2>2. Application mobile</h2>
            <span class="badge">Terrain</span><span class="badge">Notifications</span>
            <ol class="steps">
                <li>Lancer l'app avec l'URL API production ou staging.</li>
                <li>Sur l'ecran login employe, utiliser <strong>Acces Demo</strong>. La selection lance la connexion automatiquement.</li>
                <li>Verifier Home, Pointage, Modules, Notifications et Reglages.</li>
                <li>Dans Notifications, tirer pour actualiser, ouvrir une notification non lue et verifier qu'elle passe en lue.</li>
            </ol>
        </article>
        <article class="card">
            <h2>3. Admin plateforme</h2>
            <span class="badge">Super admin</span>
            <ol class="steps">
                <li>Ouvrir <code>/platform/login</code> ou le dashboard admin deploye.</li>
                <li>Utiliser le compte super admin demo si l'environnement l'autorise.</li>
                <li>Verifier plans, entreprises, sante tenant, demandes clients et configuration modules.</li>
                <li>Verifier qu'un compte RH/employe ne peut pas entrer dans cet espace.</li>
            </ol>
        </article>
        <article class="card">
            <h2>4. API et contrats</h2>
            <span class="badge">Developpeurs</span><span class="badge">QA</span>
            <ol class="steps">
                <li>Ouvrir <a href="/api-explorer">API Explorer</a>.</li>
                <li>Charger les comptes demo, choisir un profil et se connecter.</li>
                <li>Lancer <code>/auth/me</code>, <code>/notifications</code>, <code>/launch-readiness</code> et les endpoints role-specifiques.</li>
                <li>Comparer les schemas avec <a href="/docs">OpenAPI</a>.</li>
            </ol>
        </article>
    </section>

    <section class="card warn" style="margin-top:18px">
        <h2>Critere d'acceptation rapide</h2>
        <p>Un test est valide seulement si l'utilisateur voit un succes ou une erreur lisible. Aucun bouton de connexion, notification ou appel API ne doit tourner indefiniment.</p>
        <p>Les comptes demo utilisent le mot de passe <code>password123</code> et doivent exister via le seeder demo de l'environnement cible.</p>
    </section>
</main>
</body>
</html>
