/**
 * Environnement Node obligatoire : jsdom n'expose ni `Request` ni `Response`,
 * dont dépend `next/server`. Même contrainte que `lead-capture.test.ts`.
 * @jest-environment node
 */
// #7594 — barrières anti-bot des routes publiques `/api/forms/*`.
//
// Ce que ces tests verrouillent :
//   1. le honeypot rejette SEULEMENT s'il est rempli (les appelants qui ne le
//      rendent pas encore ne sont pas pénalisés) ;
//   2. le time-trap est RÉEL : une soumission plus rapide qu'humainement
//      possible est rejetée — c'était le contrôle factice de l'audit ;
//   3. un signal ABSENT n'est pas un signal négatif (choix documenté) ;
//   4. un `Origin`/`Referer` étranger est rejeté, l'origine propre ne l'est pas ;
//   5. le rejet est SILENCIEUX : même forme qu'un succès, mais rien n'est
//      persisté et aucun identifiant n'est renvoyé.

import { NextRequest } from 'next/server';
import {
  FORM_RENDERED_AT_FIELD,
  HONEYPOT_FIELD,
  MIN_FILL_DURATION_MS,
  allowedHosts,
  evaluateSpam,
  forbiddenOriginResponse,
  readSpamSignals,
  spamResponse,
} from '../antispam';

const SITE = 'https://leopardo.example.com';

function makeRequest(path = '/api/forms/contact', headers: Record<string, string> = {}): NextRequest {
  return new NextRequest(new Request(`${SITE}${path}`, { method: 'POST', headers }));
}

/** Horodatage d'un formulaire « rendu il y a `ms` millisecondes ». */
function renderedAgo(ms: number): string {
  return new Date(Date.now() - ms).toISOString();
}

describe('anti-spam des formulaires publics (#7594)', () => {
  describe('lecture des signaux', () => {
    it('lit le honeypot et le timestamp du corps brut', () => {
      const signals = readSpamSignals({
        email: 'a@b.co',
        [HONEYPOT_FIELD]: '  https://spam.example  ',
        [FORM_RENDERED_AT_FIELD]: '2026-09-17T00:00:00.000Z',
      });

      expect(signals.honeypot).toBe('https://spam.example');
      expect(signals.renderedAt).toBe('2026-09-17T00:00:00.000Z');
    });

    it('tolère un corps inattendu sans lever', () => {
      for (const body of [null, undefined, 'texte', 42, ['a']]) {
        expect(() => readSpamSignals(body)).not.toThrow();
        expect(readSpamSignals(body).honeypot).toBe('');
      }
    });

    it('ignore un honeypot non textuel', () => {
      expect(readSpamSignals({ [HONEYPOT_FIELD]: 12345 }).honeypot).toBe('');
    });
  });

  describe('honeypot', () => {
    it('rejette un honeypot rempli', () => {
      const verdict = evaluateSpam(
        { honeypot: 'https://spam.example' },
        makeRequest(),
      );

      expect(verdict).toEqual({ spam: true, reason: 'honeypot' });
    });

    it('n’est PAS un signal négatif quand le champ est absent ou vide', () => {
      for (const honeypot of ['', '   ']) {
        expect(evaluateSpam({ honeypot }, makeRequest())).toEqual({ spam: false });
      }
    });
  });

  describe('time-trap (le contrôle qui était factice)', () => {
    it('rejette une soumission instantanée', () => {
      const verdict = evaluateSpam(
        { honeypot: '', renderedAt: renderedAgo(200) },
        makeRequest(),
      );

      expect(verdict).toEqual({ spam: true, reason: 'too-fast' });
    });

    it('accepte une soumission au-delà du délai minimal', () => {
      const verdict = evaluateSpam(
        { honeypot: '', renderedAt: renderedAgo(MIN_FILL_DURATION_MS + 1000) },
        makeRequest(),
      );

      expect(verdict).toEqual({ spam: false });
    });

    it('rejette un horodatage illisible', () => {
      const verdict = evaluateSpam(
        { honeypot: '', renderedAt: 'pas-une-date' },
        makeRequest(),
      );

      expect(verdict).toEqual({ spam: true, reason: 'bad-timestamp' });
    });

    it('rejette une horloge cliente aberrante dans le futur', () => {
      const verdict = evaluateSpam(
        { honeypot: '', renderedAt: new Date(Date.now() + 6 * 60 * 60 * 1000).toISOString() },
        makeRequest(),
      );

      expect(verdict).toEqual({ spam: true, reason: 'bad-timestamp' });
    });

    it('n’est PAS un signal négatif quand le rendu n’est pas mesuré (choix documenté)', () => {
      expect(evaluateSpam({ honeypot: '' }, makeRequest())).toEqual({ spam: false });
    });

    // ⚠️ Régression attrapée en câblant #7594 : les fronts posaient déjà un
    // `timestamp`, mais AU MOMENT DE LA SOUMISSION. Le brancher au time-trap
    // rejetait tous les vrais utilisateurs (écart nul). Le champ dédié au rendu
    // est `form_rendered_at` — ce test interdit de confondre les deux.
    it('ignore le timestamp de SOUMISSION : le trap ne lit que le rendu', () => {
      const body = { timestamp: new Date().toISOString() };
      const signals = readSpamSignals(body);

      expect(signals.renderedAt).toBeUndefined();
      expect(evaluateSpam(signals, makeRequest())).toEqual({ spam: false });
    });
  });

  describe('contrôle d’origine', () => {
    it('accepte l’origine du site lui-même', () => {
      const verdict = evaluateSpam(
        { honeypot: '' },
        makeRequest('/api/forms/contact', { origin: SITE }),
      );

      expect(verdict).toEqual({ spam: false });
    });

    it('accepte un Referer du site quand Origin est absent', () => {
      const verdict = evaluateSpam(
        { honeypot: '' },
        makeRequest('/api/forms/contact', { referer: `${SITE}/contact` }),
      );

      expect(verdict).toEqual({ spam: false });
    });

    it('rejette une origine étrangère', () => {
      const verdict = evaluateSpam(
        { honeypot: '' },
        makeRequest('/api/forms/contact', { origin: 'https://spam.example' }),
      );

      expect(verdict).toEqual({ spam: true, reason: 'bad-origin' });
    });

    it('n’est PAS un signal négatif quand aucun en-tête d’origine n’est envoyé', () => {
      expect(evaluateSpam({ honeypot: '' }, makeRequest())).toEqual({ spam: false });
    });

    it('construit les hôtes autorisés depuis la requête, sans domaine codé en dur', () => {
      const hosts = allowedHosts(makeRequest());

      expect(hosts).toContain('leopardo.example.com');
      expect(hosts.join(' ')).not.toMatch(/kitokoh|leopardo-hr|vercel\.app/);
    });
  });

  describe('rejet silencieux', () => {
    it('répond 201 comme un succès, mais sans identifiant', async () => {
      const response = spamResponse();
      const body = await response.json();

      expect(response.status).toBe(201);
      expect(body.success).toBe(true);
      expect(body.data).toBeUndefined();
    });

    it('réserve un 403 explicite au contrôle d’origine', async () => {
      const response = forbiddenOriginResponse();

      expect(response.status).toBe(403);
      expect((await response.json()).error).toBe('ORIGIN_NOT_ALLOWED');
    });
  });
});
