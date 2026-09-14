import { Component, OnInit } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { TimeoutError } from 'rxjs';
import { NavController, AlertController, ModalController } from '@ionic/angular';
import { Capacitor } from '@capacitor/core';
import { addIcons } from 'ionicons';
import {
  chevronForwardOutline,
  eyeOffOutline,
  eyeOutline,
  lockClosedOutline,
  mailOutline,
  personOutline,
  fingerPrintOutline,
  personAddOutline,
  airplaneOutline,
  shieldCheckmarkOutline,
  headsetOutline,
  refreshOutline,
  serverOutline,
  closeOutline,
  checkmarkOutline,
  alertCircleOutline,
} from 'ionicons/icons';
import { environment } from '../../../../environments/environment';
import { AuthService } from '../../../services/auth.service';
import { LoadingService } from '../../../services/loading.service';
import { BiometricService } from '../../../services/biometric.service';
import { SecureStorageService } from '../../../services/secure-storage.service';
import { DeviceService } from '../../../services/device.service';
import { ConnectivityService } from '../../../services/connectivity.service';
import { ApiConfigService } from '../../../services/api-config.service';
import { TranslationService } from '../../../services/translation.service';

@Component({
  selector: 'app-login',
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.scss'],
  standalone: false,
})
export class LoginComponent implements OnInit {
  public email = '';
  public password = '';
  public showPassword = false;
  public isSubmitting = false;
  public isGoingToRegister = false;
  public isRecoveringPassword = false;
  public errorMessage = '';
  public errorCode: '' | 'network' | 'server' | 'credentials' | 'html' | 'generic' = '';
  public biometricAvailable = false;
  public biometryLabel = '';
  public showServerConfig = false;
  public serverUrl = '';
  public isSavingServer = false;
  public serverConfigMessage = '';
  public serverConfigMessageType: 'success' | 'error' | '' = '';
  public isRetrying = false;
  private readonly useFallback = environment.useFallback;

  constructor(
    private readonly navCtrl: NavController,
    private readonly authService: AuthService,
    private readonly loadingService: LoadingService,
    private readonly biometricService: BiometricService,
    private readonly secureStorage: SecureStorageService,
    private readonly alertCtrl: AlertController,
    private readonly deviceService: DeviceService,
    private readonly connectivity: ConnectivityService,
    private readonly apiConfig: ApiConfigService,
    private readonly translationService: TranslationService,
  ) {
    addIcons({
      mailOutline,
      lockClosedOutline,
      eyeOutline,
      eyeOffOutline,
      personOutline,
      chevronForwardOutline,
      fingerPrintOutline,
      personAddOutline,
      airplaneOutline,
      shieldCheckmarkOutline,
      headsetOutline,
      refreshOutline,
      serverOutline,
      closeOutline,
      checkmarkOutline,
      alertCircleOutline,
    });
  }

  async ngOnInit(): Promise<void> {
    this.serverUrl = this.apiConfig.getApiUrl();
    this.biometricAvailable = await this.biometricService.isAvailable();
    if (this.biometricAvailable) {
      this.biometryLabel = await this.biometricService.getBiometryLabel();
      const biometricEnabled = await this.secureStorage.isBiometricEnabled();
      if (biometricEnabled) {
        await this.biometricLogin();
      }
    }
  }

  async biometricLogin(): Promise<void> {
    this.errorMessage = '';
    this.errorCode = '';

    const reachable = await this.connectivity.checkWithRetry(2, 1000);
    if (!reachable) {
      this.errorMessage = this.translationService.translate('login.error.network');
      this.errorCode = 'network';
      return;
    }

    const result = await this.biometricService.authenticate();
    if (!result.success) {
      this.errorMessage = result.error || this.translationService.translate('login.error.generic');
      this.errorCode = 'generic';
      return;
    }

    const userJson = this.secureStorage.getUserJson();
    const token = this.secureStorage.getToken();
    if (!token || !userJson) {
      this.errorMessage = this.translationService.translate('login.error.no_session');
      this.errorCode = 'generic';
      return;
    }

    this.loadingService.show();
    try {
      this.authService.saveSession(token, JSON.parse(userJson));
      await this.navCtrl.navigateRoot('/home');
    } finally {
      this.loadingService.hide();
    }
  }

  public onEmailInput(event: Event): void {
    const value =
      (event as CustomEvent<{ value: string | null }>).detail?.value ?? '';

    this.email = value.trim();
  }

  public onPasswordInput(event: Event): void {
    const value =
      (event as CustomEvent<{ value: string | null }>).detail?.value ?? '';

    this.password = value;
  }

  public togglePasswordVisibility(): void {
    this.showPassword = !this.showPassword;
  }

