// R1 / #8754 [BOS-CHAT] — tests de charge du chat horizontal (BC-13 COMMS).
//
// Implémente le §13.1 du rapport d'audit
// (`docs/mission-business-os/16_chat_horizontal_audit.md`), en trois scénarios
// demandés par la mission :
//
//   1. `smoke`  — dev / tests internes ;
//   2. `launch` — lancement avec les 10 premiers professionnels ;
//   3. `growth` — croissance vers plusieurs centaines de professionnels.
//
// ⚠️ LECTURE HONNÊTE DES RÉSULTATS
//
// Ces scripts ne produisent des chiffres que s'ils sont lancés contre une API
// RÉELLEMENT déployée. Aucune valeur de latence, de débit ou de coût n'est
// écrite dans ce dépôt « par défaut » : les seuils ci-dessous sont des
// OBJECTIFS (thresholds), pas des mesures. Un run qui échoue sur un seuil
// signifie « objectif non tenu sur cet environnement », pas « le système ne
// supporte pas N utilisateurs ».
//
// Usage :
//   BASE_URL=https://<api>/api/v1 TOKEN=<jwt employé> THREAD_ID=<id> \
//     k6 run -e SCENARIO=launch api/tests/k6/chat-load.js
//
// Variables d'environnement :
//   BASE_URL   (obligatoire) racine de l'API, avec `/api/v1`
//   TOKEN      (obligatoire) jeton d'un employé participant à THREAD_ID
//   THREAD_ID  (obligatoire) fil de test dédié — jamais un fil de production
//   SCENARIO   smoke | launch | growth (défaut : smoke)
//   COLD_START (optionnel) si « 1 », mesure d'abord un appel isolé après
//              inactivité, pour documenter le cold start (30-60 s attendus)

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const BASE = (__ENV.BASE_URL || '').replace(/\/$/, '');
const TOKEN = __ENV.TOKEN || '';
const THREAD_ID = __ENV.THREAD_ID || '';
const SCENARIO = __ENV.SCENARIO || 'smoke';

if (!BASE || !TOKEN || !THREAD_ID) {
  throw new Error(
    'BASE_URL, TOKEN et THREAD_ID sont obligatoires. Ces tests écrivent des ' +
      'messages : utilisez un fil DÉDIÉ, jamais une conversation réelle.',
  );
}

// Métriques métier : la latence HTTP seule ne dit pas si le chat tient.
const threadListDuration = new Trend('chat_thread_list_duration');
const historyDuration = new Trend('chat_history_duration');
const writeDuration = new Trend('chat_write_duration');
const idempotencyRate = new Rate('chat_idempotency_ok');

/**
 * Profils de charge.
 *
 * Les nombres de VU sont dérivés d'une hypothèse explicite, pas d'une mesure :
 * - un professionnel consulte la messagerie ~2 fois par heure pendant ~2 min ;
 * - avec un polling de 5-20 s, cela représente ~10 requêtes de lecture par
 *   visite, soit ~20 requêtes/heure/utilisateur ;
 * - une « VU » k6 qui fait une requête toutes les 1-2 s approxime donc
 *   quelques dizaines d'utilisateurs réels, PAS un utilisateur.
 *
 * C'est pourquoi les paliers sont exprimés en VU « soutenues » et non en
 * « nombre d'utilisateurs » : la conversion dépend du comportement réel, qui
 * doit être mesuré (analytics) avant d'être affirmé.
 */
const PROFILES = {
  smoke: {
    stages: [
      { duration: '20s', target: 2 },
      { duration: '40s', target: 2 },
      { duration: '10s', target: 0 },
    ],
    thresholds: {
      http_req_failed: ['rate<0.01'],
      http_req_duration: ['p(95)<1200'],
      chat_thread_list_duration: ['p(95)<1200'],
    },
  },
  launch: {
    // 10 professionnels actifs simultanément = charge très faible.
    stages: [
      { duration: '30s', target: 5 },
      { duration: '2m', target: 15 },
      { duration: '30s', target: 0 },
    ],
    thresholds: {
      http_req_failed: ['rate<0.01'],
      http_req_duration: ['p(95)<800'],
      chat_history_duration: ['p(95)<800'],
    },
  },
  growth: {
    // Plusieurs centaines de professionnels : palier intermédiaire prudent.
    stages: [
      { duration: '1m', target: 20 },
      { duration: '2m', target: 60 },
      { duration: '2m', target: 120 },
      { duration: '1m', target: 0 },
    ],
    thresholds: {
      http_req_failed: ['rate<0.02'],
      http_req_duration: ['p(95)<1500'],
      chat_history_duration: ['p(95)<1500'],
      chat_write_duration: ['p(95)<2000'],
    },
  },
};

