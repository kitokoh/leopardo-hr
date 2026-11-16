import { test, expect } from '@playwright/test';

test('la langue choisie survit à la navigation dans la Toolbox', async ({ page }) => {
  await page.goto('/toolbox?lang=tr');
  await expect(page.locator('html')).toHaveAttribute('lang', 'tr');

  // Le sélecteur existe et affiche la langue servie.
  const select = page.locator('#toolbox-lang');
  await expect(select).toHaveValue('tr');

  // Clic réel sur une carte d'outil.
  await page.locator('a[href="/toolbox/imc?lang=tr"]').first().click();
  await page.waitForURL(/\/toolbox\/imc/);
  await expect(page.locator('html')).toHaveAttribute('lang', 'tr');
  await expect(page.locator('#toolbox-lang')).toHaveValue('tr');

  // Retour au catalogue via le lien de la page d'outil.
  await page.locator('a[href="/toolbox?lang=tr"]').first().click();
  await page.waitForURL(/\/toolbox(\?|$)/);
  await expect(page.locator('html')).toHaveAttribute('lang', 'tr');
});

test('le sélecteur bascule réellement la langue', async ({ page }) => {
  // Sans ?lang=, la locale vient d'Accept-Language (Chromium envoie en-US) :
  // on ne présume pas du français, on lit la valeur réellement servie.
  await page.goto('/toolbox');
  const initial = await page.locator('html').getAttribute('lang');
  expect(['fr', 'en', 'tr', 'ar']).toContain(initial);
  await page.selectOption('#toolbox-lang', 'ar');
  await page.waitForURL(/lang=ar/);
  await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
});
