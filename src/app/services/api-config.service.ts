import { Injectable } from '@angular/core';
import { Preferences } from '@capacitor/preferences';
import { environment } from '../../environments/environment';

export const SERVER_URL_KEY = 'occre_server_url';

/**
 * Permite cambiar la URL del backend en tiempo de ejecución sin recompilar.
 * La URL guardada (Ajustes > Servidor) tiene prioridad sobre la del environment.
 */
@Injectable({ providedIn: 'root' })
export class ApiConfigService {
  private baseUrl = environment.apiUrl;

  /** Carga la URL guardada antes del bootstrap de la app. */
  async load(): Promise<void> {
    const { value } = await Preferences.get({ key: SERVER_URL_KEY });
    if (value) {
      this.baseUrl = this.normalize(value);
    }
  }

  getApiUrl(): string {
    return this.baseUrl;
  }

  async setApiUrl(url: string): Promise<void> {
    const normalized = this.normalize(url);
    this.baseUrl = normalized;
    await Preferences.set({ key: SERVER_URL_KEY, value: normalized });
  }

  async resetToDefault(): Promise<void> {
    this.baseUrl = environment.apiUrl;
    await Preferences.remove({ key: SERVER_URL_KEY });
  }

  /** Apunta a la URL del environment sin borrar la preferencia guardada. */
  useDefault(): void {
    this.baseUrl = environment.apiUrl;
  }

  /** Vuelve a cargar la URL guardada en preferencias (si existe). */
  async reload(): Promise<void> {
    const { value } = await Preferences.get({ key: SERVER_URL_KEY });
    this.baseUrl = value ? this.normalize(value) : environment.apiUrl;
  }

  get isCustom(): boolean {
    return this.baseUrl !== environment.apiUrl;
  }

  private normalize(url: string): string {
    return url.trim().replace(/\/+$/, '');
  }
}
