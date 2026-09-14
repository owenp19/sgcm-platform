import { Component } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { NavController } from '@ionic/angular';
import { addIcons } from 'ionicons';
import {
  mailOutline,
  lockClosedOutline,
  eyeOutline,
  eyeOffOutline,
  alertCircleOutline,
} from 'ionicons/icons';
import { AuthService } from '../../../services/auth.service';
import { LoadingService } from '../../../services/loading.service';

@Component({
  selector: 'app-admin-login',
  templateUrl: './admin-login.component.html',
  styleUrls: ['./admin-login.component.scss'],
  standalone: false,
})
export class AdminLoginComponent {
  public email = '';
  public password = '';
  public showPassword = false;
  public isSubmitting = false;
  public errorMessage = '';

  constructor(
    private readonly navCtrl: NavController,
    private readonly authService: AuthService,
    private readonly loadingService: LoadingService,
  ) {
    addIcons({
      mailOutline,
      lockClosedOutline,
      eyeOutline,
      eyeOffOutline,
      alertCircleOutline,
    });
  }

  public onEmailInput(event: Event): void {
    this.email = (event as CustomEvent<{ value: string }>).detail?.value?.trim() ?? '';
  }

  public onPasswordInput(event: Event): void {
    this.password = (event as CustomEvent<{ value: string }>).detail?.value ?? '';
  }

  public togglePasswordVisibility(): void {
    this.showPassword = !this.showPassword;
  }

  get isFormValid(): boolean {
    return this.email.length > 0 && this.password.length >= 6;
  }

  public async login(): Promise<void> {
    if (!this.isFormValid || this.isSubmitting) return;
    this.isSubmitting = true;
    this.errorMessage = '';
    try {
      this.loadingService.show();
      const res = await firstValueFrom(this.authService.login(this.email, this.password));
      const roles = res.user.roles || [];
      if (!roles.includes('admin') && !roles.includes('funcionario')) {
        this.errorMessage = 'Acceso denegado. Esta plataforma es exclusiva para funcionarios de OCCRE.';
        return;
      }
      await this.authService.saveSession(res.token, res.user);
      await this.navCtrl.navigateRoot('/admin/dashboard');
    } catch (error: any) {
      this.errorMessage = error.error?.error || 'Error al iniciar sesión. Verifica tus credenciales.';
    } finally {
      this.isSubmitting = false;
      this.loadingService.hide();
    }
  }
}
