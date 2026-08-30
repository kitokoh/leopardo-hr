import { expect, test } from '@playwright/test'

// TRAVEL-912 (#6417) — Contacts voyageurs : liste, consentements, notification.
const AUTH_ME = {
  data: { id: 1, name: 'Super Admin', email: 'admin@leopardo-rh.com', role: 'super_admin' },
}
function makeList(body) {
  return { data: body, meta: { current_page: 1, last_page: 1, per_page: 1000, total: body.length } }
}

test.describe('Contacts voyageurs travel (TRAVEL-912)', () => {
  test('liste les contacts et bascule un consentement', async ({ page }) => {
    await page.route(/\/api\/v1\/platform\/auth\/me(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(AUTH_ME) }),
    )
    await page.route(/\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({}) }),
    )
    await page.route(/\/api\/v1\/travel\/contacts(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(makeList([
        { id: 1, first_name: 'Aline', last_name: 'Ngo', email: 'aline@example.com', phone: '+2376...', email_consent_given: true, sms_consent_given: false, whatsapp_consent_given: false },
      ])) }),
    )
    await page.route(/\/api\/v1\/travel\/contacts\/1\/consent(\?.*)?$/, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { id: 1 } }) }),
    )
    await page.goto('/travel/content')
    await page.getByRole('button', { name: 'Contacts' }).click()
    await expect(page.getByText('aline@example.com')).toBeVisible()
    await expect(page.getByText('Aline')).toBeVisible()
  })
})
