import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { NavController } from '@ionic/angular';
import { firstValueFrom } from 'rxjs';
import { addIcons } from 'ionicons';
import {
  arrowBackOutline,
  lockClosedOutline,
  eyeOutline,
  eyeOffOutline,
  checkmarkCircle,
  checkmarkCircleOutline,
  alertCircleOutline,
  shieldCheckmarkOutline,
} from 'ionicons/icons';
import { AuthService } from '../../../services/auth.service';

@Component({
  selector: 'app-reset-password',
  templateUrl: './reset-password.page.html',
  styleUrls: ['./reset-password.page.scss'],
  standalone: false,
})
export class ResetPasswordPage implements OnInit {
  public token = '';
  public password = '';
  public confirmPassword = '';
  public showPassword = false;
  public showConfirmPassword = false;
  public isSubmitting = false;
  public errorMessage = '';
  public wasSubmitted = false;
  public step: 'form' | 'success' | 'invalid' = 'form';

  constructor(
    private readonly route: ActivatedRoute,
    private readonly navCtrl: NavController,
    private readonly authService: AuthService,
  ) {
    addIcons({
      arrowBackOutline,
      lockClosedOutline,
      eyeOutline,
      eyeOffOutline,
      checkmarkCircle,
      checkmarkCircleOutline,
      alertCircleOutline,
      shieldCheckmarkOutline,
    });
  }

  ngOnInit(): void {
    this.token = this.route.snapshot.queryParamMap.get('token') ?? '';

    if (!this.token) {
      this.step = 'invalid';
    }
  }

  public get isPasswordValid(): boolean {
    return this.password.length >= 6;
  }

  public get isStrongPassword(): boolean {
    return this.isPasswordValid && /\d/.test(this.password);
  }

  public get passwordsMatch(): boolean {
    return this.password === this.confirmPassword;
  }

  public get isFormValid(): boolean {
    return this.isPasswordValid && this.passwordsMatch;
  }

  public get strength(): number {
    let score = 0;
    if (this.password.length >= 6) score += 1;
    if (this.password.length >= 10) score += 1;
    if (/\d/.test(this.password)) score += 1;
    if (/[A-Za-z]/.test(this.password) && /[^A-Za-z0-9]/.test(this.password)) score += 1;
    return score;
  }

  public get strengthColor(): string {
    if (this.password.length === 0) return 'var(--app-text-soft)';
    if (this.strength <= 1) return '#d32f2f';
    if (this.strength === 2) return '#f57c00';
    return '#2e7d32';
  }

  public strengthLabel(): string {
    if (this.strength <= 1) return 'reset.strength_weak';
    if (this.strength === 2) return 'reset.strength_fair';
    return 'reset.strength_strong';
  }

  public onInput(field: 'password' | 'confirmPassword', event: Event): void {
    const value = (event as CustomEvent<{ value: string | null }>).detail?.value ?? '';
    this[field] = value;
  }

  public togglePasswordVisibility(): void {
    this.showPassword = !this.showPassword;
  }

  public toggleConfirmPasswordVisibility(): void {
    this.showConfirmPassword = !this.showConfirmPassword;
  }

  public async resetPassword(): Promise<void> {
    this.wasSubmitted = true;
    if (!this.isFormValid || this.isSubmitting) return;

    this.isSubmitting = true;
    this.errorMessage = '';

    try {
      await firstValueFrom(this.authService.resetPassword(this.token, this.password));
      this.step = 'success';
    } catch (error: any) {
      if (error.status === 400) {
        this.step = 'invalid';
      } else if (error.status === 0) {
        this.errorMessage = 'No pudimos conectarnos. Verifica tu conexión e intenta de nuevo.';
      } else {
        this.errorMessage = 'No se pudo restablecer la contraseña. Intenta de nuevo.';
      }
    } finally {
      this.isSubmitting = false;
    }
  }

  public goToLogin(): void {
    void this.navCtrl.navigateRoot('/login');
  }
}