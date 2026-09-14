import { Component } from '@angular/core';
import { NavController } from '@ionic/angular';
import { firstValueFrom } from 'rxjs';
import { addIcons } from 'ionicons';
import {
  arrowBackOutline,
  mailOutline,
  lockClosedOutline,
  checkmarkCircle,
  checkmarkCircleOutline,
  alertCircleOutline,
  shieldCheckmarkOutline,
} from 'ionicons/icons';
import { AuthService } from '../../../services/auth.service';

@Component({
  selector: 'app-recover-password',
  templateUrl: './recover-password.page.html',
  styleUrls: ['./recover-password.page.scss'],
  standalone: false,
})
export class RecoverPasswordPage {
  public email = '';
  public sentEmail = '';
  public sentMessage = '';
  public step: 'form' | 'sent' = 'form';
  public isSubmitting = false;
  public errorMessage = '';

  constructor(
    private readonly navCtrl: NavController,
    private readonly authService: AuthService,
  ) {
    addIcons({
      arrowBackOutline,
      mailOutline,
      lockClosedOutline,
      checkmarkCircle,
      checkmarkCircleOutline,
      alertCircleOutline,
      shieldCheckmarkOutline,
    });
  }

  public get isEmailValid(): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email.trim());
  }

  public get hasEmailError(): boolean {
    return this.email.length > 0 && !this.isEmailValid;
  }

  public onEmailInput(event: Event): void {
    const value = (event as CustomEvent<{ value: string | null }>).detail?.value ?? '';
    this.email = value.trim();
  }

  public goBack(): void {
    if (this.step === 'sent') {
      this.step = 'form';
      return;
    }
    void this.goToLogin();
  }

  public goToLogin(): void {
    void this.navCtrl.navigateRoot('/login');
  }

  public goToHelp(): void {
    void this.navCtrl.navigateForward('/help-contact');
  }

  public async sendRecovery(): Promise<void> {
    if (!this.isEmailValid || this.isSubmitting) return;

    this.isSubmitting = true;
    this.errorMessage = '';

    try {
      const res = await firstValueFrom(this.authService.forgotPassword(this.email));
      this.sentEmail = this.email;
      this.sentMessage = res.message ?? '';
      this.step = 'sent';
    } catch (error: any) {
      if (error.error?.error) {
        this.errorMessage = error.error.error;
      } else if (error.status === 0) {
        this.errorMessage = 'No pudimos conectarnos. Verifica tu conexión e intenta de nuevo.';
      } else {
        this.errorMessage = 'Error al enviar la solicitud. Intenta de nuevo.';
      }
    } finally {
      this.isSubmitting = false;
    }
  }
}