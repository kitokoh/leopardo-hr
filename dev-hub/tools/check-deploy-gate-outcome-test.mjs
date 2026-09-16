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
 *     (`no-runs`), c'est ce qui laisse `deploy-staging.yml` intact.
 *
 * Depuis #7528, il teste AUSSI la DÉTECTION elle-même (le script de l'étape
 * `changes_api` de `deploy-main.yml`, extrait et exécuté de la même façon) :
 * c'est là qu'était le défaut — un merge qui ne touche que des fichiers CI
 * était classé `web_changed=true` (la liste `web` de paths-filters.yml est plus
 * large que les `paths:` de web-ci.yml), donc le gate exigeait un run
 * impossible → `no-runs` → `main` rouge, en boucle :
 *
 *   - merge CI-only (fichiers mesurés sur `d7e11294`) → `web_changed=false`,
 *     `api_changed=false`, puis `not-required` de bout en bout ;
 *   - `front/admin-dashboard/**` → `web_changed=true` (et `api_changed=true` :
 *     tests.yml porte les tests de contrat front/API) ;
 *   - `api/**` → `api_changed=true`, `web_changed=false` ;
 *   - un workflow requis modifié déclenche sa propre zone ;
 *   - panne d'API ou parent inconnu → les deux à `true` (conservateur).
 *
 * Usage : node dev-hub/tools/check-deploy-gate-outcome-test.mjs [chemin/action.yml] [chemin/deploy-main.yml]
 *         (les chemins servent à l'A/B : exécuter le test contre la version
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
// #7528 : la DÉTECTION des zones modifiées (`web_changed`/`api_changed`) vit
// dans l'étape `changes_api` de deploy-main.yml — c'est elle qui a produit le
// faux `web_changed=true` du merge CI-only. On l'extrait et on l'exécute comme
// le gate, pour tester la décision et pas seulement sa description.
const deployWorkflowPath = process.argv[3]
  ? path.resolve(process.argv[3])
  : path.join(repoRoot, '.github/workflows/deploy-main.yml');

/** Extrait un bloc `script: |` à partir de l'index de sa ligne d'en-tête. */
function extractScriptBlock(lines, start) {
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

/** Extrait la valeur du `script: |` du github-script de l'action composite. */
function extractGateScript(yaml) {
  const lines = yaml.split('\n');
  const start = lines.findIndex((line) => line.trim() === 'script: |');
  if (start === -1) throw new Error('bloc `script: |` introuvable dans action.yml');

  return extractScriptBlock(lines, start);
}

/**
 * Extrait le `script: |` de l'étape `id: <stepId>` d'un workflow.
 *
 * Les expressions `${{ … }}` qu'Actions substitue avant exécution n'existent
 * pas ici : on les remplace par des valeurs de test pour que le script soit du
 * JavaScript valide.
 */
function extractStepScript(yaml, stepId, substitutions = {}) {
  const lines = yaml.split('\n');
  const idIndex = lines.findIndex((line) => line.trim() === `id: ${stepId}`);
  if (idIndex === -1) throw new Error(`étape « id: ${stepId} » introuvable dans le workflow`);

  const start = lines.findIndex((line, i) => i > idIndex && line.trim() === 'script: |');
  if (start === -1) throw new Error(`bloc script: | introuvable pour l'étape ${stepId}`);

  let script = extractScriptBlock(lines, start);
  for (const [expression, value] of Object.entries(substitutions)) {
    script = script.split(expression).join(value);
  }
  return script;
}

const gateScript = extractGateScript(readFileSync(actionPath, 'utf8'));

// Le script de détection, avec le SHA de test substitué (comme Actions le fait).
const TEST_SHA = 'a'.repeat(40);
const changesApiScript = extractStepScript(readFileSync(deployWorkflowPath, 'utf8'), 'changes_api', {
  '${{ steps.context.outputs.sha }}': TEST_SHA,
});

/** Exécute le gate avec des dépendances simulées. */
async function runGate({ apiChanged, webChanged, runs, eventName = 'push', mainHead = 'a'.repeat(40) }) {
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
  const realSetTimeout = globalThis.setTimeout;
  process.env.GITHUB_EVENT_NAME = eventName;
  // Le gate poll toutes les 60 s : on rend l'attente instantanée.
  globalThis.setTimeout = (fn) => { fn(); return 0; };

  try {
    const factory = new Function('core', 'github', 'context', `return (async () => {\n${gateScript}\n})();`);
    await factory(core, github, context);
  } finally {
    globalThis.setTimeout = realSetTimeout;
    if (previousEvent === undefined) delete process.env.GITHUB_EVENT_NAME;
    else process.env.GITHUB_EVENT_NAME = previousEvent;
  }

  return { outputs, logs };
}

/** Exécute l'étape `changes_api` de deploy-main.yml avec des dépendances simulées. */
async function runChangesApi({ files, parent = 'p'.repeat(40), apiError = false }) {
  const outputs = {};
  const logs = [];
  const core = {
    setOutput: (k, v) => { outputs[k] = String(v); },
    setFailed: (msg) => { throw new Error(`setFailed: ${msg}`); },
    info: (m) => logs.push(`info: ${m}`),
    warning: (m) => logs.push(`warning: ${m}`),
    notice: (m) => logs.push(`notice: ${m}`),
  };
  const github = {
    rest: {
      repos: {
        getCommit: async () => ({ data: { parents: parent ? [{ sha: parent }] : [] } }),
        compareCommitsWithBasehead: async () => {
          if (apiError) throw new Error('compare failed');
          return { data: { files: files.map((filename) => ({ filename })) } };
        },
      },
    },
  };
  const context = { repo: { owner: 'kitokoh', repo: 'leopardo-hr' } };

  const factory = new Function('core', 'github', 'context', `return (async () => {\n${changesApiScript}\n})();`);
  await factory(core, github, context);

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

// ── Dispatch manuel → contourné par construction ───────────────────────────
await check('workflow_dispatch → manual-dispatch, déploiement autorisé', async () => {
  const { outputs } = await runGate({ apiChanged: 'true', webChanged: 'false', runs: [], eventName: 'workflow_dispatch' });
  assertEqual(outputs.gate_outcome, 'manual-dispatch', 'gate_outcome');
  assertEqual(outputs.should_deploy, 'true', 'should_deploy');
});

// ── #7528 : la DÉTECTION elle-même (étape `changes_api`) ───────────────────
// Liste RÉELLE des fichiers du merge d7e11294, celui du défaut mesuré.
const ciOnlyMergeFiles = [
  '.github/actions/verify-deploy-workflows/action.yml',
  '.github/workflows/actionlint.yml',
  '.github/workflows/deploy-main.yml',
  'CHANGELOG.md',
  'dev-hub/tools/check-deploy-gate-outcome.sh',
  'dev-hub/tools/check-deploy-gate-outcome-test.mjs',
  'docs/GOUVERNANCE/REGISTRE_GARDES.md',
];

await check('merge CI-only → aucun run exigé (web_changed=false, api_changed=false)', async () => {
  const { outputs } = await runChangesApi({ files: ciOnlyMergeFiles });
  assertEqual(outputs.web_changed, 'false', 'web_changed');
  assertEqual(outputs.api_changed, 'false', 'api_changed');
});

await check('merge CI-only → le gate conclut not-required de bout en bout (#7528)', async () => {
  const { outputs: changes } = await runChangesApi({ files: ciOnlyMergeFiles });
  const { outputs } = await runGate({
    apiChanged: changes.api_changed,
    webChanged: changes.web_changed,
    runs: [],
  });
  assertEqual(outputs.gate_outcome, 'not-required', 'gate_outcome');
  assertEqual(outputs.should_deploy, 'false', 'should_deploy');
});

// ── Aucun relâchement : les deux workflows restent exigés sur leur surface ──
await check('front/admin-dashboard/** → web_changed=true et api_changed=true (tests de contrat)', async () => {
  const { outputs } = await runChangesApi({ files: ['front/admin-dashboard/src/pages/Dashboard.vue'] });
  assertEqual(outputs.web_changed, 'true', 'web_changed');
  assertEqual(outputs.api_changed, 'true', 'api_changed');
});

await check('api/** → api_changed=true, web_changed=false', async () => {
  const { outputs } = await runChangesApi({ files: ['api/app/Modules/Billing/Foo.php'] });
  assertEqual(outputs.api_changed, 'true', 'api_changed');
  assertEqual(outputs.web_changed, 'false', 'web_changed');
});

await check('.github/workflows/web-ci.yml → web_changed=true (il se déclenche lui-même)', async () => {
  const { outputs } = await runChangesApi({ files: ['.github/workflows/web-ci.yml'] });
  assertEqual(outputs.web_changed, 'true', 'web_changed');
  assertEqual(outputs.api_changed, 'false', 'api_changed');
});

await check('.github/workflows/tests.yml → api_changed=true (il se déclenche lui-même)', async () => {
  const { outputs } = await runChangesApi({ files: ['.github/workflows/tests.yml'] });
  assertEqual(outputs.api_changed, 'true', 'api_changed');
  assertEqual(outputs.web_changed, 'false', 'web_changed');
});

await check('.github/workflows/deploy-main.yml seul → aucune zone (il ne déclenche ni tests ni web-ci)', async () => {
  const { outputs } = await runChangesApi({ files: ['.github/workflows/deploy-main.yml'] });
  assertEqual(outputs.web_changed, 'false', 'web_changed');
  assertEqual(outputs.api_changed, 'false', 'api_changed');
});

// ── Mesure impossible → conservateur (les deux zones exigées) ──────────────
await check('comparaison API en échec → true/true (conservateur)', async () => {
  const { outputs } = await runChangesApi({ files: [], apiError: true });
  assertEqual(outputs.web_changed, 'true', 'web_changed');
  assertEqual(outputs.api_changed, 'true', 'api_changed');
});

await check('parent inconnu (commit racine) → true/true (conservateur)', async () => {
  const { outputs } = await runChangesApi({ files: [], parent: null });
  assertEqual(outputs.web_changed, 'true', 'web_changed');
  assertEqual(outputs.api_changed, 'true', 'api_changed');
});

if (failures.length > 0) {
  console.error(`❌ check-deploy-gate-outcome-test : ${failures.length} échec(s) sur ${passed + failures.length}`);
  for (const failure of failures) console.error(`   - ${failure}`);
  process.exit(1);
}

console.log(`✅ check-deploy-gate-outcome-test : ${passed} verdicts vérifiés (détection web/api, not-required ≠ no-runs, deploy, stale, manual-dispatch).`);
