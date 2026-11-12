import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { setupPage } from './helpers';

/**
 * Pruebas de capturas de pantalla OCCRE (SGCM).
 * Genera evidencias visuales (full-page) de las vistas públicas clave en
 * docs/auditoria-visual/playwright/screenshots/.
 *
 * Nota: se usa `domcontentloaded` (no `networkidle`) porque la PWA con
 * Service Worker mantiene conexiones abiertas que impiden que `networkidle`
 * se resuelva.
 */
const SCREENSHOT_DIR = path.join(__dirname, 'screenshots');

test.describe('Capturas de pantalla OCCRE', () => {
  test.beforeAll(() => {
    if (!fs.existsSync(SCREENSHOT_DIR)) {
      fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
    }
  });

  const pages = [
    { name: 'bienvenida', url: '/' },
    { name: 'login', url: '/login' },
    { name: 'tarjeta-turismo', url: '/tarjeta-turismo' },
    { name: 'busqueda-registros', url: '/record-search' },
  ];

  for (const p of pages) {
    test(`captura ${p.name}`, async ({ page }) => {
      await setupPage(page);
      await page.goto(p.url, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('ion-content')).toBeVisible();

      await page.screenshot({
        path: path.join(SCREENSHOT_DIR, `${p.name}.png`),
        fullPage: true,
      });
    });
  }
});
