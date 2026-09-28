#!/usr/bin/env node
/**
 * POC JETABLE — ADR-0027 (BOS-036, issue #8225)
 * « Go-limité : un compte, plusieurs entreprises via user_employee_links + switch explicite »
 *
 * ⚠️ JETABLE : ce script ne sera JAMAIS fusionné dans main. Il modélise en mémoire les
 * tables existantes (users / employees / user_lookups / user_employee_links / tokens)
 * pour DÉMONTRER les invariants de la stratégie proposée, pas pour préfigurer du code prod.
 *
 * Run: node poc/bos-036-company-switch/switch.mjs
 */

// ---------------------------------------------------------------------------
// « Base de données » simulée — miroir minimal des schémas réels du repo :
// - public.users                         (identité globale)
// - tenant employees                     (email UNIQUE GLOBAL — migration 2026_04_17_000105)
// - public.user_lookups                  (email PK → company principale — 2026_04_01_000003)
// - public.user_employee_links           (unique(user_id, company_id), status — 2026_05_02_100001)
// - tokens Sanctum                       (abilities tenant_* — ADR-0025)
// ---------------------------------------------------------------------------

const db = {
  users: new Map(),            // id -> { id, email, passwordHash }
  employees: [],               // { id, companyId, email, managerRole }
  userLookups: new Map(),      // email -> { companyId, schemaName, employeeId }
  links: [],                   // { userId, employeeId, companyId, status }
  tokens: new Map(),           // token -> { userId, companyId, employeeId, revoked }
  audit: [],
  seq: 1,
};

const FAIL = (code) => ({ ok: false, code });
const OK = (data) => ({ ok: true, ...data });

function audit(event, ctx) {
  // Convention #8164 : identifiants uniquement, jamais d'email en clair.
  db.audit.push({ event, ...ctx, at: new Date().toISOString() });
}

// --- Création d'une company + manager principal (cas A — chemin actuel) ----
function provisionCompany({ companyId, email, managerRole = 'principal' }) {
  if (db.userLookups.has(email)) return FAIL('GLOBAL_COLLISION'); // règle GlobalEmailUnique
  if (db.employees.some((e) => e.email === email)) return FAIL('GLOBAL_COLLISION');
  const user = { id: db.seq++, email };
  const employee = { id: db.seq++, companyId, email, managerRole };
  db.users.set(user.id, user);
  db.employees.push(employee);
  db.userLookups.set(email, { companyId, schemaName: `tenant_${companyId}`, employeeId: employee.id });
  db.links.push({ userId: user.id, employeeId: employee.id, companyId, status: 'active' });
  return OK({ userId: user.id, employeeId: employee.id });
}

// --- Tentative de 2e COMPTE avec le même email (doit échouer) --------------
function registerSecondAccount({ email }) {
  if (db.userLookups.has(email) || db.employees.some((e) => e.email === email)) {
    return FAIL('GLOBAL_COLLISION'); // unicité globale conservée (ADR-0027 §Décision.1)
  }
  return OK({});
}

// --- Invitation d'une personne DÉJÀ titulaire d'un compte (ADR-0027 §2) ----
function inviteExistingUser({ email, companyId, managerRole }) {
  const user = [...db.users.values()].find((u) => u.email === email);
  if (!user) return FAIL('NO_ACCOUNT');
  if (db.links.some((l) => l.userId === user.id && l.companyId === companyId)) {
    return FAIL('LINK_EXISTS'); // unique(user_id, company_id)
  }
  // Nouvelle ligne employees DANS la company invitante (email global inchangé,
  // l'unicité porte sur l'IDENTITÉ users, pas sur la présence multi-tenant).
  const employee = { id: db.seq++, companyId, email, managerRole };
  db.employees.push(employee);
  db.links.push({ userId: user.id, employeeId: employee.id, companyId, status: 'active' });
  audit('invitation.link_created', { userId: user.id, companyId });
  return OK({ userId: user.id, employeeId: employee.id });
}

// --- Login : dispatch via user_lookups → company PRINCIPALE (inchangé) -----
function login({ email }) {
  const lookup = db.userLookups.get(email); // résolution O(1), jamais multi-lignes
  if (!lookup) return FAIL('INVALID_CREDENTIALS');
  const user = [...db.users.values()].find((u) => u.email === email);
  const token = `leo_${Math.random().toString(36).slice(2)}`;
  db.tokens.set(token, {
    userId: user.id,
    companyId: lookup.companyId,
    employeeId: lookup.employeeId,
    revoked: false,
  });
  return OK({ token, companyId: lookup.companyId });
}

// --- Switch : vérif lien fail-closed → révocation + réémission scopée ------
function switchCompany({ token, targetCompanyId }) {
  const session = db.tokens.get(token);
  if (!session || session.revoked) return FAIL('UNAUTHENTICATED');
  const link = db.links.find(
    (l) => l.userId === session.userId && l.companyId === targetCompanyId && l.status === 'active',
  );
  if (!link) {
    // Fail-closed + uniforme : pas de fuite d'existence de la company cible.
    audit('auth.company_switch_denied', { userId: session.userId });
    return FAIL('FORBIDDEN');
  }
  session.revoked = true; // l'ancien token ne donne plus accès au tenant précédent
  const newToken = `leo_${Math.random().toString(36).slice(2)}`;
  db.tokens.set(newToken, {
    userId: session.userId,
    companyId: targetCompanyId,
    employeeId: link.employeeId,
    revoked: false,
  });
  audit('auth.company_switched', {
    userId: session.userId,
    fromCompany: session.companyId,
    toCompany: targetCompanyId,
  });
  return OK({ token: newToken, companyId: targetCompanyId });
}

