import { Page } from '@playwright/test';

/**
 * Prepara una página de Playwright para las pruebas E2E de OCCRE.
 *
 * Bloquea el registro del Service Worker de Angular (PWA) para evitar que
 * mantenga conexiones de actualización abiertas, lo cual impediría el cierre
 * limpio del contexto de Chromium al finalizar cada prueba (teardown
 * timeout). Sin este bloqueo, algunas pruebas exceden el test.timeout al
 * desmontar el contexto del navegador.
 *
 * Debe invocarse al inicio de cada prueba, antes de cualquier `page.goto`.
 */
export async function setupPage(page: Page): Promise<void> {
  await page.route('**/ngsw-worker.js', (route) => route.abort());
  await page.route('**/ngsw.json', (route) => route.abort());
}