  public async login(): Promise<void> {
    if (!this.isFormValid || this.isSubmitting) return;

    this.isSubmitting = true;
    this.errorMessage = '';
    this.errorCode = '';

    try {
      this.clearActiveFocus();
      this.loadingService.show();

      if (this.useFallback) {
        throw new Error('BACKEND_OFFLINE');
      }

      const res = await firstValueFrom(this.authService.login(this.email, this.password));
      if (res) {
        await this.authService.saveSession(res.token, res.user);

        if (Capacitor.isNativePlatform() && await this.biometricService.isAvailable()) {
          const alreadyEnabled = await this.secureStorage.isBiometricEnabled();
          if (!alreadyEnabled) {
            await this.promptEnableBiometric(res.user.id);
          }
        }

        await this.navCtrl.navigateRoot('/home');

        if (Capacitor.isNativePlatform()) {
          this.deviceService.registerPushNotifications().catch((err) => {
            console.warn('[Push] Registro fallido:', err);
          });
        }
      }
    } catch (error: any) {
      if (error.message === 'BACKEND_OFFLINE') {
        this.errorMessage = this.translationService.translate('login.error.backend_offline');
        this.errorCode = 'server';
      } else if (error instanceof TimeoutError) {
        this.errorMessage = this.translationService.translate('login.error.timeout');
        this.errorCode = 'network';
      } else if (error.status === 0) {
        this.errorMessage = this.translationService.translate('login.error.network');
        this.errorCode = 'network';
      } else if (typeof error.error === 'string' && /^\s*</.test(error.error)) {
        this.errorMessage = this.translationService.translate('login.error.html');
        this.errorCode = 'html';
      } else if (error.status >= 500) {
        this.errorMessage = this.translationService.translate('login.error.server');
        this.errorCode = 'server';
      } else {
        this.errorMessage = error.error?.error || this.translationService.translate('login.error.generic');
        this.errorCode = error.status === 401 ? 'credentials' : 'generic';
      }
    } finally {
      this.isSubmitting = false;
      this.loadingService.hide();
    }
  }

  public async retryLogin(): Promise<void> {
    if (this.isRetrying || this.isSubmitting) return;

    this.isRetrying = true;
    this.errorMessage = '';
    this.errorCode = '';

    const reachable = await this.connectivity.checkWithRetry(2, 1200);
    this.isRetrying = false;

    if (reachable) {
      await this.login();
    } else {
      this.errorMessage = this.translationService.translate('login.error.still_unreachable');
      this.errorCode = 'network';
    }
  }

  openServerConfig(): void {
    this.serverUrl = this.apiConfig.getApiUrl();
    this.showServerConfig = true;
    this.serverConfigMessage = '';
    this.serverConfigMessageType = '';
  }

  closeServerConfig(): void {
    this.showServerConfig = false;
    this.serverConfigMessage = '';
    this.serverConfigMessageType = '';
  }

  onServerUrlInput(event: Event): void {
    const value =
      (event as CustomEvent<{ value: string | null }>).detail?.value ?? '';
    this.serverUrl = value.trim();
    this.serverConfigMessage = '';
    this.serverConfigMessageType = '';
  }

  async saveServerUrl(): Promise<void> {
    const url = this.serverUrl.trim().replace(/\/+$/, '');
    if (!/^https?:\/\//i.test(url)) {
      this.serverConfigMessage = this.translationService.translate('settings.server.invalid');
      this.serverConfigMessageType = 'error';
      return;
    }

    this.isSavingServer = true;
    this.serverConfigMessage = '';

    await this.apiConfig.setApiUrl(url);

    const reachable = await this.connectivity.check();

    if (reachable) {
      this.serverConfigMessage = this.translationService.translate('settings.server.saved');
      this.serverConfigMessageType = 'success';
      this.errorMessage = '';
      this.errorCode = '';
      setTimeout(() => {
        this.showServerConfig = false;
      }, 1200);
    } else {
      this.serverConfigMessage = this.translationService.translate('settings.server.unreachable');
      this.serverConfigMessageType = 'error';
    }

    this.isSavingServer = false;
  }

  async resetServerUrl(): Promise<void> {
    await this.apiConfig.resetToDefault();
    this.serverUrl = this.apiConfig.getApiUrl();
    this.serverConfigMessage = this.translationService.translate('settings.server.reset_ok');
    this.serverConfigMessageType = 'success';
  }

  private async promptEnableBiometric(userId: number): Promise<void> {
    const label = await this.biometricService.getBiometryLabel();
    const alert = await this.alertCtrl.create({
      header: '¿Activar ingreso con ' + label + '?',
      message: `Puedes iniciar sesión con tu ${label} en lugar de escribir correo y contraseña.`,
      buttons: [
        { text: 'No ahora', role: 'cancel' },
        {
          text: 'Activar',
          handler: async () => {
            await this.secureStorage.setBiometricEnabled(true);
            try {
              await firstValueFrom(this.authService.setBiometricPreference(true));
            } catch {
              console.warn('[Biometric] No se pudo guardar preferencia en servidor');
            }
          },
        },
      ],
    });
    await alert.present();
  }

  public async recoverPassword(): Promise<void> {
    if (this.isRecoveringPassword) return;

    this.isRecoveringPassword = true;

    try {
      this.clearActiveFocus();

      await this.delay(100);

      await this.navCtrl.navigateForward('/recover-password');
    } catch (error) {
      console.error('Error al navegar hacia /recover-password:', error);
    } finally {
      this.isRecoveringPassword = false;
    }
  }

  public goToTourismCard(): void {
    this.navCtrl.navigateForward('/tarjeta-turismo');
  }

  public goToAdminLogin(): void {
    this.navCtrl.navigateForward('/admin/login');
  }

  public async goToRegister(): Promise<void> {
    if (this.isGoingToRegister) return;

    this.isGoingToRegister = true;

    try {
      this.clearActiveFocus();

      await this.delay(100);

      await this.navCtrl.navigateRoot('/register');
    } catch (error) {
      console.error('Error al navegar hacia /register:', error);
    } finally {
      this.isGoingToRegister = false;
    }
  }

  public get isFormValid(): boolean {
    return this.email.length > 0 && this.password.length >= 6;
  }

  get currentServerUrl(): string {
    return this.apiConfig.getApiUrl();
  }

  private clearActiveFocus(): void {
    const activeElement = document.activeElement as HTMLElement | null;

    if (activeElement) {
      activeElement.blur();
    }
  }

  private delay(milliseconds: number): Promise<void> {
    return new Promise(resolve => window.setTimeout(resolve, milliseconds));
  }
}
