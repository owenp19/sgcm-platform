import { test, expect } from '@playwright/test';
import { setupPage } from './helpers';

/**
 * Pruebas de formularios OCCRE (SGCM).
 * Valida el comportamiento de los campos del formulario de login (habilitado/
 * deshabilitado del botón según validación) sin enviar datos al backend ni
 * requerir autenticación.
 *
 * Nota: se usa `domcontentloaded` (no `networkidle`) porque la PWA con
 * Service Worker mantiene conexiones abiertas que impiden que `networkidle`
 * se resuelva. El estado enabled/disabled del botón Ionic se verifica sobre
 * el elemento `<button>` nativo interno del componente `ion-button`
 * (atravesando shadow DOM), que es donde Ionic refleja el atributo
 * `disabled`.
 */
test.describe('Formularios OCCRE', () => {
  test.beforeEach(async ({ page }) => {
    await setupPage(page);
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('ion-content.login-page')).toBeVisible();
  });

  const emailInput = (page: any) =>
    page.locator('ion-input[inputmode="email"] input');
  const passwordInput = (page: any) =>
    page.locator("ion-input[autocomplete='current-password'] input");
  const loginBtnNative = (page: any) =>
    page.locator('ion-button.login-btn button');

  test('el botón de inicio de sesión inicia deshabilitado con campos vacíos', async ({
    page,
  }) => {
    await expect(loginBtnNative(page)).toBeDisabled();
  });

  test('habilita el botón al completar email y contraseña válida (>= 6 caracteres)', async ({
    page,
  }) => {
    await emailInput(page).fill('usuario@ejemplo.com');
    await expect(loginBtnNative(page)).toBeDisabled(); // contraseña vacía

    await passwordInput(page).fill('contraseña123');
    await expect(loginBtnNative(page)).toBeEnabled();
  });

  test('requiere contraseña de al menos 6 caracteres', async ({ page }) => {
    await emailInput(page).fill('usuario@ejemplo.com');

    await passwordInput(page).fill('12345'); // 5 caracteres: inválido
    await expect(loginBtnNative(page)).toBeDisabled();

    await passwordInput(page).fill('123456'); // 6 caracteres: válido
    await expect(loginBtnNative(page)).toBeEnabled();
  });

  test('al escribir en el campo de email se actualiza el valor', async ({
    page,
  }) => {
    await emailInput(page).fill('contacto@occre.gov.co');
    await expect(emailInput(page)).toHaveValue('contacto@occre.gov.co');
  });

  test('existe el enlace para recuperar contraseña', async ({ page }) => {
    await expect(page.locator('.forgot-row button')).toBeVisible();
  });

  test('navega a la vista de recuperar contraseña', async ({ page }) => {
    await page.locator('.forgot-row button').click();
    await expect(page).toHaveURL('/recover-password');
    await expect(page.locator('ion-content.recover-page')).toBeVisible();
  });
});
