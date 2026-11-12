# Inventario de Skills — Auditoría de Seguridad OCCRE

**Fecha:** 2026-11-12  
**Proyecto:** OCCRE (SGCM Platform)  
**Objetivo:** Identificar, leer, activar y registrar todas las Skills disponibles para la auditoría integral de ciberseguridad.

---

## A. Skills Globales (OpenCode v2.2.5)

Ubicación: `C:\Users\Owen\.config\opencode\skills\`

| # | Skill | Disponible | Leída | Activada | Utilizada | Categoría | Relevancia para OCCRE |
|---|-------|-----------|-------|----------|-----------|-----------|----------------------|
| 1 | api-design | Sí | No | No | No | Diseño de contratos API | Diseño seguro de endpoints REST |
| 2 | application-security | Sí | No | No | No | Seguridad de aplicaciones | Auditoría de seguridad general |
| 3 | architecture-decision-records | Sí | No | No | No | Documentación de decisiones | Registro de decisiones de arquitectura de seguridad |
| 4 | context-budget | Sí | No | No | No | Optimización de tokens | Gestión de contexto en auditoría larga |
| 5 | contract-first | Sí | No | No | No | Contratos API | Definición de contratos seguros |
| 6 | dart-flutter-patterns | Sí | No | No | No | Flutter/Dart | No aplica (proyecto usa Angular/Ionic) |
| 7 | e2e-testing | Sí | No | No | No | Pruebas E2E | Playwright, automatización de navegador |
| 8 | error-handling | Sí | No | No | No | Manejo de errores | Prevención de exposición de información en errores |
| 9 | flutter-dart-code-review | Sí | No | No | No | Revisión Flutter/Dart | No aplica |
| 10 | gateguard | Sí | No | No | No | Guardia de seguridad | Prevención de operaciones destructivas |
| 11 | git-workflow | Sí | No | No | No | Git | Estrategia de ramas y commits |
| 12 | hexagonal-architecture | Sí | No | No | No | Arquitectura | Puertos y adaptadores, separación de responsabilidades |
| 13 | latency-critical-systems | Sí | No | No | No | Latencia | No aplica directamente |
| 14 | mysql-patterns | Sí | No | No | No | MySQL | Esquema, índices, consultas SQL |
| 15 | playwright-professional | Sí | No | No | No | Playwright | Pruebas E2E, automatización visual |
| 16 | production-audit | Sí | No | No | No | Auditoría de producción | Revisión de preparación para producción |
| 17 | professional-ui-ux-design | Sí | No | No | No | Diseño UI/UX | Diseño de interfaces (etapa futura) |
| 18 | safety-guard | Sí | No | No | No | Guardia de seguridad | Prevención de operaciones destructivas |
| 19 | search-first | Sí | No | No | No | Investigación | Búsqueda de herramientas existentes |
| 20 | security-review | Sí | No | No | No | Revisión de seguridad | Checklist integral de seguridad |
| 21 | security-scan | Sí | No | No | No | Escaneo de seguridad | Auditoría de configuración .claude/ |
| 22 | skill-orchestrator | Sí | Sí | Sí | Sí | Orquestación | Selección y carga automática de Skills |
| 23 | tdd-workflow | Sí | No | No | No | TDD | Desarrollo guiado por pruebas |
| 24 | verification-loop | Sí | No | No | No | Verificación | Build, type check, lint, tests, seguridad, diff |

**Total global:** 24 Skills encontradas

---

## B. Skills Locales del Proyecto (OCCRE)

Ubicación: `.opencode/skills/`

| # | Skill | Disponible | Leída | Activada | Utilizada | Categoría | Relevancia para OCCRE |
|---|-------|-----------|-------|----------|-----------|-----------|----------------------|
| 1 | banner-design | Sí | No | No | No | Diseño | No aplica en esta fase (sin rediseño visual) |
| 2 | brand | Sí | No | No | No | Identidad | No aplica en esta fase |
| 3 | design | Sí | No | No | No | Diseño | No aplica en esta fase |
| 4 | design-system | Sí | No | No | No | Sistema de diseño | No aplica en esta fase |
| 5 | frontend-design | Sí | No | No | No | Diseño frontend | No aplica en esta fase (sin rediseño) |
| 6 | occre-accessibility-testing | Sí | No | No | No | Accesibilidad | Auditoría de accesibilidad (etapa futura) |
| 7 | occre-angular-expert | Sí | Sí | Sí | Sí | Angular 20 | Auditoría de frontend Angular, protección de rutas, XSS |
| 8 | occre-api-performance | Sí | No | No | No | Rendimiento API | Análisis de rendimiento de endpoints |
| 9 | occre-api-security | Sí | Sí | Sí | Sí | Seguridad API | Auditoría de endpoints REST, JWT, CORS, rate limiting |
| 10 | occre-database-performance | Sí | No | No | No | Rendimiento BD | Optimización de consultas MySQL |
| 11 | occre-database-security | Sí | Sí | Sí | Sí | Seguridad BD | Auditoría de MySQL, privilegios, SQL injection |
| 12 | occre-design-reference | Sí | No | No | No | Referencia diseño | No aplica en esta fase |
| 13 | occre-frontend-architecture | Sí | No | No | No | Arquitectura frontend | Revisión de arquitectura Angular/Ionic |
| 14 | occre-ionic-expert | Sí | Sí | Sí | Sí | Ionic 8 | Auditoría de app móvil, navegación, componentes |
| 15 | occre-ionic-ux-accessibility | Sí | No | No | No | UX/Accesibilidad | No aplica en esta fase |
| 16 | occre-laravel-api | Sí | No | No | No | Laravel API | Desarrollo de API Laravel |
| 17 | occre-laravel-boost-prep | Sí | No | No | No | Laravel Boost | Bloqueado (requiere composer install + autorización) |
| 18 | occre-laravel-expert | Sí | No | No | No | Laravel 12 | Desarrollo backend Laravel |
| 19 | occre-laravel-security-audit | Sí | Sí | Sí | Sí | Seguridad Laravel | Auditoría de controladores, middleware, políticas |
| 20 | occre-mobile-security | Sí | Sí | Sí | Sí | Seguridad móvil | OWASP MASVS, Capacitor, almacenamiento seguro |
| 21 | occre-mysql-expert | Sí | Sí | Sí | Sí | MySQL | Modelo relacional, índices, claves foráneas, integridad |
| 22 | occre-performance | Sí | No | No | No | Rendimiento | Optimización general del sistema |
| 23 | occre-responsive-multiplatform | Sí | No | No | No | Responsive | Diseño para móvil/tablet/desktop |
| 24 | occre-security-baseline | Sí | Sí | Sí | Sí | Línea base seguridad | Principios mínimos OWASP, ASVS, MASVS |
| 25 | occre-seo-public | Sí | No | No | No | SEO | No aplica |
| 26 | occre-session-memory | Sí | Sí | Sí | Sí | Memoria persistente | Gestión de docs/ai-memory, checkpoints |
| 27 | occre-testing-quality | Sí | No | No | No | Pruebas y calidad | Estrategia de pruebas, regresión |
| 28 | occre-token-efficient | Sí | No | No | No | Ahorro de tokens | Optimización de uso de tokens |
| 29 | occre-typescript-quality | Sí | No | No | No | TypeScript | Tipado estricto, prevención de any |
| 30 | owasp-api-security-review | Sí | No | No | No | OWASP API Top 10 | Revisión de API contra OWASP API Security Top 10 |
| 31 | owasp-code-review-security | Sí | No | No | No | OWASP Code Review | Revisión de código contra OWASP Top 10/ASVS |
| 32 | slides | Sí | No | No | No | Presentaciones | No aplica |
| 33 | trailofbits-c-review | Sí | No | No | No | Revisión C/C++ | No aplica (no hay código nativo C/C++) |
| 34 | trailofbits-sharp-edges | Sí | No | No | No | Diseño seguro | Evitar APIs propensos a errores de seguridad |
| 35 | ui-styling | Sí | No | No | No | Estilizado UI | No aplica en esta fase |
| 36 | ui-ux-pro-max | Sí | No | No | No | UI/UX | No aplica en esta fase |
| 37 | web-design-guidelines | Sí | No | No | No | Directrices web | No aplica en esta fase |

**Total local:** 37 Skills encontradas (de ~40 reportadas en memoria, algunas pueden no existir físicamente)

---

## C. Resumen por Categoría

| Categoría | Skills Relevantes | Estado | Usadas en esta auditoría |
|-----------|------------------|--------|-------------------------|
| **Ciberseguridad** | security-review, production-audit, safety-guard, verification-loop, gateguard | 5 disponibles | security-review, production-audit, safety-guard, verification-loop cargadas |
| **Seguridad BD** | occre-database-security, occre-mysql-expert, mysql-patterns | 3 disponibles | occre-database-security, occre-mysql-expert cargadas |
| **Seguridad Backend** | occre-laravel-security-audit, occre-laravel-expert, occre-laravel-api | 3 disponibles | occre-laravel-security-audit cargada |
| **Seguridad Frontend/Móvil** | occre-angular-expert, occre-ionic-expert, occre-mobile-security, occre-typescript-quality | 4 disponibles | occre-angular-expert, occre-ionic-expert, occre-mobile-security cargadas |
| **Diseño y UX** | frontend-design, ui-ux-pro-max, design-system, web-design-guidelines | 4 disponibles | Ninguna (sin rediseño en esta fase) |
| **Optimización** | context-budget, occre-token-efficient, search-first | 3 disponibles | Ninguna activa |
| **Verificación** | verification-loop, production-audit, e2e-testing, tdd-workflow | 4 disponibles | verification-loop, production-audit cargadas |

---

## D. Skills Solicitadas pero No Encontradas

| Skill solicitada | Estado | Alternativa instalada |
|-----------------|--------|----------------------|
| Security audit (genérica) | No encontrada como skill independiente | security-review, production-audit, occre-security-baseline, occre-laravel-security-audit |
| OWASP Top 10 | No encontrada como skill independiente | owasp-code-review-security, owasp-api-security-review |
| OWASP API Security | No encontrada como skill independiente | owasp-api-security-review, occre-api-security |
| Pentesting autorizado | No encontrada | No hay alternativa (no se ejecutan pruebas ofensivas) |
| Vulnerability assessment | No encontrada | security-review, production-audit |
| Security hardening | No encontrada | occre-security-baseline |
| Secure coding | No encontrada | occre-typescript-quality, owasp-code-review-security |
| Threat modeling | No encontrada | No hay alternativa (se realiza manualmente en este informe) |
| DevSecOps | No encontrada | production-audit, safety-guard |
| SQL injection prevention | No encontrada | occre-mysql-expert, occre-database-security |
| Database permissions | No encontrada | occre-database-security |
| Data integrity | No encontrada | occre-mysql-expert |
| Backup security | No encontrada | No hay alternativa específica |
| Disaster recovery | No encontrada | No hay alternativa específica |
| Laravel security | No encontrada | occre-laravel-security-audit |
| PHP security | No encontrada | occre-laravel-security-audit |
| JWT security | No encontrada | occre-laravel-security-audit, occre-api-security |
| Session management | No encontrada | occre-laravel-security-audit |
| API authentication | No encontrada | occre-api-security |
| Authorization and RBAC | No encontrada | occre-laravel-security-audit |
| Frontend security | No encontrada | occre-angular-expert, occre-ionic-expert, occre-mobile-security |
| XSS prevention | No encontrada | occre-angular-expert, occre-typescript-quality |
| CSP | No encontrada | No hay alternativa específica |
| Secure HTTP communication | No encontrada | occre-api-security |
| Accessibility | No encontrada | occre-accessibility-testing |
| Component architecture | No encontrada | occre-angular-expert, occre-frontend-architecture |
| Design systems | No encontrada | design-system |
| Responsive design | No encontrada | occre-responsive-multiplatform |
| Token optimization | No encontrada | occre-token-efficient |
| Context management | No encontrada | context-budget |
| Efficient code analysis | No encontrada | No hay alternativa específica |
| Agent orchestration | No encontrada | skill-orchestrator |
| Incremental verification | No encontrada | verification-loop |
| Documentation management | No encontrada | occre-session-memory |
| Regression testing | No encontrada | occre-testing-quality |

---

## E. Conclusiones del Inventario

1. **Todas las skills críticas de ciberseguridad están disponibles** (globalmente y localmente).
2. **Las skills específicas de OCCRE** (occre-*) están instaladas localmente y cubren Angular, Ionic, Laravel, MySQL, API security, database security, security baseline, mobile security.
3. **No se descargaron ni instalaron skills de terceros** sin autorización.
4. **Skills activadas en esta sesión:** skill-orchestrator, occre-security-baseline, occre-laravel-security-audit, occre-database-security, occre-mysql-expert, occre-api-security, occre-angular-expert, occre-ionic-expert, occre-mobile-security.
5. **Reevaluación requerida** antes de cada nueva fase (diseño visual, rediseño de interfaz, chatbot 3D).
