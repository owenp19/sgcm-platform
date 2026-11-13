import { test, expect } from '@playwright/test';
import { setupPage } from './helpers';

/**
 * Pruebas de navegación OCCRE (SGCM).
 * Valida que las rutas públicas carguen y que las transiciones entre vistas
 * funcionen correctamente, sin modificar la interfaz ni requerir autenticación.
 *
 * Nota: se usa `domcontentloaded` (no `networkidle`) porque la PWA con
 * Service Worker mantiene conexiones abiertas que impiden que `networkidle`
 * se resuelva.
 */
test.describe('Navegación OCCRE', () => {
  test.beforeEach(async ({ page }) => {
    await setupPage(page);
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('ion-content')).toBeVisible();
  });

  test('carga la página de bienvenida con logo, título y botón de inicio', async ({
    page,
  }) => {
    await expect(page).toHaveURL('/welcome');
    await expect(page.locator('img.welcome-logo')).toBeVisible();
    await expect(page.locator('#welcome-title')).toBeVisible();
    await expect(page.locator('.welcome-button')).toBeVisible();
    await expect(page.locator('.welcome-background__image').first()).toBeVisible();
  });

  test('navega de bienvenida a login al pulsar el botón de inicio', async ({
    page,
  }) => {
    await page.locator('.welcome-button').click();
    await expect(page).toHaveURL('/login');
    await expect(page.locator('ion-button.login-btn button')).toBeVisible();
    await expect(page.locator('ion-content.login-page')).toBeVisible();
  });

  test('navega de login al formulario de registro', async ({ page }) => {
    await setupPage(page);
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('ion-content')).toBeVisible();
    await page.locator('.register-btn').click();
    await expect(page).toHaveURL('/register');
    await expect(page.locator("ion-input[autocomplete='given-name']")).toBeVisible();
  });

  test('navega de login a la tarjeta de turismo (acceso visitante)', async ({
    page,
  }) => {
    await setupPage(page);
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('ion-content')).toBeVisible();
    await page.locator('.visitor-access-btn').click();
    await expect(page).toHaveURL('/tarjeta-turismo');
    await expect(page.locator('ion-content.tourism-page')).toBeVisible();
  });

  test('la ruta pública de búsqueda de registros es accesible', async ({ page }) => {
    await setupPage(page);
    await page.goto('/record-search', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('ion-content')).toBeVisible();
  });

  test('una ruta desconocida redirige a la página de bienvenida', async ({
    page,
  }) => {
    await setupPage(page);
    await page.goto('/ruta-inexistente-12345', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL('/welcome');
    await expect(page.locator('.welcome-button')).toBeVisible();
  });
});
