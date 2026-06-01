<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Explorer - Leopardo RH</title>
    <style>
        :root { --bg:#f6f7fb; --card:#fff; --text:#0f172a; --muted:#475569; --primary:#0f766e; --border:#dbe2ea; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font:15px/1.5 "Segoe UI", system-ui, sans-serif; }
        header { background:#0f172a; color:white; padding:28px 22px; }
        main { max-width:1180px; margin:0 auto; padding:22px; display:grid; gap:16px; }
        .grid { display:grid; grid-template-columns:330px 1fr; gap:16px; align-items:start; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:16px; box-shadow:0 8px 20px rgba(15,23,42,.06); }
        label { display:block; margin:10px 0 6px; font-weight:800; color:#334155; }
        select,input,textarea { width:100%; border:1px solid var(--border); border-radius:10px; padding:10px; font:inherit; }
        textarea { min-height:110px; font-family:ui-monospace, SFMono-Regular, Consolas, monospace; }
        button { border:0; border-radius:10px; background:var(--primary); color:white; padding:10px 12px; font-weight:900; cursor:pointer; }
        button.secondary { background:#334155; }
        button:disabled { opacity:.55; cursor:not-allowed; }
        pre { margin:0; white-space:pre-wrap; word-break:break-word; background:#0f172a; color:#e2e8f0; border-radius:12px; padding:14px; min-height:280px; }
        .row { display:flex; gap:8px; flex-wrap:wrap; }
        .developer-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
        .mini { border:1px solid var(--border); border-radius:12px; padding:12px; background:#fbfdff; }
        .mini h3 { margin:0 0 6px; font-size:14px; }
        .pill { display:inline-flex; align-items:center; border:1px solid #99f6e4; background:#ccfbf1; color:#115e59; border-radius:999px; padding:3px 8px; font-size:12px; font-weight:900; }
        .muted { color:var(--muted); }
        @media (max-width: 980px) { .developer-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width: 820px) { .grid { grid-template-columns:1fr; } }
        @media (max-width: 560px) { .developer-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<header>
    <h1>API Explorer Leopardo RH</h1>
    <p class="muted" style="color:#cbd5e1">Connexion demo, token Bearer et requetes pre-remplies pour testeurs et developpeurs.</p>
    <p>
        <a href="/docs" style="color:#99f6e4;font-weight:900">OpenAPI</a>
        <span style="color:#64748b"> / </span>
        <a href="/docs/openapi.yaml" style="color:#99f6e4;font-weight:900">YAML canonique</a>
        <span style="color:#64748b"> / </span>
        <a href="/tester-guide" style="color:#99f6e4;font-weight:900">Guide testeur</a>
    </p>
</header>
<main>
    <section class="card">
        <span class="pill">Developer preview</span>
        <h2>Ecosysteme developpeur</h2>
        <p class="muted">Cette page utilise les memes contrats que les apps mobile employee, manager, platform admin, la vitrine et les kiosques. Les requetes envoient toujours <code>Accept: application/json</code> et, apres login, <code>Authorization: Bearer &lt;token&gt;</code>.</p>
        <div class="developer-grid">
            <div class="mini">
                <h3>Sandbox Render</h3>
                <p class="muted">Base actuelle : <code>https://gestionemployerbackend.onrender.com/api/v1</code>. En local, utilisez <code>/api/v1</code> via le meme domaine que cette page.</p>
            </div>
            <div class="mini">
                <h3>Auth</h3>
                <p class="muted">Employe/manager : <code>/auth/login</code>. Super-admin plateforme : <code>/platform/auth/login</code>. Les tokens sont personnels et doivent etre rotatifs.</p>
            </div>
            <div class="mini">
                <h3>Erreurs standard</h3>
                <p class="muted">Les erreurs doivent exposer <code>error</code>, <code>message</code> et, pour validation, <code>errors</code>. Les codes metier restent documentes dans OpenAPI.</p>
            </div>
            <div class="mini">
                <h3>Webhooks</h3>
                <p class="muted">Signature, retry et idempotence sont decrits dans le guide partenaire. Les partenaires doivent traiter les evenements comme rejouables.</p>
            </div>
        </div>
    </section>

    <section class="grid">
        <aside class="card">
            <h2>Profil demo</h2>
            <p class="muted">Charge les profils exposes par <code>/api/v1/demo-users</code>.</p>
            <label for="baseUrl">Base API</label>
            <input id="baseUrl" type="url">
            <label for="profile">Utilisateur</label>
            <select id="profile"></select>
            <label for="email">Email</label>
            <input id="email" type="email">
            <label for="password">Mot de passe</label>
            <input id="password" type="text" value="password123">
            <div class="row" style="margin-top:12px">
                <button id="loadDemo" type="button" class="secondary">Recharger profils</button>
                <button id="login" type="button">Se connecter</button>
            </div>
            <p id="tokenStatus" class="muted" style="margin-top:10px">Aucun token charge.</p>
        </aside>

        <section class="card">
            <h2>Requete API</h2>
            <p class="muted" style="margin:0 0 8px">Auth & Profil</p>
            <div class="row">
                <button data-endpoint="/auth/me" type="button">/auth/me</button>
                <button data-endpoint="/notifications?per_page=10" type="button">/notifications</button>
                <button data-endpoint="/device-tokens" type="button">/device-tokens</button>
                <button data-endpoint="/notification-preferences" type="button">/notification-preferences</button>
                <button data-endpoint="/launch-readiness" type="button">/launch-readiness</button>
                <button data-endpoint="/platform/auth/me" data-platform="1" type="button">/platform/auth/me</button>
            </div>
            <p class="muted" style="margin:10px 0 8px">Dashboard & RH (managers)</p>
            <div class="row">
                <button data-endpoint="/dashboard/summary" type="button">/dashboard/summary</button>
                <button data-endpoint="/dashboard/kpi" type="button">/dashboard/kpi</button>
                <button data-endpoint="/employees" type="button">/employees</button>
                <button data-endpoint="/departments" type="button">/departments</button>
                <button data-endpoint="/absences" type="button">/absences</button>
                <button data-endpoint="/org-chart" type="button">/org-chart</button>
            </div>
            <p class="muted" style="margin:10px 0 8px">Self-service (employe)</p>
            <div class="row">
                <button data-endpoint="/me/daily-summary" type="button">/me/daily-summary</button>
                <button data-endpoint="/me/leave-balances" type="button">/me/leave-balances</button>
                <button data-endpoint="/me/contracts" type="button">/me/contracts</button>
                <button data-endpoint="/me/pay-slips" type="button">/me/pay-slips</button>
                <button data-endpoint="/me/trainings" type="button">/me/trainings</button>
                <button data-endpoint="/me/loans" type="button">/me/loans</button>
            </div>
            <p class="muted" style="margin:10px 0 8px">Paie, Contrats, Formation, Recrutement</p>
            <div class="row">
                <button data-endpoint="/contracts" type="button">/contracts</button>
                <button data-endpoint="/payrolls" type="button">/payrolls</button>
                <button data-endpoint="/payroll-runs" type="button">/payroll-runs</button>
                <button data-endpoint="/training/courses" type="button">/training/courses</button>
                <button data-endpoint="/recruitment/jobs" type="button">/recruitment/jobs</button>
                <button data-endpoint="/loans" type="button">/loans</button>
                <button data-endpoint="/expense-claims" type="button">/expense-claims</button>
            </div>
            <p class="muted" style="margin:10px 0 8px">Billing, Reports, Audit</p>
            <div class="row">
                <button data-endpoint="/billing/subscription" type="button">/billing/subscription</button>
                <button data-endpoint="/billing/invoices" type="button">/billing/invoices</button>
                <button data-endpoint="/reports/headcount" type="button">/reports/headcount</button>
                <button data-endpoint="/reports/turnover" type="button">/reports/turnover</button>
                <button data-endpoint="/audit-logs" type="button">/audit-logs</button>
                <button data-endpoint="/communication/analytics" type="button">/communication/analytics</button>
            </div>
            <p class="muted" style="margin:10px 0 8px">Plateforme (super admin)</p>
            <div class="row">
                <button data-endpoint="/platform/companies" data-platform="1" type="button">/platform/companies</button>
                <button data-endpoint="/platform/metrics/overview" data-platform="1" type="button">/platform/metrics</button>
                <button data-endpoint="/platform/company-requests" data-platform="1" type="button">/platform/requests</button>
            </div>
            <label for="method">Methode</label>
            <select id="method">
                <option>GET</option>
                <option>POST</option>
                <option>PATCH</option>
                <option>PUT</option>
                <option>DELETE</option>
            </select>
            <label for="endpoint">Endpoint</label>
            <input id="endpoint" value="/auth/me">
            <label for="body">Body JSON</label>
            <textarea id="body" placeholder='{"key":"value"}'></textarea>
            <div class="row" style="margin-top:12px">
                <button id="send" type="button">Envoyer</button>
                <a href="/docs" style="align-self:center;color:var(--primary);font-weight:900">Voir OpenAPI</a>
            </div>
        </section>
    </section>

    <section class="card">
        <h2>Resultat</h2>
        <pre id="output">Pret.</pre>
    </section>
</main>
<script>
const defaultApiBase = `${window.location.origin}/api/v1`;
let token = '';
let platformToken = '';
const output = document.getElementById('output');
const baseUrl = document.getElementById('baseUrl');
const profile = document.getElementById('profile');
const email = document.getElementById('email');
const password = document.getElementById('password');
const endpoint = document.getElementById('endpoint');
const method = document.getElementById('method');
const body = document.getElementById('body');
const tokenStatus = document.getElementById('tokenStatus');
baseUrl.value = defaultApiBase;

function print(value) {
    output.textContent = typeof value === 'string' ? value : JSON.stringify(value, null, 2);
}

async function request(path, options = {}) {
    const apiBase = baseUrl.value.replace(/\/+$/, '');
    const response = await fetch(`${apiBase}${path}`, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(options.platform ? (platformToken ? { Authorization: `Bearer ${platformToken}` } : {}) : (token ? { Authorization: `Bearer ${token}` } : {})),
            ...(options.headers || {}),
        },
    });
    const text = await response.text();
    let payload = text;
    try { payload = JSON.parse(text); } catch {}
    return { status: response.status, ok: response.ok, payload };
}

async function loadDemoUsers() {
    print('Chargement des profils demo...');
    const result = await request('/demo-users');
    print(result);
    profile.innerHTML = '';
    const superAdmin = result.payload?.data?.super_admin;
    if (superAdmin) {
        profile.add(new Option(`Plateforme - ${superAdmin.label}`, JSON.stringify(superAdmin)));
    }
    for (const company of result.payload?.data?.companies || []) {
        for (const user of company.users || []) {
            profile.add(new Option(`${company.name} - ${user.name} (${user.manager_role || user.role})`, JSON.stringify(user)));
        }
    }
    applyProfile();
}

function applyProfile() {
    if (!profile.value) return;
    const user = JSON.parse(profile.value);
    email.value = user.email || '';
    password.value = user.password || 'password123';
}

async function login() {
    const selected = profile.value ? JSON.parse(profile.value) : {};
    const isPlatform = selected.role === 'super_admin' || selected.surface === 'admin-platform';
    const path = isPlatform ? '/platform/auth/login' : '/auth/login';
    print(`Connexion ${email.value}...`);
    const result = await request(path, {
        method: 'POST',
        body: JSON.stringify({ email: email.value, password: password.value, device_name: 'API Explorer' }),
    });
    print(result);
    const nextToken = result.payload?.token || result.payload?.data?.token;
    if (nextToken && isPlatform) {
        platformToken = nextToken;
        tokenStatus.textContent = 'Token plateforme charge.';
    } else if (nextToken) {
        token = nextToken;
        tokenStatus.textContent = 'Token client charge.';
    }
}

async function sendRequest(platform = false) {
    const verb = method.value;
    const payload = body.value.trim();
    const result = await request(endpoint.value, {
        method: verb,
        platform,
        ...(verb !== 'GET' && payload ? { body: payload } : {}),
    });
    print(result);
}

document.getElementById('loadDemo').addEventListener('click', loadDemoUsers);
document.getElementById('login').addEventListener('click', login);
document.getElementById('send').addEventListener('click', () => sendRequest(false));
profile.addEventListener('change', applyProfile);
document.querySelectorAll('[data-endpoint]').forEach((button) => {
    button.addEventListener('click', () => {
        endpoint.value = button.dataset.endpoint;
        method.value = 'GET';
        body.value = '';
        sendRequest(button.dataset.platform === '1');
    });
});
loadDemoUsers().catch((error) => print(String(error)));
</script>
</body>
</html>
