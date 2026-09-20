/**
 * Leopardo — exemple d'utilisation de l'API v1 (fetch natif, zéro dépendance).
 *
 * ⚠️ HONNÊTETÉ DE L'EXEMPLE (#7998) : il n'existe PAS de paquet npm
 * `@leopardo-rh/sdk` — les SDK expérimentaux vivent dans `dev-hub/sdk/` et
 * ne sont pas publiés. Cet exemple montre donc l'API HTTP réelle, telle
 * qu'un intégrateur la consomme aujourd'hui.
 *
 * Prérequis :
 *   1. Un backend joignable — local (`php artisan serve`, port 8000) ou
 *      environnement déployé. NE JAMAIS pointer vers le backend DEV Render
 *      (banni par #7842/#7963).
 *   2. Un compte — en local, un persona seedé (docs/DEMO_ACCOUNTS.md,
 *      nécessite DEMO_MODE_ENABLED=true côté backend).
 *
 * Configuration par environnement (aucune valeur en dur) :
 *   export LEOPARDO_API_URL="http://localhost:8000/api/v1"
 *   export LEOPARDO_EMAIL="ahmed.benali@techcorp-algerie.dz"
 *   export LEOPARDO_PASSWORD="<mot de passe du persona — voir docs/DEMO_ACCOUNTS.md>"
 *
 * Exécution : `npx tsx examples/sdk-usage-example.ts` (ou ts-node).
 */

const BASE_URL = process.env.LEOPARDO_API_URL;
const EMAIL = process.env.LEOPARDO_EMAIL;
const PASSWORD = process.env.LEOPARDO_PASSWORD;

if (!BASE_URL || !EMAIL || !PASSWORD) {
    console.error(
        '❌ Variables manquantes : LEOPARDO_API_URL, LEOPARDO_EMAIL, LEOPARDO_PASSWORD\n' +
        '   (aucune URL ni credential en dur dans cet exemple — voir l’en-tête).',
    );
    process.exit(1);
}

interface LoginResponse {
    token: string;
    employee?: { id: number; first_name: string; last_name: string };
}

interface Paginated<T> {
    data: T[];
}

async function main() {
    // 1. Authentification — POST /auth/login (contrat réel de l'API v1).
    console.log('🔑 Authentification…');
    const loginRes = await fetch(`${BASE_URL}/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ email: EMAIL, password: PASSWORD, device_name: 'sdk-example' }),
    });

    if (!loginRes.ok) {
        throw new Error(`Login échoué (${loginRes.status}) : ${await loginRes.text()}`);
    }

    const auth = (await loginRes.json()) as LoginResponse;
    console.log(`✅ Connecté${auth.employee ? ` : ${auth.employee.first_name} ${auth.employee.last_name}` : ''}`);

    // 2. Lecture — GET /employees (jeton Sanctum en Bearer).
    console.log('👥 Récupération des employés…');
    const employeesRes = await fetch(`${BASE_URL}/employees?per_page=10`, {
        headers: { Authorization: `Bearer ${auth.token}`, Accept: 'application/json' },
    });

    if (!employeesRes.ok) {
        throw new Error(`Lecture employés échouée (${employeesRes.status}) : ${await employeesRes.text()}`);
    }

    const employees = (await employeesRes.json()) as Paginated<{
        id: number;
        first_name: string;
        last_name: string;
        email: string;
    }>;

    console.table(employees.data.map((emp) => ({
        ID: emp.id,
        Nom: `${emp.first_name} ${emp.last_name}`,
        Email: emp.email,
    })));

    // 3. Hygiène — révoquer le jeton créé pour l'exemple.
    await fetch(`${BASE_URL}/user/logout`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${auth.token}`, Accept: 'application/json' },
    });
    console.log('🚪 Jeton révoqué (logout).');
}

main().catch((err: unknown) => {
    console.error(err instanceof Error ? err.message : err);
    process.exit(1);
});
