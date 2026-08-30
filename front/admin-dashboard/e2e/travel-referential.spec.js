import { expect, test } from '@playwright/test'

// TRAVEL-602 (#6079) — Écrans référentiel : onglets, CRUD gare via endpoints
// réels mockés (enveloppe Laravel {data, meta}), i18n fr.
const AUTH_ME = {
  data: { id: 1, name: 'Super Admin', email: 'admin@leopardo-rh.com', role: 'super_admin' },
}

const cities = [
  { id: 1, country_iso2: 'CI', name: 'Abidjan', region: 'Lagunes', status: 'active' },
  { id: 2, country_iso2: 'CI', name: 'Bouaké', region: 'Gbêkê', status: 'active' },
]

function makeList(body) {
  return {
    data: body,
    meta: { current_page: 1, last_page: 1, per_page: 1000, total: body.length },
  }
}

test.describe('Référentiel travel (TRAVEL-602)', () => {
  test('affiche les onglets et liste les gares', async ({ page }) => {
    await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(AUTH_ME) }),
    )
    await page.route(/\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({}) }),
    )
    await page.route(/\/api\/v1\/travel\/countries(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([
        { id: 1, iso2: 'CI', iso3: 'CIV', name: "Côte d'Ivoire", phone_code: 225, status: 'active' },
      ])) }),
    )
    await page.route(/\/api\/v1\/travel\/cities(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList(cities)) }),
    )
    await page.route(/\/api\/v1\/travel\/stations(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([
        { id: 10, code: 'ABJ-GARE', name: 'Gare Adjamé', city_id: 1, address: 'Adjamé', contact_phone: '+225 27 00 00 00', timezone: 'Africa/Abidjan', is_terminal: true, status: 'active' },
      ])) }),
    )
    await page.route(/\/api\/v1\/travel\/offices(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([])) }),
    )
    await page.route(/\/api\/v1\/travel\/carriers(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([])) }),
    )
    await page.route(/\/api\/v1\/travel\/classes(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([])) }),
    )
    await page.route(/\/api\/v1\/travel\/vehicles(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([])) }),
    )
    await page.addInitScript(() => {
      sessionStorage.setItem('admin_token', 'e2e-travel-token')
    })

    await page.goto('/travel/referential')

    await expect(page.getByRole('button', { name: /Gares & terminaux/i })).toBeVisible()
    await page.getByRole('button', { name: /Gares & terminaux/i }).click()
    await expect(page.getByText('Gare Adjamé')).toBeVisible()
    await expect(page.getByText('ABJ-GARE')).toBeVisible()
  })

  test('crée une gare via le formulaire (POST /travel/stations)', async ({ page }) => {
    const stations = [
      { id: 10, code: 'ABJ-GARE', name: 'Gare Adjamé', city_id: 1, address: 'Adjamé', contact_phone: null, timezone: 'Africa/Abidjan', is_terminal: true, status: 'active' },
    ]
    await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(AUTH_ME) }),
    )
    await page.route(/\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({}) }),
    )
    for (const resource of ['countries', 'offices', 'carriers', 'classes', 'vehicles']) {
      await page.route(new RegExp(`/api/v1/travel/${resource}(\\?.*)?$`), (route) =>
        route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([])) }),
      )
    }
    await page.route(/\/api\/v1\/travel\/cities(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList(cities)) }),
    )
    await page.route(/\/api\/v1\/travel\/stations(\?.*)?$/, async (route) => {
      if (route.request().method() === 'POST') {
        const payload = route.request().postDataJSON()
        const created = { id: 11, ...payload, created_at: '2026-08-30T00:00:00Z', updated_at: '2026-08-30T00:00:00Z' }
        stations.push(created)
        await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: created }) })
        return
      }
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList(stations)) })
    })
    await page.addInitScript(() => {
      sessionStorage.setItem('admin_token', 'e2e-travel-token')
    })

    await page.goto('/travel/referential')
    await page.getByRole('button', { name: /Gares & terminaux/i }).click()

    await page.getByRole('button', { name: /Créer/i }).click()
    await page.locator('#field-code').fill('BKE-GARE')
    await page.locator('#field-name').fill('Gare de Bouaké')
    await page.locator('#field-city_id').selectOption('2')
    await page.getByRole('button', { name: /Enregistrer/i }).click()

    await expect(page.getByText('Gare de Bouaké')).toBeVisible({ timeout: 10_000 })
  })
})
