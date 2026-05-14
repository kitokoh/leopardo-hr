import { expect, test } from '@playwright/test'

test.describe('Recruitment flow', () => {
  test('creates a job, opens the pipeline, and advances an applicant with mocked API', async ({ page }) => {
    const corsHeaders = {
      'Access-Control-Allow-Origin': 'http://127.0.0.1:4173',
      'Access-Control-Allow-Headers': 'authorization, content-type, accept',
      'Access-Control-Allow-Methods': 'GET, POST, PATCH, OPTIONS',
    }
    const fulfillJson = (route, body, status = 200) => route.fulfill({
      status,
      contentType: 'application/json',
      headers: corsHeaders,
      body: JSON.stringify(body),
    })
    const handleOptions = (route) => {
      if (route.request().method() === 'OPTIONS') {
        route.fulfill({ status: 204, headers: corsHeaders })
        return true
      }
      return false
    }
    const jobs = [
      {
        id: 1,
        title: 'Serveur senior',
        location: 'Alger',
        status: 'published',
        created_at: '2026-05-14',
      },
    ]
    const applicants = [
      {
        id: 10,
        first_name: 'Nadia',
        last_name: 'Belaid',
        email: 'nadia@example.test',
        status: 'new',
        applied_at: '2026-05-14',
      },
    ]

    await page.route(/\/platform\/auth\/login(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      await fulfillJson(route, {
          token: 'playwright-admin-token',
          data: {
            id: 1,
            name: 'Admin',
            email: 'admin@example.test',
            role: 'super_admin',
          },
      })
    })

    await page.route(/\/platform\/auth\/me(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      await fulfillJson(route, {
          data: {
            id: 1,
            name: 'Admin',
            email: 'admin@example.test',
            role: 'super_admin',
          },
      })
    })

    await page.route(/\/api\/v1\/recruitment\/jobs(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      if (route.request().method() === 'POST') {
        const payload = route.request().postDataJSON()
        jobs.push({
          id: 2,
          title: payload.title,
          location: payload.location,
          status: 'draft',
          created_at: '2026-05-14',
        })
        await fulfillJson(route, { data: jobs.at(-1) }, 201)
        return
      }

      await fulfillJson(route, { data: jobs })
    })

    await page.route(/\/api\/v1\/recruitment\/jobs\/\d+\/applicants(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      const jobId = Number(route.request().url().match(/\/jobs\/(\d+)\/applicants/)?.[1])
      await fulfillJson(route, { data: jobId === 1 ? applicants : [] })
    })

    await page.route(/\/api\/v1\/recruitment\/applicants\/\d+\/status(?:\?.*)?$/, async (route) => {
      if (handleOptions(route)) return
      const payload = route.request().postDataJSON()
      applicants[0].status = payload.status
      await fulfillJson(route, { data: applicants[0] })
    })

    await page.addInitScript(() => {
      localStorage.setItem('admin_token', 'playwright-admin-token')
    })

    await page.goto('/recruitment')
    await page.getByRole('button', { name: /Postes/i }).click()
    await page.getByPlaceholder(/Intitule du poste/i).fill('Chef de rang')
    await page.getByPlaceholder(/Lieu/i).fill('Oran')
    await page.getByRole('button', { name: /Creer le poste/i }).click()

    await expect(page.getByText('Chef de rang')).toBeVisible()

    await page.getByRole('button', { name: /Pipeline Kanban/i }).click()
    await expect(page.getByText('Nadia Belaid').first()).toBeVisible()
    await page.getByRole('button', { name: /Avancer/i }).click()

    await expect(page.getByText('Nadia Belaid').first()).toBeVisible()
    expect(applicants[0].status).toBe('screening')
  })
})
