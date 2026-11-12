# AGENTS.md - OCCRE

## Instrucciones para agentes OpenCode

Este archivo guía a los agentes de OpenCode sobre cómo trabajar con el proyecto OCCRE.

### Memoria persistente (OBLIGATORIO)
**Al inicio de CADA sesión:**
1. Leer `docs/ai-memory/project-overview.md`
2. Leer `docs/ai-memory/architecture.md`
3. Leer `docs/ai-memory/current-state.md`
4. Leer `docs/ai-memory/decisions.md`
5. Leer `docs/ai-memory/pending-work.md`
6. Leer `docs/ai-memory/session-checkpoints.md` (último checkpoint)

**Al finalizar tareas significativas:**
- Actualizar `docs/ai-memory/current-state.md`
- Actualizar `docs/ai-memory/decisions.md` (solo decisiones aprobadas)
- Actualizar `docs/ai-memory/session-checkpoints.md` (añadir checkpoint)
- Actualizar `docs/ai-memory/completed-work.md` / `docs/ai-memory/pending-work.md`

**REGLAS:** NO guardar secretos, keys, .env, datos personales. Solo hechos verificables.

### Selección automática de skills
Según intención de solicitud, cargar bajo demanda:

| Palabra clave / Intención | Skills a cargar |
|---|---|
| Angular, componente, módulo, servicio, routing Angular | `occre-angular-expert`, `occre-typescript-quality`, `occre-frontend-architecture` |
| Ionic, Capacitor, navegación Ionic, ion-*, nativo móvil | `occre-ionic-expert`, `occre-ionic-ux-accessibility` |
| Laravel, controlador, Eloquent, Request, Resource, Policy, Middleware | `occre-laravel-expert`, `occre-laravel-api` |
| MySQL, consulta SQL, índice, EXPLAIN, migración análisis | `occre-mysql-expert`, `occre-database-security`, `occre-database-performance` |
| API, endpoint, REST, JWT, validación API | `occre-api-security`, `occre-laravel-expert` |
| Seguridad, vulnerabilidad, autenticación, autorización, OWASP | `occre-security-baseline`, `occre-api-security`, `occre-database-security` |
| UI/UX, diseño, colores, tipografía, identidad visual | `occre-design-reference`, `frontend-design`, `ui-ux-pro-max` |
| Responsive, tablet, desktop, móvil responsive | `occre-responsive-multiplatform`, `occre-ionic-expert` |
| Rendimiento, optimización, bundle, N+1, latencia | `occre-performance`, `occre-api-performance` |
| Pruebas, test, QA, verificación | `occre-testing-quality`, `occre-accessibility-testing` |
| Accesibilidad, a11y, WCAG | `occre-ionic-ux-accessibility`, `occre-accessibility-testing` |

**Principio:** Combinar ÚNICAMENTE skills necesarias. NO cargar todas simultáneamente.

### Ahorro de tokens
- Consultar memoria ANTES de investigar
- Leer solo archivos relevantes (usar glob/grep)
- Skills bajo demanda
- Búsquedas dirigidas
- Respuestas concisas
- Reutilizar resultados verificados
- Evitar MCP redundantes
- Checkpoints compactos
- NUNCA sacrificar seguridad o pruebas necesarias

### Principios obligatorios
- NO modificar funcionalidades, interfaces, autenticación, datos ni lógica institucional
- NO ejecutar migraciones, NO modificar tablas/datos
- NO modificar `.env`, configuración de auth ni datos sensibles
- Trabajar con el menor número de tokens posible
- Verificar siempre antes de hacer cambios
- Conservar identidad visual (paleta global, colores sólidos, SIN gradientes)
- Seguir versiones instaladas: Angular 20, Ionic 8, Laravel 12, MySQL, Capacitor 8

### Skills disponibles
Las skills locales se encuentran en `.opencode/skills/`. Usa las skills pertinentes a la tarea.

### Notas
- Backend: Laravel 12 (PHP 8.2) en `backend/`
- Frontend: Angular 20 + Ionic 8 + TypeScript 5.9 en raíz
- Android: Capacitor 8
- MySQL
- API REST con autenticación JWT
- Memoria: `docs/ai-memory/`
- Compactación nativa OpenCode habilitada via configuración
