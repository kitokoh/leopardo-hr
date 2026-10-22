import { onBeforeUnmount, ref } from 'vue'
import { fetchMessages, mergeMessages } from '@/lib/chat-api'

/**
 * #8749 / #8754 [BOS-CHAT] — temps réel par polling adaptatif (client Vue).
 *
 * Miroir fidèle de `front/web/src/components/chat/use-conversation-polling.ts`,
 * qui est le client de référence validé par 6 tests `jest`.
 *
 * Décision d'architecture (audit §6) : **pas** de Reverb, **pas** de nouveau
 * fournisseur payant. L'hébergement actuel ne tient pas de connexion WebSocket
 * longue durée (workers bloqués, spin-down du plan gratuit), et le SSE a été
 * retiré volontairement (#7481) avec une règle opposable : un client d'abord.
 * Ce composable EST ce client pour la console Vue.
 *
 * Comportement :
 *  - **curseur** — on ne redemande jamais l'historique complet : chaque tick
 *    demande `after_seq=<dernier seq connu>` et n'insère que les nouveautés ;
 *  - **adaptatif** — cadence rapide quand le fil bouge, cadence lente quand il
 *    est inactif (moins de requêtes = moins de charge serveur) ;
 *  - **reprise après reconnexion** — après une coupure, la MÊME requête
 *    `after_seq` rapporte tout ce qui a été manqué, car le serveur a persisté
 *    les messages : aucun message perdu par construction ;
 *  - **backoff** — en cas d'échec, l'intervalle double jusqu'à un plafond et
 *    `offline` passe à `true` pour que l'UI l'affiche honnêtement ;
 *  - **pause** — onglet en arrière-plan (`document.hidden`) : aucun appel.
 */

/** Cadences, en millisecondes. Volontairement peu agressives. */
const INTERVAL_ACTIVE_MS = 5000
const INTERVAL_IDLE_MS = 20000
/** Nombre de ticks sans nouveauté avant de passer en cadence lente. */
const IDLE_AFTER_TICKS = 3
/** Plafond du backoff après échec réseau. */
const MAX_BACKOFF_MS = 60000

/**
 * @param {object} options
 * @param {import('vue').Ref<number>} options.threadId fil courant
 * @param {import('vue').Ref<number>} options.lastSeq curseur connu du client
 * @param {import('vue').Ref<boolean>} options.messages liste réactive à enrichir
 * @param {import('vue').Ref<boolean>} [options.enabled]
 * @param {(state: 'live'|'offline') => void} [options.onStateChange]
 */
export function useConversationPolling({ threadId, lastSeq, messages, enabled, onStateChange }) {
  const offline = ref(false)

  let timer = null
  let cursor = lastSeq.value ?? 0
  let idleTicks = 0
  let failures = 0
  let inFlight = false
  let disposed = false

  function setOffline(next) {
    if (offline.value === next) return
    offline.value = next
    onStateChange?.(next ? 'offline' : 'live')
  }

  /** Le parent peut faire avancer le curseur (chargement initial). */
  function syncCursor(value) {
    if (typeof value === 'number' && value > cursor) cursor = value
  }

  /**
   * Changement de fil : le curseur repart de zéro.
   *
   * Sans cette remise à zéro, un fil dont le `seq` est plus petit que celui du
   * fil précédent ne recevrait plus jamais de messages (`after_seq` resterait
   * au-dessus de tous ses `seq`). C'est le bug le plus coûteux de ce type de
   * polling, et il est silencieux : l'UI paraît fonctionner.
   */
  function resetCursor(value = 0) {
    cursor = typeof value === 'number' && value > 0 ? value : 0
    idleTicks = 0
    failures = 0
  }

  function clearTimer() {
    if (timer !== null) {
      clearTimeout(timer)
      timer = null
    }
  }

  async function tick() {
    if (inFlight || disposed) return
    if (!threadId.value) return

    inFlight = true

    try {
      const page = await fetchMessages(threadId.value, {
        after_seq: cursor,
        per_page: 50,
      })

      // Un changement de fil pendant la requête invalide la réponse.
      if (disposed || !threadId.value) return

      failures = 0
      setOffline(false)

      if (page.data.length > 0) {
        idleTicks = 0
        cursor = Math.max(cursor, page.meta.last_seq ?? 0)
        lastSeq.value = cursor
        // Fusion par `id` + tri sur `seq` : aucune insertion en double même si
        // deux ticks se chevauchent, et l'ordre reste celui du serveur.
        messages.value = mergeMessages(messages.value, page.data)
      } else {
        idleTicks += 1
      }
    } catch {
      // Coupure réseau ou serveur indisponible. On NE remonte pas l'erreur :
      // le prochain tick réessaiera et l'UI affiche simplement « hors ligne ».
      failures += 1
      setOffline(true)
    } finally {
      inFlight = false
    }
  }

  function isHidden() {
    return typeof document !== 'undefined' && document.hidden === true
  }

  function schedule() {
    clearTimer()

    if (disposed) return
    if (enabled && !enabled.value) return
    if (isHidden()) return

    let delay = INTERVAL_ACTIVE_MS

    if (failures > 0) {
      delay = Math.min(INTERVAL_ACTIVE_MS * 2 ** failures, MAX_BACKOFF_MS)
    } else if (idleTicks >= IDLE_AFTER_TICKS) {
      delay = INTERVAL_IDLE_MS
    }

    timer = setTimeout(async () => {
      await tick()
      schedule()
    }, delay)
  }

  /** Tick immédiat : après un envoi, ou au retour de l'onglet. */
  async function refreshNow() {
    clearTimer()
    await tick()
    schedule()
  }

  function onVisibility() {
    if (isHidden()) {
      clearTimer()
      return
    }
    // Retour au premier plan : reprise immédiate. C'est exactement le cas
    // « téléphone verrouillé puis rallumé » du test de reconnexion.
    void refreshNow()
  }

  function onOnline() {
    void refreshNow()
  }

  function start() {
    disposed = false
    schedule()

    if (typeof document !== 'undefined') {
      document.addEventListener('visibilitychange', onVisibility)
    }
    if (typeof window !== 'undefined') {
      window.addEventListener('online', onOnline)
    }
  }

  function stop() {
    disposed = true
    clearTimer()

    if (typeof document !== 'undefined') {
      document.removeEventListener('visibilitychange', onVisibility)
    }
    if (typeof window !== 'undefined') {
      window.removeEventListener('online', onOnline)
    }
  }

  onBeforeUnmount(stop)

  return { offline, start, stop, refreshNow, syncCursor, resetCursor }
}
