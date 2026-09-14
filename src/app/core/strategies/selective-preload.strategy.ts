import { Injectable } from '@angular/core';
import { PreloadingStrategy, Route } from '@angular/router';
import { Observable, of } from 'rxjs';

/**
 * Estrategia de precarga selectiva (lazy-load optimizado).
 *
 * Por defecto NO precarga nada: cada módulo se descarga solo cuando el
 * usuario entra (lazy-load puro, menor consumo de ancho de banda/arranque
 * más rápido). Solo los módulos marcados con `data: { preload: true }`
 * (rutas críticas como login, home, procedimientos y perfil) se precargan
 * al terminar de cargar el bootstrap para que la primera navegación sea
 * instantánea.
 *
 * Módulos pesados (admin, tarjeta de turismo, validación QR, auditoría)
 * y rutas públicas no críticas se cargan siempre bajo demanda.
 */
@Injectable({ providedIn: 'root' })
export class SelectivePreloadStrategy implements PreloadingStrategy {
  preload(route: Route, load: () => Observable<unknown>): Observable<unknown> {
    return route.data?.['preload'] === true ? load() : of(null);
  }
}