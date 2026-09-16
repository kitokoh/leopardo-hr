#!/usr/bin/env node
/**
 * check-deploy-gate-outcome-test.mjs — test COMPORTEMENTAL du gate de
 * déploiement (issue #7511).
 *
 * `dev-hub/tools/check-deploy-gate-outcome.sh` vérifie la STRUCTURE (input
 * `api_changed` présent, `noRunExpected` actif, parité des filtres). Il ne
 * prouve pas le verdict. Ce test extrait le script `github-script` de
 * `.github/actions/verify-deploy-workflows/action.yml`, l'exécute avec un
 * `core`/`github`/`context` simulés et assert les SORTIES réelles du gate :
 *
 *   - push `main` sans aucun run requis, `api_changed=false` → `not-required`
 *     (décision : rien à déployer) — c'est le défaut #7511 ;
 *   - mêmes conditions mais `api_changed=true` → `no-runs` (indécision : un
 *     vrai trou de couverture ne doit pas être masqué) ;
 *   - run requis vert, web inchangé → `deploy` ;
 *   - `api_changed` non fourni (`''`) → comportement strict conservé
 *     (`no-runs`), c'est ce qui laisse `deploy-staging.yml` intact ;
 *   - #7559 — budget d'attente épuisé alors qu'un run requis tourne ENCORE
 *     (`in_progress`) → `pending` (différé, non fatal) et `should_deploy=false` ;
 *   - #7559 — budget épuisé SANS run requis en cours → `timeout` (indécision
 *     maintenue : un vrai trou de couverture reste signalé).
 *
 * Le budget est épuisé sans attendre 30 min via `DEPLOY_GATE_BUDGET_MINUTES`
 * (variable lue par l'action, posée uniquement par ce test).
 *
 * Usage : node dev-hub/tools/check-deploy-gate-outcome-test.mjs [chemin/action.yml]
 *         (le chemin est utile pour un A/B : exécuter le test contre la version
 *         d'avant le correctif et vérifier qu'il ÉCHOUE — sans quoi le test ne
 *         prouve rien.)
 */

import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(here, '..', '..');
const actionPath = process.argv[2]
  ? path.resolve(process.argv[2])
  : path.join(repoRoot, '.github/actions/verify-deploy-workflows/action.yml');

/** Extrait la valeur du `script: |` du github-script de l'action composite. */
function extractGateScript(yaml) {
  const lines = yaml.split('\n');
  const start = lines.findIndex((line) => line.trim() === 'script: |');
  if (start === -1) throw new Error('bloc `script: |` introuvable dans action.yml');

  const indentOfScript = lines[start].match(/^\s*/)[0].length;
  const body = [];
  for (let i = start + 1; i < lines.length; i += 1) {
    const line = lines[i];
    if (line.trim() !== '' && line.match(/^\s*/)[0].length <= indentOfScript) break;
    body.push(line);
  }
  // Le bloc est indenté sous `script:` ; on retire l'indentation commune.
  const nonEmpty = body.filter((line) => line.trim() !== '');
  const common = Math.min(...nonEmpty.map((line) => line.match(/^\s*/)[0].length));
  return body.map((line) => line.slice(common)).join('\n');
}

const gateScript = extractGateScript(readFileSync(actionPath, 'utf8'));

