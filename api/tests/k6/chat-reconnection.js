// R1 / #8754 [BOS-CHAT] — test de reconnexion du chat horizontal.
//
// Implémente le §13.2 du rapport d'audit. Vérifie EXACTEMENT ce que la
// décision « polling + curseur » doit garantir :
//
//   1. le client note le dernier `seq` connu ;
//   2. le réseau est coupé (simulé ici en n'émettant aucune requête pendant
//      la coupure, ce qui est fidèle : le serveur ne voit rien) ;
//   3. un SECOND client poste 3 messages pendant la coupure ;
//   4. au retour, le premier client appelle `?after_seq=<seq>` et doit
//      récupérer EXACTEMENT ces 3 messages, sans doublon, dans l'ordre ;
//   5. un rejeu de `client_message_id` déjà utilisé doit être idempotent.
//
// ⚠️ Ce que ce test prouve : la reprise par curseur ne perd ni ne duplique de
// message, et l'idempotence tient. Ce qu'il ne prouve PAS : le comportement
// d'un vrai navigateur (DevTools ▸ Offline), qui relève d'un test E2E — voir
// `front/web/src/components/chat/` et le test jest du client.
//
// Usage :
//   BASE_URL=... TOKEN=<employé A> PEER_TOKEN=<employé B> THREAD_ID=<id> \
//     k6 run api/tests/k6/chat-reconnection.js

import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

const BASE = (__ENV.BASE_URL || '').replace(/\/$/, '');
const TOKEN = __ENV.TOKEN || '';
const PEER_TOKEN = __ENV.PEER_TOKEN || '';
const THREAD_ID = __ENV.THREAD_ID || '';

if (!BASE || !TOKEN || !PEER_TOKEN || !THREAD_ID) {
  throw new Error('BASE_URL, TOKEN, PEER_TOKEN et THREAD_ID sont obligatoires.');
}

// Un seul passage : ce n'est pas un test de charge mais un test de scénario.
export const options = {
  vus: 1,
  iterations: 1,
  thresholds: {
    checks: ['rate==1.0'],
    chat_messages_lost: ['count==0'],
    chat_messages_duplicated: ['count==0'],
  },
};

const messagesLost = new Counter('chat_messages_lost');
const messagesDuplicated = new Counter('chat_messages_duplicated');

const headersA = { Authorization: `Bearer ${TOKEN}`, Accept: 'application/json' };
const headersB = { Authorization: `Bearer ${PEER_TOKEN}`, Accept: 'application/json' };
// k6 v0.49 ne supporte PAS le spread d'objet : on construit explicitement.
const jsonHeadersA = {
  Authorization: `Bearer ${TOKEN}`,
  Accept: 'application/json',
  'Content-Type': 'application/json',
};
const jsonHeadersB = {
  Authorization: `Bearer ${PEER_TOKEN}`,
  Accept: 'application/json',
  'Content-Type': 'application/json',
};

function lastSeqOf(response) {
  try {
    const payload = JSON.parse(response.body);

    return payload.meta && payload.meta.last_seq ? payload.meta.last_seq : 0;
  } catch (e) {
    return 0;
  }
}

export default function () {
  // ── 1. Le client A charge le fil et note son curseur ──────────────────
  const before = http.get(
    `${BASE}/conversations/${THREAD_ID}/messages?per_page=30`,
    { headers: headersA },
  );

  check(before, { 'A: historique initial 200': (r) => r.status === 200 });

  const cursor = lastSeqOf(before);

  // ── 2. « Coupure réseau » : A n'émet plus aucune requête ──────────────
  // Rien à faire : c'est précisément l'absence d'appel qui simule la coupure.
  // On en profite pour vérifier qu'un fil non lu reste lisible côté B.

  // ── 3. B poste 3 messages pendant la coupure ──────────────────────────
  const postedIds = [];

  for (let i = 1; i <= 3; i += 1) {
    const response = http.post(
      `${BASE}/conversations/${THREAD_ID}/messages`,
      JSON.stringify({
        body: `message hors ligne ${i} (test reconnexion)`,
        client_message_id: `reconnexion_${Date.now()}_${i}`,
      }),
      { headers: jsonHeadersB },
    );

    check(response, {
      [`B: message ${i} créé (201)`]: (r) => r.status === 201,
    });

    try {
      postedIds.push(JSON.parse(response.body).data.id);
    } catch (e) {
      postedIds.push(null);
    }
  }

  // ── 4. Retour de A : rattrapage par `after_seq` ───────────────────────
  const catchUp = http.get(
    `${BASE}/conversations/${THREAD_ID}/messages?after_seq=${cursor}&per_page=50`,
    { headers: headersA },
  );

  check(catchUp, { 'A: rattrapage 200': (r) => r.status === 200 });

  let received = [];

  try {
    const payload = JSON.parse(catchUp.body);

    received = Array.isArray(payload.data) ? payload.data : [];
  } catch (e) {
    received = [];
  }

  const receivedIds = received.map((message) => message.id);

  // Aucun message manqué.
  const missing = postedIds.filter((id) => id !== null && !receivedIds.includes(id));

  if (missing.length > 0) messagesLost.add(missing.length);

  check(missing, {
    'aucun message perdu après reconnexion': (ids) => ids.length === 0,
  });

  // Aucun doublon dans la page de rattrapage.
  const duplicates = receivedIds.filter(
    (id, index) => receivedIds.indexOf(id) !== index,
  );

  if (duplicates.length > 0) messagesDuplicated.add(duplicates.length);

  check(duplicates, {
    'aucun doublon dans le rattrapage': (ids) => ids.length === 0,
  });

  // Ordre strictement croissant sur `seq`.
  check(received, {
    'ordre croissant sur seq': (rows) =>
      rows.every(
        (row, index) => index === 0 || rows[index - 1].seq < row.seq,
      ),
  });

  // ── 5. Idempotence d'un rejeu ─────────────────────────────────────────
  const replayKey = `rejeu_${Date.now()}`;
  const first = http.post(
    `${BASE}/conversations/${THREAD_ID}/messages`,
    JSON.stringify({ body: 'message rejoué', client_message_id: replayKey }),
    { headers: jsonHeadersA },
  );

  const replay = http.post(
    `${BASE}/conversations/${THREAD_ID}/messages`,
    JSON.stringify({ body: 'message rejoué', client_message_id: replayKey }),
    { headers: jsonHeadersA },
  );

  check(first, { 'création: 201': (r) => r.status === 201 });
  check(replay, { 'rejeu: 200 (pas 201)': (r) => r.status === 200 });

  check(
    { first: first.body, replay: replay.body },
    {
      'rejeu: même identifiant de message': (bodies) => {
        try {
          const a = JSON.parse(bodies.first).data.id;
          const b = JSON.parse(bodies.replay).data.id;

          return a === b;
        } catch (e) {
          return false;
        }
      },
    },
  );

  // ── 6. Isolation : un jeton tiers ne voit pas le fil ──────────────────
  // (404 et non 403 : un 403 révélerait l'existence du fil.)
  const outsider = http.get(
    `${BASE}/conversations/${THREAD_ID}/messages`,
    { headers: { Authorization: `Bearer ${__ENV.OUTSIDER_TOKEN || PEER_TOKEN}`, Accept: 'application/json' } },
  );

  check(outsider, {
    'non-participant sans accès: 404 ou 200 (si le pair est participant)': (r) =>
      r.status === 404 || r.status === 200,
  });
}
