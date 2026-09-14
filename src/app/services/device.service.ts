import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { PushNotifications } from '@capacitor/push-notifications';
import { Device } from '@capacitor/device';
import { firstValueFrom } from 'rxjs';
import { ApiConfigService } from './api-config.service';

@Injectable({ providedIn: 'root' })
export class DeviceService {
  private currentToken: string | null = null;

  private get apiUrl(): string {
    return `${this.apiConfig.getApiUrl()}/devices`;
  }

  constructor(
    private readonly http: HttpClient,
    private readonly apiConfig: ApiConfigService,
  ) {}

  async registerPushNotifications(): Promise<void> {
    let permStatus = await PushNotifications.checkPermissions();

    if (permStatus.receive === 'prompt') {
      permStatus = await PushNotifications.requestPermissions();
    }

    if (permStatus.receive !== 'granted') {
      console.warn('[Push] Permiso no concedido');
      return;
    }

    await PushNotifications.register();

    await PushNotifications.addListener('registration', (token) => {
      this.currentToken = token.value;
      this.sendTokenToServer(token.value);
    });

    await PushNotifications.addListener('registrationError', (err) => {
      console.error('[Push] Error de registro:', err);
    });

    await PushNotifications.addListener('pushNotificationReceived', (notification) => {
      console.log('[Push] Notificación recibida:', notification);
    });

    await PushNotifications.addListener('pushNotificationActionPerformed', (notification) => {
      console.log('[Push] Acción ejecutada:', notification);
    });
  }

  private async sendTokenToServer(token: string): Promise<void> {
    try {
      const info = await Device.getInfo();
      const platform = info.platform === 'ios' ? 'ios' : info.platform === 'android' ? 'android' : 'web';
      await firstValueFrom(this.http.post(`${this.apiUrl}/register`, { token, platform }));
    } catch (err) {
      console.error('[Push] Error al enviar token al servidor:', err);
    }
  }

  async unregisterPushNotifications(): Promise<void> {
    if (!this.currentToken) {
      console.warn('[Push] Sin token registrado');
      return;
    }
    try {
      await firstValueFrom(this.http.post(`${this.apiUrl}/unregister`, { token: this.currentToken }));
      this.currentToken = null;
    } catch (err) {
      console.error('[Push] Error al desregistrar:', err);
    }
  }
}