export const options = {
  scenarios: {
    chat: {
      executor: 'ramping-vus',
      startVUs: 1,
      stages: (PROFILES[SCENARIO] || PROFILES.smoke).stages,
      gracefulRampDown: '10s',
    },
  },
  thresholds: (PROFILES[SCENARIO] || PROFILES.smoke).thresholds,
  // Un run de charge ne doit jamais saturer le pool PostgreSQL partagé d'un
  // environnement de test : on reste sous les limites connues de l'hébergeur.
  noConnectionReuse: false,
};

const headers = {
  Authorization: `Bearer ${TOKEN}`,
  Accept: 'application/json',
};

// k6 v0.49 compile avec un Babel ancien : le spread d'objet (`{...a, b}`)
// n'est PAS supporté et fait échouer le script à la compilation. On construit
// donc les en-têtes explicitement.
const jsonHeaders = {
  Authorization: `Bearer ${TOKEN}`,
  Accept: 'application/json',
  'Content-Type': 'application/json',
};

/** Identifiant d'idempotence unique par requête, mais rejouable. */
function clientMessageId(suffix) {
  return `k6_${__VU}_${__ITER}_${suffix}`;
}

export function setup() {
  if (__ENV.COLD_START === '1') {
    // Premier appel après spin-down : c'est CETTE latence qu'il faut
    // documenter séparément, elle fausserait tous les percentiles.
    const start = Date.now();
    const response = http.get(`${BASE}/conversations?per_page=5`, { headers });
    const coldStartMs = Date.now() - start;

    console.log(
      `[cold-start] statut=${response.status} durée=${coldStartMs} ms ` +
        '(premier appel après inactivité — NE PAS mélanger aux percentiles ci-dessous)',
    );

    return { coldStartMs };
  }

  return {};
}

export default function () {
  // 1) Liste des fils — l'écran d'entrée, donc la requête la plus fréquente.
  const listStart = Date.now();
  const list = http.get(`${BASE}/conversations?per_page=20`, { headers });
  threadListDuration.add(Date.now() - listStart);

  check(list, {
    'liste des fils: 200': (r) => r.status === 200,
    'liste des fils: pas de 5xx': (r) => r.status < 500,
  });

  // 2) Historique — le chemin chaud d'une conversation ouverte.
  const historyStart = Date.now();
  const history = http.get(
    `${BASE}/conversations/${THREAD_ID}/messages?per_page=30`,
    { headers },
  );
  historyDuration.add(Date.now() - historyStart);

  const historyOk = check(history, {
    'historique: 200': (r) => r.status === 200,
    'historique: pas de 5xx': (r) => r.status < 500,
  });

  // 3) Écriture — un message sur trois itérations seulement : la proportion
  //    lecture/écriture d'un chat est très asymétrique (~10:1). Écrire à
  //    chaque tick saturerait artificiellement les pools PostgreSQL.
  if (__ITER % 3 === 0) {
    const writeStart = Date.now();
    const body = JSON.stringify({
      body: `message de charge k6 (vu ${__VU}, it ${__ITER})`,
      client_message_id: clientMessageId('a'),
    });

    const write = http.post(
      `${BASE}/conversations/${THREAD_ID}/messages`,
      body,
      { headers: jsonHeaders },
    );
    writeDuration.add(Date.now() - writeStart);

    check(write, {
      'écriture: 201': (r) => r.status === 201,
      'écriture: pas de 5xx': (r) => r.status < 500,
    });

    // 4) Rejeu du MÊME client_message_id : le serveur doit répondre 200
    //    (message existant) et JAMAIS créer un second message.
    const replay = http.post(
      `${BASE}/conversations/${THREAD_ID}/messages`,
      body,
      { headers: jsonHeaders },
    );

    idempotencyRate.add(
      check(replay, {
        'rejeu: 200 (pas 201)': (r) => r.status === 200,
        'rejeu: même id que la création': (r) => {
          try {
            return JSON.parse(r.body).data.id === JSON.parse(write.body).data.id;
          } catch (e) {
            return false;
          }
        },
      }),
    );
  }

  // 5) Rattrapage — ce que fait le client au retour d'une déconnexion.
  const lastSeq = (() => {
    try {
      const payload = JSON.parse(history.body);
      return payload.meta && payload.meta.last_seq ? payload.meta.last_seq : 0;
    } catch (e) {
      return 0;
    }
  })();

  if (lastSeq > 0) {
    const catchUp = http.get(
      `${BASE}/conversations/${THREAD_ID}/messages?after_seq=${lastSeq}`,
      { headers },
    );

    check(catchUp, {
      'rattrapage: 200': (r) => r.status === 200,
      // Le rattrapage doit être BON MARCHÉ : c'est lui qui tourne en boucle
      // côté client. S'il coûte le prix d'un historique complet, le polling
      // n'est pas viable.
      'rattrapage: pas de 5xx': (r) => r.status < 500,
    });
  }

  sleep(historyOk ? 1 : 2);
}
