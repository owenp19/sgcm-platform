# Architecture - OCCRE

## Arquitectura General
Aplicación híbrida con backend API REST Laravel y frontend Angular/Ionic.

## Frontend
- Angular 20 + Ionic 8
- Componentes standalone/según configuración existente
- Routing con lazy loading
- Capacitor para acceso nativo (cámara, biometría, geolocalización, notificaciones)
- PWA/Service Worker configurado

## Backend
- Laravel 12 (API REST)
- Rutas API en `backend/routes/api.php`
- Autenticación JWT
- Controladores, Middleware, Policies
- Validación con Form Requests
- API Resources para serialización

## Base de Datos
- MySQL
- Migraciones en `backend/database/migrations/`
- No ejecutar migraciones sin autorización explícita

## Seguridad
- Autenticación JWT existente - NO MODIFICAR
- Configuración auth - NO MODIFICAR
- .env - NO MODIFICAR
- Datos institucionales protegidos