/** Exécute le gate avec des dépendances simulées. */
async function runGate({ apiChanged, webChanged, runs, eventName = 'push', mainHead = 'a'.repeat(40), budgetMinutes }) {
  const outputs = {};
  const logs = [];
  const core = {
    getInput: (name) => ({ sha: 'a'.repeat(40), required_workflows: '["Tests - Leopardo RH","Web CI - Leopardo Admin"]', web_changed: webChanged, api_changed: apiChanged }[name] ?? ''),
    setOutput: (k, v) => { outputs[k] = v; },
    setFailed: (msg) => { throw new Error(`setFailed: ${msg}`); },
    info: (m) => logs.push(`info: ${m}`),
    warning: (m) => logs.push(`warning: ${m}`),
    notice: (m) => logs.push(`notice: ${m}`),
  };
  const github = {
    paginate: async () => runs,
    rest: {
      actions: { listWorkflowRunsForRepo: {} },
      repos: { getBranch: async () => ({ data: { commit: { sha: mainHead } } }) },
    },
  };
  const context = { repo: { owner: 'kitokoh', repo: 'leopardo-hr' } };

  const previousEvent = process.env.GITHUB_EVENT_NAME;
  const previousBudget = process.env.DEPLOY_GATE_BUDGET_MINUTES;
  const realSetTimeout = globalThis.setTimeout;
  // Horloge simulée : le gate boucle « tant que Date.now() < deadline ». Sans
  // ça, un `setTimeout` instantané boucle indéfiniment sur l'horloge réelle
  // (constaté en A/B contre la version d'avant le correctif #7559 : la boucle
  // ne sortait plus et le test finissait en dépassement de pile). On avance
  // donc l'horloge du montant demandé à chaque attente — le budget s'épuise
  // exactement comme en CI, mais en quelques millisecondes.
  const realDateNow = Date.now;
  let fakeNow = realDateNow();
  process.env.GITHUB_EVENT_NAME = eventName;
  if (budgetMinutes !== undefined) process.env.DEPLOY_GATE_BUDGET_MINUTES = String(budgetMinutes);
  // Le gate poll toutes les 60 s : on rend l'attente instantanée.
  globalThis.setTimeout = (fn, ms) => { fakeNow += typeof ms === 'number' ? ms : 0; fn(); return 0; };
  Date.now = () => fakeNow;

  try {
    const factory = new Function('core', 'github', 'context', `return (async () => {\n${gateScript}\n})();`);
    await factory(core, github, context);
  } finally {
    globalThis.setTimeout = realSetTimeout;
    Date.now = realDateNow;
    if (previousEvent === undefined) delete process.env.GITHUB_EVENT_NAME;
    else process.env.GITHUB_EVENT_NAME = previousEvent;
    if (previousBudget === undefined) delete process.env.DEPLOY_GATE_BUDGET_MINUTES;
    else process.env.DEPLOY_GATE_BUDGET_MINUTES = previousBudget;
  }

  return { outputs, logs };
}

const failures = [];
let passed = 0;

async function check(label, fn) {
  try {
    await fn();
    passed += 1;
  } catch (error) {
    failures.push(`${label} — ${error.message}`);
  }
}

function assertEqual(actual, expected, what) {
  if (actual !== expected) throw new Error(`${what} : attendu « ${expected} », obtenu « ${actual} »`);
}

const testsSuccessRun = [{ name: 'Tests - Leopardo RH', head_sha: 'a'.repeat(40), status: 'completed', conclusion: 'success' }];

// ── Défaut #7511 : aucun run requis parce que rien d'api/web n'a changé ─────
await check('push sans aucun run requis, api_changed=false → not-required', async () => {
  const { outputs } = await runGate({ apiChanged: 'false', webChanged: 'false', runs: [] });
  assertEqual(outputs.gate_outcome, 'not-required', 'gate_outcome');
  assertEqual(outputs.should_deploy, 'false', 'should_deploy');
  assertEqual(outputs.tests_conclusion, 'not-required', 'tests_conclusion');
});

// ── L'indécision réelle reste rouge ────────────────────────────────────────
await check('push sans run requis MAIS api_changed=true → no-runs (indécision)', async () => {
  const { outputs } = await runGate({ apiChanged: 'true', webChanged: 'false', runs: [] });
  assertEqual(outputs.gate_outcome, 'no-runs', 'gate_outcome');
  assertEqual(outputs.should_deploy, 'false', 'should_deploy');
});

