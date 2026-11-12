import { test, expect } from '@playwright/test';
import { setupPage } from './helpers';

/**
 * Pruebas de diseño responsivo OCCRE (SGCM).
 * Valida que las vistas principales se rendericen correctamente en los
 * breakpoints móvil, tablet y desktop sin romper la disposición.
 *
 * Nota: se usa `domcontentloaded` (no `networkidle`) porque la PWA con
 * Service Worker mantiene conexiones abiertas que impiden que `networkidle`
 * se resuelva.
 */
test.describe('Diseño responsivo OCCRE', () => {
  const viewports = [
    { name: 'móvil', width: 375, height: 667 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1280, height: 800 },
  ];

  for (const vp of viewports) {
    test(`renderiza la bienvenida en ${vp.name} (${vp.width}x${vp.height})`, async ({
      page,
    }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await setupPage(page);
      await page.goto('/', { waitUntil: 'domcontentloaded' });
      await expect(page.locator('ion-content')).toBeVisible();

      await expect(page.locator('img.welcome-logo')).toBeVisible();
      await expect(page.locator('#welcome-title')).toBeVisible();
      await expect(page.locator('.welcome-button')).toBeVisible();
    });
  }

  test('el formulario de login se adapta a móvil y desktop', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await setupPage(page);
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('ion-content.login-page')).toBeVisible();

    await expect(page.locator('ion-button.login-btn button')).toBeVisible();
    await expect(page.locator('ion-input[inputmode="email"] input')).toBeVisible();
    await expect(
      page.locator("ion-input[autocomplete='current-password'] input")
    ).toBeVisible();

    // Cambio a desktop: los mismos elementos deben seguir visibles y funcionales
    await page.setViewportSize({ width: 1280, height: 800 });
    await expect(page.locator('ion-button.login-btn button')).toBeVisible();
    await expect(page.locator('.register-btn')).toBeVisible();
  });
});