// --- Accès à une ressource tenant (global scope BelongsToCompany simulé) ---
function readTenantResource({ token, companyId }) {
  const session = db.tokens.get(token);
  if (!session || session.revoked) return FAIL('UNAUTHENTICATED');
  if (session.companyId !== companyId) return FAIL('CROSS_TENANT_DENIED');
  return OK({ data: `données de ${companyId}` });
}

// --- Droits : le rôle vient de la ligne employees DU TENANT COURANT --------
function currentRole({ token }) {
  const session = db.tokens.get(token);
  const employee = db.employees.find((e) => e.id === session.employeeId);
  return employee.managerRole;
}

// --- Kiosk : tenant porté par le DEVICE, jamais par un compte --------------
const kioskDevice = { deviceCode: 'KSK-01', companyId: 'company-A' };
function kioskPunch({ deviceCode }) {
  const device = kioskDevice.deviceCode === deviceCode ? kioskDevice : null;
  if (!device) return FAIL('UNKNOWN_DEVICE');
  return OK({ companyId: device.companyId }); // inaffecté par tout switch
}

// ===========================================================================
// VÉRIFICATIONS (le POC porte ses propres preuves — run et lire la sortie)
// ===========================================================================
let passed = 0;
let failed = 0;
function check(name, cond) {
  if (cond) { passed++; console.log(`  ✅ ${name}`); }
  else { failed++; console.log(`  ❌ ${name}`); }
}

console.log('— Scénario : Amina possède company-A, est employée de company-B (cas B+D) —');
const a = provisionCompany({ companyId: 'company-A', email: 'amina@example.com' });
console.log('— Cas B/D : invitation dans une 2e company avec le MÊME email —');
const inv = inviteExistingUser({ email: 'amina@example.com', companyId: 'company-B', managerRole: 'comptable' });

check('création company-A + compte principal OK', a.ok);
check('invitation company-B par lien OK (pas de nouveau compte)', inv.ok && inv.userId === a.userId);
check('user_employee_links : 2 liens actifs pour 1 user',
  db.links.filter((l) => l.userId === a.userId && l.status === 'active').length === 2);

console.log('— Invariant 1 : unicité email préservée —');
const dup = registerSecondAccount({ email: 'amina@example.com' });
check('2e COMPTE au même email refusé (GLOBAL_COLLISION)', !dup.ok && dup.code === 'GLOBAL_COLLISION');
check('user_lookups reste mono-ligne (1 email → 1 company principale)',
  db.userLookups.get('amina@example.com').companyId === 'company-A');

console.log('— Invariant 2 : login inchangé (dispatch primaire) —');
const sess = login({ email: 'amina@example.com' });
check('login → company principale A', sess.ok && sess.companyId === 'company-A');
check('rôle courant = celui du tenant A (principal)', currentRole({ token: sess.token }) === 'principal');

console.log('— Invariant 3 : switch fail-closed sans lien —');
const denied = switchCompany({ token: sess.token, targetCompanyId: 'company-C' });
check('switch vers company-C (aucun lien) refusé, code uniforme', !denied.ok && denied.code === 'FORBIDDEN');
check('audit du refus tracé (auth.company_switch_denied)',
  db.audit.some((e) => e.event === 'auth.company_switch_denied'));

console.log('— Invariant 4 : switch légitime = réémission scopée + révocation —');
const sw = switchCompany({ token: sess.token, targetCompanyId: 'company-B' });
check('switch vers company-B (lien actif) accepté', sw.ok && sw.companyId === 'company-B');
check('ancien token révoqué (accès A impossible)', !readTenantResource({ token: sess.token, companyId: 'company-A' }).ok);
check('nouveau token scopé à B (accès B autorisé)', readTenantResource({ token: sw.token, companyId: 'company-B' }).ok);
check('nouveau token refusé sur A (une requête = un tenant)', !readTenantResource({ token: sw.token, companyId: 'company-A' }).ok);
check('aucun droit transporté : rôle en B = comptable (pas principal)', currentRole({ token: sw.token }) === 'comptable');
check('audit du switch (auth.company_switched, identifiants uniquement)',
  db.audit.some((e) => e.event === 'auth.company_switched' && e.fromCompany === 'company-A' && e.toCompany === 'company-B'));

console.log('— Invariant 5 : kiosk inaffecté (tenant = device) —');
check('le punch kiosk reste rattaché à company-A', kioskPunch({ deviceCode: 'KSK-01' }).companyId === 'company-A');

console.log('— Cas H : cabinet comptable lié à N clientes, accès séquentiel —');
provisionCompany({ companyId: 'company-D', email: 'cabinet@example.com', managerRole: 'comptable' });
inviteExistingUser({ email: 'cabinet@example.com', companyId: 'company-E', managerRole: 'comptable' });
const cab = login({ email: 'cabinet@example.com' });
const swE = switchCompany({ token: cab.token, targetCompanyId: 'company-E' });
check('cabinet : login sur cliente principale D puis switch vers cliente E', swE.ok);
check('cabinet : jamais deux clientes dans la même requête',
  !readTenantResource({ token: swE.token, companyId: 'company-D' }).ok);

console.log(`\nRésultat : ${passed} vérifications vertes, ${failed} rouge(s).`);
process.exit(failed === 0 ? 0 : 1);
