import { NextResponse } from 'next/server'
import { getStatusSnapshot } from '@/lib/platform-status'

// Page de statut publique (issue #8700).
//
// Le calcul vit dans `@/lib/platform-status` — cette route ne fait que le
// sérialiser. Sortie volontairement NON cachée : un statut figé 5 minutes
// pendant une panne est pire que pas de page de statut du tout (le visiteur
// conclut que tout va bien alors que son écran est cassé).
export const dynamic = 'force-dynamic'
export const revalidate = 0

export async function GET() {
  const snapshot = await getStatusSnapshot()

  return NextResponse.json(snapshot, {
    headers: {
      'Cache-Control': 'no-store, max-age=0',
    },
  })
}
