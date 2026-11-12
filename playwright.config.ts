import { defineConfig, devices } from '@playwright/test';

/**
 * Configuración de Playwright para el proyecto OCCRE (SGCM).
 * Pruebas E2E automatizadas sobre la aplicación Angular 20 + Ionic 8.
 *
 * El servidor de desarrollo se gestiona con `webServer`:
 *  - Si ya hay un servidor en el puerto 8100 (ej. `npm run dev` o `npm start`),
 *    lo reutiliza (`reuseExistingServer: true`) y NO lo detiene.
 *  - Si no, arranca `npm start` (`ng serve`) y lo detiene al terminar.
 *
 * Chromium se ejecuta en modo headed por defecto para permitir la inspección
 * visual de la aplicación. Para ejecución headless (CI) usar:
 *   npx playwright test --headless
 */

export default defineConfig({
  testDir: 'docs/auditoria-visual/playwright',
  snapshotDir: 'docs/auditoria-visual/playwright/snapshots',
  outputDir: 'docs/auditoria-visual/playwright/test-results',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  timeout: 90000, // Margen para el teardown del contexto (Service Worker de PWA)
  reporter: process.env.CI ? 'github' : 'list',

  use: {
    baseURL: 'http://localhost:8100',
    browserName: 'chromium',
    headless: false, // Modo headed para inspección visual de la aplicación
    channel: 'chrome',
    viewport: { width: 375, height: 667 }, // Móvil por defecto (Ionic)
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
    actionTimeout: 10000,
    navigationTimeout: 30000,
    launchOptions: {
      args: ['--no-sandbox', '--disable-setuid-sandbox'],
    },
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  webServer: {
    command: 'npm start',
    port: 8100,
    reuseExistingServer: true,
    timeout: 180000,
    alreadyRunningError: '',
  },
});
