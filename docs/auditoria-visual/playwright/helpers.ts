import { Page } from '@playwright/test';

/**
 * Prepara una página de Playwright para las pruebas E2E de OCCRE.
 *
 * 1. Bloquea el registro del Service Worker de Angular (PWA) para evitar que
 *    mantenga conexiones de actualización abiertas, lo cual impediría el cierre
 *    limpio del contexto de Chromium al finalizar cada prueba (teardown
 *    timeout).
 * 2. Bloquea recursos externos no esenciales (Google Fonts) para acelerar la
 *    carga de la página y evitar timeouts de navegación.
 *
 * Debe invocarse al inicio de cada prueba, antes de cualquier `page.goto`.
 */
export async function setupPage(page: Page): Promise<void> {
  // Bloquear Service Worker de Angular (PWA)
  await page.route('**/ngsw-worker.js', (route) => route.abort());
  await page.route('**/ngsw.json', (route) => route.abort());
  // Bloquear fuentes externas (Google Fonts) para acelerar la carga
  await page.route('**/fonts.googleapis.com/**', (route) => route.abort());
  await page.route('**/fonts.gstatic.com/**', (route) => route.abort());
}