// ── Appelant qui ne mesure pas api_changed → strict conservé ───────────────
await check('api_changed non fourni → no-runs (deploy-staging inchangé)', async () => {
  const { outputs } = await runGate({ apiChanged: '', webChanged: 'false', runs: [] });
  assertEqual(outputs.gate_outcome, 'no-runs', 'gate_outcome');
});

// ── Cas nominal : le gate déploie ──────────────────────────────────────────
await check('run Tests vert, web inchangé → deploy', async () => {
  const { outputs } = await runGate({ apiChanged: 'true', webChanged: 'false', runs: testsSuccessRun });
  assertEqual(outputs.gate_outcome, 'deploy', 'gate_outcome');
  assertEqual(outputs.should_deploy, 'true', 'should_deploy');
});

// ── Cas défavorable : tests rouges → décision (pas indécision) ─────────────
await check('run Tests rouge → tests-failure, should_deploy=false', async () => {
  const { outputs } = await runGate({
    apiChanged: 'true',
    webChanged: 'false',
    runs: [{ name: 'Tests - Leopardo RH', head_sha: 'a'.repeat(40), status: 'completed', conclusion: 'failure' }],
  });
  assertEqual(outputs.gate_outcome, 'tests-failure', 'gate_outcome');
  assertEqual(outputs.should_deploy, 'false', 'should_deploy');
});

// ── SHA dépassé pendant l'attente → stale ──────────────────────────────────
await check('SHA dépassé par un push plus récent → stale', async () => {
  const { outputs } = await runGate({ apiChanged: 'true', webChanged: 'false', runs: testsSuccessRun, mainHead: 'b'.repeat(40) });
  assertEqual(outputs.gate_outcome, 'stale', 'gate_outcome');
  assertEqual(outputs.should_deploy, 'false', 'should_deploy');
});

// ── #7559 : budget épuisé alors qu'un run requis tourne encore → différé ───
await check('budget épuisé + run requis in_progress → pending (différé, non fatal)', async () => {
  const { outputs, logs } = await runGate({
    apiChanged: 'true',
    webChanged: 'false',
    runs: [{ name: 'Tests - Leopardo RH', head_sha: 'a'.repeat(40), status: 'in_progress', conclusion: null }],
    budgetMinutes: 0,
  });
  assertEqual(outputs.gate_outcome, 'pending', 'gate_outcome');
  assertEqual(outputs.should_deploy, 'false', 'should_deploy');
  assertEqual(outputs.tests_conclusion, 'pending', 'tests_conclusion');
  if (!logs.some((line) => line.startsWith('notice:') && line.includes('#7559'))) {
    throw new Error('le différé doit être annoncé par un ::notice:: explicite (#7559)');
  }
});

// ── #7559 : budget épuisé SANS run en cours → indécision maintenue ─────────
await check('budget épuisé sans run requis en cours → timeout (indécision)', async () => {
  const { outputs } = await runGate({ apiChanged: 'true', webChanged: 'false', runs: [], budgetMinutes: 0 });
  assertEqual(outputs.gate_outcome, 'timeout', 'gate_outcome');
  assertEqual(outputs.should_deploy, 'false', 'should_deploy');
});

// ── Dispatch manuel → contourné par construction ───────────────────────────
await check('workflow_dispatch → manual-dispatch, déploiement autorisé', async () => {
  const { outputs } = await runGate({ apiChanged: 'true', webChanged: 'false', runs: [], eventName: 'workflow_dispatch' });
  assertEqual(outputs.gate_outcome, 'manual-dispatch', 'gate_outcome');
  assertEqual(outputs.should_deploy, 'true', 'should_deploy');
});

if (failures.length > 0) {
  console.error(`❌ check-deploy-gate-outcome-test : ${failures.length} échec(s) sur ${passed + failures.length}`);
  for (const failure of failures) console.error(`   - ${failure}`);
  process.exit(1);
}

console.log(`✅ check-deploy-gate-outcome-test : ${passed} verdicts du gate vérifiés (not-required ≠ no-runs, deploy, stale, manual-dispatch, pending ≠ timeout).`);
